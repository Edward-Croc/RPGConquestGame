"""Shared fixtures for RPGConquestGame tests."""
import os
import pymysql
import pytest

# Docker MySQL connection defaults
MYSQL_HOST = os.environ.get("MYSQL_HOST", "127.0.0.1")
MYSQL_PORT = int(os.environ.get("MYSQL_PORT", "3307"))
MYSQL_USER = os.environ.get("MYSQL_USER", "rpg_test")
MYSQL_PASSWORD = os.environ.get("MYSQL_PASSWORD", "rpg_test")
MYSQL_DB = os.environ.get("MYSQL_DB", "rpgconquestgame_test")
GAME_PREFIX = "game2_"

# Path to project root
PROJECT_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CSV_DIR = os.path.join(PROJECT_ROOT, "var", "csv")
SQL_DIR = os.path.join(PROJECT_ROOT, "var", "mysql")

# PHP app URL (Docker)
PHP_BASE_URL = os.environ.get("PHP_BASE_URL", "http://localhost:8080/RPGConquestGame")


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


@pytest.fixture(scope="session", autouse=True)
def ensure_db_usable_after_tests():
    """Ensure gm/orga and mechanics exist after all tests complete."""
    yield
    # Session teardown: ensure gm login works for manual browsing
    try:
        conn = pymysql.connect(
            host=MYSQL_HOST, port=MYSQL_PORT, user=MYSQL_USER,
            password=MYSQL_PASSWORD, database=MYSQL_DB,
            charset="utf8mb4", autocommit=True,
        )
        cursor = conn.cursor()
        cursor.execute(
            f"INSERT IGNORE INTO `{GAME_PREFIX}players` "
            f"(username, passwd, is_privileged) VALUES ('gm', 'orga', 1)"
        )
        cursor.execute(
            f"INSERT IGNORE INTO `{GAME_PREFIX}mechanics` "
            f"(turncounter, gamestate) VALUES (0, 0)"
        )
        conn.close()
    except Exception:
        pass  # DB might not be available


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
