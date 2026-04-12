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

DB_AVAILABLE = False
try:
    _conn = pymysql.connect(
        host=MYSQL_HOST, port=MYSQL_PORT, user=MYSQL_USER,
        password=MYSQL_PASSWORD, database=MYSQL_DB, connect_timeout=3,
    )
    _conn.close()
    DB_AVAILABLE = True
except Exception:
    pass


def get_db_connection():
    return pymysql.connect(
        host=MYSQL_HOST, port=MYSQL_PORT, user=MYSQL_USER,
        password=MYSQL_PASSWORD, database=MYSQL_DB,
        charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor,
    )


def login_as(page: Page, base_url: str, username: str, password: str):
    page.goto(f"{base_url}/connection/loginForm.php")
    page.wait_for_load_state("networkidle")
    page.locator("input[name='username']").fill(username)
    page.locator("input[name='passwd']").fill(password)
    page.locator("input[type='submit']").first.click()
    page.wait_for_load_state("networkidle")


def logout(page: Page, base_url: str):
    page.goto(f"{base_url}/connection/logout.php")
    page.wait_for_load_state("networkidle")


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


# ---------------------------------------------------------------------------
# Module-scoped setup: load TestConfig + seed test data
# ---------------------------------------------------------------------------

@pytest.fixture(scope="module", autouse=True)
def load_test_config_with_players(browser):
    """Load TestConfig and seed factions, controllers, and test players."""
    if not DB_AVAILABLE:
        yield
        return

    # Load TestConfig via admin UI
    context = browser.new_context()
    page = context.new_page()
    page.goto(f"{PHP_BASE_URL}/connection/loginForm.php")
    page.wait_for_load_state("networkidle")
    page.locator("input[name='username']").fill("gm")
    page.locator("input[name='passwd']").fill("orga")
    page.locator("input[type='submit']").first.click()
    page.wait_for_load_state("networkidle")
    page.goto(f"{PHP_BASE_URL}/base/admin.php")
    page.wait_for_load_state("networkidle")
    page.locator("select[name='config_name']").select_option("TestConfig")
    page.locator("input[name='submit'][value='Submit']").click(no_wait_after=True)
    page.wait_for_timeout(5000)
    page.wait_for_load_state("load", timeout=90000)
    context.close()

    # Seed test data with proper ordering for FK constraints
    conn = get_db_connection()
    cursor = conn.cursor()

    # Step 1: factions
    cursor.execute(
        f"INSERT INTO `{GAME_PREFIX}factions` (id, name) VALUES "
        f"(1, 'FactionAlpha'), (2, 'FactionBeta') "
        f"ON DUPLICATE KEY UPDATE name=VALUES(name)"
    )
    conn.commit()

    # Step 2: controllers (depends on factions)
    cursor.execute(
        f"INSERT INTO `{GAME_PREFIX}controllers` "
        f"(id, firstname, lastname, faction_id, fake_faction_id) VALUES "
        f"(1, 'Lord', 'Alpha', 1, 1), (2, 'Lady', 'Beta', 2, 2) "
        f"ON DUPLICATE KEY UPDATE firstname=VALUES(firstname)"
    )
    conn.commit()

    # Step 3: test players
    cursor.execute(
        f"INSERT INTO `{GAME_PREFIX}players` (id, username, passwd, is_privileged) VALUES "
        f"(2, 'single_player', 'test', 0), "
        f"(3, 'multi_player', 'test', 0) "
        f"ON DUPLICATE KEY UPDATE username=VALUES(username)"
    )
    conn.commit()

    # Step 4: player-controller links
    for vals in ["(1, 1)", "(1, 2)", "(2, 1)", "(3, 1)", "(3, 2)"]:
        cursor.execute(
            f"INSERT INTO `{GAME_PREFIX}player_controller` (player_id, controller_id) VALUES "
            f"{vals} ON DUPLICATE KEY UPDATE player_id=VALUES(player_id)"
        )
    conn.commit()
    conn.close()
    yield


@pytest.fixture(autouse=True)
def _require_db():
    if not DB_AVAILABLE:
        pytest.skip("No local MySQL available")


@pytest.fixture
def gm_page(page: Page, base_url):
    login_as(page, base_url, "gm", "orga")
    yield page
    logout(page, base_url)


# ---------------------------------------------------------------------------
# Admin controller tests (gm with multiple controllers)
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestControllerAdmin:
    """Admin user (gm) with multiple controllers."""

    def test_accueil_no_warnings(self, gm_page: Page, base_url):
        gm_page.goto(f"{base_url}/base/accueil.php")
        gm_page.wait_for_load_state("networkidle")
        page_html = gm_page.content()
        assert "<b>Warning</b>" not in page_html
        assert "<b>Fatal error</b>" not in page_html

    def test_admin_sees_controller_dropdown(self, gm_page: Page, base_url):
        """Admin with multiple controllers should see a selection dropdown."""
        gm_page.goto(f"{base_url}/base/accueil.php")
        gm_page.wait_for_load_state("networkidle")
        select = gm_page.locator("select#controllerSelect[name='controller_id']")
        expect(select).to_be_visible()

    def test_admin_dropdown_lists_both_controllers(self, gm_page: Page, base_url):
        gm_page.goto(f"{base_url}/base/accueil.php")
        gm_page.wait_for_load_state("networkidle")
        select = gm_page.locator("select#controllerSelect[name='controller_id']")
        options = select.locator("option").all()
        option_texts = [opt.inner_text() for opt in options]
        assert any("Alpha" in t for t in option_texts), \
            f"Alpha not in dropdown: {option_texts}"
        assert any("Beta" in t for t in option_texts), \
            f"Beta not in dropdown: {option_texts}"

    def test_admin_choose_button_visible(self, gm_page: Page, base_url):
        gm_page.goto(f"{base_url}/base/accueil.php")
        gm_page.wait_for_load_state("networkidle")
        expect(gm_page.locator("input[value='Choisir']")).to_be_visible()


# ---------------------------------------------------------------------------
# Single-controller player tests
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestControllerSinglePlayer:
    """Player with exactly one controller — no chooser dropdown."""

    @pytest.fixture
    def single_page(self, page: Page, base_url):
        login_as(page, base_url, "single_player", "test")
        yield page
        logout(page, base_url)

    def test_no_warnings(self, single_page: Page, base_url):
        single_page.goto(f"{base_url}/base/accueil.php")
        single_page.wait_for_load_state("networkidle")
        page_html = single_page.content()
        assert "<b>Warning</b>" not in page_html
        assert "<b>Fatal error</b>" not in page_html

    def test_no_controller_chooser(self, single_page: Page, base_url):
        """Single-controller player should NOT see the controller_id dropdown."""
        single_page.goto(f"{base_url}/base/accueil.php")
        single_page.wait_for_load_state("networkidle")
        chooser = single_page.locator("select#controllerSelect[name='controller_id']")
        assert chooser.count() == 0, \
            "Single-controller player should not see controller_id chooser"

    def test_sees_faction_directly(self, single_page: Page, base_url):
        """Single-controller player should see their faction info immediately."""
        single_page.goto(f"{base_url}/base/accueil.php")
        single_page.wait_for_load_state("networkidle")
        page_text = single_page.inner_text("body")
        assert "Alpha" in page_text, \
            "Single-controller player should see controller Alpha's info"


# ---------------------------------------------------------------------------
# Multi-controller non-admin player tests
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestControllerMultiPlayer:
    """Non-admin player with two controllers — should see chooser with only their own."""

    @pytest.fixture
    def multi_page(self, page: Page, base_url):
        login_as(page, base_url, "multi_player", "test")
        yield page
        logout(page, base_url)

    def test_no_warnings(self, multi_page: Page, base_url):
        multi_page.goto(f"{base_url}/base/accueil.php")
        multi_page.wait_for_load_state("networkidle")
        page_html = multi_page.content()
        assert "<b>Warning</b>" not in page_html
        assert "<b>Fatal error</b>" not in page_html

    def test_sees_controller_dropdown(self, multi_page: Page, base_url):
        multi_page.goto(f"{base_url}/base/accueil.php")
        multi_page.wait_for_load_state("networkidle")
        select = multi_page.locator("select#controllerSelect[name='controller_id']")
        expect(select).to_be_visible()

    def test_sees_only_own_controllers(self, multi_page: Page, base_url):
        """Should see Alpha and Beta (their own), not any hidden controllers."""
        multi_page.goto(f"{base_url}/base/accueil.php")
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


# ---------------------------------------------------------------------------
# Verify gm can still log in after all tests
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestPostTestCleanup:
    """Verify the gm account still works after all controller tests."""

    def test_gm_can_login(self, page: Page, base_url):
        login_as(page, base_url, "gm", "orga")
        assert "accueil.php" in page.url, \
            f"gm login should redirect to accueil, got: {page.url}"
        logout(page, base_url)
