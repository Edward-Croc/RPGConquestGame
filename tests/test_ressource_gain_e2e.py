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
    DB_AVAILABLE, end_turn, load_minimal_data, safe_goto,
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
    context = browser.new_context()
    page = context.new_page()
    ensure_gm_login(page, PHP_BASE_URL)
    safe_goto(page, f"{PHP_BASE_URL}/base/admin.php")
    page.wait_for_load_state("networkidle")
    page.locator("select[name='config_name']").select_option("TestConfig")
    page.locator("input[name='submit'][value='Submit']").click()
    page.wait_for_timeout(5000)
    page.wait_for_load_state("load", timeout=90000)
    context.close()
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


class TestRessourceGainAfterClaimSpecificZone:
    """`condition: {type: holds_zone, zone_id: Z}` with Alpha holding Z
    must add exactly `amount` to Alpha's Gold after EOT (binary match)."""

    _rule_amount = 7

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
        target_zone_id = cur.fetchone()['id']
        cur.execute(
            f"UPDATE `{GAME_PREFIX}zones` SET holder_controller_id = %s WHERE id = %s",
            (alpha_id, target_zone_id),
        )
        conn.commit()
        cur.close()
        conn.close()

        _set_gain_rules(gold_id, [{
            "amount": self._rule_amount,
            "timing": "after_claim",
            "condition": {"type": "holds_zone", "zone_id": target_zone_id},
        }])

        end_turn(page, PHP_BASE_URL)
        post_amount = _read_amount(alpha_id, gold_id)
        _set_gain_rules(gold_id, None)
        _reset_zone_holders()
        assert_no_collected_php_errors(page)
        ctx.close()

        type(self)._post_amount = post_amount
        yield

    def test_alpha_post_amount_matches_rule(self):
        """Pre-fixture sets Alpha Gold to amount=0, end_turn_gain=0.
        The 1× holds_zone rule with Alpha holding the target zone
        adds 7 → expected post-EOT amount = 7."""
        assert self._post_amount == self._rule_amount, (
            f"Expected post-EOT Gold = {self._rule_amount}; got {self._post_amount}"
        )


class TestRessourceGainAfterClaimCountStyle:
    """`condition: {type: holds_zone}` (no zone_id) with Alpha holding 3
    zones must add `amount × 3` to Alpha's Gold after EOT (count-style)."""

    _rule_amount = 50
    _zone_count = 3

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
        cur.execute(f"SELECT id FROM `{GAME_PREFIX}zones` ORDER BY id ASC LIMIT %s", (self._zone_count,))
        zoneIds = [r['id'] for r in cur.fetchall()]
        for zoneId in zoneIds:
            cur.execute(
                f"UPDATE `{GAME_PREFIX}zones` SET holder_controller_id = %s WHERE id = %s",
                (alpha_id, zoneId),
            )
        conn.commit()
        cur.close()
        conn.close()

        _set_gain_rules(gold_id, [{
            "amount": self._rule_amount,
            "timing": "after_claim",
            "condition": {"type": "holds_zone"},
        }])

        end_turn(page, PHP_BASE_URL)
        post_amount = _read_amount(alpha_id, gold_id)
        _set_gain_rules(gold_id, None)
        _reset_zone_holders()
        assert_no_collected_php_errors(page)
        ctx.close()

        type(self)._post_amount = post_amount
        yield

    def test_alpha_post_amount_matches_rule_times_count(self):
        """Pre-fixture sets Alpha Gold to amount=0, end_turn_gain=0.
        The count-style holds_zone rule with Alpha holding 3 zones
        adds 50×3 = 150 → expected post-EOT amount = 150."""
        expected = self._rule_amount * self._zone_count
        assert self._post_amount == expected, (
            f"Expected post-EOT Gold = {expected} ({self._rule_amount}×"
            f"{self._zone_count} matches); got {self._post_amount}"
        )
