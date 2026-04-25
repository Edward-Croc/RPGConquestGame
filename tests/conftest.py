"""Shared fixtures for RPGConquestGame tests."""
import os
import pymysql
import pytest

# Docker MySQL connection defaults (override via env for alt setups)
MYSQL_HOST = os.environ.get("MYSQL_HOST", "127.0.0.1")
MYSQL_PORT = int(os.environ.get("MYSQL_PORT", "3307"))
MYSQL_USER = os.environ.get("MYSQL_USER", "rpg_user")
MYSQL_PASSWORD = os.environ.get("MYSQL_PASSWORD", "rpg_pass")
MYSQL_DB = os.environ.get("MYSQL_DB", "rpgconquestgame")
GAME_PREFIX = os.environ.get("GAME_PREFIX", "game_test_")

# UI-only mode skips any test marked @pytest.mark.db.
# Intended for running the suite against a remote (production) deployment
# where direct MySQL access isn't available — tests then rely exclusively
# on the HTTP/UI path.
UI_ONLY = os.environ.get("UI_ONLY", "").lower() in ("1", "true", "yes")


def pytest_collection_modifyitems(config, items):
    """Auto-skip @pytest.mark.db tests when UI_ONLY=1 is set."""
    if not UI_ONLY:
        return
    skip_db = pytest.mark.skip(reason="UI_ONLY=1 — test requires direct DB access")
    for item in items:
        if "db" in item.keywords:
            item.add_marker(skip_db)

# Path to project root
PROJECT_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CSV_DIR = os.path.join(PROJECT_ROOT, "var", "csv")
SQL_DIR = os.path.join(PROJECT_ROOT, "var", "mysql")

# PHP app URL (Docker)
PHP_BASE_URL = os.environ.get("PHP_BASE_URL", "http://localhost:8080/RPGConquestGameTest")


def ensure_gm_login(page, base_url=None):
    """Login as gm if not already logged in. Skip login if session is active."""
    from helpers import safe_goto
    url = base_url or PHP_BASE_URL
    safe_goto(page, f"{url}/base/accueil.php", wait_state="networkidle")
    if "loginForm.php" in page.url:
        page.locator("input[name='username']").fill("gm")
        page.locator("input[name='passwd']").fill("orga")
        page.locator("input[type='submit']").first.click()
        page.wait_for_load_state("networkidle")


@pytest.fixture(autouse=True)
def _php_error_guard(request):
    """Belt-and-buckle PHP error detection: register a response listener
    on the page fixture (if the test uses one) and assert no errors at
    teardown. Catches click-driven navigations that bypass safe_goto."""
    if 'page' not in request.fixturenames:
        yield
        return
    from helpers import register_php_error_listener, assert_no_collected_php_errors
    page = request.getfixturevalue('page')
    register_php_error_listener(page)
    yield
    assert_no_collected_php_errors(page)


@pytest.fixture(scope="session", autouse=True)
def ensure_db_usable_after_tests(browser):
    """Session teardown: replay minimalData.sql, then assert gm can still log in.

    Any test that accidentally wipes minimal rows gets them back here,
    and any breakage of the gm-login flow fails the whole session.
    """
    from helpers import load_minimal_data
    yield
    if not load_minimal_data():
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
