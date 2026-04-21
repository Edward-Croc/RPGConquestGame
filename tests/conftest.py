"""Shared fixtures for RPGConquestGame tests."""
import os
import re
import pymysql
import pytest

# Docker MySQL connection defaults
MYSQL_HOST = os.environ.get("MYSQL_HOST", "127.0.0.1")
MYSQL_PORT = int(os.environ.get("MYSQL_PORT", "3307"))
MYSQL_USER = os.environ.get("MYSQL_USER", "rpg_user")
MYSQL_PASSWORD = os.environ.get("MYSQL_PASSWORD", "rpg_pass")
MYSQL_DB = os.environ.get("MYSQL_DB", "rpgconquestgame")
GAME_PREFIX = "game_test_"

# Path to project root
PROJECT_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CSV_DIR = os.path.join(PROJECT_ROOT, "var", "csv")
SQL_DIR = os.path.join(PROJECT_ROOT, "var", "mysql")

# PHP app URL (Docker)
PHP_BASE_URL = os.environ.get("PHP_BASE_URL", "http://localhost:8080/RPGConquestGameTest")


def ensure_gm_login(page, base_url=None):
    """Login as gm if not already logged in. Skip login if session is active."""
    from playwright.sync_api import Page
    url = base_url or PHP_BASE_URL
    page.goto(f"{url}/base/accueil.php")
    page.wait_for_load_state("networkidle")
    if "loginForm.php" in page.url:
        page.locator("input[name='username']").fill("gm")
        page.locator("input[name='passwd']").fill("orga")
        page.locator("input[type='submit']").first.click()
        page.wait_for_load_state("networkidle")


def _load_minimal_data(prefix=None):
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


@pytest.fixture(scope="session", autouse=True)
def ensure_db_usable_after_tests(browser):
    """Session teardown: replay minimalData.sql, then assert gm can still log in.

    Any test that accidentally wipes minimal rows gets them back here,
    and any breakage of the gm-login flow fails the whole session.
    """
    yield
    if not _load_minimal_data():
        return  # DB unreachable — skip the login assertion too

    # Verify gm can actually log in via the HTTP flow.
    context = browser.new_context()
    page = context.new_page()
    try:
        page.goto(f"{PHP_BASE_URL}/connection/loginForm.php")
        page.wait_for_load_state("networkidle")
        page.locator("input[name='username']").fill("gm")
        page.locator("input[name='passwd']").fill("orga")
        page.locator("input[type='submit']").first.click()
        page.wait_for_load_state("networkidle")
        assert "accueil.php" in page.url, (
            f"Post-suite gm login failed: expected redirect to accueil.php, got {page.url}"
        )
    finally:
        context.close()


@pytest.fixture
def db_connection():
    """Provide a MySQL connection to the test database."""
    conn = pymysql.connect(
        host=MYSQL_HOST,
        port=MYSQL_PORT,
        user=MYSQL_USER,
        password=MYSQL_PASSWORD,
        database=MYSQL_DB,
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
    )
    yield conn
    conn.close()


@pytest.fixture
def clean_tables(db_connection):
    """Clear all data from game tables (preserving structure) before each test."""
    cursor = db_connection.cursor()
    cursor.execute("SET FOREIGN_KEY_CHECKS = 0")
    cursor.execute("SHOW TABLES")
    tables = [list(row.values())[0] for row in cursor.fetchall()]
    for table in tables:
        cursor.execute(f"TRUNCATE TABLE `{table}`")
    cursor.execute("SET FOREIGN_KEY_CHECKS = 1")
    db_connection.commit()
    yield db_connection
    cursor.close()
