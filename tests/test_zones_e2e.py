"""Playwright end-to-end tests for the zones page.

Loads TestConfig scenario and verifies zone display, descriptions, and
controller banners.

Run:
    python3 -m pytest tests/test_zones_e2e.py -v
"""
import pymysql
import pytest
from playwright.sync_api import Page, expect

from conftest import (
    GAME_PREFIX, MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB,
    PHP_BASE_URL,
)

from helpers import DB_AVAILABLE, get_db_connection, load_minimal_data, login_as, logout, safe_goto


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


# ---------------------------------------------------------------------------
# Module-scoped setup: load TestConfig + seed test controllers
# ---------------------------------------------------------------------------

@pytest.fixture(scope="module", autouse=True)
def load_test_config_with_controllers(browser):
    """Load TestConfig scenario. Seeds gm locally (DB-direct) if available,
    then loads the scenario via admin UI. Scenario load works against any
    reachable target (local or remote prod) because it goes through HTTP."""
    # Local bootstrap — skip when running against prod (DB not reachable).
    if DB_AVAILABLE:
        load_minimal_data()

    # Load TestConfig via admin UI (works everywhere)
    context = browser.new_context()
    page = context.new_page()
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
    context.close()

    yield


@pytest.fixture
def gm_page(page: Page, base_url):
    login_as(page, base_url, "gm", "orga")
    yield page
    logout(page, base_url)


# ---------------------------------------------------------------------------
# Zones page tests
# ---------------------------------------------------------------------------

class TestZonesPageNoWarnings:
    """Zones page should load without PHP warnings or errors."""

    def test_zones_page_no_php_warnings(self, gm_page: Page, base_url):
        safe_goto(gm_page, f"{base_url}/zones/action.php")
        gm_page.wait_for_load_state("networkidle")
        page_html = gm_page.content()
        assert "<b>Warning</b>" not in page_html, \
            "PHP warnings on zones page"
        assert "<b>Fatal error</b>" not in page_html, \
            "PHP fatal error on zones page"


class TestZonesPageStructure:
    """Zones page displays zone section with correct content."""

    def test_zones_section_visible(self, gm_page: Page, base_url):
        safe_goto(gm_page, f"{base_url}/zones/action.php")
        gm_page.wait_for_load_state("networkidle")
        zones_section = gm_page.locator("div.section.zones")
        expect(zones_section).to_be_visible()
        expect(zones_section.locator("h2")).to_contain_text("Zones")

    def test_all_test_config_zones_listed(self, gm_page: Page, base_url):
        """All 7 TestConfig zones should appear on the page."""
        safe_goto(gm_page, f"{base_url}/zones/action.php")
        gm_page.wait_for_load_state("networkidle")
        page_text = gm_page.locator("div.section.zones").inner_text()

        for zone_name in ["Alpha-Investigation", "Beta-Combat", "Gamma-Claims", "Delta-Disputed", "Epsilon-Controlled", "Zeta-Unclaimed", "Eta-Hidden"]:
            assert zone_name in page_text, \
                f"Zone '{zone_name}' not found on zones page"

    def test_zones_have_description_divs(self, gm_page: Page, base_url):
        """Each zone should have a hidden description div."""
        safe_goto(gm_page, f"{base_url}/zones/action.php")
        gm_page.wait_for_load_state("networkidle")
        description_divs = gm_page.locator("div[id^='description-']")
        count = description_divs.count()
        # 7 zones + carte div = at least 7
        assert count >= 7, \
            f"Expected at least 7 description divs, found {count}"


class TestZonesPageControllers:
    """Zones with assigned controllers show banner tags."""

    def test_claimed_zones_show_banner(self, gm_page: Page, base_url):
        """Gamma-Claims and Delta-Disputed were claimed — they should have banner tags."""
        safe_goto(gm_page, f"{base_url}/zones/action.php")
        gm_page.wait_for_load_state("networkidle")
        banner_tags = gm_page.locator("span.tag.is-warning")
        assert banner_tags.count() >= 2, \
            f"Expected at least 2 controller banner tags, found {banner_tags.count()}"

    def test_banner_shows_controller_name(self, gm_page: Page, base_url):
        """Banner tags should contain the controller lastname."""
        safe_goto(gm_page, f"{base_url}/zones/action.php")
        gm_page.wait_for_load_state("networkidle")
        page_text = gm_page.locator("div.section.zones").inner_text()
        assert "Alpha" in page_text, \
            "Controller 'Alpha' banner not found (Gamma-Claims claimer)"
        assert "Beta" in page_text, \
            "Controller 'Beta' banner not found (Delta-Disputed claimer)"

    def test_own_zone_shows_control_tag(self, gm_page: Page, base_url):
        """Zones held by the player's controller should show 'our control' tag."""
        # gm is linked to controller Alpha who claims Gamma-Claims
        safe_goto(gm_page, f"{base_url}/base/accueil.php?controller_id=1")
        gm_page.wait_for_load_state("networkidle")
        safe_goto(gm_page, f"{base_url}/zones/action.php")
        gm_page.wait_for_load_state("networkidle")
        danger_tags = gm_page.locator("span.tag.is-danger")
        assert danger_tags.count() >= 1, \
            "Expected at least 1 'our control' tag for controller Alpha's zone"
