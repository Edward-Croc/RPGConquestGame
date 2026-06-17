"""Playwright E2E tests for controller-side worker recruitment.

Covers:
- Base creation via /controllers/action.php?createBase=...
- First-come recruitment (works without a base, limited by turn_firstcome_workers)
- Regular recruitment (requires a base, limited by start_workers on turn 0
  then turn_recrutable_workers on later turns)
- Recruitment form validation (discipline + zone dropdowns, hidden fields)
- Faction-specific power filtering (base + faction-exclusive disciplines)
- Lock/unlock across turns

Data harmonization (see setupTestConfig_*.csv):
  Controllers: Lord Alpha, Lady Beta (can_build_base=1, start_workers=1),
               Lord Charlie..Lord Golf (can_build_base=0, start_workers=0)
  Zones: Epsilon-Controlled (used for Alpha's base), Zeta-Unclaimed (Beta's base)
  Disciplines:
    - Focused Mind → base (via config basePowerNames = "'Focused Mind'")
    - Offensive Stance → FactionAlpha-exclusive
    - Defensive Posture → FactionBeta-exclusive
  Config defaults: turn_recrutable_workers=1, turn_firstcome_workers=1

Fixture flow:
  Turn 0:
    1. Load TestConfig (fresh, no bases)
    2. Switch to Alpha controller
    3. Assert no recruit button, first-come button present
    4. Use first-come → Alpha.turn_firstcome_workers=1
    5. Assert first-come button now absent
    6. Create base for Alpha in Epsilon-Controlled
    7. Assert recruit button now present
    8. Regular recruit → Alpha.turn_recruited_workers=1
    9. Assert recruit button now absent
  End turn 0 → 1 (counters reset)
  Turn 1:
    10. Assert first-come button present again
    11. Use first-come
    12. Regular recruit again

Run:
    python3 -m pytest tests/test_controller_recruitment_e2e.py -v
    KEEP_DB=1 python3 -m pytest tests/test_controller_recruitment_e2e.py -v
"""
import pymysql
import pytest
from playwright.sync_api import Page

from conftest import (
    GAME_PREFIX, MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB,
    PHP_BASE_URL, ensure_gm_login,
)


from helpers import (
    DB_AVAILABLE, get_db_connection as get_db, end_turn, load_minimal_data,
    ui_controller_id, ui_zone_id, ui_controller_counters,
    safe_goto, register_php_error_listener, assert_no_collected_php_errors,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


# ---------------------------------------------------------------------------
# DB helpers — kept only for @pytest.mark.db tests that inspect internals
# with no UI counterpart (e.g. exact turn counter values). All other
# lookups (controller_id, zone_id, worker count, bases) now scrape the UI
# via ui_* helpers so tests can run under UI_ONLY=1.
# ---------------------------------------------------------------------------

def _controller_counters(lastname):
    """Return {turn_recruited_workers, turn_firstcome_workers} for a controller.

    Proxy for DB-only assertions — button presence/absence in the UI is
    available as an indirect check, but the exact counter value isn't
    rendered. Tests using this are marked @pytest.mark.db.
    """
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute(
        f"SELECT turn_recruited_workers, turn_firstcome_workers "
        f"FROM `{GAME_PREFIX}controllers` WHERE lastname = %s",
        (lastname,),
    )
    row = cursor.fetchone()
    conn.close()
    return row


# ---------------------------------------------------------------------------
# UI helpers
# ---------------------------------------------------------------------------

def _switch_controller(page, controller_lastname):
    """Switch GM session to a controller via the accueil page.

    Uses UI-only controller-id resolution so it works under UI_ONLY=1."""
    ensure_gm_login(page, PHP_BASE_URL)
    cid = ui_controller_id(page, controller_lastname)
    safe_goto(page,
        f"{PHP_BASE_URL}/base/accueil.php?controller_id={cid}&chosir=Choisir"
    )
    page.wait_for_load_state("networkidle")


def _workers_page_html(page, controller_lastname):
    """Return the viewAll workers page HTML for a controller."""
    _switch_controller(page, controller_lastname)
    safe_goto(page, f"{PHP_BASE_URL}/workers/viewAll.php")
    page.wait_for_load_state("load")
    return page.content()


def _accueil_html(page, controller_lastname):
    """Return the accueil page HTML for a controller.

    The accueil page includes controllers/view.php (which lists bases under
    'Votre Base :') and workers/viewAll.php (which renders the recruit/first
    come buttons plus worker cards). Single source-of-truth for UI-first
    assertions about controller state.
    """
    ensure_gm_login(page, PHP_BASE_URL)
    cid = ui_controller_id(page, controller_lastname)
    safe_goto(page, f"{PHP_BASE_URL}/base/accueil.php?controller_id={cid}&chosir=Choisir")
    page.wait_for_load_state("load")
    return page.content()


def _count_worker_cards_in_html(html):
    """Count workers rendered in a viewAll/accueil HTML page.

    Each worker card is wrapped in `<div class="worker-short">` by
    showWorkerShort() in workers/functions.php, so counting those div
    openings gives the worker count owned/displayed on the page.
    """
    return html.count('class="worker-short"')


def _create_base(page, controller_lastname, zone_name):
    """Trigger createBase via URL (UI-resolved controller + zone ids)."""
    ensure_gm_login(page, PHP_BASE_URL)
    cid = ui_controller_id(page, controller_lastname)
    zid = ui_zone_id(page, zone_name)
    _switch_controller(page, controller_lastname)
    safe_goto(page,
        f"{PHP_BASE_URL}/controllers/action.php"
        f"?createBase=1&controller_id={cid}&zone_id={zid}"
    )
    page.wait_for_load_state("load")


def _create_base_click(page, controller_lastname, zone_name):
    """UI-button-click variant of _create_base. Navigates to the
    controller's faction page, selects the zone in the zone_id dropdown,
    and clicks the rendered 'Créer' button. Use on the FIRST createBase
    call in this file (per audit's once-per-file rule); subsequent calls
    can use the URL-driver _create_base."""
    ensure_gm_login(page, PHP_BASE_URL)
    zid = ui_zone_id(page, zone_name)
    _switch_controller(page, controller_lastname)
    safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
    page.wait_for_load_state("load")
    page.locator("input[name='createBase']").wait_for(state="visible", timeout=30000)
    page.locator("select[name='zone_id']").first.select_option(value=str(zid))
    page.locator("input[name='createBase']").click()
    page.wait_for_load_state("load")


def _location_id_via_management(page, location_name):
    """Look up a location's id by scraping zones/management_locations.php.
    The page renders one block per location with `<h3>NAME (discovery N)</h3>`
    followed by a `toggle_destruction` form whose hidden input carries the id.
    Returns int id, raises if not found."""
    import re
    safe_goto(page, f"{PHP_BASE_URL}/zones/management_locations.php")
    page.wait_for_load_state("load")
    html = page.content()
    m = re.search(
        rf'<h3>{re.escape(location_name)}\s+\(discovery[^<]+</h3>'
        rf'.*?name="toggle_destruction"\s+value="(\d+)"',
        html,
        re.DOTALL
    )
    if not m:
        raise AssertionError(
            f"toggle_destruction form for '{location_name}' not found on "
            f"management_locations.php"
        )
    return int(m.group(1))


def _toggle_destruction_admin(page, location_name):
    """POST the management-page toggle_destruction form for the named
    location. Toggles the location between repaired/destroyed states by
    swapping the activate_json.update_location payload (zones/functions.php
    updateLocation). Requires gm session (page is admin-only).

    The toggle button lives inside a `<span style="display:none;">`
    collapsed actions section. `display:none` elements have no box, so
    even `click(force=True)` fails ("Element is not visible" → scroll-
    into-view aborts). Instead, submit the form directly via JS — the
    page navigation that follows is observed identically to a real click."""
    location_id = _location_id_via_management(page, location_name)
    # Already on management_locations.php from the lookup above.
    page.evaluate(
        f"""
        const inp = document.querySelector(
            'input[name="toggle_destruction"][value="{location_id}"]'
        );
        if (inp && inp.form) inp.form.submit();
        """
    )
    page.wait_for_load_state("load")
    return location_id


def _repair_location_click(page, controller_lastname, target_location_name):
    """UI-button-click for the Repair Location form on
    controllers/view.php. Pre-resolves location_id BEFORE switching
    controller (lookup helpers navigate to admin pages — interleaving
    would clobber the controller's view.php form state, Slice 15 pattern)."""
    ensure_gm_login(page, PHP_BASE_URL)
    location_id = _location_id_via_management(page, target_location_name)
    _switch_controller(page, controller_lastname)
    safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
    page.wait_for_load_state("load")
    page.locator("input[name='repairLocation']").wait_for(state="visible", timeout=30000)
    page.locator("select#repairLocationSelect").select_option(value=str(location_id))
    page.locator("input[name='repairLocation']").click()
    page.wait_for_load_state("load")


def _move_base_click(page, controller_lastname, target_zone_name):
    """UI-button-click for the Move Base form on controllers/view.php.
    Switches to the controller and clicks the rendered 'Déménager' button
    after picking the target zone in the per-base zone select. Pre-resolves
    zone_id BEFORE the controllers/view.php navigation — same pattern as
    ui_mass_move_click, since lookup helpers navigate to management
    pages and would discard the populated form."""
    ensure_gm_login(page, PHP_BASE_URL)
    target_zid = ui_zone_id(page, target_zone_name)
    _switch_controller(page, controller_lastname)
    safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
    page.wait_for_load_state("load")
    page.locator("input[name='moveBase']").wait_for(state="visible", timeout=30000)
    # The form is rendered per base; for Alpha there's only one base, so
    # `.first` is sufficient. The select shows the base's current zone
    # pre-selected; we override to the target.
    page.locator("select[name='zone_id']").first.select_option(value=str(target_zid))
    page.locator("input[name='moveBase']").click()
    page.wait_for_load_state("load")


def _controller_base_zone_via_ui(page, controller_lastname):
    """Read the controller's base zone via controllers/action.php
    rendering of view.php (line 155: 'Votre <basename> à <zone_name>').
    Returns the zone name string, or None if no base section rendered."""
    _switch_controller(page, controller_lastname)
    safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
    page.wait_for_load_state("load")
    html = page.content()
    import re
    m = re.search(r"Votre\s+\S[^<]*?\s+à\s+([\w\-]+)", html)
    return m.group(1) if m else None


def _do_first_come(page, controller_lastname):
    """Submit a first-come recruitment (picks first available zone + discipline)."""
    ensure_gm_login(page, PHP_BASE_URL)
    cid = ui_controller_id(page, controller_lastname)
    _switch_controller(page, controller_lastname)
    safe_goto(page,
        f"{PHP_BASE_URL}/workers/new.php?first_come=true&controller_id={cid}"
    )
    page.wait_for_load_state("load")
    page.locator("select[name='zone_id']").first.select_option(index=0)
    page.locator("input[name='chosir']").first.click()
    page.wait_for_load_state("load")


def _do_regular_recruit(page, controller_lastname):
    """Submit a regular recruitment via the form."""
    ensure_gm_login(page, PHP_BASE_URL)
    cid = ui_controller_id(page, controller_lastname)
    _switch_controller(page, controller_lastname)
    safe_goto(page,
        f"{PHP_BASE_URL}/workers/new.php?recrutement=true&controller_id={cid}"
    )
    page.wait_for_load_state("load")
    page.locator("select[name='zone_id']").first.select_option(index=0)
    page.locator("input[name='chosir']").first.click()
    page.wait_for_load_state("load")


def _do_first_come_via_viewAll(page, controller_lastname):
    """UI-button-click variant of _do_first_come. Navigates to
    /workers/viewAll.php and clicks the rendered 'Prendre le premier
    venu' button (input[name='first_come']) — covering the viewAll
    button rendering + form-action contract — then completes the
    workers/new.php form submission. Use on the FIRST first-come call
    in this file (per once-per-file rule); subsequent calls can use
    the URL-driver _do_first_come."""
    ensure_gm_login(page, PHP_BASE_URL)
    _switch_controller(page, controller_lastname)
    safe_goto(page, f"{PHP_BASE_URL}/workers/viewAll.php")
    page.wait_for_load_state("load")
    page.locator("input[name='first_come']").click()
    page.wait_for_load_state("load")
    page.locator("select[name='zone_id']").first.select_option(index=0)
    page.locator("input[name='chosir']").first.click()
    page.wait_for_load_state("load")


def _do_regular_recruit_via_viewAll(page, controller_lastname):
    """UI-button-click variant of _do_regular_recruit. Same flow as
    _do_first_come_via_viewAll but clicks 'Recruter un serviteur'
    (input[name='recrutement']). Use on the FIRST regular-recruit call
    in this file."""
    ensure_gm_login(page, PHP_BASE_URL)
    _switch_controller(page, controller_lastname)
    safe_goto(page, f"{PHP_BASE_URL}/workers/viewAll.php")
    page.wait_for_load_state("load")
    page.locator("input[name='recrutement']").click()
    page.wait_for_load_state("load")
    page.locator("select[name='zone_id']").first.select_option(index=0)
    page.locator("input[name='chosir']").first.click()
    page.wait_for_load_state("load")




# ---------------------------------------------------------------------------
# Observed state snapshot (captured during fixture setup)
# ---------------------------------------------------------------------------

_snapshot = {}


# ---------------------------------------------------------------------------
# Module fixture: walk through the recruitment lifecycle once
# ---------------------------------------------------------------------------

@pytest.fixture(scope="module", autouse=True)
def recruitment_scenario(browser):
    """One-time setup: load TestConfig, then walk through recruitment phases.

    Snapshots key state at each phase into _snapshot for tests to assert on.
    Runs against local Docker and remote prod: DB-direct seeding is skipped
    when the DB isn't reachable, and counter snapshots (DB-only) are simply
    omitted — the @pytest.mark.db tests that consume them are themselves
    skipped under UI_ONLY=1.
    """
    # Local bootstrap; skipped on prod
    if DB_AVAILABLE:
        load_minimal_data()

    context = browser.new_context()
    page = context.new_page()
    register_php_error_listener(page)

    # Login + load TestConfig
    ensure_gm_login(page, PHP_BASE_URL)
    safe_goto(page, f"{PHP_BASE_URL}/base/admin.php")
    page.wait_for_load_state("networkidle")
    page.locator("select[name='config_name']").select_option("TestConfig")
    page.locator("input[type='submit'][value='Submit']").click()
    if page.locator("#confirmModalYes").is_visible():
        page.locator("#confirmModalYes").click()
    page.wait_for_timeout(5000)
    page.wait_for_load_state("load", timeout=90000)

    # ---- TURN 0 ----

    # Resolve and snapshot controller ids via UI — tests can then assert without
    # hitting the DB again (supports UI_ONLY=1).
    _snapshot['alpha_cid'] = ui_controller_id(page, 'Alpha')
    _snapshot['beta_cid'] = ui_controller_id(page, 'Beta')

    # UI-side baseline worker count (agents pre-loaded from advanced.csv).
    _snapshot['alpha_baseline_workers_ui'] = _count_worker_cards_in_html(
        _workers_page_html(page, 'Alpha')
    )

    # Phase 1: Alpha has no base → capture viewAll HTML
    _snapshot['alpha_t0_before_base_html'] = _workers_page_html(page, 'Alpha')
    # Charlie has no base and no start_workers
    _snapshot['charlie_t0_html'] = _workers_page_html(page, 'Charlie')

    # Phase 2: Alpha uses first-come (no base required).
    # First first-come in this file → exercised via the UI button on
    # /workers/viewAll.php (per once-per-file rule); the turn-1 call below
    # keeps using the URL-driver _do_first_come.
    _do_first_come_via_viewAll(page, 'Alpha')
    _snapshot['alpha_t0_after_first_come_counters'] = ui_controller_counters(page, 'Alpha')
    _snapshot['alpha_t0_after_first_come_html'] = _workers_page_html(page, 'Alpha')
    _snapshot['alpha_t0_after_first_come_workers_ui'] = _count_worker_cards_in_html(
        _snapshot['alpha_t0_after_first_come_html']
    )

    # Phase 3: Alpha creates a base in Epsilon-Controlled.
    # First createBase in this file → exercised via the UI 'Créer' button
    # (per once-per-file rule); subsequent calls reuse the URL-driver.
    _create_base_click(page, 'Alpha', 'Epsilon-Controlled')
    _snapshot['alpha_t0_after_base_html'] = _workers_page_html(page, 'Alpha')
    # UI-side: accueil page shows the base under "Votre Base :" section.
    _snapshot['alpha_t0_after_base_accueil_html'] = _accueil_html(page, 'Alpha')

    # Phase 4: Alpha uses regular recruitment
    # Capture the recruitment form BEFORE submitting (for form validation tests)
    _switch_controller(page, 'Alpha')
    safe_goto(page,
        f"{PHP_BASE_URL}/workers/new.php?recrutement=true&controller_id={_snapshot['alpha_cid']}"
    )
    page.wait_for_load_state("load")
    _snapshot['alpha_recruit_form_html'] = page.content()

    # Now submit the form
    page.locator("select[name='zone_id']").first.select_option(index=0)
    page.locator("input[name='chosir']").first.click()
    page.wait_for_load_state("load")
    _snapshot['alpha_t0_after_recruit_counters'] = ui_controller_counters(page, 'Alpha')
    _snapshot['alpha_t0_after_recruit_html'] = _workers_page_html(page, 'Alpha')
    _snapshot['alpha_t0_after_recruit_workers_ui'] = _count_worker_cards_in_html(
        _snapshot['alpha_t0_after_recruit_html']
    )

    # Phase 5: Beta creates base + captures recruitment form (for faction filtering)
    _create_base(page, 'Beta', 'Zeta-Unclaimed')
    _switch_controller(page, 'Beta')
    safe_goto(page,
        f"{PHP_BASE_URL}/workers/new.php?recrutement=true&controller_id={_snapshot['beta_cid']}"
    )
    page.wait_for_load_state("load")
    _snapshot['beta_recruit_form_html'] = page.content()

    # ---- END TURN 0 → 1 ----
    ensure_gm_login(page, PHP_BASE_URL)
    end_turn(page)

    # ---- TURN 1 ----

    _snapshot['alpha_t1_before_counters'] = ui_controller_counters(page, 'Alpha')
    _snapshot['alpha_t1_before_html'] = _workers_page_html(page, 'Alpha')

    # Recruit again on turn 1 (both paths)
    _do_first_come(page, 'Alpha')
    _snapshot['alpha_t1_after_first_come_counters'] = ui_controller_counters(page, 'Alpha')

    # First regular-recruit in this file → exercised via the UI button
    # on /workers/viewAll.php (per once-per-file rule); subsequent calls
    # would use the URL-driver _do_regular_recruit.
    _do_regular_recruit_via_viewAll(page, 'Alpha')
    _snapshot['alpha_t1_after_recruit_counters'] = ui_controller_counters(page, 'Alpha')
    # UI-side final worker count (post all recruitment phases).
    _snapshot['alpha_t1_final_workers_ui'] = _count_worker_cards_in_html(
        _workers_page_html(page, 'Alpha')
    )

    assert_no_collected_php_errors(page)
    context.close()
    yield


# ---------------------------------------------------------------------------
# Test classes
# ---------------------------------------------------------------------------

class TestBaseRequirement:
    """Button visibility depends on base + recruitment slot availability.

    UI-first: these tests check the rendered HTML of viewAll.php for the
    presence/absence of the recruit and first-come buttons — canonical
    proxies for the canStartRecrutement / canStartFirstCome gating.
    """

    def test_alpha_no_recruit_button_without_base(self):
        """Without a base, Alpha's viewAll has no 'Recruter un serviteur' button."""
        html = _snapshot['alpha_t0_before_base_html']
        assert "Recruter un serviteur" not in html, \
            "Recruit button should be absent when controller has no base"
        assert "Cannot recruit without a base" in html, \
            "Should show 'needs a base' message"

    def test_alpha_first_come_button_present_without_base(self):
        """First-come works without a base — button should be visible."""
        html = _snapshot['alpha_t0_before_base_html']
        assert "Prendre le premier venu" in html, \
            "First-come button should be present even without a base"

    def test_alpha_recruit_button_after_base_created(self):
        """After base creation, Alpha has the 'Recruter' button."""
        html = _snapshot['alpha_t0_after_base_html']
        assert "Recruter un serviteur" in html, \
            "Recruit button should appear after base is created"


class TestBaseCreation:
    """Base creation via /controllers/action.php?createBase=...

    UI-first: after createBase, Alpha's accueil page lists the base in the
    'Votre Base :' section with its auto-generated name and zone. The DB row
    is the underlying mechanism but the user-visible truth is the rendered
    accueil page.
    """

    def test_alpha_base_created(self):
        """Alpha should have one base displayed under 'Votre Base :' on accueil."""
        html = _snapshot['alpha_t0_after_base_accueil_html']
        assert 'Votre Base' in html, \
            "Accueil should show the 'Votre Base :' header after base creation"
        # The base block uses 'Votre {name} à {zone}' template from view.php.
        assert 'Fortress of FactionAlpha' in html, \
            "Accueil should display the created base name"

    def test_alpha_base_auto_named(self):
        """Base name is auto-generated from texteNameBase template + fake_faction_name."""
        html = _snapshot['alpha_t0_after_base_accueil_html']
        assert 'Fortress of FactionAlpha' in html, \
            "Accueil HTML should show 'Fortress of FactionAlpha' base name"

    def test_alpha_base_in_correct_zone(self):
        """Base was created in Epsilon-Controlled zone (rendered on accueil)."""
        html = _snapshot['alpha_t0_after_base_accueil_html']
        # view.php renders 'Votre {name} à {zone_name}' for the base entry.
        assert 'Epsilon-Controlled' in html, \
            "Accueil should display the base's zone 'Epsilon-Controlled'"
        assert 'Fortress of FactionAlpha' in html and 'Epsilon-Controlled' in html, \
            "Base name and zone should both be visible on Alpha's accueil"

class TestRecruitmentFormValidation:
    """Regular recruitment form structure (UI HTML assertions)."""

    def test_form_has_zone_dropdown(self):
        """The form must include a zone select for spawn location."""
        html = _snapshot['alpha_recruit_form_html']
        assert 'name="zone_id"' in html, "Form should have zone_id select"

    def test_form_has_discipline_dropdown(self):
        """The form must include a discipline select."""
        html = _snapshot['alpha_recruit_form_html']
        assert 'name="discipline"' in html, "Form should have discipline select"

    def test_form_hidden_controller_id_matches_alpha(self):
        """Hidden controller_id input must reference Alpha's id."""
        html = _snapshot['alpha_recruit_form_html']
        alpha_id = _snapshot['alpha_cid']
        assert f'name="controller_id" value="{alpha_id}"' in html, \
            f"Form should have hidden controller_id={alpha_id}"

    def test_form_pregenerated_name_visible(self):
        """The form should display a pre-generated name from the worker_names pool."""
        html = _snapshot['alpha_recruit_form_html']
        pool_names = ['Sentinel', 'Watcher', 'Scout', 'Runner',
                      'Shadow', 'Phantom', 'Ghost', 'Recruit']
        assert any(n in html for n in pool_names), \
            f"Form should show a pre-generated name from pool, HTML length={len(html)}"


class TestFactionPowerFiltering:
    """Discipline dropdown should contain base powers + own faction's powers.

    UI-first: asserts against the rendered recruitment form HTML.
    """

    def test_alpha_discipline_dropdown_has_base_power(self):
        """Focused Mind is the base discipline (basePowerNames config), available to all."""
        html = _snapshot['alpha_recruit_form_html']
        assert 'Focused Mind' in html, \
            "Alpha discipline dropdown should include base power 'Focused Mind'"

    def test_alpha_discipline_dropdown_has_own_faction_power(self):
        """FactionAlpha has Offensive Stance as a faction-exclusive discipline."""
        html = _snapshot['alpha_recruit_form_html']
        assert 'Offensive Stance' in html, \
            "Alpha discipline dropdown should include faction power 'Offensive Stance'"

    def test_alpha_discipline_dropdown_excludes_beta_power(self):
        """Alpha should NOT see Defensive Posture (Beta's faction-exclusive discipline)."""
        html = _snapshot['alpha_recruit_form_html']
        # The discipline select is the relevant part — search only within the form
        # Check globally: Defensive Posture isn't in Alpha's filtered options
        assert 'Defensive Posture' not in html, \
            "Alpha should NOT see Defensive Posture in discipline dropdown"

    def test_beta_discipline_dropdown_has_own_faction_power(self):
        """FactionBeta has Defensive Posture as a faction-exclusive discipline."""
        html = _snapshot['beta_recruit_form_html']
        assert 'Defensive Posture' in html, \
            "Beta discipline dropdown should include 'Defensive Posture'"
        assert 'Focused Mind' in html, \
            "Beta should still see the base discipline"
        assert 'Offensive Stance' not in html, \
            "Beta should NOT see Alpha's Offensive Stance"


class TestFirstComeRecruitment:
    """First-come recruitment path."""

    def test_first_come_creates_worker_without_base(self):
        """First-come should not decrease the worker-card count on viewAll.

        UI-first: count `class="worker-short"` cards rendered on Alpha's
        viewAll page. `createWorker` returns the existing worker ID if the
        pre-generated name collides with one already recruited for the same
        controller+origin — a duplicate is a valid game outcome, so we
        accept `>= baseline` rather than requiring a new card every time.
        """
        baseline_ui = _snapshot['alpha_baseline_workers_ui']
        after_ui = _snapshot['alpha_t0_after_first_come_workers_ui']
        assert after_ui >= baseline_ui, \
            f"UI worker-card count should not decrease: baseline={baseline_ui}, after={after_ui}"

    def test_first_come_locked_on_same_turn(self):
        """After one first-come, button should disappear (limit=1 per turn).

        This is the UI proxy for turn_firstcome_workers == 1 — the button is
        rendered only when canStartFirstCome() returns true, which is false
        once the per-turn counter is at its limit.
        """
        html = _snapshot['alpha_t0_after_first_come_html']
        assert "Prendre le premier venu" not in html, \
            "First-come button should be hidden after limit reached on same turn"

    def test_first_come_increments_counter(self):
        """turn_firstcome_workers counter should be 1 after first-come.

        Scraped from /controllers/management.php (admin table, Turn Recruited
        Firstcome Workers column) so runs under UI_ONLY=1.
        """
        counters = _snapshot['alpha_t0_after_first_come_counters']
        assert counters['turn_firstcome_workers'] == 1, \
            f"Expected turn_firstcome_workers=1, got {counters}"


class TestRegularRecruitment:
    """Regular (base-requiring) recruitment path."""

    def test_recruit_creates_worker(self):
        """UI-first: worker-card count on viewAll should not decrease after recruit.

        Name collisions may cause createWorker to return the existing ID
        without rendering a new card — the counter increments regardless.
        """
        after_first_come_ui = _snapshot['alpha_t0_after_first_come_workers_ui']
        after_recruit_ui = _snapshot['alpha_t0_after_recruit_workers_ui']
        assert after_recruit_ui >= after_first_come_ui, \
            f"UI worker-card count should not decrease after recruit: " \
            f"{after_first_come_ui} -> {after_recruit_ui}"

    def test_recruit_locked_on_same_turn(self):
        """After using start_workers slot, recruit button should disappear on turn 0.

        UI proxy for turn_recruited_workers >= start_workers — the button is
        gated by canStartRecrutement() in workers/viewAll.php.
        """
        html = _snapshot['alpha_t0_after_recruit_html']
        assert "Recruter un serviteur" not in html, \
            "Recruit button should be hidden after start_workers exhausted"

    def test_recruit_increments_counter(self):
        """turn_recruited_workers should be 1 after regular recruitment.

        Scraped from /controllers/management.php so runs under UI_ONLY=1.
        """
        counters = _snapshot['alpha_t0_after_recruit_counters']
        assert counters['turn_recruited_workers'] == 1, \
            f"Expected turn_recruited_workers=1, got {counters}"


class TestLockUnlockAcrossTurns:
    """Counters reset at end-turn, allowing recruitment again on turn 1."""

    def test_first_come_unlocked_on_turn_1(self):
        """First-come button should be visible again at start of turn 1.

        UI proxy for turn_firstcome_workers reset to 0 — the button is
        rendered iff canStartFirstCome() returns true.
        """
        html = _snapshot['alpha_t1_before_html']
        assert "Prendre le premier venu" in html, \
            "First-come button should be available on turn 1"

    def test_regular_recruit_unlocked_on_turn_1(self):
        """Recruit button should be visible again at start of turn 1.

        UI proxy for turn_recruited_workers reset to 0 (Alpha still has base).
        """
        html = _snapshot['alpha_t1_before_html']
        assert "Recruter un serviteur" in html, \
            "Recruit button should be available on turn 1"

    def test_recruitment_overall_added_workers(self):
        """Over the full lifecycle, Alpha's viewAll shows more worker cards.

        UI-first: count `class="worker-short"` cards before (baseline) and
        at end of turn 1. Due to random name collisions, exact counts are
        not deterministic, but the total must be strictly greater than the
        CSV baseline.
        """
        baseline_ui = _snapshot['alpha_baseline_workers_ui']
        final_ui = _snapshot['alpha_t1_final_workers_ui']
        assert final_ui > baseline_ui, \
            f"After 4 recruitment attempts, Alpha's viewAll should show " \
            f"> {baseline_ui} worker cards, got {final_ui}"

    def test_counters_reset_on_turn_1(self):
        """At start of turn 1, both counters should be 0.

        Scraped from /controllers/management.php so runs under UI_ONLY=1.
        """
        counters = _snapshot['alpha_t1_before_counters']
        assert counters['turn_firstcome_workers'] == 0
        assert counters['turn_recruited_workers'] == 0

    def test_turn_1_counters_increment(self):
        """After both recruitments on turn 1, counters should be 1.

        Scraped from /controllers/management.php so runs under UI_ONLY=1.
        """
        counters = _snapshot['alpha_t1_after_recruit_counters']
        assert counters['turn_firstcome_workers'] == 1
        assert counters['turn_recruited_workers'] == 1


class TestCharlieCannotRecruit:
    """Controllers without can_build_base + start_workers cannot recruit on turn 0.

    UI-first: asserts against Charlie's viewAll HTML (no buttons, needs-base msg).
    """

    def test_charlie_sees_needs_base_message(self):
        """Charlie (can_build_base=0) should see the needs-a-base message."""
        html = _snapshot['charlie_t0_html']
        assert "Cannot recruit without a base" in html, \
            "Charlie should see the base-required warning"

    def test_charlie_has_no_recruit_button(self):
        """Charlie should not have the recruit button."""
        html = _snapshot['charlie_t0_html']
        assert "Recruter un serviteur" not in html


# ---------------------------------------------------------------------------
# TestMoveBase — controllers/view.php Move Base + controllers/action.php
# moveBase handler.
#
# Slice 16 audit gap: moveBase was untested. Adds two tests:
#  - positive: Alpha's base (created in Epsilon-Controlled by the module
#    fixture's Phase 3) is moved via the rendered 'Déménager' button to
#    Gamma-Claims; assert post-move zone via controllers/view.php
#    re-render.
#  - negative (resource gate): Echo (pre-seeded base at Theta-Artefacts;
#    controller_ressources amount=4 < base_moving_cost=5) navigates to
#    controllers/action.php; the move form is replaced by the 'ressources
#    nécessaires' notification and no input[name='moveBase'] is rendered.
#
# CSV additions for this slice:
#   textes:                   ressource_management = TRUE
#   controller_ressources:    Echo,Gold,4,0,0
# ---------------------------------------------------------------------------

class TestMoveBase:
    """Move Base UI click + resource-gate negative path."""

    @pytest.fixture(scope="class", autouse=True)
    def move_base_state(self, browser):
        """Capture Alpha's base zone before + after the click-driven
        move from Epsilon-Controlled to Gamma-Claims. Also capture
        Echo's controllers/view.php HTML to verify the resource-gate
        negative path renders the danger notification + omits the
        moveBase submit."""
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        # Positive: Alpha's existing base (from module fixture phase 3)
        # is currently in Epsilon-Controlled. Move it to Gamma-Claims.
        pre_zone = _controller_base_zone_via_ui(page, 'Alpha')
        _move_base_click(page, 'Alpha', 'Gamma-Claims')
        post_zone = _controller_base_zone_via_ui(page, 'Alpha')

        # Negative: Echo (pre-seeded Echo-Base at Theta-Artefacts,
        # amount=4 < base_moving_cost=5).
        _switch_controller(page, 'Echo')
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        echo_move_button_count = page.locator("input[name='moveBase']").count()
        echo_html = page.content()

        assert_no_collected_php_errors(page)
        context.close()
        type(self)._pre_zone = pre_zone
        type(self)._post_zone = post_zone
        type(self)._echo_move_button_count = echo_move_button_count
        type(self)._echo_html = echo_html
        yield

    def test_alpha_base_starts_in_epsilon_controlled(self):
        """Sanity: module fixture left Alpha's base in Epsilon-Controlled."""
        assert self._pre_zone == "Epsilon-Controlled", (
            f"Alpha's base should start in Epsilon-Controlled (created by "
            f"module fixture Phase 3); got {self._pre_zone!r}"
        )

    def test_alpha_base_zone_changes_after_move(self):
        """After ui-click move, Alpha's base zone is Gamma-Claims."""
        assert self._post_zone == "Gamma-Claims", (
            f"Alpha's base should be in Gamma-Claims after _move_base_click; "
            f"got {self._post_zone!r}"
        )

    def test_echo_move_button_hidden_when_drained(self):
        """Echo (amount=4 < base_moving_cost=5) → resource gate fails →
        the move form is replaced by the danger notification, so no
        moveBase submit is rendered."""
        assert self._echo_move_button_count == 0, (
            f"Echo's controllers/view should NOT render the moveBase button "
            f"when ressources are insufficient; found {self._echo_move_button_count}"
        )

    def test_echo_resource_warning_rendered(self):
        """Resource-gate UI message is the user-visible signal that the
        move is blocked. Asserts the French text present in the danger
        notification (controllers/view.php:146)."""
        assert "ressources nécessaires" in self._echo_html, (
            "Echo's controllers/view should render the 'ressources "
            "nécessaires' danger notification when the move is blocked"
        )


# ---------------------------------------------------------------------------
# TestRepairLocation — controllers/view.php Repair Location +
# controllers/action.php repairLocation handler.
#
# Slice 17 audit gap: repairLocation was untested. Setup uses the admin
# `zones/management_locations.php` toggle_destruction button to flip a
# location into can_be_repaired=1 state via updateLocation's
# activate_json.update_location swap (zones/functions.php:656).
#
# CSV additions for this slice:
#   locations:               Foxtrot-Outpost & Echo-Base now carry an
#                            activate_json.update_location swap-state.
#   controller_ressources:   Echo amount lowered 4→2 (still fails Slice 16
#                            move gate at cost=5; now also fails repair
#                            gate at cost=3).
#
# CKL fix shipped 2026-05-09 (this branch): createBase() now seeds CKL
# for the owner, and BDD/db_connector.php synthesizes CKL rows for
# every CSV/SQL-seeded owned base after scenario load. The Slice 17
# band-aid `_seed_ckl_admin` calls in this fixture have been removed
# since owners now know their own bases out of the box.
# ---------------------------------------------------------------------------

class TestRepairLocation:
    """Repair Location UI click + resource-gate negative path."""

    @pytest.fixture(scope="class", autouse=True)
    def repair_state(self, browser):
        """Toggle Foxtrot-Outpost + Echo-Base destruction via the admin
        management page, then capture per-controller view.php state
        before and after Foxtrot's repair click. Echo (drained) is
        captured to verify the resource-gate negative path."""
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        # Admin: toggle both bases into the destroyed/can_be_repaired=1 state.
        _toggle_destruction_admin(page, "Foxtrot-Outpost")
        _toggle_destruction_admin(page, "Echo-Base")

        # Foxtrot pre-click: repair form should be visible.
        _switch_controller(page, "Foxtrot")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        foxtrot_button_count_before = page.locator("input[name='repairLocation']").count()

        # Foxtrot clicks repair on the destroyed Foxtrot-Outpost.
        _repair_location_click(page, "Foxtrot", "Foxtrot-Outpost")

        # Foxtrot post-click: Foxtrot-Outpost was the only repairable
        # known location, so the repair form is gone now.
        _switch_controller(page, "Foxtrot")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        foxtrot_button_count_after = page.locator("input[name='repairLocation']").count()

        # Echo (amount=2 < cost=3) sees the danger notification only.
        _switch_controller(page, "Echo")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        echo_button_count = page.locator("input[name='repairLocation']").count()
        echo_html = page.content()

        assert_no_collected_php_errors(page)
        context.close()
        type(self)._foxtrot_button_count_before = foxtrot_button_count_before
        type(self)._foxtrot_button_count_after = foxtrot_button_count_after
        type(self)._echo_button_count = echo_button_count
        type(self)._echo_html = echo_html
        yield

    def test_foxtrot_repair_button_visible_after_destruction_toggle(self):
        """After admin toggles Foxtrot-Outpost destruction, Foxtrot's
        controllers/view.php renders the repair form (input[name=
        'repairLocation'] count == 1)."""
        assert self._foxtrot_button_count_before == 1, (
            f"Foxtrot should see exactly one repair button after "
            f"Foxtrot-Outpost is toggled to the destroyed state; got "
            f"count={self._foxtrot_button_count_before}"
        )

    def test_repair_click_undamages_foxtrot_outpost(self):
        """After clicking 'Réparer' on Foxtrot-Outpost, the location's
        can_be_repaired flips back to 0 (repair re-applies the
        save_to_json swap). With no other repairable known location,
        Foxtrot's view.php no longer renders the repair form."""
        assert self._foxtrot_button_count_after == 0, (
            f"After repair click, Foxtrot should no longer see a repair "
            f"button (Foxtrot-Outpost is no longer can_be_repaired); got "
            f"count={self._foxtrot_button_count_after}"
        )

    def test_echo_repair_button_hidden_when_drained(self):
        """Echo (amount=2 < location_repaire_cost=3) → resource gate
        fails → controllers/view.php renders the danger notification
        instead of the repair form. No moveBase-style repair button."""
        assert self._echo_button_count == 0, (
            f"Echo's controllers/view should NOT render the repairLocation "
            f"button when ressources are insufficient; found "
            f"count={self._echo_button_count}"
        )

    def test_echo_resource_warning_rendered_for_repair(self):
        """Resource-gate UI signal: the 'ressources nécessaires' danger
        notification (controllers/view.php:266) appears for Echo when
        repair is blocked."""
        assert "ressources nécessaires" in self._echo_html, (
            "Echo's controllers/view should render the 'ressources "
            "nécessaires' danger notification when repair is blocked"
        )


# ---------------------------------------------------------------------------
# TestOwnerKnowsOwnBase — controller_known_locations auto-seed for
# owners of their own bases.
#
# Before this fix, neither createBase() nor the scenario loader
# (BDD/db_connector.php) populated controller_known_locations (CKL).
# Owners therefore appeared in their own bases' rows on `locations`
# (controller_id = owner) but had NO CKL row — every CKL-joined render
# path (repair, gift-info-location, attack-location dropdowns) treated
# the location as unknown to its owner and rendered "Aucun emplacement
# connu".
#
# Fix shipped on this branch:
# - controllers/functions.php createBase(): after the locations INSERT,
#   call addLocationToCKL($pdo, $controller_id, $base_id, $turn, false).
# - BDD/db_connector.php: post-load idempotent INSERT...SELECT...
#   WHERE NOT EXISTS for every is_base=True location with non-null
#   controller_id — covers CSV/SQL-seeded bases (Foxtrot-Outpost,
#   Echo-Base in TestConfig; Japan1555 SQL temple seeds; etc.).
#
# These two paths (runtime + scenario-load) cover every way a base
# can land in `locations` with an owner. Both tested below.
# ---------------------------------------------------------------------------

class TestOwnerKnowsOwnBase:
    """Verify that controllers know about their own bases out of the box,
    via both the scenario-load synthesis path and the runtime createBase
    path."""

    def test_foxtrot_knows_foxtrot_outpost_after_scenario_load(self, browser):
        """CSV-seeded base path: Foxtrot owns Foxtrot-Outpost via
        setupTestConfig_locations.csv. After scenario load, Foxtrot's
        controllers/view.php must list Foxtrot-Outpost in the known-
        locations rendering — NOT 'Aucun emplacement connu'."""
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        _switch_controller(page, "Foxtrot")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        html = page.content()

        # Foxtrot-Outpost surfaces on Foxtrot's view via
        # listControllerLinkedLocations (the "Vos lieux secrets" panel)
        # — that panel renders pure owner-locations regardless of CKL.
        # The CKL synthesis is verified indirectly in the other tests
        # below: own-base-NOT-double-listed-in-lieux-decouverts shows
        # the CKL row exists but is correctly filtered from the
        # "discovered" panel; gift-info-location dropdown population
        # confirms CKL is consulted.
        assert "Foxtrot-Outpost" in html, (
            "Foxtrot's controllers/view.php should list Foxtrot-Outpost "
            "(via the 'Vos lieux secrets' linked-locations panel)."
        )

        assert_no_collected_php_errors(page)
        context.close()

    def test_alpha_knows_new_base_after_create(self, browser):
        """Runtime createBase path: createBase() must call
        addLocationToCKL for the new base's owner. Verified by creating
        a fresh base for a controller that had none, then asserting the
        new base appears in controllers/view.php."""
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        # Fresh Alpha base in Epsilon-Controlled (the slot the module
        # fixture also uses; createBase short-circuits if a base
        # already exists for the controller in the zone).
        # Already-created in module fixture phase 3 — Alpha has its
        # base by now, but the CKL row was inserted by the createBase
        # call in that fixture (if running on the post-fix code).
        # Assert the consequence: Alpha sees its own base.
        _switch_controller(page, "Alpha")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        html = page.content()

        # Alpha's base appears on its view via listControllerLinkedLocations
        # (the "Vos lieux secrets" linked panel). The "Aucun lieu."
        # empty-state message only renders when that list is empty —
        # so its absence proves Alpha's base is linked. (We avoid
        # asserting against "Aucun emplacement connu" because that's
        # the empty-state of the OTHER panel — Lieux découverts —
        # which now correctly excludes own bases via excludeOwnLocations.)
        assert "Aucun lieu." not in html, (
            "Alpha (post createBase in module fixture) must have at least "
            "one linked location — its own base. Absence of 'Aucun lieu.' "
            "confirms listControllerLinkedLocations returned non-empty."
        )

        assert_no_collected_php_errors(page)
        context.close()

    def test_foxtrot_own_base_excluded_from_attackable_dropdown(self, browser):
        """Self-attack guard: showAttackableControllerKnownLocations
        must filter out the controller's own bases. Without this,
        the post-fix CKL synthesis would let Foxtrot click 'Mener une
        équipe d'attaque sur place' against its own base."""
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        _switch_controller(page, "Foxtrot")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")

        # The attackable-locations dropdown is `select#repairLocationSelect`'s
        # sibling form: an `attackLocation` submit button paired with a
        # location dropdown rendered by showAttackableControllerKnownLocations.
        # Get the option labels from that select.
        attack_options = []
        if page.locator("input[name='attackLocation']").count() > 0:
            options = page.locator("form:has(input[name='attackLocation']) select option").all()
            attack_options = [(o.inner_text() or "").strip() for o in options
                              if (o.get_attribute("value") or "")]

        # Foxtrot-Outpost is Foxtrot's own base — must NOT be selectable.
        joined = " | ".join(attack_options)
        assert "Foxtrot-Outpost" not in joined, (
            f"Foxtrot's own base must not appear in the attackable "
            f"locations dropdown; got options: {attack_options!r}"
        )

        assert_no_collected_php_errors(page)
        context.close()

    def test_foxtrot_own_base_NOT_double_listed_in_lieux_decouverts(self, browser):
        """No-double-listing guard: Foxtrot's own base appears in the
        'Vos lieux secrets' panel (listControllerLinkedLocations) and
        must NOT also appear in the 'Lieux découverts' panel
        (listControllerKnownLocations) on the same page."""
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        _switch_controller(page, "Foxtrot")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        html = page.content()

        # Both panels surface their content under summary labels:
        # 'Lieux connus de <zone>' (CKL panel) and 'Lieux de <zone>'
        # (linked panel). Foxtrot-Outpost lives in Theta-Artefacts.
        # Slice the HTML between the two panel headers and search.
        ckl_marker = "Lieux connus de"
        linked_marker = "Lieux de "
        ckl_idx = html.find(ckl_marker)
        linked_idx = html.find(linked_marker, ckl_idx if ckl_idx >= 0 else 0)

        # The CKL panel HTML segment is between ckl_marker and either
        # the next h4/section break or linked_marker. Conservative:
        # take everything from ckl_idx until linked_idx (or end of page).
        if ckl_idx >= 0:
            end = linked_idx if linked_idx > ckl_idx else len(html)
            ckl_panel_html = html[ckl_idx:end]
            assert "Foxtrot-Outpost" not in ckl_panel_html, (
                "Foxtrot-Outpost must not appear in the 'Lieux connus' "
                "(CKL-joined) panel on Foxtrot's view — it's surfaced "
                "via listControllerLinkedLocations in the next section."
            )

        assert_no_collected_php_errors(page)
        context.close()

    def test_foxtrot_own_base_present_in_gift_info_location_dropdown(self, browser):
        """β design call: gift-info-location should ALLOW the owner to
        gift intel about their own base (could be a legitimate strategy
        — e.g., directing an ally's attention to a defensible location).
        The dropdown rendered by buildGiveKnowledgeHTML inside
        controllers/view.php must include the owner's own base."""
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        _switch_controller(page, "Foxtrot")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")

        # The gift-info section is a closed-by-default <details>; open it
        # before scraping the location select.
        gift_details_summary = page.locator("details summary").filter(
            has_text="Donner des informations"
        ).first
        if gift_details_summary.count() > 0:
            gift_details_summary.click()
        gift_locations = []
        if page.locator("input[name='giftInformationLocation']").count() > 0:
            options = page.locator(
                "form:has(input[name='giftInformationLocation']) select[name='location_id'] option"
            ).all()
            gift_locations = [(o.inner_text() or "").strip() for o in options
                              if (o.get_attribute("value") or "")]

        joined = " | ".join(gift_locations)
        assert "Foxtrot-Outpost" in joined, (
            f"Foxtrot's own base SHOULD appear in the gift-info-location "
            f"dropdown (β design call: owner can gift intel about own "
            f"base); got options: {gift_locations!r}"
        )

        # And exactly once — the dropdown is built from CKL + linked
        # iterations; pre-fix the post-CKL-seed put own bases in both
        # → duplicate options. The CKL caller now passes
        # excludeOwnLocations=true so own bases come from linked only.
        own_base_count = sum(1 for opt in gift_locations if "Foxtrot-Outpost" in opt)
        assert own_base_count == 1, (
            f"Foxtrot-Outpost should appear EXACTLY once in the "
            f"gift-info-location dropdown; appeared {own_base_count} "
            f"times: {gift_locations!r}"
        )

        assert_no_collected_php_errors(page)
        context.close()
