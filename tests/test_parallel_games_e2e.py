"""E2E tests for two games running in parallel in the same MySQL database.

The Docker stack mounts the project source at two paths:
  - http://localhost:8080/RPGConquestGameTest  → prefix `game_test_`  (primary / default)
  - http://localhost:8080/RPGConquestGameTest2 → prefix `game_test2_` (secondary / overlay config)

Both share the same database (`rpgconquestgame`) but use distinct table
prefixes, so their state is fully isolated.

Fixture flow:
  1. Load TestConfig into the primary game via /RPGConquestGameTest/base/admin.php
  2. Load Japon1555SQL into the secondary game via /RPGConquestGameTest2/base/admin.php
  3. End-turn in the secondary game only (via /RPGConquestGameTest2/mechanics/endTurn.php)

Tests verify:
  - Each game has its own prefixed tables populated with scenario data
  - Cross-game isolation: secondary controllers are not in primary tables, etc.
  - End-turn in secondary advances secondary's turncounter but NOT primary's
  - Shodoshima island zone loaded in the secondary game with its associated locations

Run:
    python3 -m pytest tests/test_parallel_games_e2e.py -v
"""
import pymysql
import pytest
from playwright.sync_api import Page

from conftest import (
    MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB,
    PHP_BASE_URL, ensure_gm_login,
)


# The secondary game lives at a sibling folder. If PHP_BASE_URL is overridden,
# preserve the host portion and swap the trailing folder.
PHP_BASE_URL_SECONDARY = PHP_BASE_URL.rstrip('/').rsplit('/', 1)[0] + '/RPGConquestGameTest2'

PRIMARY_PREFIX = "game_test_"
SECONDARY_PREFIX = "game_test2_"


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


def get_db():
    return pymysql.connect(
        host=MYSQL_HOST, port=MYSQL_PORT, user=MYSQL_USER,
        password=MYSQL_PASSWORD, database=MYSQL_DB,
        charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor,
    )


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(autouse=True)
def _require_db():
    if not DB_AVAILABLE:
        pytest.skip("No local MySQL available")


# ---------------------------------------------------------------------------
# DB helpers
# ---------------------------------------------------------------------------

def _count(prefix, table):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute(f"SELECT COUNT(*) AS c FROM `{prefix}{table}`")
    n = cursor.fetchone()['c']
    conn.close()
    return n


def _turncounter(prefix):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute(f"SELECT turncounter FROM `{prefix}mechanics` LIMIT 1")
    row = cursor.fetchone()
    conn.close()
    return row['turncounter'] if row else None


def _controller_lastnames(prefix):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute(f"SELECT lastname FROM `{prefix}controllers` ORDER BY lastname")
    result = [r['lastname'] for r in cursor.fetchall()]
    conn.close()
    return result


def _zone_names(prefix):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute(f"SELECT name FROM `{prefix}zones` ORDER BY name")
    result = [r['name'] for r in cursor.fetchall()]
    conn.close()
    return result


# ---------------------------------------------------------------------------
# UI helpers
# ---------------------------------------------------------------------------

def _login_and_load_scenario(browser, url_base, scenario):
    """Login as gm under the given URL base and load the given scenario."""
    context = browser.new_context()
    page = context.new_page()
    page.goto(f"{url_base}/connection/loginForm.php")
    page.wait_for_load_state("networkidle")
    page.locator("input[name='username']").fill("gm")
    page.locator("input[name='passwd']").fill("orga")
    page.locator("input[type='submit']").first.click()
    page.wait_for_load_state("networkidle")
    page.goto(f"{url_base}/base/admin.php")
    page.wait_for_load_state("networkidle")
    page.locator("select[name='config_name']").select_option(scenario)
    page.locator("input[name='submit'][value='Submit']").click()
    page.wait_for_timeout(5000)
    page.wait_for_load_state("load", timeout=120000)
    context.close()


def _end_turn(browser, url_base):
    """Trigger end-of-turn via the given URL base."""
    context = browser.new_context()
    page = context.new_page()
    page.goto(f"{url_base}/connection/loginForm.php")
    page.wait_for_load_state("networkidle")
    page.locator("input[name='username']").fill("gm")
    page.locator("input[name='passwd']").fill("orga")
    page.locator("input[type='submit']").first.click()
    page.wait_for_load_state("networkidle")
    page.goto(f"{url_base}/mechanics/endTurn.php")
    page.wait_for_load_state("load", timeout=120000)
    html = page.content()
    assert "<b>Warning</b>" not in html, "PHP warning during end turn"
    assert "<b>Fatal error</b>" not in html, "PHP fatal error during end turn"
    context.close()


# ---------------------------------------------------------------------------
# Module fixture: load both games + advance only the secondary
# ---------------------------------------------------------------------------

_snapshot = {}


@pytest.fixture(scope="module", autouse=True)
def parallel_games_scenario(browser):
    """Load different scenarios into primary and secondary, then end-turn in secondary only."""
    if not DB_AVAILABLE:
        yield
        return

    # Ensure gm exists in BOTH prefixes so login works on either URL base.
    # Other test modules may have truncated the players table mid-session
    # (via the `clean_tables` fixture in conftest.py) before this test runs.
    conn = get_db()
    cursor = conn.cursor()
    for prefix in (SECONDARY_PREFIX, PRIMARY_PREFIX):
        cursor.execute(
            f"INSERT IGNORE INTO `{prefix}players` "
            f"(username, passwd, is_privileged) VALUES ('gm', 'orga', 1)"
        )
        cursor.execute(
            f"INSERT IGNORE INTO `{prefix}mechanics` "
            f"(turncounter, gamestate) VALUES (0, 0)"
        )
    conn.commit()
    conn.close()

    # Load Japon1555SQL into secondary (larger scenario — Shodoshima, 9 workers)
    _login_and_load_scenario(
        browser, PHP_BASE_URL_SECONDARY, 'Japon1555SQL'
    )

    # Load TestConfig into primary (baseline test scenario — 26 workers)
    _login_and_load_scenario(
        browser, PHP_BASE_URL, 'TestConfig'
    )

    # Snapshot state AFTER both loaded
    _snapshot['secondary_turn_before_endturn'] = _turncounter(SECONDARY_PREFIX)
    _snapshot['primary_turn_before_endturn'] = _turncounter(PRIMARY_PREFIX)
    _snapshot['secondary_workers_before'] = _count(SECONDARY_PREFIX, 'workers')
    _snapshot['primary_workers_before'] = _count(PRIMARY_PREFIX, 'workers')

    # End-turn ONLY in secondary — primary should be untouched
    _end_turn(browser, PHP_BASE_URL_SECONDARY)

    # Snapshot state AFTER secondary end-turn
    _snapshot['secondary_turn_after'] = _turncounter(SECONDARY_PREFIX)
    _snapshot['primary_turn_after'] = _turncounter(PRIMARY_PREFIX)

    yield


# ---------------------------------------------------------------------------
# Tests: both games loaded with distinct scenarios
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestBothGamesLoaded:
    """Each game's prefix has its own tables populated."""

    def test_secondary_has_workers(self):
        """Japon1555SQL in secondary loaded 9 workers (Shikoku advanced scenario)."""
        assert _count(SECONDARY_PREFIX, 'workers') == 9, \
            f"Expected 9 Shikoku workers, got {_count(SECONDARY_PREFIX, 'workers')}"

    def test_primary_has_workers(self):
        """TestConfig in primary loaded 26 workers (detection + combat + recruitment)."""
        assert _count(PRIMARY_PREFIX, 'workers') == 26, \
            f"Expected 26 TestConfig workers, got {_count(PRIMARY_PREFIX, 'workers')}"

    def test_secondary_has_shikoku_zones(self):
        """Secondary has Shikoku zones (11 total)."""
        assert _count(SECONDARY_PREFIX, 'zones') == 11, \
            f"Expected 11 Shikoku zones, got {_count(SECONDARY_PREFIX, 'zones')}"

    def test_primary_has_testconfig_zones(self):
        """Primary has the 7 TestConfig zones (harmonized Greek names)."""
        assert _count(PRIMARY_PREFIX, 'zones') == 7, \
            f"Expected 7 TestConfig zones, got {_count(PRIMARY_PREFIX, 'zones')}"


@pytest.mark.db
class TestShodoshimaInSecondary:
    """Specific Shikoku feature: Shodoshima island zone and locations exist."""

    def test_shodoshima_zone_exists(self):
        zones = _zone_names(SECONDARY_PREFIX)
        assert 'Ile de Shōdoshima' in zones, \
            f"Shodoshima zone missing from secondary game, got zones: {zones}"

    def test_shodoshima_not_in_primary(self):
        """Primary should NOT have Shodoshima — proves isolation."""
        zones = _zone_names(PRIMARY_PREFIX)
        assert 'Ile de Shōdoshima' not in zones, \
            "Shodoshima leaked into primary (isolation broken)"

    def test_shodoshima_has_locations(self):
        """At least 1 location should be tagged to Shodoshima."""
        conn = get_db()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT COUNT(*) AS c FROM `{SECONDARY_PREFIX}locations` l
            JOIN `{SECONDARY_PREFIX}zones` z ON z.id = l.zone_id
            WHERE z.name = 'Ile de Shōdoshima'
        """)
        n = cursor.fetchone()['c']
        conn.close()
        assert n >= 1, f"Expected at least 1 location in Shodoshima, got {n}"


# ---------------------------------------------------------------------------
# Tests: cross-game isolation
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestGameIsolation:
    """Verify that each game's data is isolated in its own prefixed tables."""

    def test_secondary_controllers_not_in_primary(self):
        """Shikoku controllers (e.g. 'Shikoku (四国)') should not appear in primary."""
        secondary = set(_controller_lastnames(SECONDARY_PREFIX))
        primary = set(_controller_lastnames(PRIMARY_PREFIX))
        assert secondary & primary == set(), \
            f"Controllers leaked between games: intersection={secondary & primary}"

    def test_primary_controllers_not_in_secondary(self):
        """TestConfig controllers (Alpha..Golf) should not appear in secondary."""
        secondary = set(_controller_lastnames(SECONDARY_PREFIX))
        primary = set(_controller_lastnames(PRIMARY_PREFIX))
        nato = {'Alpha', 'Beta', 'Charlie', 'Delta', 'Echo', 'Foxtrot', 'Golf'}
        assert not (nato & secondary), \
            f"TestConfig NATO controllers leaked into secondary: {nato & secondary}"
        assert nato <= primary, \
            f"Primary should have all NATO controllers: missing {nato - primary}"

    def test_secondary_has_shikoku_controller(self):
        """Shikoku controller must be in secondary."""
        secondary = _controller_lastnames(SECONDARY_PREFIX)
        assert 'Shikoku (四国)' in secondary, \
            f"Shikoku controller not found in secondary: {secondary}"

    def test_zone_names_disjoint(self):
        """Zone name sets should be disjoint — each scenario uses distinct names."""
        z_secondary = set(_zone_names(SECONDARY_PREFIX))
        z_primary = set(_zone_names(PRIMARY_PREFIX))
        assert z_secondary & z_primary == set(), \
            f"Zone names leaked between games: intersection={z_secondary & z_primary}"


# ---------------------------------------------------------------------------
# Tests: end-turn in secondary does not affect primary
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestEndTurnIsolation:
    """End-turn in one game must not change the other game's state."""

    def test_secondary_turn_advanced(self):
        """After /RPGConquestGameTest2/mechanics/endTurn.php, secondary turncounter == 1."""
        before = _snapshot['secondary_turn_before_endturn']
        after = _snapshot['secondary_turn_after']
        assert before == 0, f"Secondary pre-endturn should be 0, got {before}"
        assert after == 1, f"Secondary post-endturn should be 1, got {after}"

    def test_primary_turn_unchanged(self):
        """Primary turncounter should NOT change when secondary ends turn."""
        before = _snapshot['primary_turn_before_endturn']
        after = _snapshot['primary_turn_after']
        assert before == after, \
            f"Primary turncounter changed: before={before}, after={after} (isolation broken)"

    def test_primary_worker_count_unchanged(self):
        """Primary workers count should not change."""
        before = _snapshot['primary_workers_before']
        after = _count(PRIMARY_PREFIX, 'workers')
        assert before == after, \
            f"Primary worker count changed: before={before}, after={after}"

    def test_secondary_new_turn_actions_created(self):
        """After secondary end-turn, new worker_actions rows for turn 1 exist in secondary."""
        conn = get_db()
        cursor = conn.cursor()
        cursor.execute(
            f"SELECT COUNT(*) AS c FROM `{SECONDARY_PREFIX}worker_actions` "
            f"WHERE turn_number = 1"
        )
        n = cursor.fetchone()['c']
        conn.close()
        assert n >= 1, f"Expected turn-1 actions in secondary, got {n}"

    def test_primary_has_no_turn_1_actions(self):
        """Primary should have no turn-1 rows (its end-turn was never called)."""
        conn = get_db()
        cursor = conn.cursor()
        cursor.execute(
            f"SELECT COUNT(*) AS c FROM `{PRIMARY_PREFIX}worker_actions` "
            f"WHERE turn_number = 1"
        )
        n = cursor.fetchone()['c']
        conn.close()
        assert n == 0, f"Primary should have no turn-1 actions, got {n}"
