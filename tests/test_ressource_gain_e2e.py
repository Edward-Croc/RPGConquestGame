"""Playwright E2E tests for Issue #11 — ressource gain rules.

`mechanics/ressourceGainMechanic.php` fires at two end-turn moments:
- inside the existing `updateRessources` step (`before_claim` timing)
- in a new `ressourceGainAfterClaim` step (`after_claim` timing)

Each rule's `condition` is evaluated against zones/locations state to
produce an amount × COUNT(matches) gain per matching controller.

Each class below targets its OWN synthetic `GainTest_*` resource row
(added to the TestConfig CSVs) so their gain_rules never collide, which
lets the 6 zone-based classes share a single end-of-turn via one
module-scoped fixture. The 2 location-based classes stay behind
`@pytest.mark.db` (raw SQL to create synthetic locations — no admin UI
path exists) and share a second, separate end-of-turn of their own so
that fixture never runs under UI_ONLY.

Post-EOT expected amount = rule_gain × match_count.

Run:
    python3 -m pytest tests/test_ressource_gain_e2e.py -v
"""
import json

import pymysql
import pytest

from conftest import (
    GAME_PREFIX, MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB,
    PHP_BASE_URL, ensure_gm_login,
)
from helpers import (
    DB_AVAILABLE, end_turn, load_minimal_data, load_scenario_via_admin, safe_goto,
    ui_turn_counter,
    register_php_error_listener, assert_no_collected_php_errors,
)


def _db_conn():
    return pymysql.connect(
        host=MYSQL_HOST, port=MYSQL_PORT, user=MYSQL_USER,
        password=MYSQL_PASSWORD, database=MYSQL_DB,
        charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor,
    )


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def load_test_config(browser):
    if DB_AVAILABLE:
        load_minimal_data()
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
    yield


def _resolve_alpha_id():
    """Return Alpha's controller_id from TestConfig."""
    conn = _db_conn()
    cur = conn.cursor()
    cur.execute(f"SELECT id FROM `{GAME_PREFIX}controllers` WHERE lastname='Alpha' LIMIT 1")
    alpha_id = cur.fetchone()['id']
    cur.close()
    conn.close()
    return alpha_id


def _resolve_ressource_id(ressource_name):
    """Return a ressources_config.id by ressource_name."""
    conn = _db_conn()
    cur = conn.cursor()
    cur.execute(
        f"SELECT id FROM `{GAME_PREFIX}ressources_config` WHERE ressource_name=%s LIMIT 1",
        (ressource_name,),
    )
    ressource_id = cur.fetchone()['id']
    cur.close()
    conn.close()
    return ressource_id


def _set_gain_rules(ressource_id, rules):
    conn = _db_conn()
    cur = conn.cursor()
    cur.execute(
        f"UPDATE `{GAME_PREFIX}ressources_config` SET gain_rules = %s WHERE id = %s",
        (json.dumps(rules) if rules is not None else None, ressource_id),
    )
    conn.commit()
    cur.close()
    conn.close()


def _read_amount(controller_id, ressource_id):
    conn = _db_conn()
    cur = conn.cursor()
    cur.execute(
        f"SELECT amount FROM `{GAME_PREFIX}controller_ressources` "
        f"WHERE controller_id = %s AND ressource_id = %s LIMIT 1",
        (controller_id, ressource_id),
    )
    row = cur.fetchone()
    cur.close()
    conn.close()
    return row['amount'] if row else None


# --- UI-only helpers (POC for converting DB-marked tests to UI-only) ---

import re as _re


def _ui_resolve_controller_id(page, lastname):
    safe_goto(page, f"{PHP_BASE_URL}/base/accueil.php")
    page.wait_for_load_state("load")
    return page.locator(
        f"select[name='controller_id'] option:has-text('{lastname}')"
    ).first.get_attribute("value")


def _ui_resolve_zone_id(page, zone_name):
    safe_goto(page, f"{PHP_BASE_URL}/zones/management_zones.php")
    page.wait_for_load_state("load")
    return page.locator(
        f"tr:has(td:text-is('{zone_name}')) input[name='zone_id']"
    ).first.get_attribute("value")


def _ui_resolve_ressource_config_id(page, ressource_name):
    safe_goto(page, f"{PHP_BASE_URL}/ressources/management.php")
    page.wait_for_load_state("load")
    return page.locator(
        f"tr:has(td:text-is('{ressource_name}')) input[name='ressource_config_id']"
    ).first.get_attribute("value")


def _ui_resolve_controller_ressource_id(page, lastname, ressource_name):
    safe_goto(page, f"{PHP_BASE_URL}/ressources/management.php")
    page.wait_for_load_state("load")
    return page.locator(
        f"tr:has(td:has-text('{lastname}')):has(td:text-is('{ressource_name}')) "
        f"input[name='controller_ressource_id']"
    ).first.get_attribute("value")


def _ui_set_zone_holder(page, zone_id, controller_id_or_none, claimer_id_or_none=None):
    page.request.post(
        f"{PHP_BASE_URL}/zones/management_zones.php",
        form={
            "zone_id":    str(zone_id),
            "claimer_id": str(claimer_id_or_none) if claimer_id_or_none else "",
            "holder_id":  str(controller_id_or_none) if controller_id_or_none else "",
        },
    )


def _ui_set_gain_rules(page, ressource_config_id, rules_json_or_none):
    page.request.post(
        f"{PHP_BASE_URL}/ressources/management.php",
        form={
            "ressource_config_id": str(ressource_config_id),
            "gain_rules":          rules_json_or_none if rules_json_or_none else "",
            "update_gain_rules":   "1",
        },
    )


def _ui_set_controller_ressource(page, controller_ressource_id, amount, amount_stored=0, end_turn_gain=0):
    page.request.post(
        f"{PHP_BASE_URL}/ressources/management.php",
        form={
            "controller_ressource_id": str(controller_ressource_id),
            "amount":         str(amount),
            "amount_stored":  str(amount_stored),
            "end_turn_gain":  str(end_turn_gain),
            "update_ressource": "1",
        },
    )


def _ui_read_amount(page, controller_id, ressource_name):
    safe_goto(page, f"{PHP_BASE_URL}/base/accueil.php?controller_id={controller_id}")
    page.wait_for_load_state("load")
    safe_goto(page, f"{PHP_BASE_URL}/ressources/view.php")
    page.wait_for_load_state("load")
    content = page.content()
    pattern = rf'<td>{_re.escape(ressource_name)}</td>\s*<td[^>]*>\s*(-?\d+)\s*</td>'
    m = _re.search(pattern, content)
    return int(m.group(1)) if m else None


# --- Shared zone-based batch: one end-of-turn for 6 classes -----------------
#
# Alpha holds Alpha-Investigation (used by the 5 zone_id-scoped rules below)
# plus Beta-Combat/Epsilon-Controlled/Zeta-Unclaimed (added to the baseline
# Gamma-Claims holding from the TestConfig CSV) for the count-style rule.
# Each rule targets its own resource, so the zone_id-scoped rules don't
# interfere with each other; the no-zone_id count-style rule counts ALL
# zones Alpha holds, so it necessarily also counts Alpha-Investigation —
# its expected count is 5 (Gamma-Claims + Beta-Combat + Epsilon-Controlled
# + Zeta-Unclaimed + Alpha-Investigation), not 4.

_ZONE_RESOURCES = {
    "specific_zone":  "GainTest_SpecificZone",
    "count_style":    "GainTest_CountStyle",
    "before_claim":   "GainTest_BeforeClaimTiming",
    "negative_amount": "GainTest_NegativeAmount",
    "unlock_skip":    "GainTest_UnlockSkip",
    "unlock_fires":   "GainTest_UnlockFires",
}


@pytest.fixture(scope="module")
def shared_gain_results(browser, load_test_config):
    """Single end-of-turn shared by the 6 zone-based ressource-gain classes."""
    ctx = browser.new_context()
    page = ctx.new_page()
    register_php_error_listener(page)
    ensure_gm_login(page, PHP_BASE_URL)

    alpha_id = _ui_resolve_controller_id(page, "Alpha")
    config_ids = {
        key: _ui_resolve_ressource_config_id(page, name)
        for key, name in _ZONE_RESOURCES.items()
    }
    rc_ids = {
        key: _ui_resolve_controller_ressource_id(page, "Alpha", name)
        for key, name in _ZONE_RESOURCES.items()
    }
    for key in _ZONE_RESOURCES:
        _ui_set_controller_ressource(page, rc_ids[key], amount=0)

    investigation_zone_id = _ui_resolve_zone_id(page, "Alpha-Investigation")
    count_style_zone_ids = [
        _ui_resolve_zone_id(page, "Beta-Combat"),
        _ui_resolve_zone_id(page, "Epsilon-Controlled"),
        _ui_resolve_zone_id(page, "Zeta-Unclaimed"),
    ]
    _ui_set_zone_holder(page, investigation_zone_id, alpha_id)
    for zid in count_style_zone_ids:
        _ui_set_zone_holder(page, zid, alpha_id)

    # Both unlock_turn rules are computed from this one turncounter read.
    current_turn = ui_turn_counter(page)
    locked_unlock_turn = current_turn + 1
    threshold_unlock_turn = current_turn

    _ui_set_gain_rules(page, config_ids["specific_zone"], json.dumps([{
        "amount": 7,
        "timing": "after_claim",
        "condition": {"type": "holds_zone", "zone_id": int(investigation_zone_id)},
    }]))
    _ui_set_gain_rules(page, config_ids["count_style"], json.dumps([{
        "amount": 50,
        "timing": "after_claim",
        "condition": {"type": "holds_zone"},
    }]))
    _ui_set_gain_rules(page, config_ids["before_claim"], json.dumps([{
        "amount": 8,
        "timing": "before_claim",
        "condition": {"type": "holds_zone", "zone_id": int(investigation_zone_id)},
    }]))
    _ui_set_gain_rules(page, config_ids["negative_amount"], json.dumps([{
        "amount": -30,
        "timing": "after_claim",
        "condition": {"type": "holds_zone", "zone_id": int(investigation_zone_id)},
    }]))
    _ui_set_gain_rules(page, config_ids["unlock_skip"], json.dumps([{
        "amount": 50,
        "timing": "after_claim",
        "unlock_turn": locked_unlock_turn,
        "condition": {"type": "holds_zone", "zone_id": int(investigation_zone_id)},
    }]))
    _ui_set_gain_rules(page, config_ids["unlock_fires"], json.dumps([{
        "amount": 50,
        "timing": "after_claim",
        "unlock_turn": threshold_unlock_turn,
        "condition": {"type": "holds_zone", "zone_id": int(investigation_zone_id)},
    }]))

    end_turn(page, PHP_BASE_URL)

    results = {
        key: _ui_read_amount(page, alpha_id, name)
        for key, name in _ZONE_RESOURCES.items()
    }

    for key in _ZONE_RESOURCES:
        _ui_set_gain_rules(page, config_ids[key], None)
    _ui_set_zone_holder(page, investigation_zone_id, None)
    for zid in count_style_zone_ids:
        _ui_set_zone_holder(page, zid, None)
    assert_no_collected_php_errors(page)
    ctx.close()

    return results


class TestRessourceGainAfterClaimSpecificZone:
    """`condition: {type: holds_zone, zone_id: Z}` with Alpha holding Z
    must add exactly `amount` to Alpha's Gold after EOT (binary match)."""

    _rule_amount = 7

    def test_alpha_post_amount_matches_rule(self, shared_gain_results):
        got = shared_gain_results["specific_zone"]
        assert got == self._rule_amount, (
            f"Expected post-EOT amount = {self._rule_amount}; got {got}"
        )


class TestRessourceGainAfterClaimCountStyle:
    """`condition: {type: holds_zone}` (no zone_id) with Alpha holding 5
    zones must add `amount × 5` after EOT (count-style). Baseline
    Gamma-Claims + Beta-Combat/Epsilon-Controlled/Zeta-Unclaimed (this
    class) + Alpha-Investigation (shared by the other 5 classes in this
    batch) = 5 held zones."""

    _rule_amount = 50
    _expected_zone_count = 5

    def test_alpha_post_amount_matches_rule_times_count(self, shared_gain_results):
        got = shared_gain_results["count_style"]
        expected = self._rule_amount * self._expected_zone_count
        assert got == expected, (
            f"Expected post-EOT amount = {expected} ({self._rule_amount}×"
            f"{self._expected_zone_count} matches); got {got}"
        )


class TestRessourceGainBeforeClaimTiming:
    """`timing: 'before_claim'` fires inside the existing updateRessources
    end_step at the start of EOT (rather than the new after_claim step).
    Verifies the inside-updateRessources hook actually runs."""

    _rule_amount = 8

    def test_before_claim_hook_fires_with_correct_amount(self, shared_gain_results):
        got = shared_gain_results["before_claim"]
        assert got == self._rule_amount, (
            f"Expected post-EOT amount = {self._rule_amount} (before_claim "
            f"hook inside updateRessources); got {got}"
        )


class TestRessourceGainNegativeAmountPenalty:
    """Per docs/configuration.md, amount=0 is a no-op but negative amounts
    are allowed and subtract from the resource — useful for configuring
    conditional penalties. A -30 rule on a held zone should produce
    post-EOT amount = -30."""

    _rule_amount = -30

    def test_negative_amount_subtracts(self, shared_gain_results):
        """Negative amount (-30) × 1 match = -30 net penalty."""
        got = shared_gain_results["negative_amount"]
        assert got == self._rule_amount, (
            f"Expected post-EOT amount = {self._rule_amount} (penalty "
            f"semantics, amount < 0); got {got}"
        )


class TestRessourceGainUnlockTurnSkipsBeforeThreshold:
    """`unlock_turn > current_turn` must suppress the rule at end-of-turn.
    unlock_turn = current_turn + 1, read at fixture time, so the rule is
    locked at THIS EOT."""

    _rule_amount = 50

    def test_alpha_gold_does_not_gain_when_unlock_turn_above_current(self, shared_gain_results):
        got = shared_gain_results["unlock_skip"]
        assert got == 0, (
            f"unlock_turn = current_turn + 1 must suppress the rule; "
            f"expected amount=0, got {got}"
        )


class TestRessourceGainUnlockTurnFiresAtThreshold:
    """`unlock_turn == current_turn` must fire (inclusive lower boundary —
    `value > turn` is false when equal). unlock_turn = current_turn, read
    at the same fixture-time turncounter read as the skip test above."""

    _rule_amount = 50

    def test_alpha_gold_gains_when_unlock_turn_equals_current(self, shared_gain_results):
        got = shared_gain_results["unlock_fires"]
        assert got == self._rule_amount, (
            f"unlock_turn == current_turn must fire (inclusive boundary); "
            f"expected amount={self._rule_amount}, got {got}"
        )


# --- Shared location-based batch: one end-of-turn for 2 @pytest.mark.db classes
#
# Still @pytest.mark.db: needs synthetic locations owned by Alpha. Admin UI
# exposes location_types editor + delete + toggle but has no path to create
# a location or set locations.controller_id. Conversion deferred until a
# locations-ownership admin editor lands. Kept in its own fixture (separate
# from shared_gain_results above) so this raw-SQL setup never runs under
# UI_ONLY — the @pytest.mark.db skip on both classes prevents this fixture
# from being requested at all in that mode.

_TEMPLE_COUNT = 2
_OTHER_COUNT = 1


@pytest.fixture(scope="module")
def shared_gain_results_db(browser, load_test_config):
    """Single end-of-turn shared by the 2 location-based ressource-gain classes."""
    ctx = browser.new_context()
    page = ctx.new_page()
    register_php_error_listener(page)
    ensure_gm_login(page, PHP_BASE_URL)

    alpha_id = _resolve_alpha_id()
    location_type_tag_id = _resolve_ressource_id("GainTest_LocationTypeTag")
    location_by_id_config_id = _resolve_ressource_id("GainTest_LocationById")

    conn = _db_conn()
    cur = conn.cursor()
    cur.execute(f"SELECT id FROM `{GAME_PREFIX}zones` LIMIT 1")
    zone_id = cur.fetchone()['id']

    # 2 temple-tagged + 1 untagged location, all owned by Alpha
    synthetic_ids = []
    for i in range(_TEMPLE_COUNT):
        cur.execute(
            f"INSERT INTO `{GAME_PREFIX}locations` "
            f"(name, description, zone_id, controller_id, can_be_destroyed, is_base, location_types) "
            f"VALUES (%s, %s, %s, %s, 0, 0, %s)",
            (f"SyntheticTempleA{i}", "test", zone_id, alpha_id, '["temple"]'),
        )
        synthetic_ids.append(cur.lastrowid)
    for i in range(_OTHER_COUNT):
        cur.execute(
            f"INSERT INTO `{GAME_PREFIX}locations` "
            f"(name, description, zone_id, controller_id, can_be_destroyed, is_base, location_types) "
            f"VALUES (%s, %s, %s, %s, 0, 0, %s)",
            (f"SyntheticPlainA{i}", "test", zone_id, alpha_id, None),
        )
        synthetic_ids.append(cur.lastrowid)

    # 1 location targeted by its exact locations.id
    cur.execute(
        f"INSERT INTO `{GAME_PREFIX}locations` "
        f"(name, description, zone_id, controller_id, can_be_destroyed, is_base) "
        f"VALUES ('SyntheticByIdTarget', 'test', %s, %s, 0, 0)",
        (zone_id, alpha_id),
    )
    location_by_id_target = cur.lastrowid
    synthetic_ids.append(location_by_id_target)
    conn.commit()

    _set_gain_rules(location_type_tag_id, [{
        "amount": 30,
        "timing": "after_claim",
        "condition": {"type": "owns_location_type", "location_type": "temple"},
    }])
    _set_gain_rules(location_by_id_config_id, [{
        "amount": 11,
        "timing": "after_claim",
        "condition": {"type": "owns_location_type", "location_id": location_by_id_target},
    }])

    end_turn(page, PHP_BASE_URL)

    results = {
        "location_type_tag": _read_amount(alpha_id, location_type_tag_id),
        "location_by_id": _read_amount(alpha_id, location_by_id_config_id),
    }

    _set_gain_rules(location_type_tag_id, None)
    _set_gain_rules(location_by_id_config_id, None)
    # Cleanup: delete CKL rows first (FK), then synthetic locations.
    placeholders = ",".join(["%s"] * len(synthetic_ids))
    cur.execute(
        f"DELETE FROM `{GAME_PREFIX}controller_known_locations` WHERE location_id IN ({placeholders})",
        synthetic_ids,
    )
    cur.execute(
        f"DELETE FROM `{GAME_PREFIX}locations` WHERE id IN ({placeholders})",
        synthetic_ids,
    )
    conn.commit()
    cur.close()
    conn.close()
    assert_no_collected_php_errors(page)
    ctx.close()

    return results


@pytest.mark.db
class TestRessourceGainOwnsLocationTypeTag:
    """`condition: {type: owns_location_type, location_type: 'temple'}` with
    Alpha owning 2 synthetic temple-tagged locations and 1 non-tagged location
    must add `amount × 2` (tag-filtered count) to Alpha's resource."""

    _rule_amount = 30
    _temple_count = _TEMPLE_COUNT
    _other_count = _OTHER_COUNT

    def test_alpha_post_amount_matches_temple_tagged_count(self, shared_gain_results_db):
        """Alpha owns 2 temple-tagged + 1 untagged locations. Filter
        location_type='temple' matches the 2 temples; untagged skipped."""
        got = shared_gain_results_db["location_type_tag"]
        expected = self._rule_amount * self._temple_count
        assert got == expected, (
            f"Expected post-EOT amount = {expected} ({self._rule_amount}×"
            f"{self._temple_count} temple matches; untagged location ignored); "
            f"got {got}"
        )


@pytest.mark.db
class TestRessourceGainOwnsLocationByLocationId:
    """Regression test for the location_id column-mapping fix:
    `owns_location_type` with `location_id: N` must match the location
    whose `locations.id = N` (not a hypothetical `location_id` column).
    Binary match → +amount × 1."""

    _rule_amount = 11

    def test_location_id_filter_matches_specific_location(self, shared_gain_results_db):
        """location_id filter resolves to locations.id at SQL build time
        (special-cased away from the auto l.{key} = ? pattern). Match
        is binary → +11."""
        got = shared_gain_results_db["location_by_id"]
        assert got == self._rule_amount, (
            f"Expected post-EOT amount = {self._rule_amount} (location_id "
            f"binary match); got {got}"
        )
