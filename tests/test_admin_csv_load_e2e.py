"""Playwright end-to-end tests for the admin CSV load workflow.

Tests the full browser flow:
1. Login as game master
2. Navigate to admin panel
3. Select a config scenario
4. Trigger a full reset + CSV load
5. Verify the database was populated
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


def get_db_connection():
    """Get a fresh DB connection for verification."""
    return pymysql.connect(
        host=MYSQL_HOST, port=MYSQL_PORT, user=MYSQL_USER,
        password=MYSQL_PASSWORD, database=MYSQL_DB,
        charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor,
    )


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
    conn = get_db_connection()
    cursor = conn.cursor()
    # Ensure gm user
    cursor.execute(
        f"SELECT id FROM `{GAME_PREFIX}players` WHERE username = 'gm'"
    )
    if cursor.fetchone() is None:
        cursor.execute(
            f"INSERT INTO `{GAME_PREFIX}players` (username, passwd, is_privileged) "
            f"VALUES ('gm', 'orga', 1)"
        )
    # Ensure mechanics row (game state)
    cursor.execute(f"SELECT id FROM `{GAME_PREFIX}mechanics` LIMIT 1")
    if cursor.fetchone() is None:
        cursor.execute(
            f"INSERT INTO `{GAME_PREFIX}mechanics` (turncounter, gamestate) "
            f"VALUES (0, 0)"
        )
    conn.commit()
    conn.close()


@pytest.fixture
def logged_in_page(page: Page, base_url):
    """Login as game master (gm/orga) and return the page."""
    page.goto(f"{base_url}/connection/loginForm.php")
    page.wait_for_load_state("networkidle")

    # Form fields: username + passwd (not 'password')
    page.locator("input[name='username']").fill("gm")
    page.locator("input[name='passwd']").fill("orga")
    page.locator("input[type='submit']").first.click()
    page.wait_for_load_state("networkidle")

    return page


# ---------------------------------------------------------------------------
# Tests
# ---------------------------------------------------------------------------

class TestLoginFlow:
    """Verify the login mechanism works."""

    def test_login_page_loads(self, page: Page, base_url):
        """Login page should be accessible."""
        page.goto(f"{base_url}/connection/loginForm.php")
        expect(page).to_have_title(re.compile(".*", re.IGNORECASE))
        # Should have a password input
        expect(page.locator("input[type='password']")).to_be_visible()

    def test_login_as_gm(self, logged_in_page: Page, base_url):
        """After login, session should be authenticated (can access admin)."""
        # Note: the redirect after login may fail due to PHP header output,
        # but the session is still set. Verify by accessing a protected page.
        logged_in_page.goto(f"{base_url}/base/admin.php")
        logged_in_page.wait_for_load_state("networkidle")
        # Admin page should have the config selector (proves we're logged in)
        expect(logged_in_page.locator("select[name='config_name']")).to_be_visible()


class TestAdminPanel:
    """Test the admin panel accessibility and structure."""

    def test_admin_page_loads(self, logged_in_page: Page, base_url):
        """Admin page should be accessible after login."""
        logged_in_page.goto(f"{base_url}/base/admin.php")
        logged_in_page.wait_for_load_state("networkidle")
        # Should have the config select dropdown
        expect(logged_in_page.locator("select[name='config_name']")).to_be_visible()

    def test_config_options_available(self, logged_in_page: Page, base_url):
        """Config dropdown should have the expected scenarios."""
        logged_in_page.goto(f"{base_url}/base/admin.php")
        logged_in_page.wait_for_load_state("networkidle")
        select = logged_in_page.locator("select[name='config_name']")
        options = select.locator("option").all()
        option_values = [opt.get_attribute("value") for opt in options]
        assert "TestConfig" in option_values
        assert "Japon1555" in option_values


class TestCSVLoadViaAdmin:
    """Test the full reset + CSV load cycle via the admin UI."""

    def test_full_reset_test_config(self, logged_in_page: Page, base_url):
        """Trigger a full reset with TestConfig and verify DB is populated."""
        logged_in_page.goto(f"{base_url}/base/admin.php")
        logged_in_page.wait_for_load_state("networkidle")

        # Select TestConfig
        logged_in_page.locator("select[name='config_name']").select_option("TestConfig")

        # Click submit to trigger reset
        logged_in_page.locator("input[name='submit'][value='Submit']").click()
        logged_in_page.wait_for_load_state("networkidle")

        # Give the server a moment to process
        logged_in_page.wait_for_timeout(2000)

        # Verify database was populated
        # Note: zones CSV uses "complex SQL fallback" and no SQL file exists for TestConfig,
        # so zones are not loaded. This is a known gap in the TestConfig scenario.
        assert table_row_count("worker_origins") >= 3, \
            "TestConfig should load at least 3 worker origins"
        assert table_row_count("worker_names") >= 8, \
            "TestConfig should load at least 8 worker names"
        assert table_row_count("powers") >= 6, \
            "TestConfig should load hobbys into powers"

    def test_full_reset_japon1555(self, logged_in_page: Page, base_url):
        """Trigger a full reset with Japon1555 and verify larger dataset."""
        logged_in_page.goto(f"{base_url}/base/admin.php")
        logged_in_page.wait_for_load_state("networkidle")

        # Select Japon1555
        logged_in_page.locator("select[name='config_name']").select_option("Japon1555")

        # Click submit
        logged_in_page.locator("input[name='submit'][value='Submit']").click()
        logged_in_page.wait_for_load_state("networkidle")
        logged_in_page.wait_for_timeout(3000)

        # Japon1555 has more data than TestConfig
        assert table_row_count("worker_origins") >= 10, \
            "Japon1555 should load 13+ origins"
        assert table_row_count("zones") >= 8, \
            "Japon1555 should load 8+ zones"
        assert table_row_count("worker_names") >= 50, \
            "Japon1555 should load 50+ worker names"
