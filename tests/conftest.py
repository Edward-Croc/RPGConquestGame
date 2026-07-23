"""Shared fixtures for RPGConquestGame tests."""
import os
import pymysql
import pytest
import requests

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


def pytest_configure(config):
    """Register custom markers."""
    config.addinivalue_line(
        "markers",
        "expects_errors: test intentionally triggers game_error_log ERROR entries; skip the log-tail assertion.",
    )


# Module-level HTTP session for /base/admin_logs.php queries.
# UI-only per feedback_demo_ui_only: no direct filesystem access.
_admin_logs_session = None
_admin_logs_available = False


def _ensure_admin_logs_session():
    """Login as gm once (session-scoped) and return the shared requests.Session.
    Returns None if login or endpoint is unreachable (fixture will silently skip)."""
    global _admin_logs_session, _admin_logs_available
    if _admin_logs_session is not None:
        return _admin_logs_session if _admin_logs_available else None
    session = requests.Session()
    try:
        session.post(
            f"{PHP_BASE_URL}/connection/loginForm.php",
            data={"username": "gm", "passwd": "orga"},
            allow_redirects=True,
            timeout=10,
        )
        probe = session.get(
            f"{PHP_BASE_URL}/base/admin_logs.php",
            timeout=10,
            allow_redirects=False,
        )
        _admin_logs_available = (probe.status_code == 200)
    except Exception:
        _admin_logs_available = False
    _admin_logs_session = session
    return session if _admin_logs_available else None


def _count_admin_logs_errors():
    """Fetch admin_logs.php filtered by current prefix + ERROR level via UI.
    Returns count of ERROR spans on the rendered page, or None if unreachable."""
    session = _ensure_admin_logs_session()
    if session is None:
        return None
    try:
        response = session.get(
            f"{PHP_BASE_URL}/base/admin_logs.php",
            params={"prefix": GAME_PREFIX, "level": "ERROR"},
            timeout=10,
        )
        if response.status_code != 200:
            return None
        # Each rendered ERROR line is wrapped in <span style="color:#c0392b;">…</span>.
        return response.text.count('style="color:#c0392b')
    except Exception:
        return None


def _count_admin_logs_warnings():
    session = _ensure_admin_logs_session()
    if session is None:
        return None
    try:
        response = session.get(
            f"{PHP_BASE_URL}/base/admin_logs.php",
            params={"prefix": GAME_PREFIX, "level": "WARNING"},
            timeout=10,
        )
        if response.status_code != 200:
            return None
        return response.text.count('style="color:#e67e22')
    except Exception:
        return None


_session_warning_count_start = None


def pytest_sessionstart(session):
    """Snapshot the WARNING count at session start via admin_logs.php UI so
    pytest_terminal_summary can report the session delta."""
    global _session_warning_count_start
    _session_warning_count_start = _count_admin_logs_warnings()


def pytest_terminal_summary(terminalreporter, exitstatus, config):
    """Non-blocking WARNING report at session end via admin_logs.php UI."""
    if _session_warning_count_start is None:
        return
    end_count = _count_admin_logs_warnings()
    if end_count is None or end_count <= _session_warning_count_start:
        return
    delta = end_count - _session_warning_count_start
    terminalreporter.write_sep(
        "=",
        f"WARNING report for '{GAME_PREFIX}' ({delta} new during session)"
    )
    terminalreporter.write_line(
        f"  See {PHP_BASE_URL}/base/admin_logs.php?prefix={GAME_PREFIX}&level=WARNING"
    )

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


@pytest.fixture(autouse=True)
def _assert_no_new_game_log_errors(request):
    """Fail the test when the [GAME_PREFIX] [ERROR] count reported by
    /base/admin_logs.php grows during the test's execution.

    UI-only (respects feedback_demo_ui_only): reads via the admin viewer
    HTTP endpoint, never touches the filesystem. Silently skips when the
    admin viewer is unreachable (Demo without admin auth, endpoint down).

    WARNING and DEBUG entries are ignored. Opt out with
    `@pytest.mark.expects_errors` for tests that intentionally trigger
    ERROR entries to verify error behavior.
    """
    if request.node.get_closest_marker("expects_errors"):
        yield
        return

    pre_count = _count_admin_logs_errors()
    yield
    if pre_count is None:
        return
    post_count = _count_admin_logs_errors()
    if post_count is None or post_count <= pre_count:
        return
    delta = post_count - pre_count
    pytest.fail(
        f"New game_error_log [ERROR] entries during '{request.node.name}' "
        f"(prefix '{GAME_PREFIX}'): +{delta}. "
        f"See {PHP_BASE_URL}/base/admin_logs.php?prefix={GAME_PREFIX}&level=ERROR "
        f"(or mark @pytest.mark.expects_errors if intentional)."
    )


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
