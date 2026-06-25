"""Playwright E2E tests for Issue #11 — ressource gain rules.

`mechanics/ressourceGainMechanic.php` fires at two end-turn moments:
- inside the existing `updateRessources` step (`before_claim` timing)
- in a new `ressourceGainAfterClaim` step (`after_claim` timing)

Each rule's `condition` is evaluated against zones/locations state to
produce an amount × COUNT(matches) gain per matching controller.

Each fixture resets Alpha's Gold row to amount=0, end_turn_gain=0
before EOT so the gain-rule arithmetic is isolated from the existing
updateRessources reset/carry behaviour (which has its own quirks
around BOOLEAN-as-int handling — out of scope for Issue #11).

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
    register_php_error_listener, assert_no_collected_php_errors,
)


def _db_conn():
    return pymysql.connect(
        host=MYSQL_HOST, port=MYSQL_PORT, user=MYSQL_USER,
        password=MYSQL_PASSWORD, database=MYSQL_DB,
        charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor,
    )


def _current_turn():
    conn = _db_conn(); cur = conn.cursor()
    cur.execute(f"SELECT turncounter FROM `{GAME_PREFIX}mechanics` LIMIT 1")
    row = cur.fetchone()
    cur.close(); conn.close()
    return int(row['turncounter'])


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def load_test_config(browser):
    if DB_AVAILABLE:
        load_minimal_data()
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
    yield


def _resolve_ids():
    """Return (gold_id, alpha_id) from TestConfig."""
    conn = _db_conn()
    cur = conn.cursor()
    cur.execute(f"SELECT id FROM `{GAME_PREFIX}ressources_config` WHERE ressource_name='Gold' LIMIT 1")
    gold_id = cur.fetchone()['id']
    cur.execute(f"SELECT id FROM `{GAME_PREFIX}controllers` WHERE lastname='Alpha' LIMIT 1")
    alpha_id = cur.fetchone()['id']
    cur.close()
    conn.close()
    return gold_id, alpha_id


def _reset_zone_holders():
    """Wipe holder/claimer on all zones before a test seeds its own."""
    conn = _db_conn()
    cur = conn.cursor()
    cur.execute(
        f"UPDATE `{GAME_PREFIX}zones` "
        f"SET holder_controller_id = NULL, claimer_controller_id = NULL"
    )
    conn.commit()
    cur.close()
    conn.close()


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


def _zero_controller_ressource(controller_id, ressource_id):
    """Reset amount/amount_stored/end_turn_gain to 0 so post-EOT amount
    is purely the gain-rule contribution."""
    conn = _db_conn()
    cur = conn.cursor()
    cur.execute(
        f"UPDATE `{GAME_PREFIX}controller_ressources` "
        f"SET amount = 0, amount_stored = 0, end_turn_gain = 0 "
        f"WHERE controller_id = %s AND ressource_id = %s",
        (controller_id, ressource_id),
    )
    conn.commit()
    cur.close()
    conn.close()


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


class TestRessourceGainAfterClaimSpecificZone:
    """`condition: {type: holds_zone, zone_id: Z}` with Alpha holding Z
    must add exactly `amount` to Alpha's Gold after EOT (binary match).
    UI-only: state seeded via admin pages, amount read from Alpha's
    Ressources view, no direct DB access."""

    _rule_amount = 7

    @pytest.fixture(scope="class", autouse=True)
    def gain_state(self, browser):
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        alpha_id = _ui_resolve_controller_id(page, "Alpha")
        gold_config_id = _ui_resolve_ressource_config_id(page, "Gold")
        alpha_gold_rc_id = _ui_resolve_controller_ressource_id(page, "Alpha", "Gold")
        zone_id = _ui_resolve_zone_id(page, "Alpha-Investigation")

        _ui_set_controller_ressource(page, alpha_gold_rc_id, amount=0)
        _ui_set_zone_holder(page, zone_id, alpha_id)
        _ui_set_gain_rules(page, gold_config_id, json.dumps([{
            "amount": self._rule_amount,
            "timing": "after_claim",
            "condition": {"type": "holds_zone", "zone_id": int(zone_id)},
        }]))

        end_turn(page, PHP_BASE_URL)
        post_amount = _ui_read_amount(page, alpha_id, "Gold")

        _ui_set_gain_rules(page, gold_config_id, None)
        _ui_set_zone_holder(page, zone_id, None)
        assert_no_collected_php_errors(page)
        ctx.close()

        type(self)._post_amount = post_amount
        yield

    def test_alpha_post_amount_matches_rule(self):
        assert self._post_amount == self._rule_amount, (
            f"Expected post-EOT Gold = {self._rule_amount}; got {self._post_amount}"
        )


class TestRessourceGainAfterClaimCountStyle:
    """`condition: {type: holds_zone}` (no zone_id) with Alpha holding 4
    zones must add `amount × 4` to Alpha's Gold after EOT (count-style).
    Baseline: TestConfig CSV already sets Alpha as holder of Gamma-Claims;
    this test adds Alpha as holder of Beta-Combat, Epsilon-Controlled,
    Zeta-Unclaimed → total 4."""

    _rule_amount = 50
    _expected_zone_count = 4

    @pytest.fixture(scope="class", autouse=True)
    def gain_state(self, browser):
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        alpha_id = _ui_resolve_controller_id(page, "Alpha")
        gold_config_id = _ui_resolve_ressource_config_id(page, "Gold")
        alpha_gold_rc_id = _ui_resolve_controller_ressource_id(page, "Alpha", "Gold")
        added_zone_ids = [
            _ui_resolve_zone_id(page, "Beta-Combat"),
            _ui_resolve_zone_id(page, "Epsilon-Controlled"),
            _ui_resolve_zone_id(page, "Zeta-Unclaimed"),
        ]

        _ui_set_controller_ressource(page, alpha_gold_rc_id, amount=0)
        for zid in added_zone_ids:
            _ui_set_zone_holder(page, zid, alpha_id)
        _ui_set_gain_rules(page, gold_config_id, json.dumps([{
            "amount": self._rule_amount,
            "timing": "after_claim",
            "condition": {"type": "holds_zone"},
        }]))

        end_turn(page, PHP_BASE_URL)
        post_amount = _ui_read_amount(page, alpha_id, "Gold")

        _ui_set_gain_rules(page, gold_config_id, None)
        for zid in added_zone_ids:
            _ui_set_zone_holder(page, zid, None)
        assert_no_collected_php_errors(page)
        ctx.close()

        type(self)._post_amount = post_amount
        yield

    def test_alpha_post_amount_matches_rule_times_count(self):
        expected = self._rule_amount * self._expected_zone_count
        assert self._post_amount == expected, (
            f"Expected post-EOT Gold = {expected} ({self._rule_amount}×"
            f"{self._expected_zone_count} matches); got {self._post_amount}"
        )


# Still @pytest.mark.db: needs synthetic temple-tagged locations owned
# by Alpha. Admin UI exposes location_types editor + delete + toggle
# but has no path to create a location or set locations.controller_id.
# Conversion deferred until a locations-ownership admin editor lands.
@pytest.mark.db
class TestRessourceGainOwnsLocationTypeTag:
    """`condition: {type: owns_location_type, location_type: 'temple'}` with
    Alpha owning 2 synthetic temple-tagged locations and 1 non-tagged location
    must add `amount × 2` (tag-filtered count) to Alpha's Gold."""

    _rule_amount = 30
    _temple_count = 2  # 2 tagged locations created
    _other_count = 1   # 1 untagged location created (should NOT count)

    @pytest.fixture(scope="class", autouse=True)
    def gain_state(self, browser):
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        gold_id, alpha_id = _resolve_ids()
        _reset_zone_holders()
        _zero_controller_ressource(alpha_id, gold_id)

        conn = _db_conn()
        cur = conn.cursor()
        cur.execute(f"SELECT id FROM `{GAME_PREFIX}zones` LIMIT 1")
        zone_id = cur.fetchone()['id']
        # Create 2 temple-tagged locations + 1 untagged, all owned by Alpha
        synthetic_ids = []
        for i in range(self._temple_count):
            cur.execute(
                f"INSERT INTO `{GAME_PREFIX}locations` "
                f"(name, description, zone_id, controller_id, can_be_destroyed, is_base, location_types) "
                f"VALUES (%s, %s, %s, %s, 0, 0, %s)",
                (f"SyntheticTempleA{i}", "test", zone_id, alpha_id, '["temple"]'),
            )
            synthetic_ids.append(cur.lastrowid)
        for i in range(self._other_count):
            cur.execute(
                f"INSERT INTO `{GAME_PREFIX}locations` "
                f"(name, description, zone_id, controller_id, can_be_destroyed, is_base, location_types) "
                f"VALUES (%s, %s, %s, %s, 0, 0, %s)",
                (f"SyntheticPlainA{i}", "test", zone_id, alpha_id, None),
            )
            synthetic_ids.append(cur.lastrowid)
        conn.commit()

        _set_gain_rules(gold_id, [{
            "amount": self._rule_amount,
            "timing": "after_claim",
            "condition": {"type": "owns_location_type", "location_type": "temple"},
        }])

        end_turn(page, PHP_BASE_URL)
        post_amount = _read_amount(alpha_id, gold_id)
        _set_gain_rules(gold_id, None)
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
        _reset_zone_holders()
        assert_no_collected_php_errors(page)
        ctx.close()

        type(self)._post_amount = post_amount
        yield

    def test_alpha_post_amount_matches_temple_tagged_count(self):
        """Pre-fixture sets Alpha Gold to amount=0, end_turn_gain=0.
        Alpha owns 2 temple-tagged + 1 untagged locations. Filter
        location_type='temple' matches the 2 temples; untagged skipped.
        Expected: 30 × 2 = 60."""
        expected = self._rule_amount * self._temple_count
        assert self._post_amount == expected, (
            f"Expected post-EOT Gold = {expected} ({self._rule_amount}×"
            f"{self._temple_count} temple matches; untagged location ignored); "
            f"got {self._post_amount}"
        )


class TestRessourceGainBeforeClaimTiming:
    """`timing: 'before_claim'` fires inside the existing updateRessources
    end_step at the start of EOT (rather than the new after_claim step).
    Verifies the inside-updateRessources hook actually runs."""

    _rule_amount = 8

    @pytest.fixture(scope="class", autouse=True)
    def gain_state(self, browser):
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        alpha_id = _ui_resolve_controller_id(page, "Alpha")
        gold_config_id = _ui_resolve_ressource_config_id(page, "Gold")
        alpha_gold_rc_id = _ui_resolve_controller_ressource_id(page, "Alpha", "Gold")
        zone_id = _ui_resolve_zone_id(page, "Alpha-Investigation")

        _ui_set_controller_ressource(page, alpha_gold_rc_id, amount=0)
        _ui_set_zone_holder(page, zone_id, alpha_id)
        _ui_set_gain_rules(page, gold_config_id, json.dumps([{
            "amount": self._rule_amount,
            "timing": "before_claim",
            "condition": {"type": "holds_zone", "zone_id": int(zone_id)},
        }]))

        end_turn(page, PHP_BASE_URL)
        post_amount = _ui_read_amount(page, alpha_id, "Gold")

        _ui_set_gain_rules(page, gold_config_id, None)
        _ui_set_zone_holder(page, zone_id, None)
        assert_no_collected_php_errors(page)
        ctx.close()

        type(self)._post_amount = post_amount
        yield

    def test_before_claim_hook_fires_with_correct_amount(self):
        assert self._post_amount == self._rule_amount, (
            f"Expected post-EOT Gold = {self._rule_amount} (before_claim "
            f"hook inside updateRessources); got {self._post_amount}"
        )


# Still @pytest.mark.db: needs a synthetic location with a specific
# locations.id owned by Alpha. No admin path to create a location or
# set its controller_id directly. Conversion deferred.
@pytest.mark.db
class TestRessourceGainOwnsLocationByLocationId:
    """Regression test for the location_id column-mapping fix:
    `owns_location_type` with `location_id: N` must match the location
    whose `locations.id = N` (not a hypothetical `location_id` column).
    Binary match → +amount × 1."""

    _rule_amount = 11

    @pytest.fixture(scope="class", autouse=True)
    def gain_state(self, browser):
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        gold_id, alpha_id = _resolve_ids()
        _reset_zone_holders()
        _zero_controller_ressource(alpha_id, gold_id)

        conn = _db_conn()
        cur = conn.cursor()
        cur.execute(f"SELECT id FROM `{GAME_PREFIX}zones` LIMIT 1")
        zone_id = cur.fetchone()['id']
        cur.execute(
            f"INSERT INTO `{GAME_PREFIX}locations` "
            f"(name, description, zone_id, controller_id, can_be_destroyed, is_base) "
            f"VALUES ('SyntheticByIdTarget', 'test', %s, %s, 0, 0)",
            (zone_id, alpha_id),
        )
        synthetic_id = cur.lastrowid
        conn.commit()

        _set_gain_rules(gold_id, [{
            "amount": self._rule_amount,
            "timing": "after_claim",
            "condition": {"type": "owns_location_type", "location_id": synthetic_id},
        }])

        end_turn(page, PHP_BASE_URL)
        post_amount = _read_amount(alpha_id, gold_id)
        _set_gain_rules(gold_id, None)
        # Cleanup: delete CKL rows first (FK), then synthetic location.
        cur.execute(
            f"DELETE FROM `{GAME_PREFIX}controller_known_locations` WHERE location_id = %s",
            (synthetic_id,),
        )
        cur.execute(
            f"DELETE FROM `{GAME_PREFIX}locations` WHERE id = %s",
            (synthetic_id,),
        )
        conn.commit()
        cur.close()
        conn.close()
        _reset_zone_holders()
        assert_no_collected_php_errors(page)
        ctx.close()

        type(self)._post_amount = post_amount
        yield

    def test_location_id_filter_matches_specific_location(self):
        """location_id filter resolves to locations.id at SQL build time
        (special-cased away from the auto l.{key} = ? pattern). Match
        is binary → +11."""
        assert self._post_amount == self._rule_amount, (
            f"Expected post-EOT Gold = {self._rule_amount} (location_id "
            f"binary match); got {self._post_amount}"
        )


class TestRessourceGainNegativeAmountPenalty:
    """Per docs/configuration.md, amount=0 is a no-op but negative amounts
    are allowed and subtract from the resource — useful for configuring
    conditional penalties. Starts Alpha Gold at 0; a -30 rule on a held
    zone should produce post-EOT amount = -30."""

    _rule_amount = -30

    @pytest.fixture(scope="class", autouse=True)
    def gain_state(self, browser):
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        alpha_id = _ui_resolve_controller_id(page, "Alpha")
        gold_config_id = _ui_resolve_ressource_config_id(page, "Gold")
        alpha_gold_rc_id = _ui_resolve_controller_ressource_id(page, "Alpha", "Gold")
        zone_id = _ui_resolve_zone_id(page, "Alpha-Investigation")

        _ui_set_controller_ressource(page, alpha_gold_rc_id, amount=0)
        _ui_set_zone_holder(page, zone_id, alpha_id)
        _ui_set_gain_rules(page, gold_config_id, json.dumps([{
            "amount": self._rule_amount,
            "timing": "after_claim",
            "condition": {"type": "holds_zone", "zone_id": int(zone_id)},
        }]))

        end_turn(page, PHP_BASE_URL)
        post_amount = _ui_read_amount(page, alpha_id, "Gold")

        _ui_set_gain_rules(page, gold_config_id, None)
        _ui_set_zone_holder(page, zone_id, None)
        assert_no_collected_php_errors(page)
        ctx.close()

        type(self)._post_amount = post_amount
        yield

    def test_negative_amount_subtracts(self):
        """Negative amount (-30) × 1 match = -30 net penalty."""
        assert self._post_amount == self._rule_amount, (
            f"Expected post-EOT Gold = {self._rule_amount} (penalty "
            f"semantics, amount < 0); got {self._post_amount}"
        )


class TestRessourceGainUnlockTurnSkipsBeforeThreshold:
    """`unlock_turn > current_turn` must suppress the rule at end-of-turn.
    Order-independent: read turncounter at fixture time and set
    unlock_turn = current_turn + 1 so the rule is locked at THIS EOT."""

    _rule_amount = 50

    @pytest.fixture(scope="class", autouse=True)
    def gain_state(self, browser):
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        alpha_id = _ui_resolve_controller_id(page, "Alpha")
        gold_config_id = _ui_resolve_ressource_config_id(page, "Gold")
        alpha_gold_rc_id = _ui_resolve_controller_ressource_id(page, "Alpha", "Gold")
        zone_id = _ui_resolve_zone_id(page, "Alpha-Investigation")
        locked_unlock_turn = _current_turn() + 1

        _ui_set_controller_ressource(page, alpha_gold_rc_id, amount=0)
        _ui_set_zone_holder(page, zone_id, alpha_id)
        _ui_set_gain_rules(page, gold_config_id, json.dumps([{
            "amount": self._rule_amount,
            "timing": "after_claim",
            "unlock_turn": locked_unlock_turn,
            "condition": {"type": "holds_zone", "zone_id": int(zone_id)},
        }]))

        end_turn(page, PHP_BASE_URL)
        post_amount = _ui_read_amount(page, alpha_id, "Gold")

        _ui_set_gain_rules(page, gold_config_id, None)
        _ui_set_zone_holder(page, zone_id, None)
        assert_no_collected_php_errors(page)
        ctx.close()

        type(self)._post_amount = post_amount
        yield

    def test_alpha_gold_does_not_gain_when_unlock_turn_above_current(self):
        assert self._post_amount == 0, (
            f"unlock_turn = current_turn + 1 must suppress the rule; "
            f"expected Gold=0, got {self._post_amount}"
        )


class TestRessourceGainUnlockTurnFiresAtThreshold:
    """`unlock_turn == current_turn` must fire (inclusive lower boundary —
    `value > turn` is false when equal). Order-independent: read
    turncounter at fixture time and set unlock_turn = current_turn."""

    _rule_amount = 50

    @pytest.fixture(scope="class", autouse=True)
    def gain_state(self, browser):
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        alpha_id = _ui_resolve_controller_id(page, "Alpha")
        gold_config_id = _ui_resolve_ressource_config_id(page, "Gold")
        alpha_gold_rc_id = _ui_resolve_controller_ressource_id(page, "Alpha", "Gold")
        zone_id = _ui_resolve_zone_id(page, "Alpha-Investigation")
        threshold_unlock_turn = _current_turn()

        _ui_set_controller_ressource(page, alpha_gold_rc_id, amount=0)
        _ui_set_zone_holder(page, zone_id, alpha_id)
        _ui_set_gain_rules(page, gold_config_id, json.dumps([{
            "amount": self._rule_amount,
            "timing": "after_claim",
            "unlock_turn": threshold_unlock_turn,
            "condition": {"type": "holds_zone", "zone_id": int(zone_id)},
        }]))

        end_turn(page, PHP_BASE_URL)
        post_amount = _ui_read_amount(page, alpha_id, "Gold")

        _ui_set_gain_rules(page, gold_config_id, None)
        _ui_set_zone_holder(page, zone_id, None)
        assert_no_collected_php_errors(page)
        ctx.close()

        type(self)._post_amount = post_amount
        yield

    def test_alpha_gold_gains_when_unlock_turn_equals_current(self):
        assert self._post_amount == self._rule_amount, (
            f"unlock_turn == current_turn must fire (inclusive boundary); "
            f"expected Gold={self._rule_amount}, got {self._post_amount}"
        )
