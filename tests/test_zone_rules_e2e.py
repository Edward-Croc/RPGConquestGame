"""Source-presence + runtime-behaviour checks for the zones.zone_rules
JSON column and its `applyZoneRules` helper.

`TestZoneRulesSchemaAndLoader` verifies the column is declared in
MySQL/Postgres schemas and the CSV loader zones column list carries the
new key. `TestApplyZoneRulesBehaviour` exercises the helper wired into
`calculateControllerValue` via end-of-turn claim resolution.
`TestJapon1555KyotoGate` exercises the Plaines du Kansai adjacency gate
wired into Cité impériale de Kyōto's `zone_rules` on the Japon1555
scenario CSV.
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
    `calculateControllerValue`.

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


@pytest.fixture(scope="module")
def japon1555_load(browser):
    """Module-scoped: load Japon1555 once + set claimMode=worker_leader."""
    load_minimal_data()
    load_scenario_via_admin(browser, PHP_BASE_URL, "Japon1555CSV")
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


@pytest.mark.db
class TestJapon1555KyotoGate:
    """Scenario-level tests wiring the Plaines du Kansai adjacency gate
    into Cité impériale de Kyōto's `zone_rules`.

    Kyoto's CSV row carries a Claim zone_rules block with two rules
    referencing Plaines du Kansai: `not_held_by_actor` value_delta=-4
    and `held_by_actor` value_delta=+2. Kyoto's `adjacent_zones` already
    lists Plaines (id 10) so `applyZoneRules` finds Plaines when the
    helper walks the actor's Claim rules.

    Baseline math with Ashikaga's 4 Kyoto workers moved to Plaines
    (defence dropped to 3 = 0 base + 1 holder + 2 owned locations):
      claim-fails  -- 3 Shikoku claim workers → claim_val=3+2=5, baseline
                      diff=2 (would succeed); -4 rule → claim_val=1,
                      diff=-2, claim FAILS (Ashikaga keeps Kyoto).
      claim-wins   -- 2 Shikoku claim workers → claim_val=2+1=3, baseline
                      diff=0 (would fail); +2 rule → claim_val=5, diff=2,
                      claim SUCCEEDS (Shikoku takes Kyoto).
    """

    @pytest.fixture(autouse=True)
    def _load(self, japon1555_load):
        yield

    def _prep_kyoto_claim(self, cur, attacker_id, plaines_holder_id,
                          ashikaga_id, n_workers):
        """Reset Kyoto/Plaines holders, evict every Ashikaga worker to
        Plaines (drops defence to 3), park every attacker worker in
        Montagnes d'Iyo, then move exactly `n_workers` attacker workers
        into Kyoto with `action_choice='claim'` for the current turn.

        The clean-slate parking pass prevents leftover workers from a
        prior test's EOT (still in Kyoto with an auto-created 'passive'
        row for the new turn) from adding to the attacker's claim_val.

        Returns the Kyoto zone id."""
        cur.execute(
            f"SELECT id FROM `{GAME_PREFIX}zones` "
            f"WHERE name='Cité impériale de Kyōto' LIMIT 1"
        )
        kyoto_id = cur.fetchone()['id']
        cur.execute(
            f"SELECT id FROM `{GAME_PREFIX}zones` "
            f"WHERE name='Plaines du Kansai' LIMIT 1"
        )
        plaines_id = cur.fetchone()['id']
        cur.execute(
            f"SELECT id FROM `{GAME_PREFIX}zones` "
            f"WHERE name='Montagnes d’Iyo' LIMIT 1"
        )
        parking_id = cur.fetchone()['id']

        cur.execute(
            f"UPDATE `{GAME_PREFIX}workers` w "
            f"JOIN `{GAME_PREFIX}controller_worker` cw "
            f"    ON cw.worker_id=w.id AND cw.controller_id=%s "
            f"SET w.zone_id=%s "
            f"WHERE w.zone_id=%s",
            (ashikaga_id, plaines_id, kyoto_id)
        )
        cur.execute(
            f"UPDATE `{GAME_PREFIX}workers` w "
            f"JOIN `{GAME_PREFIX}controller_worker` cw "
            f"    ON cw.worker_id=w.id AND cw.controller_id=%s "
            f"SET w.zone_id=%s",
            (attacker_id, parking_id)
        )

        cur.execute(
            f"UPDATE `{GAME_PREFIX}zones` "
            f"SET holder_controller_id=%s, claimer_controller_id=%s "
            f"WHERE id=%s",
            (ashikaga_id, ashikaga_id, kyoto_id)
        )
        cur.execute(
            f"UPDATE `{GAME_PREFIX}zones` "
            f"SET holder_controller_id=%s, claimer_controller_id=%s "
            f"WHERE id=%s",
            (plaines_holder_id, plaines_holder_id, plaines_id)
        )

        cur.execute(
            f"SELECT w.id FROM `{GAME_PREFIX}workers` w "
            f"JOIN `{GAME_PREFIX}controller_worker` cw ON cw.worker_id=w.id "
            f"WHERE cw.controller_id=%s AND cw.is_primary_controller=1 "
            f"ORDER BY w.id LIMIT %s",
            (attacker_id, n_workers)
        )
        rows = cur.fetchall()
        assert len(rows) == n_workers, (
            f"Need {n_workers} primary attacker workers; found {len(rows)}"
        )
        worker_ids = [r['id'] for r in rows]

        for wid in worker_ids:
            cur.execute(
                f"UPDATE `{GAME_PREFIX}workers` "
                f"SET zone_id=%s WHERE id=%s",
                (kyoto_id, wid)
            )

        cur.execute(
            f"SELECT turncounter FROM `{GAME_PREFIX}mechanics` LIMIT 1"
        )
        current_turn = cur.fetchone()['turncounter']

        for wid in worker_ids:
            cur.execute(
                f"INSERT INTO `{GAME_PREFIX}worker_actions` "
                f"(worker_id, controller_id, turn_number, zone_id, "
                f" action_choice, action_params) "
                f"VALUES (%s, %s, %s, %s, 'claim', '{{}}') "
                f"ON DUPLICATE KEY UPDATE "
                f"zone_id=%s, action_choice='claim', action_params='{{}}', "
                f"controller_id=%s",
                (wid, attacker_id, current_turn, kyoto_id,
                 kyoto_id, attacker_id)
            )
        return kyoto_id

    def _run_eot(self, browser):
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)
        end_turn(page, PHP_BASE_URL)
        assert_no_collected_php_errors(page)
        context.close()

    def test_kyoto_has_zone_rules_after_scenario_load(self):
        """After Japon1555 loads, Kyoto's `zone_rules` JSON must carry a
        `Claim` list referencing Plaines du Kansai with the ±2 / -4
        adjacency deltas."""
        conn = _db_conn()
        cur = conn.cursor()
        cur.execute(
            f"SELECT zone_rules FROM `{GAME_PREFIX}zones` "
            f"WHERE name='Cité impériale de Kyōto' LIMIT 1"
        )
        row = cur.fetchone()
        cur.close()
        conn.close()
        assert row is not None, "Cité impériale de Kyōto zone not found"
        assert row['zone_rules'] is not None, (
            "zone_rules should be non-NULL after Japon1555 load — "
            "Kyoto needs the Plaines du Kansai Claim gate wired via the "
            "zones CSV"
        )
        rules = json.loads(row['zone_rules'])
        assert isinstance(rules, dict), (
            f"zone_rules should decode to a dict; got {type(rules).__name__}"
        )
        assert 'Claim' in rules, (
            f"zone_rules should carry a 'Claim' key; got keys={list(rules)!r}"
        )
        claim_rules = rules['Claim']
        assert isinstance(claim_rules, list) and len(claim_rules) >= 2, (
            f"Claim rules should be a list with ≥2 entries; got "
            f"{claim_rules!r}"
        )
        for r in claim_rules:
            assert r.get('adjacent_zone_name') == 'Plaines du Kansai', (
                f"Every Claim rule on Kyoto should reference Plaines du "
                f"Kansai; got {r!r}"
            )
        held_rule = next(
            (r for r in claim_rules if r.get('condition') == 'held_by_actor'),
            None,
        )
        assert held_rule is not None, (
            f"Missing held_by_actor rule for Plaines du Kansai; got "
            f"{claim_rules!r}"
        )
        assert held_rule.get('value_delta') == 2, (
            f"held_by_actor value_delta should be +2; got "
            f"{held_rule.get('value_delta')!r}"
        )
        not_held_rule = next(
            (r for r in claim_rules
             if r.get('condition') == 'not_held_by_actor'),
            None,
        )
        assert not_held_rule is not None, (
            f"Missing not_held_by_actor rule for Plaines du Kansai; got "
            f"{claim_rules!r}"
        )
        assert not_held_rule.get('value_delta') == -4, (
            f"not_held_by_actor value_delta should be -4; got "
            f"{not_held_rule.get('value_delta')!r}"
        )

    def test_kyoto_claim_fails_without_plaines(self, browser):
        """Shikoku attacks Kyoto with 3 claim-workers while NOT holding
        Plaines du Kansai. Baseline math (no zone_rules) would succeed
        (claim_val=5 vs defence=3, diff=2 ≥ claimDiff=1). The -4 rule
        drops claim_val to 1 → diff=-2 → claim FAILS and Ashikaga
        stays as Kyoto holder."""
        conn = _db_conn()
        cur = conn.cursor()
        cur.execute(
            f"SELECT id FROM `{GAME_PREFIX}controllers` "
            f"WHERE lastname='Ashikaga (足利)' LIMIT 1"
        )
        ashikaga_id = cur.fetchone()['id']
        cur.execute(
            f"SELECT id FROM `{GAME_PREFIX}controllers` "
            f"WHERE lastname='Shikoku (四国)' LIMIT 1"
        )
        attacker_id = cur.fetchone()['id']

        kyoto_id = self._prep_kyoto_claim(
            cur,
            attacker_id=attacker_id,
            plaines_holder_id=ashikaga_id,
            ashikaga_id=ashikaga_id,
            n_workers=3,
        )
        conn.commit()

        self._run_eot(browser)

        conn.commit()
        cur.execute(
            f"SELECT holder_controller_id FROM `{GAME_PREFIX}zones` "
            f"WHERE id=%s",
            (kyoto_id,)
        )
        post = cur.fetchone()
        cur.close()
        conn.close()
        assert post['holder_controller_id'] == ashikaga_id, (
            f"Expected Ashikaga (id={ashikaga_id}) to STILL hold Kyoto — "
            f"the not_held_by_actor -4 rule must fire because Shikoku "
            f"does not hold Plaines du Kansai; got holder="
            f"{post['holder_controller_id']!r}"
        )

    def test_kyoto_claim_succeeds_when_holding_plaines(self, browser):
        """Shikoku attacks Kyoto with 2 claim-workers while ALSO holding
        Plaines du Kansai. Baseline math (no zone_rules) would fail
        (claim_val=3 vs defence=3, diff=0 < claimDiff=1). The +2 rule
        lifts claim_val to 5 → diff=2 → claim SUCCEEDS and Shikoku
        becomes Kyoto holder."""
        conn = _db_conn()
        cur = conn.cursor()
        cur.execute(
            f"SELECT id FROM `{GAME_PREFIX}controllers` "
            f"WHERE lastname='Ashikaga (足利)' LIMIT 1"
        )
        ashikaga_id = cur.fetchone()['id']
        cur.execute(
            f"SELECT id FROM `{GAME_PREFIX}controllers` "
            f"WHERE lastname='Shikoku (四国)' LIMIT 1"
        )
        attacker_id = cur.fetchone()['id']

        kyoto_id = self._prep_kyoto_claim(
            cur,
            attacker_id=attacker_id,
            plaines_holder_id=attacker_id,
            ashikaga_id=ashikaga_id,
            n_workers=2,
        )
        conn.commit()

        self._run_eot(browser)

        conn.commit()
        cur.execute(
            f"SELECT holder_controller_id FROM `{GAME_PREFIX}zones` "
            f"WHERE id=%s",
            (kyoto_id,)
        )
        post = cur.fetchone()
        cur.close()
        conn.close()
        assert post['holder_controller_id'] == attacker_id, (
            f"Expected Shikoku (id={attacker_id}) to hold Kyoto — the "
            f"held_by_actor +2 rule must fire because Shikoku holds "
            f"Plaines du Kansai; got holder="
            f"{post['holder_controller_id']!r}"
        )
