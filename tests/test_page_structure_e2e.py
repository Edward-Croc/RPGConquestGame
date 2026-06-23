"""Playwright E2E tests for read-only page-structure behaviour on TestConfig.

Three formerly-separate files merged here to share one scenario load:

  - controllers/action.php auth + ownership guard (formerly
    test_controllers_action_auth_e2e.py)
  - controllers page selection behaviour for admin / single / multi player
    (formerly test_controllers_e2e.py)
  - zones page section, descriptions, banners, toggle clicks
    (formerly test_zones_e2e.py)

All three suites are read-only on the seeded TestConfig (no end_turn, no DB
writes, no recruitment) so they collapse onto one module-scope fixture.

Run:
    python3 -m pytest tests/test_page_structure_e2e.py -v
"""
import pytest
from playwright.sync_api import Page, expect

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, load_minimal_data, load_scenario_via_admin,
    login_as, logout, safe_goto, ui_controller_id,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def setup_testconfig(browser):
    """Load TestConfig once for the whole module."""
    if DB_AVAILABLE:
        load_minimal_data()
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
    yield


@pytest.fixture
def gm_page(page: Page, base_url):
    login_as(page, base_url, "gm", "orga")
    yield page
    logout(page, base_url)


# ---------------------------------------------------------------------------
# controllers/action.php auth + ownership guard
# ---------------------------------------------------------------------------

def _resolve_controller_id(browser, base_url, lastname):
    ctx = browser.new_context()
    page = ctx.new_page()
    ensure_gm_login(page, base_url)
    cid = ui_controller_id(page, lastname, base_url=base_url)
    ctx.close()
    return cid


def test_anonymous_get_redirects_to_login(browser, base_url):
    """No session → action.php redirects (302) to /connection/loginForm.php."""
    alpha_cid = _resolve_controller_id(browser, base_url, "Alpha")
    ctx = browser.new_context()
    page = ctx.new_page()
    page.goto(f"{base_url}/controllers/action.php?controller_id={alpha_cid}")
    assert "loginForm.php" in page.url, (
        f"Anonymous GET on /controllers/action.php must redirect to the login form; "
        f"landed on {page.url}"
    )
    ctx.close()


def test_non_owner_mutation_returns_403(browser, base_url):
    """single_player auto-selects Alpha on login; createBase on Beta must 403."""
    beta_cid = _resolve_controller_id(browser, base_url, "Beta")
    ctx = browser.new_context()
    page = ctx.new_page()
    login_as(page, base_url, "single_player", "test")
    response = page.goto(
        f"{base_url}/controllers/action.php"
        f"?controller_id={beta_cid}&createBase=1&zone_id=1"
    )
    assert response is not None
    assert response.status == 403, \
        f"Non-owner mutation on a foreign controller must 403; got {response.status}"
    ctx.close()


def test_owner_acts_on_own_controller(browser, base_url):
    """single_player on Alpha hitting Alpha's own controller_id renders 200."""
    alpha_cid = _resolve_controller_id(browser, base_url, "Alpha")
    ctx = browser.new_context()
    page = ctx.new_page()
    login_as(page, base_url, "single_player", "test")
    response = page.goto(f"{base_url}/controllers/action.php?controller_id={alpha_cid}")
    assert response is not None
    assert response.status == 200, \
        f"Owner action on own controller must succeed; got {response.status}"
    ctx.close()


def test_gm_privileged_bypasses_ownership(browser, base_url):
    """gm has is_privileged=1; guard must let them through on any controller."""
    beta_cid = _resolve_controller_id(browser, base_url, "Beta")
    ctx = browser.new_context()
    page = ctx.new_page()
    login_as(page, base_url, "gm", "orga")
    response = page.goto(f"{base_url}/controllers/action.php?controller_id={beta_cid}")
    assert response is not None
    assert response.status == 200, \
        f"Privileged gm must bypass ownership; got {response.status}"
    ctx.close()


# ---------------------------------------------------------------------------
# Controllers page — selection behaviour by player type
# ---------------------------------------------------------------------------

class TestControllerAdmin:
    """Admin user (gm) with multiple controllers."""

    def test_admin_sees_controller_dropdown(self, gm_page: Page, base_url):
        safe_goto(gm_page, f"{base_url}/base/accueil.php")
        gm_page.wait_for_load_state("networkidle")
        select = gm_page.locator("select#controllerSelect[name='controller_id']")
        expect(select).to_be_visible()

    def test_admin_dropdown_lists_both_controllers(self, gm_page: Page, base_url):
        safe_goto(gm_page, f"{base_url}/base/accueil.php")
        gm_page.wait_for_load_state("networkidle")
        select = gm_page.locator("select#controllerSelect[name='controller_id']")
        options = select.locator("option").all()
        option_texts = [opt.inner_text() for opt in options]
        assert any("Alpha" in t for t in option_texts), \
            f"Alpha not in dropdown: {option_texts}"
        assert any("Beta" in t for t in option_texts), \
            f"Beta not in dropdown: {option_texts}"

    def test_admin_choose_button_visible(self, gm_page: Page, base_url):
        safe_goto(gm_page, f"{base_url}/base/accueil.php")
        gm_page.wait_for_load_state("networkidle")
        expect(gm_page.locator("input[value='Choisir']")).to_be_visible()


class TestControllerSinglePlayer:
    """Player with exactly one controller — no chooser dropdown."""

    @pytest.fixture
    def single_page(self, page: Page, base_url):
        login_as(page, base_url, "single_player", "test")
        yield page
        logout(page, base_url)

    def test_no_controller_chooser(self, single_page: Page, base_url):
        safe_goto(single_page, f"{base_url}/base/accueil.php")
        single_page.wait_for_load_state("networkidle")
        chooser = single_page.locator("select#controllerSelect[name='controller_id']")
        assert chooser.count() == 0, \
            "Single-controller player should not see controller_id chooser"

    def test_sees_faction_directly(self, single_page: Page, base_url):
        safe_goto(single_page, f"{base_url}/base/accueil.php")
        single_page.wait_for_load_state("networkidle")
        page_text = single_page.inner_text("body")
        assert "Alpha" in page_text, \
            "Single-controller player should see controller Alpha's info"


class TestControllerMultiPlayer:
    """Non-admin player with two controllers — chooser limited to their own."""

    @pytest.fixture
    def multi_page(self, page: Page, base_url):
        login_as(page, base_url, "multi_player", "test")
        yield page
        logout(page, base_url)

    def test_sees_controller_dropdown(self, multi_page: Page, base_url):
        safe_goto(multi_page, f"{base_url}/base/accueil.php")
        multi_page.wait_for_load_state("networkidle")
        select = multi_page.locator("select#controllerSelect[name='controller_id']")
        expect(select).to_be_visible()

    def test_sees_only_own_controllers(self, multi_page: Page, base_url):
        safe_goto(multi_page, f"{base_url}/base/accueil.php")
        multi_page.wait_for_load_state("networkidle")
        select = multi_page.locator("select#controllerSelect[name='controller_id']")
        options = select.locator("option").all()
        option_texts = [opt.inner_text() for opt in options]
        assert any("Alpha" in t for t in option_texts), \
            f"Expected Alpha in options: {option_texts}"
        assert any("Beta" in t for t in option_texts), \
            f"Expected Beta in options: {option_texts}"
        assert len(option_texts) == 2, \
            f"Multi-controller player should see exactly 2 controllers, got {len(option_texts)}: {option_texts}"


# ---------------------------------------------------------------------------
# Zones page — sections, descriptions, banners, toggle clicks
# ---------------------------------------------------------------------------

class TestZonesPageStructure:
    """Zones page displays zone section with correct content."""

    def test_zones_section_visible(self, gm_page: Page, base_url):
        safe_goto(gm_page, f"{base_url}/zones/action.php")
        gm_page.wait_for_load_state("networkidle")
        zones_section = gm_page.locator("div.section.zones")
        expect(zones_section).to_be_visible()
        expect(zones_section.locator("h2")).to_contain_text("Zones")

    def test_all_test_config_zones_listed(self, gm_page: Page, base_url):
        safe_goto(gm_page, f"{base_url}/zones/action.php")
        gm_page.wait_for_load_state("networkidle")
        page_text = gm_page.locator("div.section.zones").inner_text()
        for zone_name in [
            "Alpha-Investigation", "Beta-Combat", "Gamma-Claims",
            "Delta-Disputed", "Epsilon-Controlled", "Zeta-Unclaimed",
            "Eta-Hidden", "Theta-Artefacts",
        ]:
            assert zone_name in page_text, \
                f"Zone '{zone_name}' not found on zones page"

    def test_zones_have_description_divs(self, gm_page: Page, base_url):
        safe_goto(gm_page, f"{base_url}/zones/action.php")
        gm_page.wait_for_load_state("networkidle")
        description_divs = gm_page.locator("div[id^='description-']")
        count = description_divs.count()
        assert count >= 7, \
            f"Expected at least 7 description divs, found {count}"


class TestZonesPageControllers:
    """Zones with assigned controllers show banner tags."""

    def test_claimed_zones_show_banner(self, gm_page: Page, base_url):
        safe_goto(gm_page, f"{base_url}/zones/action.php")
        gm_page.wait_for_load_state("networkidle")
        banner_tags = gm_page.locator("span.tag.is-warning")
        assert banner_tags.count() >= 2, \
            f"Expected at least 2 controller banner tags, found {banner_tags.count()}"

    def test_banner_shows_controller_name(self, gm_page: Page, base_url):
        safe_goto(gm_page, f"{base_url}/zones/action.php")
        gm_page.wait_for_load_state("networkidle")
        page_text = gm_page.locator("div.section.zones").inner_text()
        assert "Alpha" in page_text, \
            "Controller 'Alpha' banner not found (Gamma-Claims claimer)"
        assert "Beta" in page_text, \
            "Controller 'Beta' banner not found (Delta-Disputed claimer)"

    def test_own_zone_shows_control_tag(self, gm_page: Page, base_url):
        safe_goto(gm_page, f"{base_url}/base/accueil.php?controller_id=1")
        gm_page.wait_for_load_state("networkidle")
        safe_goto(gm_page, f"{base_url}/zones/action.php")
        gm_page.wait_for_load_state("networkidle")
        danger_tags = gm_page.locator("span.tag.is-danger")
        assert danger_tags.count() >= 1, \
            "Expected at least 1 'our control' tag for controller Alpha's zone"


class TestZoneToggleClicks:
    """toggleDescription(id) flips inline `display` on collapsible sections."""

    def test_carte_header_toggles_map(self, gm_page: Page, base_url):
        safe_goto(gm_page, f"{base_url}/zones/action.php")
        carte = gm_page.locator("#description-carte")
        assert carte.evaluate("el => el.style.display") == "none"
        gm_page.locator("h4[onclick=\"toggleDescription('carte')\"]").click()
        assert carte.evaluate("el => el.style.display") == "block"
        gm_page.locator("h4[onclick=\"toggleDescription('carte')\"]").click()
        assert carte.evaluate("el => el.style.display") == "none"

    def test_zone_header_toggles_description(self, gm_page: Page, base_url):
        safe_goto(gm_page, f"{base_url}/zones/action.php")
        first_desc = gm_page.locator(
            "div[id^='description-']:not([id='description-carte'])"
        ).first
        zone_id = (first_desc.get_attribute("id") or "").replace("description-", "")
        assert zone_id, "Could not extract zone_id from first description div"
        assert first_desc.evaluate("el => el.style.display") == "none"
        header = gm_page.locator(
            f"h3[onclick=\"toggleDescription('{zone_id}')\"]"
        )
        header.click()
        assert first_desc.evaluate("el => el.style.display") == "block"
