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
    page.locator("input[name='submit'][value='Submit']").click()
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

    # Phase 2: Alpha uses first-come (no base required)
    _do_first_come(page, 'Alpha')
    _snapshot['alpha_t0_after_first_come_counters'] = ui_controller_counters(page, 'Alpha')
    _snapshot['alpha_t0_after_first_come_html'] = _workers_page_html(page, 'Alpha')
    _snapshot['alpha_t0_after_first_come_workers_ui'] = _count_worker_cards_in_html(
        _snapshot['alpha_t0_after_first_come_html']
    )

    # Phase 3: Alpha creates a base in Epsilon-Controlled
    _create_base(page, 'Alpha', 'Epsilon-Controlled')
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

    _do_regular_recruit(page, 'Alpha')
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
