"""Playwright end-to-end tests for the admin CSV load workflow.

Run against local Docker:
    python3 -m pytest tests/test_admin_csv_load_e2e.py -v

Run against production (browser-only tests, skips DB checks):
    PHP_BASE_URL="https://voix-eidolon.fr/RPGConquestGameDemo" python3 -m pytest tests/test_admin_csv_load_e2e.py -v -m "not db"

Run only DB-dependent tests:
    python3 -m pytest tests/test_admin_csv_load_e2e.py -v -m db
"""
import os
import re

import pymysql
import pytest
from playwright.sync_api import Page, expect

from conftest import (
    GAME_PREFIX, MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB,
    PHP_BASE_URL,
)

from helpers import DB_AVAILABLE, get_db_connection


def table_row_count(table_name):
    """Count rows in a prefixed table."""
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute(f"SELECT COUNT(*) as c FROM `{GAME_PREFIX}{table_name}`")
    count = cursor.fetchone()["c"]
    conn.close()
    return count


# ---------------------------------------------------------------------------
# Fixtures
# ---------------------------------------------------------------------------

@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(autouse=True)
def ensure_base_data():
    """Ensure the default gm user and mechanics row exist before each test."""
    if not DB_AVAILABLE:
        yield
        return
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute(
        f"SELECT id FROM `{GAME_PREFIX}players` WHERE username = 'gm'"
    )
    if cursor.fetchone() is None:
        cursor.execute(
            f"INSERT INTO `{GAME_PREFIX}players` (username, passwd, is_privileged) "
            f"VALUES ('gm', 'orga', 1)"
        )
    cursor.execute(f"SELECT id FROM `{GAME_PREFIX}mechanics` LIMIT 1")
    if cursor.fetchone() is None:
        cursor.execute(
            f"INSERT INTO `{GAME_PREFIX}mechanics` (turncounter, gamestate) "
            f"VALUES (0, 0)"
        )
    conn.commit()
    conn.close()
    yield


@pytest.fixture
def logged_in_page(page: Page, base_url):
    """Login as game master (gm/orga) and return the page."""
    page.goto(f"{base_url}/connection/loginForm.php")
    page.wait_for_load_state("networkidle")

    page.locator("input[name='username']").fill("gm")
    page.locator("input[name='passwd']").fill("orga")
    page.locator("input[type='submit']").first.click()
    page.wait_for_load_state("networkidle")

    return page


# ---------------------------------------------------------------------------
# Browser-only tests — work against any running instance
# ---------------------------------------------------------------------------

class TestLoginFlow:
    """Verify the login mechanism works."""

    def test_login_page_loads(self, page: Page, base_url):
        """Login page should be accessible."""
        page.goto(f"{base_url}/connection/loginForm.php")
        expect(page).to_have_title(re.compile(".*", re.IGNORECASE))
        expect(page.locator("input[type='password']")).to_be_visible()

    def test_login_as_gm(self, logged_in_page: Page, base_url):
        """After login, the redirect to accueil.php should fire and the session is authenticated."""
        # Regression: loginForm.php used to echo debug text before header(),
        # breaking the Location redirect when DEBUG was enabled.
        assert "loginForm.php" not in logged_in_page.url, \
            f"Login redirect failed — still on loginForm.php: {logged_in_page.url}"
        assert "accueil.php" in logged_in_page.url, \
            f"Expected redirect to accueil.php, got: {logged_in_page.url}"
        logged_in_page.goto(f"{base_url}/base/admin.php")
        logged_in_page.wait_for_load_state("networkidle")
        expect(logged_in_page.locator("select[name='config_name']")).to_be_visible()


class TestAdminPanel:
    """Test the admin panel accessibility and structure."""

    def test_admin_page_loads(self, logged_in_page: Page, base_url):
        """Admin page should be accessible after login."""
        logged_in_page.goto(f"{base_url}/base/admin.php")
        logged_in_page.wait_for_load_state("networkidle")
        expect(logged_in_page.locator("select[name='config_name']")).to_be_visible()

    def test_config_options_available(self, logged_in_page: Page, base_url):
        """Config dropdown should have the expected scenarios."""
        logged_in_page.goto(f"{base_url}/base/admin.php")
        logged_in_page.wait_for_load_state("networkidle")
        select = logged_in_page.locator("select[name='config_name']")
        options = select.locator("option").all()
        option_values = [opt.get_attribute("value") for opt in options]
        assert "Japon1555SQL" in option_values


# ---------------------------------------------------------------------------
# DB-dependent tests — require local Docker MySQL
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestCSVLoadViaAdmin:
    """Test the full reset + CSV load cycle via the admin UI.

    These tests verify database state after loading, so they require
    direct MySQL access (local Docker only).
    """

    @pytest.fixture(autouse=True)
    def _require_db(self):
        if not DB_AVAILABLE:
            pytest.skip("No local MySQL available — run with Docker or use -m 'not db'")

    def test_full_reset_test_config(self, logged_in_page: Page, base_url):
        """Trigger a full reset with TestConfig and verify DB is populated."""
        logged_in_page.goto(f"{base_url}/base/admin.php")
        logged_in_page.wait_for_load_state("networkidle")

        logged_in_page.locator("select[name='config_name']").select_option("TestConfig")
        logged_in_page.locator("input[name='submit'][value='Submit']").click()
        logged_in_page.wait_for_load_state("networkidle")
        logged_in_page.wait_for_timeout(2000)

        page_html = logged_in_page.content()

        # No PHP warnings should appear on the page
        assert "<b>Warning</b>" not in page_html, \
            "PHP warnings found on page after TestConfig reset"

        # Verify CSV load success messages with correct row counts
        assert "setupTestConfig_worker_origins.csv loaded successfully (3 rows)" in page_html, \
            "Expected worker_origins CSV to load 3 rows"
        assert "setupTestConfig_worker_names.csv loaded successfully (8 rows)" in page_html, \
            "Expected worker_names CSV to load 8 rows"
        assert "setupTestConfig_zones.csv loaded successfully (7 rows)" in page_html, \
            "Expected zones CSV to load 7 rows"
        assert "setupTestConfig_hobbys.csv loaded successfully (13 rows)" in page_html, \
            "Expected hobbys CSV to load 13 rows"

        # Verify DB row counts
        assert table_row_count("worker_origins") >= 3, \
            "TestConfig should load at least 3 worker origins"
        assert table_row_count("worker_names") >= 8, \
            "TestConfig should load at least 8 worker names"
        assert table_row_count("zones") >= 6, \
            "TestConfig should load zones via CSV"
        assert table_row_count("powers") >= 6, \
            "TestConfig should load hobbys into powers"

        # Verify page title and header reflect the loaded scenario
        logged_in_page.goto(f"{base_url}/base/accueil.php")
        logged_in_page.wait_for_load_state("networkidle")
        header_text = logged_in_page.locator("div.header").inner_text()
        assert "Tour" in header_text, \
            f"Header should show turn info after reset, got: {header_text}"

    def test_full_reset_japon1555_sql(self, logged_in_page: Page, base_url):
        """Trigger a full reset with Japon1555SQL and verify larger dataset."""
        logged_in_page.goto(f"{base_url}/base/admin.php")
        logged_in_page.wait_for_load_state("networkidle")

        logged_in_page.locator("select[name='config_name']").select_option("Japon1555SQL")
        logged_in_page.locator("input[name='submit'][value='Submit']").click()
        logged_in_page.wait_for_load_state("networkidle")
        logged_in_page.wait_for_timeout(3000)

        page_html = logged_in_page.content()

        # No PHP warnings should appear on the page
        assert "<b>Warning</b>" not in page_html, \
            "PHP warnings found on page after Japon1555SQL reset"

        # Verify SQL load success messages
        assert "setupJapon1555SQL_zones.sql executed successfully" in page_html, \
            "Expected zones SQL to execute successfully"
        assert "setupJapon1555SQL_worker_names.sql executed successfully" in page_html, \
            "Expected worker_names SQL to execute successfully"
        assert "setupJapon1555SQL_hobbys.sql executed successfully" in page_html, \
            "Expected hobbys SQL to execute successfully"
        assert "setupJapon1555SQL_jobs.sql executed successfully" in page_html, \
            "Expected jobs SQL to execute successfully"

        # Verify DB row counts
        assert table_row_count("worker_origins") >= 10, \
            "Japon1555 should load 13+ origins"
        assert table_row_count("zones") >= 8, \
            "Japon1555 should load 8+ zones"
        assert table_row_count("worker_names") >= 50, \
            "Japon1555 should load 50+ worker names"

        # Verify page title and header reflect Japon1555 scenario
        logged_in_page.goto(f"{base_url}/base/accueil.php")
        logged_in_page.wait_for_load_state("networkidle")
        page_html = logged_in_page.content()
        assert "<b>Warning</b>" not in page_html, \
            "PHP warnings on accueil after Japon1555SQL reset"
        header_text = logged_in_page.locator("div.header").inner_text()
        assert "Shikoku" in header_text or "1555" in header_text, \
            f"Header should reflect Japon1555 scenario, got: {header_text}"

    def test_full_reset_japon1555_csv(self, logged_in_page: Page, base_url):
        """Trigger a full reset with Japon1555CSV and verify CSV-loaded dataset."""
        logged_in_page.goto(f"{base_url}/base/admin.php")
        logged_in_page.wait_for_load_state("networkidle")

        logged_in_page.locator("select[name='config_name']").select_option("Japon1555CSV")
        logged_in_page.locator("input[name='submit'][value='Submit']").click()
        logged_in_page.wait_for_load_state("networkidle")
        logged_in_page.wait_for_timeout(5000)

        page_html = logged_in_page.content()

        # No PHP warnings should appear on the page
        assert "<b>Warning</b>" not in page_html, \
            "PHP warnings found on page after Japon1555CSV reset"

        # Verify CSV load success messages with exact row counts
        assert "setupJapon1555CSV_worker_origins.csv loaded successfully (13 rows)" in page_html, \
            "Expected worker_origins CSV to load 13 rows"
        assert "setupJapon1555CSV_worker_names.csv loaded successfully (122 rows)" in page_html, \
            "Expected worker_names CSV to load 122 rows"
        assert "setupJapon1555CSV_zones.csv loaded successfully (11 rows)" in page_html, \
            "Expected zones CSV to load 11 rows"
        assert "setupJapon1555CSV_hobbys.csv loaded successfully (48 rows)" in page_html, \
            "Expected hobbys CSV to load 48 rows"
        assert "setupJapon1555CSV_jobs.csv loaded successfully (46 rows)" in page_html, \
            "Expected jobs CSV to load 46 rows"
        assert "setupJapon1555CSV_factions.csv loaded successfully (11 rows)" in page_html, \
            "Expected factions CSV to load 11 rows"
        assert "setupJapon1555CSV_locations.csv loaded successfully (48 rows)" in page_html, \
            "Expected locations CSV to load 48 rows"
        assert "setupJapon1555CSV_advanced.csv loaded successfully (9 rows)" in page_html, \
            "Expected advanced workers CSV to load 9 rows"

        # Verify DB row counts
        assert table_row_count("worker_origins") == 13, \
            "Japon1555CSV should load exactly 13 worker origins"
        assert table_row_count("zones") == 11, \
            "Japon1555CSV should load exactly 11 zones"
        assert table_row_count("worker_names") == 122, \
            "Japon1555CSV should load exactly 122 worker names"
        assert table_row_count("factions") == 11, \
            "Japon1555CSV should load exactly 11 factions"
        assert table_row_count("locations") == 48, \
            "Japon1555CSV should load exactly 48 locations"
        # Workers from advanced.csv (9 rows × 1 worker each)
        assert table_row_count("workers") == 9, \
            "Japon1555CSV advanced should create exactly 9 workers"

        # Verify page header reflects Japon1555 scenario
        logged_in_page.goto(f"{base_url}/base/accueil.php")
        logged_in_page.wait_for_load_state("networkidle")
        page_html = logged_in_page.content()
        assert "<b>Warning</b>" not in page_html, \
            "PHP warnings on accueil after Japon1555CSV reset"
        header_text = logged_in_page.locator("div.header").inner_text()
        assert "Shikoku" in header_text or "1555" in header_text, \
            f"Header should reflect Japon1555 scenario, got: {header_text}"

    def test_full_reset_vampire1966_csv(self, logged_in_page: Page, base_url):
        """Trigger a full reset with Vampire1966CSV and verify CSV-loaded dataset."""
        logged_in_page.goto(f"{base_url}/base/admin.php")
        logged_in_page.wait_for_load_state("networkidle")

        logged_in_page.locator("select[name='config_name']").select_option("Vampire1966CSV")
        logged_in_page.locator("input[name='submit'][value='Submit']").click()
        logged_in_page.wait_for_load_state("networkidle")
        logged_in_page.wait_for_timeout(5000)

        page_html = logged_in_page.content()

        # No PHP warnings should appear on the page
        assert "<b>Warning</b>" not in page_html, \
            "PHP warnings found on page after Vampire1966CSV reset"

        # Verify CSV load success messages with exact row counts
        assert "setupVampire1966CSV_worker_origins.csv loaded successfully (12 rows)" in page_html, \
            "Expected worker_origins CSV to load 12 rows"
        assert "setupVampire1966CSV_worker_names.csv loaded successfully (116 rows)" in page_html, \
            "Expected worker_names CSV to load 116 rows"
        assert "setupVampire1966CSV_zones.csv loaded successfully (13 rows)" in page_html, \
            "Expected zones CSV to load 13 rows"
        assert "setupVampire1966CSV_hobbys.csv loaded successfully (53 rows)" in page_html, \
            "Expected hobbys CSV to load 53 rows"
        assert "setupVampire1966CSV_jobs.csv loaded successfully (69 rows)" in page_html, \
            "Expected jobs CSV to load 69 rows"
        assert "setupVampire1966CSV_factions.csv loaded successfully (16 rows)" in page_html, \
            "Expected factions CSV to load 16 rows"
        assert "setupVampire1966CSV_locations.csv loaded successfully (25 rows)" in page_html, \
            "Expected locations CSV to load 25 rows"
        assert "setupVampire1966CSV_advanced.csv loaded successfully (3 rows)" in page_html, \
            "Expected advanced workers CSV to load 3 rows"

        # Verify DB row counts
        assert table_row_count("worker_origins") == 12, \
            "Vampire1966CSV should load exactly 12 worker origins"
        assert table_row_count("zones") == 13, \
            "Vampire1966CSV should load exactly 13 zones"
        assert table_row_count("worker_names") == 116, \
            "Vampire1966CSV should load exactly 116 worker names"
        assert table_row_count("factions") == 16, \
            "Vampire1966CSV should load exactly 16 factions"
        assert table_row_count("locations") == 25, \
            "Vampire1966CSV should load exactly 25 locations"
        # Workers from advanced.csv (3 rows × 1 worker each)
        assert table_row_count("workers") == 3, \
            "Vampire1966CSV advanced should create exactly 3 workers"

        # Verify page header reflects Vampire1966 scenario
        logged_in_page.goto(f"{base_url}/base/accueil.php")
        logged_in_page.wait_for_load_state("networkidle")
        page_html = logged_in_page.content()
        assert "<b>Warning</b>" not in page_html, \
            "PHP warnings on accueil after Vampire1966CSV reset"
        header_text = logged_in_page.locator("div.header").inner_text()
        assert "Firenze" in header_text or "1966" in header_text or "Vampire" in header_text, \
            f"Header should reflect Vampire1966 scenario, got: {header_text}"
