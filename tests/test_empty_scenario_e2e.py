"""Playwright end-to-end tests for edge cases: empty scenario.

Tests that the game handles scenarios where no agents find any
locations and no agents find any other agents (all agents on the
same controller, or no locations in the zone).

Run:
    python3 -m pytest tests/test_empty_scenario_e2e.py -v
"""
import pymysql
import pytest
from playwright.sync_api import Page

from conftest import (
    GAME_PREFIX, MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB,
    PHP_BASE_URL, ensure_gm_login,
)

from helpers import DB_AVAILABLE, get_db_connection, load_minimal_data, ui_turn_counter


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(autouse=True)
def _require_db():
    if not DB_AVAILABLE:
        pytest.skip("No local MySQL available")


# ---------------------------------------------------------------------------
# Module fixture: load TestConfig, remove locations and isolate agents
# ---------------------------------------------------------------------------

@pytest.fixture(scope="module", autouse=True)
def load_empty_scenario(browser):
    """Load TestConfig then remove all locations and put all agents
    on the same controller so no detection or location discovery occurs."""
    if not DB_AVAILABLE:
        yield
        return

    load_minimal_data()

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
    page.locator("input[name='submit'][value='Submit']").click()
    page.wait_for_timeout(5000)
    page.wait_for_load_state("load", timeout=90000)

    # Remove all locations so locationSearchMechanic finds nothing.
    # Artefacts FK-reference locations, so delete them first.
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute(f"DELETE FROM `{GAME_PREFIX}artefacts`")
    cursor.execute(f"DELETE FROM `{GAME_PREFIX}locations`")
    # Move all agents to the same controller so investigateMechanic finds no enemies
    # Must update both controller_worker AND worker_actions.controller_id
    cursor.execute(
        f"SET @alpha_id = (SELECT id FROM `{GAME_PREFIX}controllers` WHERE lastname = 'Alpha' LIMIT 1)"
    )
    cursor.execute(
        f"UPDATE `{GAME_PREFIX}controller_worker` SET controller_id = @alpha_id"
    )
    cursor.execute(
        f"UPDATE `{GAME_PREFIX}worker_actions` SET controller_id = @alpha_id"
    )
    conn.commit()
    conn.close()

    # Trigger end turn
    page.goto(f"{PHP_BASE_URL}/mechanics/endTurn.php")
    page.wait_for_load_state("load", timeout=90000)

    context.close()
    yield


# ---------------------------------------------------------------------------
# Tests
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestEmptyDetectionResults:
    """When all agents are on the same controller, no detections should occur."""

    def test_no_agent_detections(self):
        """controllers_known_enemies should be empty."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"SELECT COUNT(*) AS c FROM `{GAME_PREFIX}controllers_known_enemies`")
        count = cursor.fetchone()['c']
        conn.close()
        assert count == 0, \
            f"Expected 0 detections (all agents same controller), got {count}"

    def test_no_location_discoveries(self):
        """controller_known_locations should be empty."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"SELECT COUNT(*) AS c FROM `{GAME_PREFIX}controller_known_locations`")
        count = cursor.fetchone()['c']
        conn.close()
        assert count == 0, \
            f"Expected 0 location discoveries (no locations exist), got {count}"


@pytest.mark.db
class TestEndTurnCompletesWithEmptyResults:
    """End turn should complete successfully even with nothing to find."""

    def test_turn_counter_incremented(self, page: Page, base_url):
        """Turn counter should still advance. Scraped from page header."""
        ensure_gm_login(page, base_url)
        turn = ui_turn_counter(page, base_url=base_url)
        assert turn >= 1, f"Turn counter should be >= 1, got {turn}"

    def test_new_turn_actions_created(self):
        """New worker_action rows should exist for the next turn."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(
            f"SELECT COUNT(*) AS c FROM `{GAME_PREFIX}worker_actions` WHERE turn_number = 1"
        )
        count = cursor.fetchone()['c']
        conn.close()
        assert count >= 1, \
            f"Expected action rows for turn 1, got {count}"

    def test_agent_reports_empty_investigations(self):
        """Agent reports should have investigation section but no detected agents."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT w.lastname, wa.report
            FROM `{GAME_PREFIX}worker_actions` wa
            JOIN `{GAME_PREFIX}workers` w ON w.id = wa.worker_id
            WHERE wa.turn_number = 0
            ORDER BY w.lastname
        """)
        for r in cursor.fetchall():
            report = str(r['report'] or '')
            # No other agents should be mentioned (all on same controller)
            assert 'Agent' not in report or r['lastname'] in report, \
                f"{r['lastname']} report should not mention other agents: {report[:100]}"
        conn.close()
