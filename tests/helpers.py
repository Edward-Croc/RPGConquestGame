"""Shared helpers for RPGConquestGame test suite.

Extracts exact-copy duplicated functions from individual test modules:
  - DB connection + worker/controller ID lookups
  - Login / logout page navigation
  - End-turn trigger (caller handles login)
  - Scenario load via admin UI (uses a fresh browser context)
  - Minimal-data replay (seeds gm, config rows, power_types, mechanics)

The DB_AVAILABLE flag is computed once at import time so test modules
can use it for `pytest.skip` guards without repeating the probe.
"""
import os
import re
import pymysql
from playwright.sync_api import Page

from conftest import (
    GAME_PREFIX, MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB,
    PHP_BASE_URL, SQL_DIR,
)


# ---------------------------------------------------------------------------
# DB availability probe (runs once at module import)
# ---------------------------------------------------------------------------

DB_AVAILABLE = False
try:
    _probe = pymysql.connect(
        host=MYSQL_HOST, port=MYSQL_PORT, user=MYSQL_USER,
        password=MYSQL_PASSWORD, database=MYSQL_DB, connect_timeout=3,
    )
    _probe.close()
    DB_AVAILABLE = True
except Exception:
    pass


# ---------------------------------------------------------------------------
# DB connection + lookup helpers
# ---------------------------------------------------------------------------

def get_db_connection():
    """Open a new pymysql connection with DictCursor + utf8mb4."""
    return pymysql.connect(
        host=MYSQL_HOST, port=MYSQL_PORT, user=MYSQL_USER,
        password=MYSQL_PASSWORD, database=MYSQL_DB,
        charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor,
    )


def get_worker_id(lastname):
    """Return the id of a worker by lastname, or None if not found."""
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute(
        f"SELECT id FROM `{GAME_PREFIX}workers` WHERE lastname = %s",
        (lastname,),
    )
    row = cursor.fetchone()
    conn.close()
    return row['id'] if row else None


def get_controller_id(lastname):
    """Return the id of a controller by lastname, or None if not found."""
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute(
        f"SELECT id FROM `{GAME_PREFIX}controllers` WHERE lastname = %s",
        (lastname,),
    )
    row = cursor.fetchone()
    conn.close()
    return row['id'] if row else None


def load_minimal_data(prefix=None):
    """Replay var/mysql/minimalData.sql against the DB to reinstate the
    minimal invariants (gm user, default config rows, starting mechanics
    row, fixed power types). Safe to call anytime — all statements are
    idempotent via INSERT IGNORE / WHERE NOT EXISTS.

    Returns True on success, False if the DB is unreachable.
    """
    prefix = prefix or GAME_PREFIX
    minimal_path = os.path.join(SQL_DIR, "minimalData.sql")
    if not os.path.exists(minimal_path):
        return False
    try:
        sql = open(minimal_path, encoding="utf-8").read().replace("{prefix}", prefix)
        # Strip line comments (-- ...) so they don't confuse statement splitting
        sql = re.sub(r"--[^\n]*\n", "\n", sql)
        conn = pymysql.connect(
            host=MYSQL_HOST, port=MYSQL_PORT, user=MYSQL_USER,
            password=MYSQL_PASSWORD, database=MYSQL_DB,
            charset="utf8mb4", autocommit=True,
        )
        cursor = conn.cursor()
        for stmt in sql.split(";"):
            stmt = stmt.strip()
            if stmt:
                cursor.execute(stmt)
        conn.close()
        return True
    except pymysql.err.OperationalError:
        return False


# ---------------------------------------------------------------------------
# Page navigation helpers
# ---------------------------------------------------------------------------

def login_as(page: Page, base_url: str, username: str, password: str):
    """Navigate to the login form and submit the given credentials."""
    page.goto(f"{base_url}/connection/loginForm.php")
    page.wait_for_load_state("networkidle")
    page.locator("input[name='username']").fill(username)
    page.locator("input[name='passwd']").fill(password)
    page.locator("input[type='submit']").first.click()
    page.wait_for_load_state("networkidle")


def logout(page: Page, base_url: str):
    """Navigate to the logout endpoint."""
    page.goto(f"{base_url}/connection/logout.php")
    page.wait_for_load_state("networkidle")


def end_turn(page: Page, base_url: str = None):
    """Trigger end-of-turn. Page must already be logged in.

    Asserts no PHP warning or fatal error in the rendered response.
    """
    url = base_url or PHP_BASE_URL
    page.goto(f"{url}/mechanics/endTurn.php")
    page.wait_for_load_state("load", timeout=120000)
    html = page.content()
    assert "<b>Warning</b>" not in html, "PHP warning during end turn"
    assert "<b>Fatal error</b>" not in html, "PHP fatal error during end turn"


def load_scenario_via_admin(browser, base_url: str, scenario_name: str):
    """Login as gm in a fresh context and load the named scenario via admin.php.

    Uses its own browser context so it does not disturb the caller's page.
    """
    context = browser.new_context()
    page = context.new_page()
    login_as(page, base_url, "gm", "orga")
    page.goto(f"{base_url}/base/admin.php")
    page.wait_for_load_state("networkidle")
    page.locator("select[name='config_name']").select_option(scenario_name)
    page.locator("input[name='submit'][value='Submit']").click()
    page.wait_for_timeout(5000)
    page.wait_for_load_state("load", timeout=120000)
    context.close()
