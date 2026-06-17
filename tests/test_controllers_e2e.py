"""Playwright end-to-end tests for the controllers page.

Loads TestConfig scenario with seeded test players/controllers and
verifies controller selection behaviour for admin, single-controller,
and multi-controller players.

Run:
    python3 -m pytest tests/test_controllers_e2e.py -v
"""
import pymysql
import pytest
from playwright.sync_api import Page, expect

from conftest import (
    GAME_PREFIX, MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB,
    PHP_BASE_URL,
)

from helpers import DB_AVAILABLE


from helpers import (
    get_db_connection, load_minimal_data, load_scenario_via_admin, login_as, logout, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


# ---------------------------------------------------------------------------
# Module-scoped setup: load TestConfig + seed test data
# ---------------------------------------------------------------------------

@pytest.fixture(scope="module", autouse=True)
def load_test_config_with_players(browser):
    """Load TestConfig. Seeds gm locally (DB-direct) if available, then
    loads the scenario via admin UI — works against local or remote prod."""
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
# Admin controller tests (gm with multiple controllers)
# ---------------------------------------------------------------------------

class TestControllerAdmin:
    """Admin user (gm) with multiple controllers."""

    def test_admin_sees_controller_dropdown(self, gm_page: Page, base_url):
        """Admin with multiple controllers should see a selection dropdown."""
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


# ---------------------------------------------------------------------------
# Single-controller player tests
# ---------------------------------------------------------------------------

class TestControllerSinglePlayer:
    """Player with exactly one controller — no chooser dropdown."""

    @pytest.fixture
    def single_page(self, page: Page, base_url):
        login_as(page, base_url, "single_player", "test")
        yield page
        logout(page, base_url)

    def test_no_controller_chooser(self, single_page: Page, base_url):
        """Single-controller player should NOT see the controller_id dropdown."""
        safe_goto(single_page, f"{base_url}/base/accueil.php")
        single_page.wait_for_load_state("networkidle")
        chooser = single_page.locator("select#controllerSelect[name='controller_id']")
        assert chooser.count() == 0, \
            "Single-controller player should not see controller_id chooser"

    def test_sees_faction_directly(self, single_page: Page, base_url):
        """Single-controller player should see their faction info immediately."""
        safe_goto(single_page, f"{base_url}/base/accueil.php")
        single_page.wait_for_load_state("networkidle")
        page_text = single_page.inner_text("body")
        assert "Alpha" in page_text, \
            "Single-controller player should see controller Alpha's info"


# ---------------------------------------------------------------------------
# Multi-controller non-admin player tests
# ---------------------------------------------------------------------------

class TestControllerMultiPlayer:
    """Non-admin player with two controllers — should see chooser with only their own."""

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
        """Should see Alpha and Beta (their own), not any hidden controllers."""
        safe_goto(multi_page, f"{base_url}/base/accueil.php")
        multi_page.wait_for_load_state("networkidle")
        select = multi_page.locator("select#controllerSelect[name='controller_id']")
        options = select.locator("option").all()
        option_texts = [opt.inner_text() for opt in options]
        assert any("Alpha" in t for t in option_texts), \
            f"Expected Alpha in options: {option_texts}"
        assert any("Beta" in t for t in option_texts), \
            f"Expected Beta in options: {option_texts}"
        # Should have exactly 2 options
        assert len(option_texts) == 2, \
            f"Multi-controller player should see exactly 2 controllers, got {len(option_texts)}: {option_texts}"


