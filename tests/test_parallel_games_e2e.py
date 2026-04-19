"""E2E tests for two games running in parallel in the same MySQL database.

The Docker stack mounts the project source at two paths:
  - http://localhost:8080/RPGConquestGame  → prefix `game2_` (default)
  - http://localhost:8080/RPGConquestGame1 → prefix `game1_` (overlay config)

Both share the same database (`rpgconquestgame`) but use distinct table
prefixes, so their state is fully isolated.

Fixture flow:
  1. Load TestConfig into game2 via /RPGConquestGame/base/admin.php
  2. Load Japon1555SQL into game1 via /RPGConquestGame1/base/admin.php
  3. End-turn in game1 only (via /RPGConquestGame1/mechanics/endTurn.php)

Tests verify:
  - Each game has its own prefixed tables populated with scenario data
  - Cross-game isolation: game1 controllers are not in game2_ tables, etc.
  - End-turn in game1 advances game1's turncounter but NOT game2's
  - Shodoshima island zone loaded in game1 with its associated locations

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


# The second game lives at a sibling folder. If PHP_BASE_URL is overridden,
# preserve the host portion and swap the trailing folder.
PHP_BASE_URL_GAME1 = PHP_BASE_URL.rstrip('/').rsplit('/', 1)[0] + '/RPGConquestGame1'

GAME2_PREFIX = "game2_"
GAME1_PREFIX = "game1_"


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
# Module fixture: load both games + advance only game1
# ---------------------------------------------------------------------------

_snapshot = {}


@pytest.fixture(scope="module", autouse=True)
def parallel_games_scenario(browser):
    """Load different scenarios into game1 and game2, then end-turn in game1 only."""
    if not DB_AVAILABLE:
        yield
        return

    # Ensure gm exists in BOTH prefixes so login works on either URL base.
    # Other test modules may have truncated the players table mid-session
    # (via the `clean_tables` fixture in conftest.py) before this test runs.
    conn = get_db()
    cursor = conn.cursor()
    for prefix in (GAME1_PREFIX, GAME2_PREFIX):
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

    # Load Japon1555SQL into game1 (larger scenario — Shodoshima, 9 workers)
    _login_and_load_scenario(
        browser, PHP_BASE_URL_GAME1, 'Japon1555SQL'
    )

    # Load TestConfig into game2 (baseline test scenario — 26 workers)
    _login_and_load_scenario(
        browser, PHP_BASE_URL, 'TestConfig'
    )

    # Snapshot state AFTER both loaded
    _snapshot['game1_turn_before_endturn'] = _turncounter(GAME1_PREFIX)
    _snapshot['game2_turn_before_endturn'] = _turncounter(GAME2_PREFIX)
    _snapshot['game1_workers_before'] = _count(GAME1_PREFIX, 'workers')
    _snapshot['game2_workers_before'] = _count(GAME2_PREFIX, 'workers')

    # End-turn ONLY in game1 — game2 should be untouched
    _end_turn(browser, PHP_BASE_URL_GAME1)

    # Snapshot state AFTER game1 end-turn
    _snapshot['game1_turn_after'] = _turncounter(GAME1_PREFIX)
    _snapshot['game2_turn_after'] = _turncounter(GAME2_PREFIX)

    yield


# ---------------------------------------------------------------------------
# Tests: both games loaded with distinct scenarios
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestBothGamesLoaded:
    """Each game's prefix has its own tables populated."""

    def test_game1_has_workers(self):
        """Japon1555SQL in game1 loaded 9 workers (Shikoku advanced scenario)."""
        assert _count(GAME1_PREFIX, 'workers') == 9, \
            f"Expected 9 Shikoku workers, got {_count(GAME1_PREFIX, 'workers')}"

    def test_game2_has_workers(self):
        """TestConfig in game2 loaded 26 workers (detection + combat + recruitment)."""
        assert _count(GAME2_PREFIX, 'workers') == 26, \
            f"Expected 26 TestConfig workers, got {_count(GAME2_PREFIX, 'workers')}"

    def test_game1_has_shikoku_zones(self):
        """game1 has Shikoku zones (11 total)."""
        assert _count(GAME1_PREFIX, 'zones') == 11, \
            f"Expected 11 Shikoku zones, got {_count(GAME1_PREFIX, 'zones')}"

    def test_game2_has_testconfig_zones(self):
        """game2 has the 7 TestConfig zones (harmonized Greek names)."""
        assert _count(GAME2_PREFIX, 'zones') == 7, \
            f"Expected 7 TestConfig zones, got {_count(GAME2_PREFIX, 'zones')}"


@pytest.mark.db
class TestShodoshimaInGame1:
    """Specific Shikoku feature: Shodoshima island zone and locations exist."""

    def test_shodoshima_zone_exists(self):
        zones = _zone_names(GAME1_PREFIX)
        assert 'Ile de Shōdoshima' in zones, \
            f"Shodoshima zone missing from game1, got zones: {zones}"

    def test_shodoshima_not_in_game2(self):
        """game2 should NOT have Shodoshima — proves isolation."""
        zones = _zone_names(GAME2_PREFIX)
        assert 'Ile de Shōdoshima' not in zones, \
            "Shodoshima leaked into game2 (isolation broken)"

    def test_shodoshima_has_locations(self):
        """At least 1 location should be tagged to Shodoshima."""
        conn = get_db()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT COUNT(*) AS c FROM `{GAME1_PREFIX}locations` l
            JOIN `{GAME1_PREFIX}zones` z ON z.id = l.zone_id
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

    def test_game1_controllers_not_in_game2(self):
        """Shikoku controllers (e.g. 'Shikoku (四国)') should not appear in game2."""
        g1 = set(_controller_lastnames(GAME1_PREFIX))
        g2 = set(_controller_lastnames(GAME2_PREFIX))
        assert g1 & g2 == set(), \
            f"Controllers leaked between games: intersection={g1 & g2}"

    def test_game2_controllers_not_in_game1(self):
        """TestConfig controllers (Alpha..Golf) should not appear in game1."""
        g1 = set(_controller_lastnames(GAME1_PREFIX))
        g2 = set(_controller_lastnames(GAME2_PREFIX))
        nato = {'Alpha', 'Beta', 'Charlie', 'Delta', 'Echo', 'Foxtrot', 'Golf'}
        assert not (nato & g1), \
            f"TestConfig NATO controllers leaked into game1: {nato & g1}"
        assert nato <= g2, \
            f"game2 should have all NATO controllers: missing {nato - g2}"

    def test_game1_has_shikoku_controller(self):
        """Shikoku controller must be in game1."""
        g1 = _controller_lastnames(GAME1_PREFIX)
        assert 'Shikoku (四国)' in g1, \
            f"Shikoku controller not found in game1: {g1}"

    def test_zone_names_disjoint(self):
        """Zone name sets should be disjoint — each scenario uses distinct names."""
        z1 = set(_zone_names(GAME1_PREFIX))
        z2 = set(_zone_names(GAME2_PREFIX))
        assert z1 & z2 == set(), \
            f"Zone names leaked between games: intersection={z1 & z2}"


# ---------------------------------------------------------------------------
# Tests: end-turn in game1 does not affect game2
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestEndTurnIsolation:
    """End-turn in one game must not change the other game's state."""

    def test_game1_turn_advanced(self):
        """After /RPGConquestGame1/mechanics/endTurn.php, game1 turncounter == 1."""
        before = _snapshot['game1_turn_before_endturn']
        after = _snapshot['game1_turn_after']
        assert before == 0, f"game1 pre-endturn should be 0, got {before}"
        assert after == 1, f"game1 post-endturn should be 1, got {after}"

    def test_game2_turn_unchanged(self):
        """game2 turncounter should NOT change when game1 ends turn."""
        before = _snapshot['game2_turn_before_endturn']
        after = _snapshot['game2_turn_after']
        assert before == after, \
            f"game2 turncounter changed: before={before}, after={after} (isolation broken)"

    def test_game2_worker_count_unchanged(self):
        """game2 workers count should not change."""
        before = _snapshot['game2_workers_before']
        after = _count(GAME2_PREFIX, 'workers')
        assert before == after, \
            f"game2 worker count changed: before={before}, after={after}"

    def test_game1_new_turn_actions_created(self):
        """After game1 end-turn, new worker_actions rows for turn 1 exist in game1."""
        conn = get_db()
        cursor = conn.cursor()
        cursor.execute(
            f"SELECT COUNT(*) AS c FROM `{GAME1_PREFIX}worker_actions` "
            f"WHERE turn_number = 1"
        )
        n = cursor.fetchone()['c']
        conn.close()
        assert n >= 1, f"Expected turn-1 actions in game1, got {n}"

    def test_game2_has_no_turn_1_actions(self):
        """game2 should have no turn-1 rows (its end-turn was never called)."""
        conn = get_db()
        cursor = conn.cursor()
        cursor.execute(
            f"SELECT COUNT(*) AS c FROM `{GAME2_PREFIX}worker_actions` "
            f"WHERE turn_number = 1"
        )
        n = cursor.fetchone()['c']
        conn.close()
        assert n == 0, f"game2 should have no turn-1 actions, got {n}"
