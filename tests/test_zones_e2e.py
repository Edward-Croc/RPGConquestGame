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
# Module-scoped setup: load TestConfig + seed test controllers
# ---------------------------------------------------------------------------

@pytest.fixture(scope="module", autouse=True)
def load_test_config_with_controllers(browser):
    """Load TestConfig scenario and seed minimal controllers for testing."""
    if not DB_AVAILABLE:
        yield
        return

    # Ensure gm user exists before loading TestConfig
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute(
        f"INSERT IGNORE INTO `{GAME_PREFIX}players` "
        f"(username, passwd, is_privileged) VALUES ('gm', 'orga', 1)"
    )
    cursor.execute(
        f"INSERT IGNORE INTO `{GAME_PREFIX}mechanics` "
        f"(turncounter, gamestate) VALUES (0, 0)"
    )
    conn.commit()
    conn.close()

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

    # Seed test factions, controllers, and zone claims
    conn = get_db_connection()
    cursor = conn.cursor()

    # Step 1: factions (no FK deps)
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

    # Step 3: assign zone claims (depends on controllers)
    # ZoneB: claimed AND held by Alpha (triggers both banner + "our control" tag)
    cursor.execute(
        f"UPDATE `{GAME_PREFIX}zones` SET claimer_controller_id = 1, holder_controller_id = 1 WHERE name = 'ZoneB'"
    )
    cursor.execute(
        f"UPDATE `{GAME_PREFIX}zones` SET claimer_controller_id = 2 WHERE name = 'ZoneC'"
    )

    # Step 4: link gm to controller Alpha
    cursor.execute(
        f"INSERT INTO `{GAME_PREFIX}player_controller` (player_id, controller_id) VALUES "
        f"(1, 1) ON DUPLICATE KEY UPDATE player_id=VALUES(player_id)"
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
# Zones page tests
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestZonesPageNoWarnings:
    """Zones page should load without PHP warnings or errors."""

    def test_zones_page_no_php_warnings(self, gm_page: Page, base_url):
        gm_page.goto(f"{base_url}/zones/action.php")
        gm_page.wait_for_load_state("networkidle")
        page_html = gm_page.content()
        assert "<b>Warning</b>" not in page_html, \
            "PHP warnings on zones page"
        assert "<b>Fatal error</b>" not in page_html, \
            "PHP fatal error on zones page"


@pytest.mark.db
class TestZonesPageStructure:
    """Zones page displays zone section with correct content."""

    def test_zones_section_visible(self, gm_page: Page, base_url):
        gm_page.goto(f"{base_url}/zones/action.php")
        gm_page.wait_for_load_state("networkidle")
        zones_section = gm_page.locator("div.section.zones")
        expect(zones_section).to_be_visible()
        expect(zones_section.locator("h2")).to_contain_text("Zones")

    def test_all_test_config_zones_listed(self, gm_page: Page, base_url):
        """All 7 TestConfig zones should appear on the page."""
        gm_page.goto(f"{base_url}/zones/action.php")
        gm_page.wait_for_load_state("networkidle")
        page_text = gm_page.locator("div.section.zones").inner_text()

        for zone_name in ["ZoneA", "ZoneB", "ZoneC", "ZoneD", "ZoneE", "ZoneF", "ZoneG"]:
            assert zone_name in page_text, \
                f"Zone '{zone_name}' not found on zones page"

    def test_zones_have_description_divs(self, gm_page: Page, base_url):
        """Each zone should have a hidden description div."""
        gm_page.goto(f"{base_url}/zones/action.php")
        gm_page.wait_for_load_state("networkidle")
        description_divs = gm_page.locator("div[id^='description-']")
        count = description_divs.count()
        # 7 zones + carte div = at least 7
        assert count >= 7, \
            f"Expected at least 7 description divs, found {count}"


@pytest.mark.db
class TestZonesPageControllers:
    """Zones with assigned controllers show banner tags."""

    def test_claimed_zones_show_banner(self, gm_page: Page, base_url):
        """ZoneB and ZoneC were claimed — they should have banner tags."""
        gm_page.goto(f"{base_url}/zones/action.php")
        gm_page.wait_for_load_state("networkidle")
        banner_tags = gm_page.locator("span.tag.is-warning")
        assert banner_tags.count() >= 2, \
            f"Expected at least 2 controller banner tags, found {banner_tags.count()}"

    def test_banner_shows_controller_name(self, gm_page: Page, base_url):
        """Banner tags should contain the controller lastname."""
        gm_page.goto(f"{base_url}/zones/action.php")
        gm_page.wait_for_load_state("networkidle")
        page_text = gm_page.locator("div.section.zones").inner_text()
        assert "Alpha" in page_text, \
            "Controller 'Alpha' banner not found (ZoneB claimer)"
        assert "Beta" in page_text, \
            "Controller 'Beta' banner not found (ZoneC claimer)"

    def test_own_zone_shows_control_tag(self, gm_page: Page, base_url):
        """Zones held by the player's controller should show 'our control' tag."""
        # gm is linked to controller Alpha who claims ZoneB
        gm_page.goto(f"{base_url}/base/accueil.php?controller_id=1")
        gm_page.wait_for_load_state("networkidle")
        gm_page.goto(f"{base_url}/zones/action.php")
        gm_page.wait_for_load_state("networkidle")
        danger_tags = gm_page.locator("span.tag.is-danger")
        assert danger_tags.count() >= 1, \
            "Expected at least 1 'our control' tag for controller Alpha's zone"
