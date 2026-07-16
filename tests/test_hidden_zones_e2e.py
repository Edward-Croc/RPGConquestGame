"""E2E tests for the `is_hidden` boolean column on zones and its
checkbox editor on `zones/management_zones.php`: schema presence,
checkbox presence, tick/untick persistence, and claimer-update
regression guard.

Persistence contract:
  - Checkbox ticked         -> UPDATE is_hidden = 1
  - Checkbox unticked (form submitted without the field, per HTML
    form semantics) -> UPDATE is_hidden = 0

Zone locked: `Zeta-Unclaimed` (TestConfig CSV row 8), an untouched
reserve zone. Shared with test_zones_management_zones_e2e.py —
is_hidden is orthogonal to zone_rules/adjacent_zones so no state race.

Run:
    python3 -m pytest tests/test_hidden_zones_e2e.py -v
"""
import pymysql
import pytest
from playwright.sync_api import Page

from conftest import (
    GAME_PREFIX, MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB,
    PHP_BASE_URL, ensure_gm_login,
)
from helpers import (
    DB_AVAILABLE, load_minimal_data, load_scenario_via_admin, safe_goto,
)


_TARGET_ZONE = "Zeta-Unclaimed"


def _db_conn():
    return pymysql.connect(
        host=MYSQL_HOST, port=MYSQL_PORT, user=MYSQL_USER,
        password=MYSQL_PASSWORD, database=MYSQL_DB,
        charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor,
    )


def _describe_zones_columns():
    """Return the list of column names on the zones table via DESCRIBE."""
    conn = _db_conn()
    cur = conn.cursor()
    cur.execute(f"DESCRIBE `{GAME_PREFIX}zones`")
    rows = cur.fetchall()
    cur.close()
    conn.close()
    return [row["Field"] for row in rows]


def _get_zone_id(zone_name):
    """Return the id of the zone with the given name from the DB."""
    conn = _db_conn()
    cur = conn.cursor()
    cur.execute(
        f"SELECT id FROM `{GAME_PREFIX}zones` WHERE name=%s LIMIT 1",
        (zone_name,),
    )
    row = cur.fetchone()
    cur.close()
    conn.close()
    assert row, f"Zone {zone_name!r} not found in TestConfig"
    return int(row["id"])


def _get_zone_row(zone_id):
    """Return {is_hidden, claimer_controller_id, holder_controller_id}
    for a zone id."""
    conn = _db_conn()
    cur = conn.cursor()
    cur.execute(
        f"SELECT is_hidden, claimer_controller_id, holder_controller_id "
        f"FROM `{GAME_PREFIX}zones` WHERE id=%s",
        (zone_id,),
    )
    row = cur.fetchone()
    cur.close()
    conn.close()
    return row


def _get_controller_id_by_lastname(lastname):
    """Return a controller id by lastname (used for the claimer regression)."""
    conn = _db_conn()
    cur = conn.cursor()
    cur.execute(
        f"SELECT id FROM `{GAME_PREFIX}controllers` WHERE lastname=%s LIMIT 1",
        (lastname,),
    )
    row = cur.fetchone()
    cur.close()
    conn.close()
    assert row, f"Controller lastname={lastname!r} not found"
    return int(row["id"])


def _set_is_hidden(zone_id, int_value):
    """Direct DB write of `is_hidden` (0 or 1)."""
    conn = _db_conn()
    cur = conn.cursor()
    cur.execute(
        f"UPDATE `{GAME_PREFIX}zones` SET is_hidden=%s WHERE id=%s",
        (int_value, zone_id),
    )
    conn.commit()
    cur.close()
    conn.close()


def _reset_zone(zone_id):
    """Wipe claimer + holder + is_hidden on the target row so each test
    starts from a known baseline."""
    conn = _db_conn()
    cur = conn.cursor()
    cur.execute(
        f"UPDATE `{GAME_PREFIX}zones` "
        f"SET claimer_controller_id=NULL, holder_controller_id=NULL, "
        f"    is_hidden=0 "
        f"WHERE id=%s",
        (zone_id,),
    )
    conn.commit()
    cur.close()
    conn.close()


def _row_form_locator(page, zone_id):
    """Return the row locator for the zone whose hidden zone_id matches.

    Uses `tr:has(...)` rather than `form:has(...)` because the page uses
    the standard `<tr> <form>...</form> </tr>` pattern (matching
    ressources/management.php). Chromium's HTML5 parser DOM-evicts the
    <form> element in that pattern, so form-scoped locators return no
    matches; scoping by the enclosing <tr> is the reliable path."""
    return page.locator(
        f"tr:has(input[name='zone_id'][value='{zone_id}'])"
    )


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(autouse=True)
def _require_db():
    if not DB_AVAILABLE:
        pytest.skip("No local MySQL available")


@pytest.fixture(scope="module", autouse=True)
def load_test_config(browser):
    """Load TestConfig fresh for this module so Zeta-Unclaimed exists."""
    if not DB_AVAILABLE:
        yield
        return
    load_minimal_data()
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
    yield


class TestIsHiddenSchemaAndAdmin:
    """Verifies the yet-to-be-built `is_hidden` boolean column and its
    admin checkbox editor on zones/management_zones.php: schema
    presence, checkbox presence, tick/untick persistence, and the
    claimer-update regression guard."""

    def test_is_hidden_column_exists_in_zones_schema(self, page: Page, base_url):
        """After loading TestConfig, DESCRIBE zones must include an
        `is_hidden` column (D89.1 Option A: BOOL DEFAULT FALSE)."""
        columns = _describe_zones_columns()
        assert "is_hidden" in columns, (
            f"Expected `is_hidden` column in zones table after TestConfig "
            f"load; got columns={columns!r}"
        )

    def test_is_hidden_checkbox_present_on_management_zones(
        self, page: Page, base_url
    ):
        """A input[type='checkbox'][name='is_hidden'] must exist for at
        least one row on management_zones.php."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/zones/management_zones.php")
        page.wait_for_load_state("load")
        checkboxCount = page.locator(
            "input[type='checkbox'][name='is_hidden']"
        ).count()
        assert checkboxCount >= 1, (
            "Expected at least one input[type='checkbox'][name='is_hidden'] "
            f"on management_zones.php; found {checkboxCount}"
        )

    def test_check_is_hidden_persists_to_db(self, page: Page, base_url):
        """Ticking the row's is_hidden checkbox and submitting must
        write `is_hidden=1` to the DB."""
        zoneId = _get_zone_id(_TARGET_ZONE)
        _reset_zone(zoneId)
        assert _get_zone_row(zoneId)["is_hidden"] == 0, (
            "Pre-condition failed: is_hidden should be 0 after reset"
        )
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/zones/management_zones.php")
        page.wait_for_load_state("load")
        form = _row_form_locator(page, zoneId)
        form.locator("input[type='checkbox'][name='is_hidden']").check()
        form.locator("button[type='submit']").click()
        page.wait_for_load_state("load")

        stored = _get_zone_row(zoneId)["is_hidden"]
        assert stored == 1, (
            f"After checked-checkbox submit, is_hidden for {_TARGET_ZONE} "
            f"should be 1; got {stored!r}"
        )

    def test_uncheck_is_hidden_persists_to_db(self, page: Page, base_url):
        """Pre-seed is_hidden=1, load form, leave checkbox UNCHECKED,
        submit; is_hidden must persist as 0."""
        zoneId = _get_zone_id(_TARGET_ZONE)
        _reset_zone(zoneId)
        _set_is_hidden(zoneId, 1)
        assert _get_zone_row(zoneId)["is_hidden"] == 1, (
            "Pre-condition failed: is_hidden should be 1 after seed"
        )

        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/zones/management_zones.php")
        page.wait_for_load_state("load")
        form = _row_form_locator(page, zoneId)
        # Uncheck the pre-checked box to simulate the admin clearing it.
        form.locator("input[type='checkbox'][name='is_hidden']").uncheck()
        form.locator("button[type='submit']").click()
        page.wait_for_load_state("load")

        stored = _get_zone_row(zoneId)["is_hidden"]
        assert stored == 0, (
            f"Unchecked-checkbox submit must persist is_hidden=0; "
            f"got {stored!r}"
        )

    def test_is_hidden_regression_with_claimer_update(
        self, page: Page, base_url
    ):
        """Regression guard: with the new checkbox in place, a POST
        that changes claimer_id while leaving the pre-checked is_hidden
        alone must update the claimer AND keep is_hidden=1."""
        zoneId = _get_zone_id(_TARGET_ZONE)
        _reset_zone(zoneId)
        _set_is_hidden(zoneId, 1)
        alphaId = _get_controller_id_by_lastname("Alpha")

        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/zones/management_zones.php")
        page.wait_for_load_state("load")
        form = _row_form_locator(page, zoneId)
        # Leave the pre-checked box alone; only change claimer.
        form.locator("select[name='claimer_id']").select_option(str(alphaId))
        form.locator("button[type='submit']").click()
        page.wait_for_load_state("load")

        row = _get_zone_row(zoneId)
        assert row["claimer_controller_id"] == alphaId, (
            "Claimer update did not persist alongside the is_hidden field"
        )
        assert row["is_hidden"] == 1, (
            "Regression: is_hidden was cleared by an unrelated claimer "
            f"update; got {row['is_hidden']!r}"
        )
