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
from helpers import (
    DB_AVAILABLE, get_db_connection as get_db,
    login_as, end_turn, load_scenario_via_admin, load_minimal_data,
    ui_worker_count, ui_zone_names, ui_all_controllers,
    register_php_error_listener, assert_no_collected_php_errors,
    csv_row_count,
)


# The secondary game lives at a sibling folder. If PHP_BASE_URL is overridden,
# preserve the host portion and swap the trailing folder.
PHP_BASE_URL_SECONDARY = PHP_BASE_URL.rstrip('/').rsplit('/', 1)[0] + '/RPGConquestGameTest2'

PRIMARY_PREFIX = "game_test_"
SECONDARY_PREFIX = "game_test2_"


# Probe the secondary URL at module load. If it doesn't serve a login page,
# skip the whole module — the parallel-games setup requires both URLs, and a
# target like the production demo typically only hosts one game path.
def _secondary_reachable():
    import urllib.request
    try:
        with urllib.request.urlopen(
            f"{PHP_BASE_URL_SECONDARY}/connection/loginForm.php", timeout=5
        ) as r:
            body = r.read().decode("utf-8", "replace")
            return 'name="username"' in body
    except Exception:
        return False


if not _secondary_reachable():
    pytest.skip(
        f"Parallel-games setup unavailable: {PHP_BASE_URL_SECONDARY} "
        f"does not serve a login form. These tests require a dual-mount "
        f"Docker Compose setup (local). Skipping module.",
        allow_module_level=True,
    )


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


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

def _end_turn_fresh_context(browser, url_base):
    """End turn via a fresh browser context (used for parallel-games isolation)."""
    context = browser.new_context()
    page = context.new_page()
    register_php_error_listener(page)
    login_as(page, url_base, "gm", "orga")
    end_turn(page, url_base)
    assert_no_collected_php_errors(page)
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

    # Seed minimal data in BOTH prefixes so login + scenario load work on either URL base.
    # Other test modules may have truncated the players table mid-session
    # (via the `clean_tables` fixture in conftest.py) before this test runs.
    for prefix in (SECONDARY_PREFIX, PRIMARY_PREFIX):
        load_minimal_data(prefix)

    # Load Japon1555SQL into secondary (larger scenario — Shodoshima, 9 workers)
    load_scenario_via_admin(browser, PHP_BASE_URL_SECONDARY, 'Japon1555SQL')

    # Load TestConfig into primary (baseline test scenario — 26 workers)
    load_scenario_via_admin(browser, PHP_BASE_URL, 'TestConfig')

    # Snapshot state AFTER both loaded
    _snapshot['secondary_turn_before_endturn'] = _turncounter(SECONDARY_PREFIX)
    _snapshot['primary_turn_before_endturn'] = _turncounter(PRIMARY_PREFIX)
    _snapshot['secondary_workers_before'] = _count(SECONDARY_PREFIX, 'workers')
    _snapshot['primary_workers_before'] = _count(PRIMARY_PREFIX, 'workers')

    # End-turn ONLY in secondary — primary should be untouched
    _end_turn_fresh_context(browser, PHP_BASE_URL_SECONDARY)

    # Snapshot state AFTER secondary end-turn
    _snapshot['secondary_turn_after'] = _turncounter(SECONDARY_PREFIX)
    _snapshot['primary_turn_after'] = _turncounter(PRIMARY_PREFIX)

    yield


# ---------------------------------------------------------------------------
# Tests: both games loaded with distinct scenarios
# ---------------------------------------------------------------------------

class TestBothGamesLoaded:
    """Each game's prefix has its own tables populated."""

    def test_secondary_has_workers(self, page: Page, base_url):
        """Japon1555SQL in secondary loaded 9 workers (Shikoku advanced scenario).

        Counts rows on /workers/management_workers.php served by the
        secondary URL — UI-runnable, no direct DB access required.
        """
        login_as(page, PHP_BASE_URL_SECONDARY, "gm", "orga")
        count = ui_worker_count(page, base_url=PHP_BASE_URL_SECONDARY)
        assert count == 9, f"Expected 9 Shikoku workers, got {count}"

    def test_primary_has_workers(self, page: Page, base_url):
        """TestConfig in primary loaded the workers from setupTestConfig_advanced.csv.

        Counts rows on /workers/management_workers.php on the primary URL.
        Compared dynamically against the CSV row count so CSV growth
        doesn't require a test-side bump.
        """
        login_as(page, base_url, "gm", "orga")
        count = ui_worker_count(page, base_url=base_url)
        expected = csv_row_count("setupTestConfig_advanced.csv")
        assert count == expected, \
            f"Expected {expected} TestConfig workers (per setupTestConfig_advanced.csv), got {count}"

    def test_secondary_has_shikoku_zones(self, page: Page, base_url):
        """Secondary has Shikoku zones (11 total). Counted via management_zones."""
        login_as(page, PHP_BASE_URL_SECONDARY, "gm", "orga")
        zones = ui_zone_names(page, base_url=PHP_BASE_URL_SECONDARY)
        assert len(zones) == 11, \
            f"Expected 11 Shikoku zones, got {len(zones)}: {zones}"

    def test_primary_has_testconfig_zones(self, page: Page, base_url):
        """Primary has the TestConfig zones from setupTestConfig_zones.csv. Counted via management_zones."""
        login_as(page, base_url, "gm", "orga")
        zones = ui_zone_names(page, base_url=base_url)
        expected = csv_row_count("setupTestConfig_zones.csv")
        assert len(zones) == expected, \
            f"Expected {expected} TestConfig zones (per setupTestConfig_zones.csv), got {len(zones)}: {zones}"


class TestShodoshimaInSecondary:
    """Specific Shikoku feature: Shodoshima island zone and locations exist."""

    def test_shodoshima_zone_exists(self, page: Page, base_url):
        """Shodoshima zone rendered on secondary game's management_zones page."""
        login_as(page, PHP_BASE_URL_SECONDARY, "gm", "orga")
        zones = ui_zone_names(page, base_url=PHP_BASE_URL_SECONDARY)
        assert 'Ile de Shōdoshima' in zones, \
            f"Shodoshima zone missing from secondary game, got zones: {zones}"

    def test_shodoshima_not_in_primary(self, page: Page, base_url):
        """Primary should NOT have Shodoshima — proves isolation (via management_zones)."""
        login_as(page, base_url, "gm", "orga")
        zones = ui_zone_names(page, base_url=base_url)
        assert 'Ile de Shōdoshima' not in zones, \
            f"Shodoshima leaked into primary (isolation broken): zones={zones}"

    @pytest.mark.db
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

class TestGameIsolation:
    """Verify that each game's data is isolated in its own prefixed tables.

    Controller lastname membership scraped from each game's
    /controllers/management.php admin table (tr.controller-row
    data-controller-name). Uses isolated browser contexts so the two
    games' sessions don't interfere.
    """

    def _scrape_both(self, browser, base_url):
        """Return (primary_controllers, secondary_controllers) sets."""
        ctx1 = browser.new_context()
        p1 = ctx1.new_page()
        login_as(p1, base_url, "gm", "orga")
        primary = ui_all_controllers(p1, base_url=base_url)
        ctx1.close()
        ctx2 = browser.new_context()
        p2 = ctx2.new_page()
        login_as(p2, PHP_BASE_URL_SECONDARY, "gm", "orga")
        secondary = ui_all_controllers(p2, base_url=PHP_BASE_URL_SECONDARY)
        ctx2.close()
        return primary, secondary

    def test_secondary_controllers_not_in_primary(self, browser, base_url):
        """Shikoku controllers (e.g. 'Shikoku (四国)') should not appear in primary."""
        primary, secondary = self._scrape_both(browser, base_url)
        assert secondary & primary == set(), \
            f"Controllers leaked between games: intersection={secondary & primary}"

    def test_primary_controllers_not_in_secondary(self, browser, base_url):
        """TestConfig controllers (Alpha..Golf) should not appear in secondary."""
        primary, secondary = self._scrape_both(browser, base_url)
        nato = {'Alpha', 'Beta', 'Charlie', 'Delta', 'Echo', 'Foxtrot', 'Golf'}
        assert not (nato & secondary), \
            f"TestConfig NATO controllers leaked into secondary: {nato & secondary}"
        assert nato <= primary, \
            f"Primary should have all NATO controllers: missing {nato - primary}"

    def test_secondary_has_shikoku_controller(self, browser, base_url):
        """Shikoku controller must be in secondary."""
        _, secondary = self._scrape_both(browser, base_url)
        assert 'Shikoku (四国)' in secondary, \
            f"Shikoku controller not found in secondary: {secondary}"

    def test_zone_names_disjoint(self, browser, base_url):
        """Zone name sets should be disjoint — each scenario uses distinct names.

        Scrapes management_zones on both URLs via isolated browser contexts
        so session switching for each login stays isolated.
        """
        ctx1 = browser.new_context()
        p1 = ctx1.new_page()
        login_as(p1, base_url, "gm", "orga")
        z_primary = ui_zone_names(p1, base_url=base_url)
        ctx1.close()

        ctx2 = browser.new_context()
        p2 = ctx2.new_page()
        login_as(p2, PHP_BASE_URL_SECONDARY, "gm", "orga")
        z_secondary = ui_zone_names(p2, base_url=PHP_BASE_URL_SECONDARY)
        ctx2.close()

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
