"""E2E tests for the `is_hidden` boolean column on zones and its
checkbox editor on `zones/management_zones.php`: schema presence,
checkbox presence, tick/untick persistence, and claimer-update
regression guard.

Persistence contract:
  - Checkbox ticked         -> UPDATE is_hidden = 1
  - Checkbox unticked (form submitted without the field, per HTML
    form semantics) -> UPDATE is_hidden = 0

Runtime visibility contract (TestHiddenZoneVisibility): a zone with
`is_hidden=1` is filtered out of `showZoneSelect` (workers/new.php)
and `zones/view.php` for every non-privileged controller that is
neither its holder nor its claimer. GM (privileged) always sees.
Coexists with the legacy `hide_turn_zero` filter at turn 0.

Zone locked for the schema/admin class: `Zeta-Unclaimed` (TestConfig
CSV row 8). The visibility class uses `Epsilon-Controlled` (reserve
row 6) plus `Eta-Hidden` (row 8, hide_turn_zero=1) for the legacy-
coexistence case. Shared with test_zones_management_zones_e2e.py —
is_hidden / holder / claimer flags are orthogonal to
zone_rules/adjacent_zones so no state race.

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
    DB_AVAILABLE, _wait_loaded, load_minimal_data, load_scenario_via_admin,
    login_as, safe_goto,
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


def _set_zone_flags(zone_id, is_hidden=None, holder_controller_id=None,
                    claimer_controller_id=None):
    """Direct DB write to prime a zone's visibility flags. Fields left
    as None are not modified."""
    updates = []
    params = []
    if is_hidden is not None:
        updates.append("is_hidden=%s")
        params.append(int(is_hidden))
    if holder_controller_id is not None:
        updates.append("holder_controller_id=%s")
        params.append(int(holder_controller_id))
    if claimer_controller_id is not None:
        updates.append("claimer_controller_id=%s")
        params.append(int(claimer_controller_id))
    if not updates:
        return
    params.append(zone_id)
    conn = _db_conn()
    cur = conn.cursor()
    cur.execute(
        f"UPDATE `{GAME_PREFIX}zones` SET {', '.join(updates)} WHERE id=%s",
        params,
    )
    conn.commit()
    cur.close()
    conn.close()


def _reset_zone_visibility(zone_id):
    """Reset a zone: is_hidden=0, holder_controller_id=NULL,
    claimer_controller_id=NULL."""
    conn = _db_conn()
    cur = conn.cursor()
    cur.execute(
        f"UPDATE `{GAME_PREFIX}zones` "
        f"SET is_hidden=0, holder_controller_id=NULL, "
        f"    claimer_controller_id=NULL "
        f"WHERE id=%s",
        (zone_id,),
    )
    conn.commit()
    cur.close()
    conn.close()


def _reset_all_recruitment_counters():
    """Zero every controller's turn_firstcome_workers / turn_recruited_workers
    so a test that navigates to workers/new.php can render the recruit
    form (the page increments the counter each visit and blocks past
    turn_firstcome_workers >= config.turn_firstcome_workers)."""
    conn = _db_conn()
    cur = conn.cursor()
    cur.execute(
        f"UPDATE `{GAME_PREFIX}controllers` "
        f"SET turn_firstcome_workers=0, turn_recruited_workers=0"
    )
    conn.commit()
    cur.close()
    conn.close()


def _worker_new_zone_option_names(page):
    """Return the zone-name text (id suffix stripped) of each <option>
    in <select name='zone_id'> on workers/new.php. Option label is
    `Name (id)` per showZoneSelect."""
    raw = page.locator("select[name='zone_id'] option").all_text_contents()
    return [r.split(" (")[0].strip() for r in raw]


def _zones_view_box_zone_names(page):
    """Names of zones rendered as `<div class="box" id="zone-N">` on
    zones/view.php. Reads the `<h3>` text of each box; the title format
    is `Name (id) ...` so we strip the id + trailing banner suffix."""
    boxes = page.locator("div.section.zones div.box[id^='zone-']").all()
    names = []
    for box in boxes:
        raw = (box.locator("h3").first.inner_text() or "").strip()
        names.append(raw.split(" (")[0].strip())
    return names


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


class TestHiddenZoneVisibility:
    """Runtime visibility of `is_hidden=1` zones: only the holder /
    claimer controller and GM see them; other non-privileged
    controllers do not. Covers the `showZoneSelect` output on
    workers/new.php and the zone-box list on zones/view.php, plus
    coexistence with the legacy `hide_turn_zero` filter and mid-turn
    holder-flip reveal."""

    _EPSILON = "Epsilon-Controlled"
    _ETA = "Eta-Hidden"

    def test_non_privileged_non_holder_cannot_see_hidden_zone_in_worker_new_select(
        self, page: Page, base_url
    ):
        """Alpha is neither holder nor claimer of Epsilon (is_hidden=1);
        Epsilon must not appear in workers/new.php zone select."""
        epsilonId = _get_zone_id(self._EPSILON)
        _reset_zone_visibility(epsilonId)
        _set_zone_flags(epsilonId, is_hidden=1)
        _reset_all_recruitment_counters()

        login_as(page, base_url, "single_player", "test")
        safe_goto(page, f"{base_url}/workers/new.php")
        _wait_loaded(page, "select[name='zone_id']")
        zoneNames = _worker_new_zone_option_names(page)
        assert self._EPSILON not in zoneNames, (
            f"Non-holder non-privileged controller must not see a hidden "
            f"zone; got zoneNames={zoneNames!r}"
        )

    def test_holder_controller_sees_hidden_zone_in_worker_new_select(
        self, page: Page, base_url
    ):
        """Alpha holds Epsilon (is_hidden=1); Epsilon must appear in
        Alpha's workers/new.php zone select."""
        epsilonId = _get_zone_id(self._EPSILON)
        alphaId = _get_controller_id_by_lastname("Alpha")
        _reset_zone_visibility(epsilonId)
        _set_zone_flags(epsilonId, is_hidden=1, holder_controller_id=alphaId)
        _reset_all_recruitment_counters()

        login_as(page, base_url, "single_player", "test")
        safe_goto(page, f"{base_url}/workers/new.php")
        _wait_loaded(page, "select[name='zone_id']")
        zoneNames = _worker_new_zone_option_names(page)
        assert self._EPSILON in zoneNames, (
            f"Holder controller must see the hidden zone it owns; got "
            f"zoneNames={zoneNames!r}"
        )

    def test_gm_sees_hidden_zone_in_worker_new_select(
        self, page: Page, base_url
    ):
        """GM (privileged) sees every zone regardless of is_hidden — on
        both workers/new.php's recruitment select and the zone-box list
        rendered by zones/action.php."""
        epsilonId = _get_zone_id(self._EPSILON)
        _reset_zone_visibility(epsilonId)
        _set_zone_flags(epsilonId, is_hidden=1)
        _reset_all_recruitment_counters()
        alphaId = _get_controller_id_by_lastname("Alpha")

        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/workers/new.php?controller_id={alphaId}")
        _wait_loaded(page, "select[name='zone_id']")
        zoneNames = _worker_new_zone_option_names(page)
        assert self._EPSILON in zoneNames, (
            f"GM must see hidden zone in worker recruitment; got "
            f"zoneNames={zoneNames!r}"
        )

        safe_goto(page, f"{base_url}/zones/action.php")
        _wait_loaded(page, "div.section.zones")
        viewNames = _zones_view_box_zone_names(page)
        assert self._EPSILON in viewNames, (
            f"GM must see hidden zone on zones/action.php; got "
            f"viewNames={viewNames!r}"
        )

    def test_hide_turn_zero_coexistence_preserved_at_turn_zero_for_non_privileged(
        self, page: Page, base_url
    ):
        """A zone with hide_turn_zero=1 remains hidden from
        non-privileged controllers at turn 0 (legacy filter path). The
        new is_hidden filter must not regress this behaviour."""
        etaId = _get_zone_id(self._ETA)
        _reset_zone_visibility(etaId)
        _reset_all_recruitment_counters()

        try:
            login_as(page, base_url, "single_player", "test")
            safe_goto(page, f"{base_url}/workers/new.php")
            _wait_loaded(page, "select[name='zone_id']")
            zoneNames = _worker_new_zone_option_names(page)
            assert self._ETA not in zoneNames, (
                f"Eta-Hidden (hide_turn_zero=1) must still be filtered from "
                f"the recruitment select at turn 0; got zoneNames={zoneNames!r}"
            )
        finally:
            # Restore the CSV baseline so downstream tests / suites are unaffected.
            _reset_zone_visibility(etaId)

    def test_mid_turn_holder_flip_reveals_hidden_zone_to_new_holder(
        self, page: Page, base_url
    ):
        """Zone visibility recomputes per request: when Alpha becomes
        the holder mid-turn, a reload of workers/new.php reveals the
        previously-hidden Epsilon."""
        epsilonId = _get_zone_id(self._EPSILON)
        alphaId = _get_controller_id_by_lastname("Alpha")
        _reset_zone_visibility(epsilonId)
        _set_zone_flags(epsilonId, is_hidden=1)
        _reset_all_recruitment_counters()

        login_as(page, base_url, "single_player", "test")
        safe_goto(page, f"{base_url}/workers/new.php")
        _wait_loaded(page, "select[name='zone_id']")
        beforeNames = _worker_new_zone_option_names(page)
        assert self._EPSILON not in beforeNames, (
            f"Baseline: Alpha (non-holder) must not see Epsilon; got "
            f"zoneNames={beforeNames!r}"
        )

        _set_zone_flags(epsilonId, holder_controller_id=alphaId)
        _reset_all_recruitment_counters()
        page.reload()
        _wait_loaded(page, "select[name='zone_id']")
        afterNames = _worker_new_zone_option_names(page)
        assert self._EPSILON in afterNames, (
            f"Post-flip: Alpha (new holder) must see Epsilon; got "
            f"zoneNames={afterNames!r}"
        )
