"""Regression tests for insufficient-stock handling in the three
spendRessourcesTo* functions (build base / move base / repair location).

Pre-refactor (and PR #82-era) bug:
  - The three functions ran an UPDATE on every controller_ressources row
    regardless of whether stock >= cost. With cost > stock, the PHP
    subtraction wrote a NEGATIVE value back to the DB; rowCount remained 1
    so the silent "Failed to update" branch never fired; the function
    returned true unconditionally. Callers proceeded to actually
    create/move/repair the base/location.
  - Crafted GET requests bypassing the display-time `hasEnough*` check
    landed straight in the spend path. TOCTOU races where another action
    drops stock between display and commit had the same outcome.

Post-refactor:
  - Each spend* composes `consumeRessource()` calls inside a transaction.
  - `consumeRessource` UPDATE has `WHERE amount >= :amt` — atomic, no negative
    writes possible; matched=0 returns false.
  - First failure rolls the whole transaction back; spend* returns false.
  - Callers in controllers/functions.php::createBase, moveBase, and
    controllers/action.php repairLocation branch render
    "Stock insuffisant ou modifié." and abort the base/repair op.

Test pattern: directly mutate `controller_ressources.amount` via SQL to put
the controller below cost, send a crafted GET to controllers/action.php
as gm, assert the page shows the notification, the operation did NOT
happen (no INSERT / no UPDATE on the locations side), and the ressource
amount is unchanged.

Run:
    python3 -m pytest tests/test_spend_ressources_insufficient_stock_e2e.py -v
"""
import pytest
import pymysql

from conftest import (
    GAME_PREFIX, MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB,
    PHP_BASE_URL, ensure_gm_login,
)
from helpers import (
    DB_AVAILABLE, load_minimal_data, load_scenario_via_admin,
    safe_goto, register_php_error_listener, assert_no_collected_php_errors,
    ui_controller_ids_map, ui_zone_id,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def setup_testconfig(browser):
    if DB_AVAILABLE:
        load_minimal_data()
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
    yield


def _db():
    return pymysql.connect(
        host=MYSQL_HOST, port=MYSQL_PORT, user=MYSQL_USER,
        password=MYSQL_PASSWORD, database=MYSQL_DB,
        charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor,
    )


def _gold_id():
    conn = _db(); cur = conn.cursor()
    cur.execute(f"SELECT id FROM `{GAME_PREFIX}ressources_config` WHERE ressource_name='Gold'")
    row = cur.fetchone()
    cur.close(); conn.close()
    return int(row['id'])


def _set_gold(controller_id: int, amount: int):
    conn = _db(); cur = conn.cursor()
    cur.execute(
        f"UPDATE `{GAME_PREFIX}controller_ressources` SET amount = %s "
        f"WHERE controller_id = %s AND ressource_id = %s",
        (amount, controller_id, _gold_id()),
    )
    conn.commit()
    cur.close(); conn.close()


def _read_gold(controller_id: int) -> int:
    conn = _db(); cur = conn.cursor()
    cur.execute(
        f"SELECT amount FROM `{GAME_PREFIX}controller_ressources` "
        f"WHERE controller_id = %s AND ressource_id = %s",
        (controller_id, _gold_id()),
    )
    row = cur.fetchone()
    cur.close(); conn.close()
    return int(row['amount'])


def _count_bases_for(controller_id: int) -> int:
    conn = _db(); cur = conn.cursor()
    cur.execute(
        f"SELECT COUNT(*) AS c FROM `{GAME_PREFIX}locations` "
        f"WHERE controller_id = %s AND is_base = 1",
        (controller_id,),
    )
    row = cur.fetchone()
    cur.close(); conn.close()
    return int(row['c'])


def _zone_of_base(base_id: int) -> int:
    conn = _db(); cur = conn.cursor()
    cur.execute(
        f"SELECT zone_id FROM `{GAME_PREFIX}locations` WHERE id = %s",
        (base_id,),
    )
    row = cur.fetchone()
    cur.close(); conn.close()
    return int(row['zone_id'])


def _base_id_of(controller_id: int) -> int:
    conn = _db(); cur = conn.cursor()
    cur.execute(
        f"SELECT id FROM `{GAME_PREFIX}locations` "
        f"WHERE controller_id = %s AND is_base = 1 LIMIT 1",
        (controller_id,),
    )
    row = cur.fetchone()
    cur.close(); conn.close()
    return int(row['id'])


def _set_location_repairable(location_name: str):
    """Force a location into the post-attack 'repairable' state (can_be_repaired=1,
    can_be_destroyed=0) so the repairLocation handler accepts it."""
    conn = _db(); cur = conn.cursor()
    cur.execute(
        f"UPDATE `{GAME_PREFIX}locations` SET can_be_repaired = 1, can_be_destroyed = 0 "
        f"WHERE name = %s",
        (location_name,),
    )
    conn.commit()
    cur.close(); conn.close()


def _location_repaired_state(name: str) -> dict:
    conn = _db(); cur = conn.cursor()
    cur.execute(
        f"SELECT can_be_repaired, can_be_destroyed FROM `{GAME_PREFIX}locations` WHERE name = %s",
        (name,),
    )
    row = cur.fetchone()
    cur.close(); conn.close()
    return row


def _controller_ids(browser):
    ctx = browser.new_context()
    page = ctx.new_page()
    ensure_gm_login(page, PHP_BASE_URL)
    ids = ui_controller_ids_map(page, PHP_BASE_URL)
    ctx.close()
    return ids


# ---------------------------------------------------------------------------
# createBase
# ---------------------------------------------------------------------------

class TestBuildBaseInsufficientStock:
    """createBase must abort cleanly when stock < base_building_cost: no new
    locations row, no ressource decrement, notification rendered."""

    def test_build_base_aborts_when_stock_below_cost(self, browser):
        ids = _controller_ids(browser)
        alpha_id = ids["Alpha"]
        beta_zone_id = None  # resolved via UI

        bases_before = _count_bases_for(alpha_id)
        _set_gold(alpha_id, 1)  # cost=10, set to 1 → insufficient

        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)
        beta_zone_id = ui_zone_id(page, "Beta-Combat", base_url=PHP_BASE_URL)
        safe_goto(
            page,
            f"{PHP_BASE_URL}/controllers/action.php"
            f"?controller_id={alpha_id}&zone_id={beta_zone_id}&createBase=1",
        )
        page.wait_for_load_state("load")
        html = page.content()
        ctx.close()

        assert "Stock insuffisant ou modifié" in html, (
            "createBase with insufficient stock must render the "
            "'Stock insuffisant ou modifié' notification; not found in page"
        )
        assert _count_bases_for(alpha_id) == bases_before, (
            "createBase with insufficient stock must NOT INSERT a locations row"
        )
        assert _read_gold(alpha_id) == 1, (
            "createBase with insufficient stock must NOT decrement (atomic guard) — "
            f"expected Gold=1 unchanged, got {_read_gold(alpha_id)}"
        )

    def test_build_base_decrements_when_stock_sufficient(self, browser):
        ids = _controller_ids(browser)
        alpha_id = ids["Alpha"]
        bases_before = _count_bases_for(alpha_id)
        _set_gold(alpha_id, 50)  # cost=10, set to 50 → sufficient

        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)
        # Pick a zone where Alpha has no base; Eta-Hidden is unclaimed in TestConfig.
        zone_id = ui_zone_id(page, "Eta-Hidden", base_url=PHP_BASE_URL)
        safe_goto(
            page,
            f"{PHP_BASE_URL}/controllers/action.php"
            f"?controller_id={alpha_id}&zone_id={zone_id}&createBase=1",
        )
        page.wait_for_load_state("load")
        html = page.content()
        ctx.close()

        assert "Stock insuffisant" not in html, (
            f"Sufficient stock must NOT trigger the insufficient notification; got it"
        )
        assert _count_bases_for(alpha_id) == bases_before + 1, (
            f"Sufficient stock must INSERT exactly one new base for Alpha; "
            f"before={bases_before}, after={_count_bases_for(alpha_id)}"
        )
        assert _read_gold(alpha_id) == 40, (
            f"createBase must deduct base_building_cost=10 atomically; "
            f"expected Gold=40, got {_read_gold(alpha_id)}"
        )


# ---------------------------------------------------------------------------
# moveBase
# ---------------------------------------------------------------------------

class TestMoveBaseInsufficientStock:
    """moveBase must abort cleanly when stock < base_moving_cost: locations
    row stays in original zone, no ressource decrement, notification rendered."""

    def test_move_base_aborts_when_stock_below_cost(self, browser):
        ids = _controller_ids(browser)
        echo_id = ids["Echo"]
        echo_base_id = _base_id_of(echo_id)
        zone_before = _zone_of_base(echo_base_id)

        _set_gold(echo_id, 1)  # cost=5, set to 1 → insufficient

        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)
        # Move to Alpha-Investigation (arbitrary other zone)
        target_zone_id = ui_zone_id(page, "Alpha-Investigation", base_url=PHP_BASE_URL)
        safe_goto(
            page,
            f"{PHP_BASE_URL}/controllers/action.php"
            f"?controller_id={echo_id}&base_id={echo_base_id}"
            f"&zone_id={target_zone_id}&moveBase=1",
        )
        page.wait_for_load_state("load")
        html = page.content()
        ctx.close()

        assert "Stock insuffisant ou modifié" in html, (
            "moveBase with insufficient stock must render the notification"
        )
        assert _zone_of_base(echo_base_id) == zone_before, (
            f"moveBase with insufficient stock must NOT change locations.zone_id; "
            f"expected {zone_before}, got {_zone_of_base(echo_base_id)}"
        )
        assert _read_gold(echo_id) == 1, (
            f"moveBase with insufficient stock must NOT decrement; "
            f"expected Gold=1 unchanged, got {_read_gold(echo_id)}"
        )

    def test_move_base_decrements_when_stock_sufficient(self, browser):
        ids = _controller_ids(browser)
        # Echo is used here too (only controller with both a base and a Gold
        # row in TestConfig). The prior abort test left the base where it
        # was; here we set Gold=50 and assert the move + 5-Gold deduction.
        echo_id = ids["Echo"]
        echo_base_id = _base_id_of(echo_id)
        _set_gold(echo_id, 50)  # cost=5, set to 50 → sufficient

        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)
        target_zone_id = ui_zone_id(page, "Eta-Hidden", base_url=PHP_BASE_URL)
        safe_goto(
            page,
            f"{PHP_BASE_URL}/controllers/action.php"
            f"?controller_id={echo_id}&base_id={echo_base_id}"
            f"&zone_id={target_zone_id}&moveBase=1",
        )
        page.wait_for_load_state("load")
        html = page.content()
        ctx.close()

        assert "Stock insuffisant" not in html, (
            "Sufficient stock must NOT trigger the insufficient notification"
        )
        assert _zone_of_base(echo_base_id) == target_zone_id, (
            f"moveBase must move the base to the new zone; "
            f"expected zone_id={target_zone_id}, got {_zone_of_base(echo_base_id)}"
        )
        assert _read_gold(echo_id) == 45, (
            f"moveBase must deduct base_moving_cost=5; "
            f"expected Gold=45, got {_read_gold(echo_id)}"
        )


# ---------------------------------------------------------------------------
# repairLocation
# ---------------------------------------------------------------------------

class TestRepairLocationInsufficientStock:
    """repairLocation must abort cleanly when stock < location_repaire_cost:
    locations row unchanged, no ressource decrement, notification rendered."""

    def test_repair_location_aborts_when_stock_below_cost(self, browser):
        ids = _controller_ids(browser)
        echo_id = ids["Echo"]
        _set_location_repairable("Echo-Base")
        before_state = _location_repaired_state("Echo-Base")
        _set_gold(echo_id, 1)  # cost=3, set to 1 → insufficient

        # Resolve location id (admin-management surface)
        conn = _db(); cur = conn.cursor()
        cur.execute(
            f"SELECT id FROM `{GAME_PREFIX}locations` WHERE name = 'Echo-Base'"
        )
        loc_id = int(cur.fetchone()['id'])
        cur.close(); conn.close()

        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)
        safe_goto(
            page,
            f"{PHP_BASE_URL}/controllers/action.php"
            f"?controller_id={echo_id}&target_location_id={loc_id}&repairLocation=1",
        )
        page.wait_for_load_state("load")
        html = page.content()
        ctx.close()

        assert "Stock insuffisant ou modifié" in html, (
            "repairLocation with insufficient stock must render the notification"
        )
        # The location state must not have been mutated by updateLocation
        # (which would have applied activate_json transformations).
        after_state = _location_repaired_state("Echo-Base")
        assert after_state == before_state, (
            f"repairLocation with insufficient stock must NOT mutate the location; "
            f"before={before_state}, after={after_state}"
        )
        assert _read_gold(echo_id) == 1, (
            f"repairLocation with insufficient stock must NOT decrement; "
            f"expected Gold=1 unchanged, got {_read_gold(echo_id)}"
        )

    def test_repair_location_decrements_when_stock_sufficient(self, browser):
        ids = _controller_ids(browser)
        echo_id = ids["Echo"]
        _set_location_repairable("Echo-Base")
        _set_gold(echo_id, 50)  # cost=3, set to 50 → sufficient

        conn = _db(); cur = conn.cursor()
        cur.execute(
            f"SELECT id FROM `{GAME_PREFIX}locations` WHERE name = 'Echo-Base'"
        )
        loc_id = int(cur.fetchone()['id'])
        cur.close(); conn.close()

        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)
        safe_goto(
            page,
            f"{PHP_BASE_URL}/controllers/action.php"
            f"?controller_id={echo_id}&target_location_id={loc_id}&repairLocation=1",
        )
        page.wait_for_load_state("load")
        html = page.content()
        ctx.close()

        assert "Stock insuffisant" not in html, (
            "Sufficient stock must NOT trigger the insufficient notification"
        )
        assert _read_gold(echo_id) == 47, (
            f"repairLocation must deduct location_repaire_cost=3; "
            f"expected Gold=47, got {_read_gold(echo_id)}"
        )
