"""Playwright E2E tests for artefact visibility on faction-scoped pages.

Verifies the artefact-rendering rules per issue #2 spec:
  - Owned artefacts → visible in 'Vos lieux secrets:' on /zones/action.php AND /controllers/action.php
  - Detected artefacts → ONLY in worker reports (NOT on either faction page)
  - Move via /artefacts/management.php → owner pages reflect new owner

Test data (TestConfig, in zone Theta-Artefacts):
  - Echo-Base (owned by Echo) hosts artefact 'Echo-Base Relic'
  - Foxtrot-Outpost (owned by Foxtrot) hosts artefact 'Foxtrot-Outpost Cipher'
  - Civic-Site (unowned, can_be_destroyed=0)
  - Artefact_Searcher_Echo (Echo, investigates) — detects Foxtrot-Outpost on end-turn
  - Artefact_Worker_Foxtrot (Foxtrot, passive)

Note on choice of Echo/Foxtrot: bench controllers used so that pre-seeded
ownership doesn't conflict with `test_controller_recruitment_e2e.py`,
which axiomatically requires Alpha and Beta to start without bases.

UI-only: all lookups (controller id, worker id, location/artefact selection)
go through the UI so the suite remains runnable against the production
DEMO target (no DB access required after the scenario is loaded).

Run:
    python3 -m pytest tests/test_artefact_visibility_e2e.py -v
"""
import pytest
from playwright.sync_api import Page

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, load_minimal_data, login_as, logout, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
    ui_worker_id,
)


_controller_ids = {}


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


def _scrape_controller_ids(page, base_url):
    """Populate the controller-lastname → id map by scraping accueil's
    select#controllerSelect. Works against any reachable target."""
    safe_goto(page, f"{base_url}/base/accueil.php")
    page.wait_for_load_state("networkidle")
    for opt in page.locator("select#controllerSelect option").all():
        val = opt.get_attribute("value") or ""
        text = (opt.inner_text() or "").strip()
        if val and text:
            # "Lord Echo" → "Echo"
            _controller_ids[text.split()[-1]] = int(val)


@pytest.fixture(scope="module", autouse=True)
def load_artefact_scenario(browser):
    """Load TestConfig via admin UI + run end-turn so detection seeds.
    Skips local minimal-data seeding when DB is unavailable (prod target)."""
    if DB_AVAILABLE:
        load_minimal_data()

    context = browser.new_context()
    page = context.new_page()
    register_php_error_listener(page)
    safe_goto(page, f"{PHP_BASE_URL}/connection/loginForm.php")
    page.wait_for_load_state("networkidle")
    page.locator("input[name='username']").fill("gm")
    page.locator("input[name='passwd']").fill("orga")
    page.locator("input[type='submit']").first.click()
    page.wait_for_load_state("networkidle")
    safe_goto(page, f"{PHP_BASE_URL}/base/admin.php")
    page.wait_for_load_state("networkidle")
    page.locator("select[name='config_name']").select_option("TestConfig")
    page.locator("input[name='submit'][value='Submit']").click()
    page.wait_for_timeout(5000)
    page.wait_for_load_state("load", timeout=90000)

    safe_goto(page, f"{PHP_BASE_URL}/mechanics/endTurn.php")
    page.wait_for_load_state("load", timeout=90000)

    _scrape_controller_ids(page, PHP_BASE_URL)

    assert_no_collected_php_errors(page)
    context.close()

    yield


@pytest.fixture
def gm_page(page: Page, base_url):
    login_as(page, base_url, "gm", "orga")
    yield page
    logout(page, base_url)


def _select_controller(page, base_url, lastname):
    cid = _controller_ids[lastname]
    safe_goto(page, f"{base_url}/base/accueil.php?controller_id={cid}&chosir=Choisir")
    page.wait_for_load_state("networkidle")


def _zones_page_text(page, base_url):
    safe_goto(page, f"{base_url}/zones/action.php")
    page.wait_for_load_state("networkidle")
    return page.content()


def _controllers_page_text(page, base_url):
    safe_goto(page, f"{base_url}/controllers/action.php")
    page.wait_for_load_state("networkidle")
    return page.content()


# ---------------------------------------------------------------------------
# Owned-artefact visibility (read-only)
# ---------------------------------------------------------------------------

class TestOwnedArtefactOnZonesPage:
    """Owned artefacts render in 'Vos lieux secrets:' on /zones/action.php."""

    def test_echo_sees_own_base(self, gm_page: Page, base_url):
        _select_controller(gm_page, base_url, "Echo")
        html = _zones_page_text(gm_page, base_url)
        assert "Echo-Base" in html, "Echo should see Echo-Base on /zones/action.php"

    def test_echo_sees_own_artefact_name(self, gm_page: Page, base_url):
        _select_controller(gm_page, base_url, "Echo")
        html = _zones_page_text(gm_page, base_url)
        assert "Echo-Base Relic" in html, \
            "Echo (owner) should see 'Echo-Base Relic' on /zones/action.php"

    def test_echo_sees_own_artefact_full_lore(self, gm_page: Page, base_url):
        _select_controller(gm_page, base_url, "Echo")
        html = _zones_page_text(gm_page, base_url)
        assert "Lore visible only to Echo as owner" in html, \
            "Echo should see full_description of own artefact"

    def test_foxtrot_does_not_see_echos_artefact(self, gm_page: Page, base_url):
        _select_controller(gm_page, base_url, "Foxtrot")
        html = _zones_page_text(gm_page, base_url)
        assert "Echo-Base Relic" not in html, \
            "Foxtrot should NOT see Echo's artefact name"
        assert "Lore visible only to Echo as owner" not in html, \
            "Foxtrot should NOT see Echo's artefact lore"

    def test_foxtrot_sees_own_artefact(self, gm_page: Page, base_url):
        _select_controller(gm_page, base_url, "Foxtrot")
        html = _zones_page_text(gm_page, base_url)
        assert "Foxtrot-Outpost Cipher" in html, \
            "Foxtrot (owner) should see 'Foxtrot-Outpost Cipher' on /zones/action.php"


class TestOwnedArtefactOnControllersPage:
    """Owned artefacts also render in 'Vos lieux secrets:' on /controllers/action.php."""

    def test_echo_sees_own_artefact(self, gm_page: Page, base_url):
        _select_controller(gm_page, base_url, "Echo")
        html = _controllers_page_text(gm_page, base_url)
        assert "Echo-Base" in html, "Echo-Base should render on /controllers/action.php"
        assert "Echo-Base Relic" in html, \
            "Owned artefact name must render on /controllers/action.php"
        assert "Lore visible only to Echo as owner" in html, \
            "Owned artefact full_description must render on /controllers/action.php"

    def test_foxtrot_does_not_see_echos_artefact(self, gm_page: Page, base_url):
        _select_controller(gm_page, base_url, "Foxtrot")
        html = _controllers_page_text(gm_page, base_url)
        assert "Echo-Base Relic" not in html, \
            "Foxtrot must NOT see Echo's artefact on /controllers/action.php"


# ---------------------------------------------------------------------------
# Detected artefact: only in worker report, not on faction pages
# ---------------------------------------------------------------------------

class TestDetectedArtefactStaysInWorkerReport:
    """Per spec, a detected artefact appears ONLY in the worker report.

    Artefact_Searcher_Echo investigates in Theta-Artefacts; on end-turn
    they detect Foxtrot-Outpost. Their per-turn report should include the
    artefact at Foxtrot-Outpost ('Foxtrot-Outpost Cipher')."""

    def test_searcher_report_includes_detected_artefact(self, page: Page, base_url):
        ensure_gm_login(page, base_url)
        wid = ui_worker_id(page, "Artefact_Searcher_Echo", base_url=base_url)
        # Worker action page requires a selected controller in session first
        _select_controller(page, base_url, "Echo")
        safe_goto(page, f"{base_url}/workers/action.php?worker_id={wid}")
        page.wait_for_load_state("load")
        html = page.content()
        assert "Foxtrot-Outpost" in html, \
            "Artefact_Searcher_Echo report should include detected location Foxtrot-Outpost"
        assert "Foxtrot-Outpost Cipher" in html, \
            "Artefact_Searcher_Echo report should include the detected artefact"


class TestDetectedArtefactNotOnFactionPages:
    """The artefact at a detected (non-owned) location must NOT appear on
    /zones/action.php or /controllers/action.php — only in the worker report.

    Echo detected Foxtrot-Outpost (via Artefact_Searcher_Echo). The location
    itself surfaces as a 'Lieu découvert', but the artefact must not."""

    def test_echo_zones_page_shows_detected_location(self, gm_page: Page, base_url):
        _select_controller(gm_page, base_url, "Echo")
        html = _zones_page_text(gm_page, base_url)
        assert "Foxtrot-Outpost" in html, \
            "Detected location should appear on /zones/action.php"

    def test_echo_zones_page_hides_detected_artefact(self, gm_page: Page, base_url):
        _select_controller(gm_page, base_url, "Echo")
        html = _zones_page_text(gm_page, base_url)
        assert "Foxtrot-Outpost Cipher" not in html, \
            "Detected artefact must NOT leak to /zones/action.php (only in worker report)"

    def test_echo_controllers_page_hides_detected_artefact(self, gm_page: Page, base_url):
        _select_controller(gm_page, base_url, "Echo")
        html = _controllers_page_text(gm_page, base_url)
        assert "Foxtrot-Outpost Cipher" not in html, \
            "Detected artefact must NOT leak to /controllers/action.php"


# ---------------------------------------------------------------------------
# Admin: create + move via /artefacts/management.php (UI-only)
# ---------------------------------------------------------------------------

class TestArtefactCreateViaAdmin:
    """Admin can add a new artefact at /artefacts/management.php; it then
    surfaces on the owner's faction pages."""

    def test_admin_create_citadelle_appears_for_echo(self, gm_page: Page, base_url):
        safe_goto(gm_page, f"{base_url}/artefacts/management.php")
        gm_page.wait_for_load_state("networkidle")
        # The 'Add New Artefact' form is the form that contains artefact_name
        add_form = gm_page.locator("form:has(input[name='artefact_name'])")
        add_form.locator("input[name='artefact_name']").fill("Citadelle Crown of Echo")
        add_form.locator("input[name='artefact_description']").fill("Carved circlet of the Echo citadel")
        add_form.locator("input[name='artefact_full_description']").fill("Forged in Echo's old stronghold; details lost to history")
        add_form.locator("select[name='location_id']").select_option(label="Echo - Echo-Base")
        add_form.locator("button[name='add_artefact']").click()
        gm_page.wait_for_load_state("networkidle")

        # Verify it now appears on Echo's zones page
        _select_controller(gm_page, base_url, "Echo")
        zones_html = _zones_page_text(gm_page, base_url)
        assert "Citadelle Crown of Echo" in zones_html, \
            "Newly-created artefact should appear on Echo's /zones/action.php"

        # And on Echo's controllers page
        ctrl_html = _controllers_page_text(gm_page, base_url)
        assert "Citadelle Crown of Echo" in ctrl_html, \
            "Newly-created artefact should appear on Echo's /controllers/action.php"


class TestArtefactManagementDropdown:
    """/artefacts/management.php location dropdown must:
      (a) hide locations with can_be_destroyed=0 (artefacts can only live
          on attackable locations);
      (b) display each option as 'Controller - Location' so fortresses
          sharing names across factions stay distinguishable;
      (c) sort by controller name (unowned bucketed last).

    Asserts against the 'Add New Artefact' dropdown (the simplest surface
    — same query feeds both the per-row Change Location dropdown and
    this one)."""

    def _add_form_option_labels(self, page, base_url):
        safe_goto(page, f"{base_url}/artefacts/management.php")
        page.wait_for_load_state("networkidle")
        add_form = page.locator("form:has(input[name='artefact_name'])")
        return [
            (opt.inner_text() or "").strip()
            for opt in add_form.locator("select[name='location_id'] option").all()
        ]

    def test_civic_site_excluded_from_dropdown(self, gm_page: Page, base_url):
        """Civic-Site has can_be_destroyed=0 — must NOT appear as an option."""
        labels = self._add_form_option_labels(gm_page, base_url)
        for label in labels:
            assert "Civic-Site" not in label, \
                f"Civic-Site (can_be_destroyed=0) leaked into dropdown: {labels}"

    def test_options_use_controller_location_format(self, gm_page: Page, base_url):
        """Each option must be 'Controller - Location' (or '(unowned) - X')."""
        labels = self._add_form_option_labels(gm_page, base_url)
        assert "Echo - Echo-Base" in labels, \
            f"Expected 'Echo - Echo-Base' in dropdown labels: {labels}"
        assert "Foxtrot - Foxtrot-Outpost" in labels, \
            f"Expected 'Foxtrot - Foxtrot-Outpost' in dropdown labels: {labels}"

    def test_options_sorted_by_controller_then_location(self, gm_page: Page, base_url):
        """Echo-owned options must appear before Foxtrot-owned options
        (alphabetical by controller lastname)."""
        labels = self._add_form_option_labels(gm_page, base_url)
        echo_idx = next(i for i, l in enumerate(labels) if l.startswith("Echo - "))
        foxtrot_idx = next(i for i, l in enumerate(labels) if l.startswith("Foxtrot - "))
        assert echo_idx < foxtrot_idx, \
            f"Echo entries should precede Foxtrot entries; got order: {labels}"


class TestArtefactMoveViaAdmin:
    """Admin can change an artefact's location; ownership view flips.

    Move 'Echo-Base Relic' from Echo-Base → Foxtrot-Outpost.
    Pre: Echo sees it, Foxtrot does not.
    Post: Foxtrot sees it, Echo does not."""

    def test_artefact_move_flips_ownership_view(self, gm_page: Page, base_url):
        # Pre-condition checkpoint
        _select_controller(gm_page, base_url, "Echo")
        pre_echo = _zones_page_text(gm_page, base_url)
        assert "Echo-Base Relic" in pre_echo, "Pre-condition: Echo should see the relic"

        _select_controller(gm_page, base_url, "Foxtrot")
        pre_foxtrot = _zones_page_text(gm_page, base_url)
        assert "Echo-Base Relic" not in pre_foxtrot, "Pre-condition: Foxtrot should NOT see the relic"

        # Drive the per-row 'Change Location' form for 'Echo-Base Relic' (UI-located)
        safe_goto(gm_page, f"{base_url}/artefacts/management.php")
        gm_page.wait_for_load_state("networkidle")
        # Each artefact row has the artefact name in a <td> and a Change Location form
        # in the same row. Locate the row by name, then the form within.
        relic_row = gm_page.locator("tr").filter(has_text="Echo-Base Relic").first
        change_form = relic_row.locator("form:has(select[name='new_location_id'])")
        change_form.locator("select[name='new_location_id']").select_option(label="Foxtrot - Foxtrot-Outpost")
        change_form.locator("button[name='update_location']").click()
        gm_page.wait_for_load_state("networkidle")

        # Post-condition: ownership view has flipped
        _select_controller(gm_page, base_url, "Echo")
        post_echo = _zones_page_text(gm_page, base_url)
        assert "Echo-Base Relic" not in post_echo, \
            "Post-move: Echo should NOT see the relic anymore"

        _select_controller(gm_page, base_url, "Foxtrot")
        post_foxtrot = _zones_page_text(gm_page, base_url)
        assert "Echo-Base Relic" in post_foxtrot, \
            "Post-move: Foxtrot should see the relic now"
        # Also reflect on /controllers/action.php for the new owner
        post_foxtrot_ctrl = _controllers_page_text(gm_page, base_url)
        assert "Echo-Base Relic" in post_foxtrot_ctrl, \
            "Post-move: Foxtrot's /controllers/action.php should show the relic"
