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


def _grant_alpha_base_stock(alpha_id):
    """Refill every ressource stock for Alpha so consecutive createBase
    calls do not run out. Idempotent no-op when no rows exist."""
    conn = _db_conn()
    cur = conn.cursor()
    cur.execute(
        f"UPDATE `{GAME_PREFIX}controller_ressources` "
        f"SET amount=99 WHERE controller_id=%s",
        (alpha_id,),
    )
    conn.commit()
    cur.close()
    conn.close()


def _count_bases(zone_id, controller_id):
    """Number of is_base=1 locations owned by a controller in a zone."""
    conn = _db_conn()
    cur = conn.cursor()
    cur.execute(
        f"SELECT COUNT(*) AS base_count FROM `{GAME_PREFIX}locations` "
        f"WHERE zone_id=%s AND controller_id=%s AND is_base=1",
        (zone_id, controller_id),
    )
    row = cur.fetchone()
    cur.close()
    conn.close()
    return int(row["base_count"])


def _delete_bases(zone_id, controller_id):
    """Remove any is_base=1 locations for the (zone, controller) pair,
    plus any controller_known_locations rows that reference them, so
    tests leave a clean slate without hitting the FK constraint."""
    conn = _db_conn()
    cur = conn.cursor()
    cur.execute(
        f"DELETE ckl FROM `{GAME_PREFIX}controller_known_locations` ckl "
        f"JOIN `{GAME_PREFIX}locations` l ON l.id = ckl.location_id "
        f"WHERE l.zone_id=%s AND l.controller_id=%s AND l.is_base=1",
        (zone_id, controller_id),
    )
    cur.execute(
        f"DELETE FROM `{GAME_PREFIX}locations` "
        f"WHERE zone_id=%s AND controller_id=%s AND is_base=1",
        (zone_id, controller_id),
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


class TestBasePlacementGuard:
    """A crafted `createBase` GET against a hidden zone must be refused
    unless the caller is holder / claimer / GM. Non-holder Alpha is
    blocked; GM and holder Alpha succeed. Uses `Epsilon-Controlled` and
    `Zeta-Unclaimed` as isolated reserve zones (TestConfig CSV rows 6
    and 7)."""

    _EPSILON = "Epsilon-Controlled"
    _ZETA = "Zeta-Unclaimed"

    def test_non_holder_cannot_create_base_in_hidden_zone_via_crafted_url(
        self, page: Page, base_url
    ):
        """Alpha is neither holder nor claimer of Epsilon (is_hidden=1);
        a crafted createBase GET must not create the base row."""
        epsilonId = _get_zone_id(self._EPSILON)
        _reset_zone_visibility(epsilonId)
        _set_zone_flags(epsilonId, is_hidden=1)
        alphaId = _get_controller_id_by_lastname("Alpha")
        _grant_alpha_base_stock(alphaId)
        _delete_bases(epsilonId, alphaId)
        assert _count_bases(epsilonId, alphaId) == 0, (
            "Pre-condition failed: baseline base count must be 0"
        )

        login_as(page, base_url, "single_player", "test")
        safe_goto(
            page,
            f"{base_url}/controllers/action.php?createBase=1"
            f"&controller_id={alphaId}&zone_id={epsilonId}",
        )
        page.wait_for_load_state("load")

        postCount = _count_bases(epsilonId, alphaId)
        assert postCount == 0, (
            f"Non-holder must not build a base in a hidden zone via a "
            f"crafted URL; got postCount={postCount!r}"
        )

    def test_gm_can_create_base_in_hidden_zone_via_crafted_url(
        self, page: Page, base_url
    ):
        """GM (privileged) may build Alpha's base in a hidden zone."""
        zetaId = _get_zone_id(self._ZETA)
        _reset_zone_visibility(zetaId)
        _set_zone_flags(zetaId, is_hidden=1)
        alphaId = _get_controller_id_by_lastname("Alpha")
        _grant_alpha_base_stock(alphaId)
        _delete_bases(zetaId, alphaId)
        assert _count_bases(zetaId, alphaId) == 0, (
            "Pre-condition failed: baseline base count must be 0"
        )

        ensure_gm_login(page, base_url)
        safe_goto(
            page,
            f"{base_url}/controllers/action.php?createBase=1"
            f"&controller_id={alphaId}&zone_id={zetaId}",
        )
        page.wait_for_load_state("load")

        postCount = _count_bases(zetaId, alphaId)
        _delete_bases(zetaId, alphaId)
        assert postCount == 1, (
            f"GM must be allowed to build a base in a hidden zone; got "
            f"postCount={postCount!r}"
        )

    def test_holder_can_create_base_in_hidden_zone(
        self, page: Page, base_url
    ):
        """Alpha is the holder of Epsilon (is_hidden=1); Alpha's crafted
        createBase GET must succeed."""
        epsilonId = _get_zone_id(self._EPSILON)
        alphaId = _get_controller_id_by_lastname("Alpha")
        _reset_zone_visibility(epsilonId)
        _set_zone_flags(epsilonId, is_hidden=1, holder_controller_id=alphaId)
        _grant_alpha_base_stock(alphaId)
        _delete_bases(epsilonId, alphaId)
        assert _count_bases(epsilonId, alphaId) == 0, (
            "Pre-condition failed: baseline base count must be 0"
        )

        login_as(page, base_url, "single_player", "test")
        safe_goto(
            page,
            f"{base_url}/controllers/action.php?createBase=1"
            f"&controller_id={alphaId}&zone_id={epsilonId}",
        )
        page.wait_for_load_state("load")

        postCount = _count_bases(epsilonId, alphaId)
        _delete_bases(epsilonId, alphaId)
        assert postCount == 1, (
            f"Holder controller must be allowed to build a base in its "
            f"own hidden zone; got postCount={postCount!r}"
        )


class TestShikokuTakedasHiddenZone:
    """Scenario 7: the persistent hidden zone `Kai (甲斐)` (claimed and
    held by `Takeda (武田)`) must be visible only to Takeda's player and
    to GM on Japon1555. All other players must not see it on the zones
    page. Class-scoped fixture swaps the scenario to Japon1555 — this
    class is last in the file so the module TestConfig load does not
    need to be restored afterwards."""

    _KAI = "Kai (甲斐)"

    @pytest.fixture(scope="class", autouse=True)
    def _load_japon1555(self, browser):
        """Class-scoped: load Japon1555 for these tests. Placed last in
        the file so no downstream class depends on the module TestConfig
        fixture after the switch."""
        load_minimal_data()
        load_scenario_via_admin(browser, PHP_BASE_URL, "Japon1555CSV")
        yield

    def _zones_page_h3_texts(self, page):
        """Raw text of every zone box `<h3 class='title'>` on the zones
        view page. Substring inclusion is used downstream because the
        Japon1555 zone names themselves contain `(...)`."""
        return page.locator("div.section.zones h3.title").all_text_contents()

    def test_kai_zone_exists_in_japon1555_scenario(
        self, page: Page, base_url
    ):
        """Kai (甲斐) must exist as a row in the zones table with
        is_hidden=1 after loading Japon1555."""
        conn = _db_conn()
        cur = conn.cursor()
        cur.execute(
            f"SELECT id, is_hidden FROM `{GAME_PREFIX}zones` WHERE name=%s",
            (self._KAI,),
        )
        row = cur.fetchone()
        cur.close()
        conn.close()
        assert row is not None, (
            f"Expected a zones row named {self._KAI!r} in Japon1555"
        )
        assert int(row["is_hidden"]) == 1, (
            f"Kai (甲斐) must be marked is_hidden=1 in the Japon1555 CSV; "
            f"got is_hidden={row['is_hidden']!r}"
        )

    def test_kai_zone_invisible_to_non_takeda_player(
        self, page: Page, base_url
    ):
        """Motochika (Chōsokabe) is neither holder nor claimer of Kai;
        the zones view must not render Kai for him."""
        login_as(page, base_url, "motochika", "chosone")
        safe_goto(page, f"{base_url}/zones/action.php")
        _wait_loaded(page, "div.section.zones")
        h3Texts = self._zones_page_h3_texts(page)
        matches = [t for t in h3Texts if self._KAI in t]
        assert matches == [], (
            f"Non-Takeda non-privileged player must not see Kai (甲斐); "
            f"got matching h3 texts={matches!r}"
        )

    def test_kai_zone_visible_to_takeda_holder_player(
        self, page: Page, base_url
    ):
        """Shingen (Takeda) is the holder of Kai; the zones view must
        render Kai for him."""
        login_as(page, base_url, "shingen", "takeda")
        safe_goto(page, f"{base_url}/zones/action.php")
        _wait_loaded(page, "div.section.zones")
        h3Texts = self._zones_page_h3_texts(page)
        matches = [t for t in h3Texts if self._KAI in t]
        assert matches, (
            f"Takeda holder player must see Kai (甲斐) on the zones page; "
            f"got h3 texts={h3Texts!r}"
        )

    def test_kai_zone_visible_to_gm_on_japon1555(
        self, page: Page, base_url
    ):
        """GM (privileged) sees every zone regardless of is_hidden."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/zones/action.php")
        _wait_loaded(page, "div.section.zones")
        h3Texts = self._zones_page_h3_texts(page)
        matches = [t for t in h3Texts if self._KAI in t]
        assert matches, (
            f"GM must see Kai (甲斐) on Japon1555 zones page; got h3 "
            f"texts={h3Texts!r}"
        )
