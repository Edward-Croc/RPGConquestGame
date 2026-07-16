"""Source-presence + runtime-behaviour checks for the zones.zone_rules
JSON column and its `applyZoneRules` helper (issue #88).

`TestZoneRulesSchemaAndLoader` verifies the column is declared in
MySQL/Postgres schemas and the CSV loader zones column list carries the
new key. `TestApplyZoneRulesBehaviour` exercises the helper wired into
`calculateControllerValue` via end-of-turn claim resolution.
"""
import json
import re
from pathlib import Path

import pymysql
import pytest

from conftest import (
    GAME_PREFIX, MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB,
    PHP_BASE_URL, ensure_gm_login,
)
from helpers import (
    end_turn, load_minimal_data, load_scenario_via_admin,
    register_php_error_listener, assert_no_collected_php_errors,
)


REPO = Path(__file__).resolve().parent.parent


def _db_conn():
    return pymysql.connect(
        host=MYSQL_HOST, port=MYSQL_PORT, user=MYSQL_USER,
        password=MYSQL_PASSWORD, database=MYSQL_DB,
        charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor,
    )


class TestZoneRulesSchemaAndLoader:

    def test_zone_rules_column_in_mysql_schema(self):
        text = (REPO / "var" / "mysql" / "setupBDD.sql").read_text()
        match = re.search(
            r"CREATE TABLE \{prefix\}zones \((.*?)\);", text, re.DOTALL
        )
        assert match, "zones CREATE TABLE block not found in mysql schema"
        block = match.group(1)
        assert "zone_rules" in block, (
            "zone_rules column missing from mysql zones CREATE TABLE block"
        )
        line = next(
            (ln for ln in block.splitlines() if "zone_rules" in ln), ""
        )
        assert "JSON" in line, (
            f"zone_rules column type does not include 'JSON' (got: {line!r})"
        )
        assert "DEFAULT NULL" in line, (
            f"zone_rules column does not declare 'DEFAULT NULL' (got: {line!r})"
        )

    def test_zone_rules_column_in_postgres_schema(self):
        text = (REPO / "var" / "postgres" / "setupBDD.sql").read_text()
        match = re.search(
            r"CREATE TABLE \{prefix\}zones \((.*?)\);", text, re.DOTALL
        )
        assert match, "zones CREATE TABLE block not found in postgres schema"
        block = match.group(1)
        assert "zone_rules" in block, (
            "zone_rules column missing from postgres zones CREATE TABLE block"
        )
        line = next(
            (ln for ln in block.splitlines() if "zone_rules" in ln), ""
        )
        assert re.search(r"\bjson", line, re.IGNORECASE), (
            f"zone_rules column type does not include 'JSON' (got: {line!r})"
        )
        assert re.search(r"DEFAULT\s+NULL", line, re.IGNORECASE), (
            f"zone_rules column does not declare 'DEFAULT NULL' (got: {line!r})"
        )

    def test_zone_rules_in_csv_loader_columns(self):
        text = (REPO / "BDD" / "db_connector.php").read_text()
        match = re.search(r"'zones'\s*=>\s*\[([^\]]*)\]", text)
        assert match, "'zones' => [...] entry not found in db_connector.php"
        columns_literal = match.group(1)
        assert "'zone_rules'" in columns_literal, (
            "'zone_rules' missing from zones loader column list "
            f"(got: {columns_literal!r})"
        )


@pytest.fixture(scope="module")
def zone_rules_load_test_config(browser):
    """Module-scoped: load TestConfig once + set claimMode=worker_leader."""
    load_minimal_data()
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
    conn = _db_conn()
    cur = conn.cursor()
    cur.execute(
        f"UPDATE `{GAME_PREFIX}config` SET value='worker_leader' "
        f"WHERE name='claimMode'"
    )
    conn.commit()
    cur.close()
    conn.close()
    yield


# Zone names touched by TestApplyZoneRulesBehaviour (reset before each test).
_ZR_ZONES_TO_RESET = (
    'Delta-Disputed',        # claim target
    'Epsilon-Controlled',    # primary adjacent proxy
    'Zeta-Unclaimed',        # secondary adjacent proxy
    'Alpha-Investigation',   # existing real zone used as non-adjacent probe
)


@pytest.mark.db
class TestApplyZoneRulesBehaviour:
    """Runtime tests for the `applyZoneRules` helper wired into
    `calculateControllerValue` (issue #88).

    Each test configures `zones.zone_rules` + `zones.adjacent_zones` on
    Delta-Disputed (the claim target) and adjusts the adjacent zone's
    holder to satisfy or fail each rule's condition. A single Beta
    worker in Delta-Disputed submits `action_choice='claim'`; end-of-turn
    resolves via `claimByWorkerLeaderMath` which calls
    `calculateControllerValue('Claim', delta_id, beta_id)`.

    Baseline math (Beta, 1 claim-worker in Delta, holder=NULL, claimer=NULL):
      claim_val   = baseClaim(0) + workers(1) + owned_locations(0)
                  + supporting(max(0,1-1)=0) = 1
      defence_val = baseZoneDefence(0) + noControllerZoneDefenceBonus(3) = 3
      diff        = 1 - 3 = -2, does not clear claimDiff(1) → FAIL

    Every test's zone_rules is tuned so that, once `applyZoneRules` is
    wired in, the delta pushes claim_val above the threshold and Beta
    wins Delta. Without the helper (current code) all tests FAIL
    because zone_rules is ignored and claim_val stays at baseline (1).
    """

    @pytest.fixture(autouse=True)
    def _load(self, zone_rules_load_test_config):
        yield

    def _reset_state(self):
        """Clear rule/adjacency/holder/claimer on touched zones and move
        Beta workers back to Beta-Combat. Reset current-turn worker_actions
        to passive."""
        conn = _db_conn()
        cur = conn.cursor()
        for name in _ZR_ZONES_TO_RESET:
            cur.execute(
                f"UPDATE `{GAME_PREFIX}zones` "
                f"SET zone_rules=NULL, adjacent_zones='', defence_val=0, "
                f"    calculated_defence_val=0, holder_controller_id=NULL, "
                f"    claimer_controller_id=NULL "
                f"WHERE name=%s",
                (name,)
            )
        cur.execute(f"SELECT turncounter FROM `{GAME_PREFIX}mechanics` LIMIT 1")
        current_turn = cur.fetchone()['turncounter']
        cur.execute(
            f"UPDATE `{GAME_PREFIX}worker_actions` "
            f"SET action_choice='passive', action_params='{{}}' "
            f"WHERE turn_number=%s",
            (current_turn,)
        )
        cur.execute(
            f"UPDATE `{GAME_PREFIX}workers` w "
            f"JOIN `{GAME_PREFIX}controller_worker` cw ON cw.worker_id=w.id "
            f"SET w.zone_id=("
            f"    SELECT id FROM `{GAME_PREFIX}zones` "
            f"    WHERE name='Beta-Combat' LIMIT 1"
            f") "
            f"WHERE cw.controller_id=("
            f"    SELECT id FROM `{GAME_PREFIX}controllers` "
            f"    WHERE lastname='Beta' LIMIT 1"
            f")"
        )
        conn.commit()
        cur.close()
        conn.close()

    def _run_scenario(self, browser, adjacent_specs, rules_json,
                      extra_beta_holdings=()):
        """Configure and run one claim scenario, return post-EOT zone state.

        adjacent_specs: list of dicts {'name': str, 'held_by_beta': bool}.
          The zone IDs of these zones populate Delta-Disputed's
          `adjacent_zones` (comma-separated); those with held_by_beta=True
          also get Beta stamped as holder + claimer.
        rules_json: python dict/list (encoded via json.dumps) or None for
          the `Claim` key of zone_rules on Delta-Disputed.
        extra_beta_holdings: iterable of zone names to mark as Beta-held
          WITHOUT adding to Delta's adjacent_zones list. Used to probe
          the single-hop adjacency invariant (a rule may reference this
          zone but must be skipped by the helper).
        """
        self._reset_state()
        conn = _db_conn()
        cur = conn.cursor()
        cur.execute(
            f"SELECT id FROM `{GAME_PREFIX}controllers` "
            f"WHERE lastname='Beta' LIMIT 1"
        )
        beta_id = cur.fetchone()['id']
        cur.execute(
            f"SELECT id FROM `{GAME_PREFIX}zones` "
            f"WHERE name='Delta-Disputed' LIMIT 1"
        )
        delta_id = cur.fetchone()['id']

        adjacent_ids = []
        for spec in adjacent_specs:
            cur.execute(
                f"SELECT id FROM `{GAME_PREFIX}zones` WHERE name=%s LIMIT 1",
                (spec['name'],)
            )
            row = cur.fetchone()
            assert row, f"Fixture zone {spec['name']!r} not found in TestConfig"
            adjacent_ids.append(str(row['id']))
            if spec.get('held_by_beta'):
                cur.execute(
                    f"UPDATE `{GAME_PREFIX}zones` "
                    f"SET holder_controller_id=%s, claimer_controller_id=%s "
                    f"WHERE id=%s",
                    (beta_id, beta_id, row['id'])
                )

        for extra_name in extra_beta_holdings:
            cur.execute(
                f"UPDATE `{GAME_PREFIX}zones` "
                f"SET holder_controller_id=%s, claimer_controller_id=%s "
                f"WHERE name=%s",
                (beta_id, beta_id, extra_name)
            )

        cur.execute(
            f"UPDATE `{GAME_PREFIX}zones` "
            f"SET adjacent_zones=%s, zone_rules=%s "
            f"WHERE id=%s",
            (
                ','.join(adjacent_ids),
                json.dumps(rules_json) if rules_json is not None else None,
                delta_id,
            )
        )

        cur.execute(
            f"SELECT w.id FROM `{GAME_PREFIX}workers` w "
            f"JOIN `{GAME_PREFIX}controller_worker` cw ON cw.worker_id=w.id "
            f"WHERE cw.controller_id=%s ORDER BY w.id LIMIT 1",
            (beta_id,)
        )
        claim_worker_id = cur.fetchone()['id']

        cur.execute(f"SELECT turncounter FROM `{GAME_PREFIX}mechanics` LIMIT 1")
        current_turn = cur.fetchone()['turncounter']

        cur.execute(
            f"UPDATE `{GAME_PREFIX}workers` SET zone_id=%s WHERE id=%s",
            (delta_id, claim_worker_id)
        )
        cur.execute(
            f"INSERT INTO `{GAME_PREFIX}worker_actions` "
            f"(worker_id, turn_number, zone_id, controller_id, "
            f" action_choice, action_params) "
            f"VALUES (%s, %s, %s, %s, 'claim', '{{}}') "
            f"ON DUPLICATE KEY UPDATE "
            f"zone_id=%s, action_choice='claim', action_params='{{}}'",
            (claim_worker_id, current_turn, delta_id, beta_id, delta_id)
        )
        conn.commit()

        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)
        end_turn(page, PHP_BASE_URL)
        assert_no_collected_php_errors(page)

        conn.commit()  # refresh REPEATABLE READ snapshot before post-SELECT
        cur.execute(
            f"SELECT holder_controller_id, claimer_controller_id "
            f"FROM `{GAME_PREFIX}zones` WHERE id=%s",
            (delta_id,)
        )
        post = cur.fetchone()
        cur.close()
        conn.close()
        context.close()
        return post, beta_id

    def test_held_by_actor_condition_met_flips_claim_to_success(self, browser):
        """held_by_actor +10 on Epsilon-Controlled (Beta holds Epsilon).
        Rule matches → claim_val = 1 + 10 = 11, diff = 8 → Beta wins Delta.
        Without the helper: claim_val stays 1, diff = -2, Beta loses."""
        post, beta_id = self._run_scenario(
            browser,
            adjacent_specs=[{'name': 'Epsilon-Controlled', 'held_by_beta': True}],
            rules_json={
                'Claim': [
                    {'adjacent_zone_name': 'Epsilon-Controlled',
                     'condition': 'held_by_actor', 'value_delta': 10},
                ],
            },
        )
        assert post['holder_controller_id'] == beta_id, (
            f"Expected Beta (id={beta_id}) to hold Delta after "
            f"held_by_actor +10 rule fires; got holder="
            f"{post['holder_controller_id']!r}"
        )

    def test_not_held_by_actor_condition_met_flips_claim_to_success(self, browser):
        """not_held_by_actor +10 on Epsilon-Controlled (Beta does NOT hold
        Epsilon). Rule matches → claim_val = 1 + 10 = 11, diff = 8 → Beta
        wins Delta. Without the helper: claim_val stays 1, Beta loses."""
        post, beta_id = self._run_scenario(
            browser,
            adjacent_specs=[{'name': 'Epsilon-Controlled', 'held_by_beta': False}],
            rules_json={
                'Claim': [
                    {'adjacent_zone_name': 'Epsilon-Controlled',
                     'condition': 'not_held_by_actor', 'value_delta': 10},
                ],
            },
        )
        assert post['holder_controller_id'] == beta_id, (
            f"Expected Beta (id={beta_id}) to hold Delta after "
            f"not_held_by_actor +10 rule fires; got holder="
            f"{post['holder_controller_id']!r}"
        )

    def test_two_rules_only_matching_condition_applies(self, browser):
        """Two rules on same adjacent, opposite conditions, deltas +10 / -50.
        Beta HOLDS Epsilon → only held_by_actor matches. If both applied
        (bug), claim_val = 1 + 10 - 50 = -39, Beta loses. Correct behaviour:
        only +10 → claim_val = 11, diff = 8, Beta wins."""
        post, beta_id = self._run_scenario(
            browser,
            adjacent_specs=[{'name': 'Epsilon-Controlled', 'held_by_beta': True}],
            rules_json={
                'Claim': [
                    {'adjacent_zone_name': 'Epsilon-Controlled',
                     'condition': 'held_by_actor', 'value_delta': 10},
                    {'adjacent_zone_name': 'Epsilon-Controlled',
                     'condition': 'not_held_by_actor', 'value_delta': -50},
                ],
            },
        )
        assert post['holder_controller_id'] == beta_id, (
            f"Expected Beta (id={beta_id}) to hold Delta — only the "
            f"held_by_actor rule should have applied; got holder="
            f"{post['holder_controller_id']!r}"
        )

    def test_two_matching_rules_are_additive(self, browser):
        """Two held_by_actor rules on Epsilon, each +2. Only their SUM
        (+4) clears the threshold: claim_val = 1 + 4 = 5, diff = 2. If a
        single rule applied (bug — only-first / only-last), claim_val = 3,
        diff = 0, Beta loses. Both must fire additively."""
        post, beta_id = self._run_scenario(
            browser,
            adjacent_specs=[{'name': 'Epsilon-Controlled', 'held_by_beta': True}],
            rules_json={
                'Claim': [
                    {'adjacent_zone_name': 'Epsilon-Controlled',
                     'condition': 'held_by_actor', 'value_delta': 2},
                    {'adjacent_zone_name': 'Epsilon-Controlled',
                     'condition': 'held_by_actor', 'value_delta': 2},
                ],
            },
        )
        assert post['holder_controller_id'] == beta_id, (
            f"Expected Beta (id={beta_id}) to hold Delta — both +2 rules "
            f"must apply additively for claim to clear; got holder="
            f"{post['holder_controller_id']!r}"
        )

    def test_rule_referencing_nonexistent_zone_is_skipped(self, browser):
        """One rule references a zone name that doesn't exist in the DB
        (should fail-open and be skipped). A second rule on a real
        adjacent zone still fires → +10 → Beta wins.
        If the missing-zone rule was not skipped (naive lookup crash or
        applied with delta -100), Beta loses. Without helper: baseline
        FAIL."""
        post, beta_id = self._run_scenario(
            browser,
            adjacent_specs=[{'name': 'Epsilon-Controlled', 'held_by_beta': True}],
            rules_json={
                'Claim': [
                    {'adjacent_zone_name': 'ZoneThatDoesNotExist',
                     'condition': 'held_by_actor', 'value_delta': -100},
                    {'adjacent_zone_name': 'Epsilon-Controlled',
                     'condition': 'held_by_actor', 'value_delta': 10},
                ],
            },
        )
        assert post['holder_controller_id'] == beta_id, (
            f"Expected Beta (id={beta_id}) to hold Delta — the missing-"
            f"zone rule must be skipped fail-open while the Epsilon rule "
            f"applies; got holder={post['holder_controller_id']!r}"
        )

    def test_rule_referencing_non_adjacent_zone_is_skipped(self, browser):
        """One rule references a REAL zone that is NOT in Delta's
        adjacent_zones list. That rule must be skipped (single-hop
        adjacency invariant). The second rule on the real adjacent
        Epsilon still fires → +10 → Beta wins.
        Alpha-Investigation is stamped as Beta-held (via
        extra_beta_holdings) so a helper missing the adjacency guard
        would apply the -100 rule and Beta would lose. Correct helper
        skips the non-adjacent rule and Beta wins."""
        post, beta_id = self._run_scenario(
            browser,
            adjacent_specs=[
                {'name': 'Epsilon-Controlled', 'held_by_beta': True},
            ],
            extra_beta_holdings=('Alpha-Investigation',),
            rules_json={
                'Claim': [
                    {'adjacent_zone_name': 'Alpha-Investigation',
                     'condition': 'held_by_actor', 'value_delta': -100},
                    {'adjacent_zone_name': 'Epsilon-Controlled',
                     'condition': 'held_by_actor', 'value_delta': 10},
                ],
            },
        )
        assert post['holder_controller_id'] == beta_id, (
            f"Expected Beta (id={beta_id}) to hold Delta — non-adjacent "
            f"rule must be skipped; got holder="
            f"{post['holder_controller_id']!r}"
        )

    def test_rule_with_unknown_condition_is_skipped(self, browser):
        """Rule with an unrecognised `condition` string must be skipped
        (fail-open). A second well-formed rule on the same adjacent
        still fires → +10 → Beta wins.
        Without helper: baseline FAIL. If the unknown-condition rule
        was silently treated as truthy (delta -100 applied), Beta loses."""
        post, beta_id = self._run_scenario(
            browser,
            adjacent_specs=[{'name': 'Epsilon-Controlled', 'held_by_beta': True}],
            rules_json={
                'Claim': [
                    {'adjacent_zone_name': 'Epsilon-Controlled',
                     'condition': 'not_a_real_condition_value',
                     'value_delta': -100},
                    {'adjacent_zone_name': 'Epsilon-Controlled',
                     'condition': 'held_by_actor', 'value_delta': 10},
                ],
            },
        )
        assert post['holder_controller_id'] == beta_id, (
            f"Expected Beta (id={beta_id}) to hold Delta — unknown-"
            f"condition rule must be skipped; got holder="
            f"{post['holder_controller_id']!r}"
        )
