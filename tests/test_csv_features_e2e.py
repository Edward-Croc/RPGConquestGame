"""Integration tests for new CSV loading features.

Verifies that PHP loadCSVFile (linkTable_, compound FK) and loadWorkersCSV
produce the expected database state after a TestConfig reset.

Tests:
- linkTable_: each power has a link_power_type entry matching its type
- Compound FK lookup: faction_powers correctly resolves power name -> link_power_type_id
- Missing file handling: admin reset completes even when optional files absent
- loadWorkersCSV: 26 workers (7 detection + 19 combat) created with controllers, actions, and powers

Run:
    python3 -m pytest tests/test_csv_features_e2e.py -v
"""
import os
import shutil
import pymysql
import pytest
from playwright.sync_api import Page

from conftest import (
    GAME_PREFIX, MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB,
    PHP_BASE_URL, PROJECT_ROOT,
)


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


def get_db_connection():
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


def _load_test_config(browser):
    """Load TestConfig via admin reset. Returns when complete."""
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute(
        f"INSERT IGNORE INTO `{GAME_PREFIX}players` "
        f"(username, passwd, is_privileged) VALUES ('gm', 'orga', 1)"
    )
    cursor.execute(
        f"INSERT IGNORE INTO `{GAME_PREFIX}mechanics` "
        f"(turncounter, gamestate) VALUES (0, 0)"
    )
    conn.commit()
    conn.close()

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
    context.close()


# ---------------------------------------------------------------------------
# Module fixture: load TestConfig once
# ---------------------------------------------------------------------------

@pytest.fixture(scope="module", autouse=True)
def load_test_config(browser):
    """Load TestConfig fresh at module start."""
    if not DB_AVAILABLE:
        yield
        return
    _load_test_config(browser)
    yield


# ---------------------------------------------------------------------------
# Test 1: linkTable_ junction insert
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestLinkTableFeature:
    """Verify linkTable_ column creates correct junction rows.

    TestConfig loads 30 powers (13 hobbys + 12 jobs + 3 disciplines + 2 transformations).
    Each power row with `linkTable_power_types__name->link_power_type__power_type_id`
    column creates a row in link_power_type linking it to the correct power_type.
    """

    def test_every_power_has_link_power_type_entry(self):
        """Every power should have exactly one link_power_type entry."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT p.id, p.name, COUNT(lpt.id) as link_count
            FROM `{GAME_PREFIX}powers` p
            LEFT JOIN `{GAME_PREFIX}link_power_type` lpt ON lpt.power_id = p.id
            GROUP BY p.id, p.name
        """)
        for r in cursor.fetchall():
            assert r['link_count'] == 1, \
                f"Power '{r['name']}' has {r['link_count']} link entries (expected 1)"
        conn.close()

    def test_hobbys_linked_to_hobby_type(self):
        """All 13 hobby CSV powers should be linked to power_type 'Hobby'."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT pt.name AS type_name, COUNT(*) AS c
            FROM `{GAME_PREFIX}link_power_type` lpt
            JOIN `{GAME_PREFIX}power_types` pt ON pt.id = lpt.power_type_id
            WHERE pt.name = 'Hobby'
            GROUP BY pt.name
        """)
        rows = cursor.fetchall()
        conn.close()
        assert len(rows) == 1, f"Hobby powers should link only to 'Hobby' type, got: {rows}"
        assert rows[0]['type_name'] == 'Hobby'
        assert rows[0]['c'] == 13, f"Expected 13 hobby powers, got {rows[0]['c']}"

    def test_disciplines_linked_to_discipline_type(self):
        """All discipline CSV powers should be linked to power_type 'Discipline'."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT pt.name AS type_name, p.name AS power_name
            FROM `{GAME_PREFIX}link_power_type` lpt
            JOIN `{GAME_PREFIX}powers` p ON p.id = lpt.power_id
            JOIN `{GAME_PREFIX}power_types` pt ON pt.id = lpt.power_type_id
            WHERE p.name IN ('Offensive Stance', 'Defensive Posture', 'Focused Mind')
        """)
        rows = cursor.fetchall()
        conn.close()
        assert len(rows) == 3, f"Expected 3 discipline powers, got {len(rows)}"
        for r in rows:
            assert r['type_name'] == 'Discipline', \
                f"Power '{r['power_name']}' linked to '{r['type_name']}', expected 'Discipline'"

    def test_transformations_linked_to_transformation_type(self):
        """All transformation CSV powers should be linked to power_type 'Transformation'."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT pt.name AS type_name, COUNT(*) AS c
            FROM `{GAME_PREFIX}link_power_type` lpt
            JOIN `{GAME_PREFIX}powers` p ON p.id = lpt.power_id
            JOIN `{GAME_PREFIX}power_types` pt ON pt.id = lpt.power_type_id
            WHERE p.name IN ('War Gear', 'Shadow Cloak')
            GROUP BY pt.name
        """)
        rows = cursor.fetchall()
        conn.close()
        assert len(rows) == 1
        assert rows[0]['type_name'] == 'Transformation'
        assert rows[0]['c'] == 2

    def test_power_type_counts(self):
        """Verify exact counts per power type."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT pt.name AS type_name, COUNT(*) AS c
            FROM `{GAME_PREFIX}link_power_type` lpt
            JOIN `{GAME_PREFIX}power_types` pt ON pt.id = lpt.power_type_id
            GROUP BY pt.name
        """)
        counts = {r['type_name']: r['c'] for r in cursor.fetchall()}
        conn.close()
        assert counts == {'Hobby': 13, 'Metier': 12, 'Discipline': 3, 'Transformation': 2}, \
            f"Unexpected power type counts: {counts}"


# ---------------------------------------------------------------------------
# Test 2: Compound FK lookup
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestCompoundFKLookup:
    """Verify the compound lookup syntax `powers__name->link_power_type__power_id`.

    faction_powers CSV references powers by NAME, but the target column is
    link_power_type_id. The loader resolves power name → powers.id → link_power_type
    row where power_id matches → return link_power_type.id.
    """

    def test_faction_powers_row_count(self):
        """TestConfig faction_powers CSV has 8 rows (6 base + 2 recruitment disciplines)."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"SELECT COUNT(*) AS c FROM `{GAME_PREFIX}faction_powers`")
        count = cursor.fetchone()['c']
        conn.close()
        assert count == 8

    def test_compound_lookup_resolves_to_correct_power(self):
        """Each faction_powers row should link the faction to the correct power."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT f.name AS faction, p.name AS power
            FROM `{GAME_PREFIX}faction_powers` fp
            JOIN `{GAME_PREFIX}factions` f ON f.id = fp.faction_id
            JOIN `{GAME_PREFIX}link_power_type` lpt ON lpt.id = fp.link_power_type_id
            JOIN `{GAME_PREFIX}powers` p ON p.id = lpt.power_id
            ORDER BY f.name, p.name
        """)
        results = [(r['faction'], r['power']) for r in cursor.fetchall()]
        conn.close()

        expected = [
            ('FactionAlpha', 'Keen Eye'),
            ('FactionAlpha', 'Offensive Stance'),
            ('FactionAlpha', 'Sword Bearer'),
            ('FactionBeta', 'Defensive Posture'),
            ('FactionBeta', 'Field Analyst'),
            ('FactionBeta', 'Steady Arm'),
            ('FactionCharlie', 'Eagle Scout'),
            ('FactionDelta', 'Eagle Scout'),
        ]
        assert results == expected, f"Expected {expected}, got {results}"

    def test_compound_lookup_no_null_fks(self):
        """All faction_powers rows should have valid (non-null) FKs."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT COUNT(*) AS c FROM `{GAME_PREFIX}faction_powers`
            WHERE faction_id IS NULL OR link_power_type_id IS NULL
        """)
        null_count = cursor.fetchone()['c']
        conn.close()
        assert null_count == 0, "All faction_powers rows should have valid FKs"


# ---------------------------------------------------------------------------
# Test 4: Missing file handling
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestMissingFileHandling:
    """Verify the admin reset handles missing CSV files gracefully.

    TestConfig has no 'base' CSV or SQL file — the loader should skip it
    silently without crashing. Also test with a CSV temporarily removed.
    """

    def test_missing_base_file_does_not_crash(self):
        """TestConfig doesn't have setupTestConfig_base.csv — reset should still complete."""
        # Verify we loaded correctly despite missing base
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"SELECT COUNT(*) AS c FROM `{GAME_PREFIX}players`")
        player_count = cursor.fetchone()['c']
        conn.close()
        # Should have at least gm + single_player + multi_player
        assert player_count >= 3, \
            f"After reset with missing base file, expected >=3 players, got {player_count}"

    def test_missing_optional_file_handled(self, browser):
        """Temporarily rename a CSV to simulate a missing optional file.
        Reset should still complete — other data should load."""
        csv_file = os.path.join(PROJECT_ROOT, 'var', 'csv', 'setupTestConfig_disciplines.csv')
        backup_file = csv_file + '.bak_missing_test'

        if not os.path.exists(csv_file):
            pytest.skip("Disciplines CSV not found")

        shutil.move(csv_file, backup_file)
        try:
            _load_test_config(browser)

            # Verify reset completed: mechanics row exists
            conn = get_db_connection()
            cursor = conn.cursor()
            cursor.execute(f"SELECT COUNT(*) AS c FROM `{GAME_PREFIX}mechanics`")
            assert cursor.fetchone()['c'] == 1
            # Powers table should still have hobbys, jobs, transformations but fewer disciplines
            cursor.execute(f"""
                SELECT COUNT(*) AS c FROM `{GAME_PREFIX}link_power_type` lpt
                JOIN `{GAME_PREFIX}power_types` pt ON pt.id = lpt.power_type_id
                WHERE pt.name = 'Discipline'
            """)
            discipline_count = cursor.fetchone()['c']
            conn.close()
            assert discipline_count == 0, \
                f"With disciplines CSV missing, expected 0 Discipline links, got {discipline_count}"
        finally:
            # Always restore the file
            shutil.move(backup_file, csv_file)
            # Reload to restore state for subsequent tests
            _load_test_config(browser)


# ---------------------------------------------------------------------------
# Test 5: loadWorkersCSV
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestLoadWorkersCSV:
    """Verify loadWorkersCSV creates 4 tables of data from one CSV row per worker.

    setupTestConfig_advanced.csv has 26 rows. Each should create:
    - 1 row in workers
    - 1 row in controller_worker
    - 1 row in worker_actions (turn 0)
    - N rows in worker_powers (pipe-separated list)
    """

    def test_workers_table_populated(self):
        """Exactly 26 workers should exist."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"SELECT COUNT(*) AS c FROM `{GAME_PREFIX}workers`")
        count = cursor.fetchone()['c']
        conn.close()
        assert count == 26

    def test_all_workers_have_origin_and_zone(self):
        """Every worker should have non-null origin_id and zone_id (FK lookups resolved)."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT COUNT(*) AS c FROM `{GAME_PREFIX}workers`
            WHERE origin_id IS NULL OR zone_id IS NULL
        """)
        null_count = cursor.fetchone()['c']
        conn.close()
        assert null_count == 0

    def test_controller_worker_junction_created(self):
        """Each worker should have exactly one controller_worker entry."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT w.lastname, COUNT(cw.id) AS link_count
            FROM `{GAME_PREFIX}workers` w
            LEFT JOIN `{GAME_PREFIX}controller_worker` cw ON cw.worker_id = w.id
            GROUP BY w.id, w.lastname
        """)
        for r in cursor.fetchall():
            assert r['link_count'] == 1, \
                f"Worker '{r['lastname']}' has {r['link_count']} controller links"
        conn.close()

    def test_worker_actions_created_for_turn_0(self):
        """Each worker should have exactly one worker_actions row at turn 0."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT w.lastname, COUNT(wa.id) AS action_count
            FROM `{GAME_PREFIX}workers` w
            LEFT JOIN `{GAME_PREFIX}worker_actions` wa
              ON wa.worker_id = w.id AND wa.turn_number = 0
            GROUP BY w.id, w.lastname
        """)
        for r in cursor.fetchall():
            assert r['action_count'] == 1, \
                f"Worker '{r['lastname']}' has {r['action_count']} turn-0 actions"
        conn.close()

    def test_specific_agent_controller_mapping(self):
        """Verify Finder_1 -> Charlie, Searcher_1 -> Alpha, etc. match the CSV."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT w.lastname AS worker, c.lastname AS controller
            FROM `{GAME_PREFIX}workers` w
            JOIN `{GAME_PREFIX}controller_worker` cw ON cw.worker_id = w.id
            JOIN `{GAME_PREFIX}controllers` c ON c.id = cw.controller_id
            ORDER BY w.lastname
        """)
        mapping = {r['worker']: r['controller'] for r in cursor.fetchall()}
        conn.close()

        expected = {
            # Detection agents (Alpha-Investigation zone)
            'Finder_1': 'Charlie', 'Finder_2': 'Delta', 'Finder_3': 'Echo',
            'Finder_4': 'Foxtrot', 'Finder_5': 'Golf',
            'Searcher_1': 'Alpha', 'Bystander_1': 'Beta',
            # Chain attack (Beta-Combat zone)
            'Chain_A': 'Alpha', 'Chain_B': 'Beta', 'Chain_C': 'Charlie',
            'Chain_D': 'Delta', 'Chain_E': 'Echo', 'Chain_F': 'Foxtrot', 'Chain_G': 'Golf',
            # Base interactions (Beta-Combat zone)
            'Even_Atk': 'Alpha', 'Even_Def': 'Beta',
            'Counter_Atk': 'Golf', 'Counter_Def': 'Foxtrot',
            # Blocked investigate (Beta-Combat zone)
            'Inv_Atk_1': 'Alpha', 'Inv_Def_1': 'Beta',
            'Inv_Atk_2': 'Charlie', 'Inv_Def_2': 'Delta',
            # Blocked claim (Beta-Combat zone)
            'Claim_Atk_1': 'Echo', 'Claim_Def_1': 'Beta',
            'Claim_Atk_2': 'Charlie', 'Claim_Def_2': 'Delta',
        }
        assert mapping == expected, f"Worker-controller mapping wrong: got {mapping}"

    def test_searcher1_has_investigate_action(self):
        """Searcher_1 CSV has action_choice='investigate' — should appear in DB."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT wa.action_choice FROM `{GAME_PREFIX}worker_actions` wa
            JOIN `{GAME_PREFIX}workers` w ON w.id = wa.worker_id
            WHERE w.lastname = 'Searcher_1' AND wa.turn_number = 0
        """)
        action = cursor.fetchone()['action_choice']
        conn.close()
        assert action == 'investigate'

    def test_other_agents_have_passive_action(self):
        """All other agents should have action_choice='passive'."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT w.lastname, wa.action_choice
            FROM `{GAME_PREFIX}worker_actions` wa
            JOIN `{GAME_PREFIX}workers` w ON w.id = wa.worker_id
            WHERE wa.turn_number = 0 AND w.lastname != 'Searcher_1'
        """)
        for r in cursor.fetchall():
            assert r['action_choice'] == 'passive', \
                f"Worker '{r['lastname']}' has action '{r['action_choice']}', expected 'passive'"
        conn.close()

    def test_pipe_separated_powers_parsed(self):
        """Workers have correct power counts (2 or 4 depending on CSV pipe list)."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT w.lastname, COUNT(wp.id) AS power_count
            FROM `{GAME_PREFIX}workers` w
            LEFT JOIN `{GAME_PREFIX}worker_powers` wp ON wp.worker_id = w.id
            GROUP BY w.id, w.lastname
        """)
        four_power_agents = {'Chain_A', 'Inv_Atk_1', 'Claim_Atk_1'}
        for r in cursor.fetchall():
            expected = 4 if r['lastname'] in four_power_agents else 2
            assert r['power_count'] == expected, \
                f"Worker '{r['lastname']}' has {r['power_count']} powers, expected {expected}"
        conn.close()

    def test_finder1_has_correct_powers(self):
        """Finder_1 CSV: powers='Eagle Scout|Veteran Tactician'."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT p.name
            FROM `{GAME_PREFIX}workers` w
            JOIN `{GAME_PREFIX}worker_powers` wp ON wp.worker_id = w.id
            JOIN `{GAME_PREFIX}link_power_type` lpt ON lpt.id = wp.link_power_type_id
            JOIN `{GAME_PREFIX}powers` p ON p.id = lpt.power_id
            WHERE w.lastname = 'Finder_1'
            ORDER BY p.name
        """)
        powers = [r['name'] for r in cursor.fetchall()]
        conn.close()
        assert powers == ['Eagle Scout', 'Veteran Tactician']

    def test_bystander1_has_negative_power(self):
        """Bystander_1 has 'Dark Impulse' with negative defence — verify power is linked."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT p.name, p.defence
            FROM `{GAME_PREFIX}workers` w
            JOIN `{GAME_PREFIX}worker_powers` wp ON wp.worker_id = w.id
            JOIN `{GAME_PREFIX}link_power_type` lpt ON lpt.id = wp.link_power_type_id
            JOIN `{GAME_PREFIX}powers` p ON p.id = lpt.power_id
            WHERE w.lastname = 'Bystander_1' AND p.name = 'Dark Impulse'
        """)
        row = cursor.fetchone()
        conn.close()
        assert row is not None, "Bystander_1 should have Dark Impulse power"
        assert row['defence'] == -1, f"Dark Impulse should have defence=-1, got {row['defence']}"
