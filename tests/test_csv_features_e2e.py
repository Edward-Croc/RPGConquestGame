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
    PHP_BASE_URL, PROJECT_ROOT, ensure_gm_login,
)


from helpers import (
    DB_AVAILABLE, get_db_connection, load_minimal_data,
    ui_worker_count, ui_power_options_by_type, ui_all_workers,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


def _load_test_config(browser):
    """Load TestConfig via admin reset. Returns when complete."""
    if DB_AVAILABLE:
        load_minimal_data()

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
    """Load TestConfig fresh at module start. UI load works on any target;
    local-DB seeding only runs when MySQL is reachable."""
    _load_test_config(browser)
    yield


# ---------------------------------------------------------------------------
# Test 1: linkTable_ junction insert
# ---------------------------------------------------------------------------

class TestLinkTableFeature:
    """Verify linkTable_ column creates correct junction rows.

    TestConfig loads 30 powers (13 hobbys + 12 jobs + 3 disciplines + 2 transformations).
    Each power row with `linkTable_power_types__name->link_power_type__power_type_id`
    column creates a row in link_power_type linking it to the correct power_type.

    UI-first: the admin.php "Create Perfect Agent" form exposes one
    <select> per power type (power_hobby_id, power_metier_id,
    disciplineSelect, transformationSelect) whose option lists are
    populated from link_power_type joined with power_types. Counting
    options per select verifies the junction is correct.
    """

    def test_every_power_has_link_power_type_entry(self, page: Page, base_url):
        """Every power should appear in exactly one type-specific dropdown."""
        ensure_gm_login(page, base_url)
        options_by_type = ui_power_options_by_type(page, base_url=base_url)
        # Flatten: (type, power_name) pairs. Each power_name should appear
        # in exactly one type list (the linkTable_ join places it there).
        seen = {}
        for type_name, names in options_by_type.items():
            for name in names:
                seen.setdefault(name, []).append(type_name)
        duplicates = {n: t for n, t in seen.items() if len(t) > 1}
        assert not duplicates, \
            f"Powers appearing in >1 type dropdown (bad junction): {duplicates}"

    def test_hobbys_linked_to_hobby_type(self, page: Page, base_url):
        """All 13 hobby CSV powers should appear in the Hobby dropdown."""
        ensure_gm_login(page, base_url)
        options_by_type = ui_power_options_by_type(page, base_url=base_url)
        assert len(options_by_type['Hobby']) == 13, \
            f"Expected 13 hobby powers, got {len(options_by_type['Hobby'])}: {options_by_type['Hobby']}"

    def test_disciplines_linked_to_discipline_type(self, page: Page, base_url):
        """The 3 discipline CSV powers appear in the Discipline dropdown."""
        ensure_gm_login(page, base_url)
        options_by_type = ui_power_options_by_type(page, base_url=base_url)
        disciplines = set(options_by_type['Discipline'])
        expected = {'Offensive Stance', 'Defensive Posture', 'Focused Mind'}
        assert disciplines == expected, \
            f"Discipline dropdown mismatch — expected {expected}, got {disciplines}"

    def test_transformations_linked_to_transformation_type(self, page: Page, base_url):
        """The 2 transformation CSV powers appear in the Transformation dropdown."""
        ensure_gm_login(page, base_url)
        options_by_type = ui_power_options_by_type(page, base_url=base_url)
        transformations = set(options_by_type['Transformation'])
        expected = {'War Gear', 'Shadow Cloak'}
        assert transformations == expected, \
            f"Transformation dropdown mismatch — expected {expected}, got {transformations}"

    def test_power_type_counts(self, page: Page, base_url):
        """Exact counts per power type via admin.php dropdowns."""
        ensure_gm_login(page, base_url)
        options_by_type = ui_power_options_by_type(page, base_url=base_url)
        counts = {t: len(names) for t, names in options_by_type.items()}
        expected = {'Hobby': 13, 'Metier': 12, 'Discipline': 3, 'Transformation': 2}
        assert counts == expected, f"Power type counts mismatch: {counts}"


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

class TestLoadWorkersCSV:
    """Verify loadWorkersCSV creates 4 tables of data from one CSV row per worker.

    setupTestConfig_advanced.csv has 26 rows. Each should create:
    - 1 row in workers
    - 1 row in controller_worker
    - 1 row in worker_actions (turn 0)
    - N rows in worker_powers (pipe-separated list)
    """

    def test_workers_table_populated(self, page: Page, base_url):
        """Exactly 28 workers should exist (7 detection + 19 combat + 2 cross).

        Counts rows on /workers/management_workers.php — UI-runnable.
        """
        ensure_gm_login(page, base_url)
        count = ui_worker_count(page, base_url=base_url)
        assert count == 28, f"Expected 28 workers, got {count}"

    @pytest.mark.db
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

    @pytest.mark.db
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

    @pytest.mark.db
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

    def test_specific_agent_controller_mapping(self, page: Page, base_url):
        """Verify Finder_1 -> Charlie, Searcher_1 -> Alpha, etc. match the CSV.

        Cross-references `/workers/management_workers.php` (worker →
        controller_id) with `select#controllerSelect` on accueil.php
        (controller_id → lastname) to build the same mapping the DB
        query produced. UI-runnable.
        """
        ensure_gm_login(page, base_url)
        # Build controller_id → lastname map from accueil's controllerSelect
        page.goto(f"{base_url}/base/accueil.php")
        page.wait_for_load_state("networkidle")
        id_to_lastname = {}
        for opt in page.locator("select#controllerSelect option").all():
            val = opt.get_attribute("value") or ""
            text = (opt.inner_text() or "").strip()
            if val and text:
                id_to_lastname[int(val)] = text.split()[-1]  # "Lord Alpha" → "Alpha"

        # Build worker → controller lastname map via management_workers
        workers = ui_all_workers(page, base_url=base_url)
        mapping = {w['lastname']: id_to_lastname.get(w['controller_id']) for w in workers}

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
            # Cross-zone attack (Beta-Combat → Delta-Disputed)
            'Hunter_Cross': 'Alpha', 'Runner_Cross': 'Beta',
        }
        assert mapping == expected, f"Worker-controller mapping wrong: got {mapping}"

    def test_searcher1_has_investigate_action(self, page: Page, base_url):
        """Searcher_1 CSV has action_choice='investigate' — management_workers
        'Ongoing action' column surfaces the current-turn action_choice."""
        ensure_gm_login(page, base_url)
        workers = {w['lastname']: w for w in ui_all_workers(page, base_url=base_url)}
        assert 'Searcher_1' in workers, f"Searcher_1 not in management_workers: {list(workers)[:5]}..."
        assert workers['Searcher_1']['action_choice'] == 'investigate', \
            f"Searcher_1 action_choice should be 'investigate', got {workers['Searcher_1']}"

    def test_other_agents_have_passive_action(self, page: Page, base_url):
        """All other agents should have action_choice='passive' at turn 0.

        Exceptions (CSV-seeded with a non-passive action):
          - Searcher_1 (detection): action='investigate'
          - Hunter_Cross (cross-zone scenario): action='investigate'
        """
        ensure_gm_login(page, base_url)
        investigate_agents = {'Searcher_1', 'Hunter_Cross'}
        for w in ui_all_workers(page, base_url=base_url):
            if w['lastname'] in investigate_agents:
                continue
            assert w['action_choice'] == 'passive', \
                f"Worker '{w['lastname']}' has action '{w['action_choice']}', expected 'passive'"

    @pytest.mark.db
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

    @pytest.mark.db
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

    @pytest.mark.db
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
