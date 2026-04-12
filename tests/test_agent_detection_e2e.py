"""Playwright end-to-end tests for agent detection mechanics (Issue #2).

Tests the investigation mechanic: after end turn, verify which agents
detect which other agents and locations based on investigation values.

Test data loaded via TestConfig CSVs + setupTestConfig_advanced.sql.

Agent stats (PASSIVEVAL=3, MINROLL=MAXROLL=3):
    Agent1 (Charlie):  enquete=7  (passive 3 + power bonus 4)
    Agent2 (Delta):    enquete=7  (passive 3 + power bonus 4)
    Agent3 (Echo):     enquete=6  (passive 3 + power bonus 3)
    Agent4 (Foxtrot):  enquete=5  (passive 3 + power bonus 2)
    Agent5 (Golf):     enquete=4  (passive 3 + power bonus 1)
    Agent6 (Alpha):    enquete=3  (investigate roll 3 + power bonus 0)
    Agent7 (Beta):     enquete=3  (passive 3 + power bonus 0, negative defence)

Location A: discovery_diff=4, in ZoneA.

Run:
    python3 -m pytest tests/test_agent_detection_e2e.py -v
"""
import pymysql
import pytest
from playwright.sync_api import Page

from conftest import (
    GAME_PREFIX, MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB,
    PHP_BASE_URL,
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


def get_worker_report(worker_lastname, turn=0):
    """Return the report JSON string for a worker at a given turn."""
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute(f"""
        SELECT wa.report FROM `{GAME_PREFIX}worker_actions` wa
        JOIN `{GAME_PREFIX}workers` w ON w.id = wa.worker_id
        WHERE w.lastname = %s AND wa.turn_number = %s
    """, (worker_lastname, turn))
    row = cursor.fetchone()
    conn.close()
    return str(row['report'] or '') if row else ''


def get_worker_id(worker_lastname):
    """Return the database ID for a worker by lastname."""
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute(f"SELECT id FROM `{GAME_PREFIX}workers` WHERE lastname = %s", (worker_lastname,))
    row = cursor.fetchone()
    conn.close()
    return row['id'] if row else None


def get_controller_id(controller_lastname):
    """Return the database ID for a controller by lastname."""
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute(f"SELECT id FROM `{GAME_PREFIX}controllers` WHERE lastname = %s", (controller_lastname,))
    row = cursor.fetchone()
    conn.close()
    return row['id'] if row else None


# ---------------------------------------------------------------------------
# Module fixture: load TestConfig + run end turn once
# ---------------------------------------------------------------------------

@pytest.fixture(scope="module", autouse=True)
def load_and_end_turn(browser):
    """Load TestConfig, then trigger end turn to execute mechanics."""
    if not DB_AVAILABLE:
        yield
        return

    # Ensure gm exists
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

    # Load TestConfig
    page.goto(f"{PHP_BASE_URL}/connection/loginForm.php")
    page.wait_for_load_state("networkidle")
    page.locator("input[name='username']").fill("gm")
    page.locator("input[name='passwd']").fill("orga")
    page.locator("input[type='submit']").first.click()
    page.wait_for_load_state("networkidle")

    page.goto(f"{PHP_BASE_URL}/base/admin.php")
    page.wait_for_load_state("networkidle")
    page.locator("select[name='config_name']").select_option("TestConfig")
    page.locator("input[name='submit'][value='Submit']").click(no_wait_after=True)
    page.wait_for_timeout(5000)
    page.wait_for_load_state("load", timeout=90000)

    # Trigger end turn (executes immediately on page load)
    page.goto(f"{PHP_BASE_URL}/mechanics/endTurn.php")
    page.wait_for_load_state("load", timeout=90000)

    # Logout
    page.goto(f"{PHP_BASE_URL}/connection/logout.php")
    page.wait_for_load_state("networkidle")
    context.close()
    yield


@pytest.fixture(autouse=True)
def _require_db():
    if not DB_AVAILABLE:
        pytest.skip("No local MySQL available")


# ---------------------------------------------------------------------------
# Helper to query detection data
# ---------------------------------------------------------------------------

def get_detections_for_controller(controller_lastname):
    """Return list of detected agent lastnames for a given controller."""
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute(f"""
        SELECT w.lastname
        FROM `{GAME_PREFIX}controllers_known_enemies` ke
        JOIN `{GAME_PREFIX}controllers` c ON c.id = ke.controller_id
        JOIN `{GAME_PREFIX}workers` w ON w.id = ke.discovered_worker_id
        WHERE c.lastname = %s
        ORDER BY w.lastname
    """, (controller_lastname,))
    result = [r['lastname'] for r in cursor.fetchall()]
    conn.close()
    return result


def get_location_discoveries_for_controller(controller_lastname):
    """Return list of discovered location names for a given controller."""
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute(f"""
        SELECT l.name
        FROM `{GAME_PREFIX}controller_known_locations` kl
        JOIN `{GAME_PREFIX}controllers` c ON c.id = kl.controller_id
        JOIN `{GAME_PREFIX}locations` l ON l.id = kl.location_id
        WHERE c.lastname = %s
    """, (controller_lastname,))
    result = [r['name'] for r in cursor.fetchall()]
    conn.close()
    return result


# ---------------------------------------------------------------------------
# Test: calculated investigation values
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestCalculatedValues:
    """Verify that calculateVals produces correct enquete/attack/defence values."""

    def test_investigation_values(self):
        """Each agent's enquete_val should be PASSIVEVAL(3) + power bonus."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT w.lastname, wa.enquete_val, wa.attack_val, wa.defence_val
            FROM `{GAME_PREFIX}worker_actions` wa
            JOIN `{GAME_PREFIX}workers` w ON w.id = wa.worker_id
            WHERE wa.turn_number = 0
            ORDER BY w.lastname
        """)
        vals = {r['lastname']: r for r in cursor.fetchall()}
        conn.close()

        # Agent1: power bonus 4 → passive 3 + 4 = 7
        assert vals['Agent1']['enquete_val'] == 7
        assert vals['Agent1']['attack_val'] == 6
        assert vals['Agent1']['defence_val'] == 6

        # Agent3: power bonus 3 → passive 3 + 3 = 6
        assert vals['Agent3']['enquete_val'] == 6

        # Agent5: power bonus 1 → passive 3 + 1 = 4
        assert vals['Agent5']['enquete_val'] == 4
        assert vals['Agent5']['attack_val'] == 8  # 3 + 5
        assert vals['Agent5']['defence_val'] == 6  # 3 + 3

        # Agent6: investigate roll(3) + 0 = 3
        assert vals['Agent6']['enquete_val'] == 3

    def test_negative_defence_value(self):
        """Agent7 has negative power defence (-1): val should be 3 + (-1) = 2."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT wa.enquete_val, wa.attack_val, wa.defence_val
            FROM `{GAME_PREFIX}worker_actions` wa
            JOIN `{GAME_PREFIX}workers` w ON w.id = wa.worker_id
            WHERE w.lastname = 'Agent7' AND wa.turn_number = 0
        """)
        r = cursor.fetchone()
        conn.close()

        assert r['enquete_val'] == 3   # 3 + 0
        assert r['attack_val'] == 4    # 3 + 1
        assert r['defence_val'] == 2   # 3 + (-1)


# ---------------------------------------------------------------------------
# Test: agent detection of other agents
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestAgentDetection:
    """Verify which agents detect which other agents after end turn.

    Detection threshold REPORTDIFF0 = -1 (from setupBDD.sql config).
    An agent detects another if: own_enquete - target_enquete >= -1.
    Agents only detect workers from OTHER controllers.
    """

    def test_agent1_detects_all_others(self):
        """Agent1 (enquete=7) should detect all other agents (all on different controllers)."""
        detected = get_detections_for_controller('Charlie')
        for agent in ['Agent2', 'Agent3', 'Agent4', 'Agent5', 'Agent6', 'Agent7']:
            assert agent in detected, \
                f"Agent1 (Charlie, enq=7) should detect {agent}"

    def test_agent3_detects_agents_with_lower_enquete(self):
        """Agent3 (enquete=6) detects agents with enquete <= 7 (diff >= -1)."""
        detected = get_detections_for_controller('Echo')
        # Agent3 CAN see Agent1(7) and Agent2(7) because 6-7 = -1 >= REPORTDIFF0(-1)
        assert 'Agent1' in detected, "Agent3 should detect Agent1 (diff=-1 >= -1)"
        assert 'Agent2' in detected, "Agent3 should detect Agent2 (diff=-1 >= -1)"
        assert 'Agent4' in detected
        assert 'Agent5' in detected

    def test_agent4_detects_lower_and_equal(self):
        """Agent4 (enquete=5) detects agents where diff >= -1."""
        detected = get_detections_for_controller('Foxtrot')
        assert 'Agent3' in detected, "Agent4 should detect Agent3 (diff=-1)"
        assert 'Agent5' in detected, "Agent4 should detect Agent5 (diff=1)"
        assert 'Agent6' in detected, "Agent4 should detect Agent6 (diff=2)"
        # Agent4 should NOT detect Agent1 or Agent2 (diff = 5-7 = -2 < -1)
        assert 'Agent1' not in detected, \
            "Agent4 should NOT detect Agent1 (diff=-2 < REPORTDIFF0)"
        assert 'Agent2' not in detected, \
            "Agent4 should NOT detect Agent2 (diff=-2 < REPORTDIFF0)"

    def test_agent5_detects_weaker_only(self):
        """Agent5 (enquete=4) should detect agents with enquete <= 5."""
        detected = get_detections_for_controller('Golf')
        assert 'Agent4' in detected, "Agent5 should detect Agent4 (diff=-1)"
        assert 'Agent6' in detected, "Agent5 should detect Agent6 (diff=1)"
        # Should NOT detect Agent1(7), Agent2(7), Agent3(6) — diff too negative
        assert 'Agent1' not in detected, "Agent5 should NOT detect Agent1 (diff=-3)"
        assert 'Agent2' not in detected, "Agent5 should NOT detect Agent2 (diff=-3)"
        assert 'Agent3' not in detected, "Agent5 should NOT detect Agent3 (diff=-2)"

    def test_agent6_detects_equal_and_weaker(self):
        """Agent6 (enquete=3) on Alpha should detect agents with enquete <= 4."""
        detected = get_detections_for_controller('Alpha')
        # Agent6 is on Alpha. Agent7 is on Beta.
        # Agent6(3) vs Agent7(3): diff=0 >= -1 → detects
        assert 'Agent7' in detected, "Agent6 should detect Agent7 (diff=0)"
        assert 'Agent5' in detected, "Agent6 should detect Agent5 (diff=-1)"
        # Should NOT detect Agent1-4 (diff too negative)
        assert 'Agent1' not in detected, "Agent6 should NOT detect Agent1 (diff=-4)"

    def test_agent7_detects_equal_and_weaker(self):
        """Agent7 (enquete=3) on Beta should detect agents with enquete <= 4."""
        detected = get_detections_for_controller('Beta')
        assert 'Agent6' in detected, "Agent7 should detect Agent6 (diff=0)"
        assert 'Agent5' in detected, "Agent7 should detect Agent5 (diff=-1)"
        assert 'Agent1' not in detected, "Agent7 should NOT detect Agent1 (diff=-4)"


# ---------------------------------------------------------------------------
# Test: location detection — 4 discovery levels
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestLocationDetectionLevels:
    """Verify the 4 levels of location discovery.

    Location A: discovery_diff=4, in ZoneA.
    Thresholds: LOCATIONNAMEDIFF=0, LOCATIONINFORMATIONDIFF=1, LOCATIONARTEFACTSDIFF=2.

    Level -1 (unfound):  diff < 0 — nothing in report or known_locations.
    Level  0 (name):     diff >= 0, < 1 — name in agent report only, NOT in known_locations.
    Level  1 (desc):     diff >= 1, < 2 — name+desc in report, added to known_locations (secret=0).
    Level  2 (secret):   diff >= 2 — name+desc+secret in report, known_locations (secret=1).

    Agents:
        Agent6 (Alpha,  enq=3): diff=-1 → unfound
        Agent5 (Golf,   enq=4): diff=0  → name only
        Agent4 (Foxtrot,enq=5): diff=1  → name+desc, in known_locations
        Agent1 (Charlie,enq=7): diff=3  → everything, in known_locations with secret
    """

    def test_level_unfound_no_report(self):
        """Agent6 (diff=-1): no location info in report, not in known_locations."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT wa.report FROM `{GAME_PREFIX}worker_actions` wa
            JOIN `{GAME_PREFIX}workers` w ON w.id = wa.worker_id
            WHERE w.lastname = 'Agent6' AND wa.turn_number = 0
        """)
        report = str(cursor.fetchone()['report'] or '')
        conn.close()

        assert 'Location A' not in report, \
            "Agent6 (diff=-1) should have no location info in report"
        locs = get_location_discoveries_for_controller('Alpha')
        assert 'Location A' not in locs, \
            "Agent6 should NOT appear in known_locations"

    def test_level0_name_in_report_only(self):
        """Agent5 (diff=0): location name in report, NOT in known_locations."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT wa.report FROM `{GAME_PREFIX}worker_actions` wa
            JOIN `{GAME_PREFIX}workers` w ON w.id = wa.worker_id
            WHERE w.lastname = 'Agent5' AND wa.turn_number = 0
        """)
        report = str(cursor.fetchone()['report'] or '')
        conn.close()

        assert 'Location A' in report, \
            "Agent5 (diff=0) should see location NAME in report"
        assert 'test location' not in report.lower(), \
            "Agent5 (diff=0) should NOT see location description in report"
        locs = get_location_discoveries_for_controller('Golf')
        assert 'Location A' not in locs, \
            "Agent5 (diff=0) should NOT be in known_locations (requires diff>=1)"

    def test_level1_desc_in_report_and_known_locations(self):
        """Agent4 (diff=1): name+desc in report, added to known_locations without secret."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT wa.report FROM `{GAME_PREFIX}worker_actions` wa
            JOIN `{GAME_PREFIX}workers` w ON w.id = wa.worker_id
            WHERE w.lastname = 'Agent4' AND wa.turn_number = 0
        """)
        report = str(cursor.fetchone()['report'] or '')
        conn.close()

        assert 'Location A' in report, \
            "Agent4 (diff=1) should see location name in report"
        assert 'test location' in report.lower(), \
            "Agent4 (diff=1) should see location description in report"
        assert 'Secret details' not in report, \
            "Agent4 (diff=1) should NOT see hidden_description"

        locs = get_location_discoveries_for_controller('Foxtrot')
        assert 'Location A' in locs, \
            "Agent4 (diff=1) should be in known_locations"

        # Verify secret flag is 0
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT kl.found_secret FROM `{GAME_PREFIX}controller_known_locations` kl
            JOIN `{GAME_PREFIX}controllers` c ON c.id = kl.controller_id
            JOIN `{GAME_PREFIX}locations` l ON l.id = kl.location_id
            WHERE c.lastname = 'Foxtrot' AND l.name = 'Location A'
        """)
        assert cursor.fetchone()['found_secret'] == 0, \
            "Agent4 (diff=1) should have found_secret=0"
        conn.close()

    def test_level2_secret_in_report_and_known_locations(self):
        """Agent1 (diff=3): name+desc+secret in report, known_locations with secret=1."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT wa.report FROM `{GAME_PREFIX}worker_actions` wa
            JOIN `{GAME_PREFIX}workers` w ON w.id = wa.worker_id
            WHERE w.lastname = 'Agent1' AND wa.turn_number = 0
        """)
        report = str(cursor.fetchone()['report'] or '')
        conn.close()

        assert 'Location A' in report, \
            "Agent1 (diff=3) should see location name in report"
        assert 'test location' in report.lower(), \
            "Agent1 (diff=3) should see location description in report"
        assert 'Secret details' in report, \
            "Agent1 (diff=3) should see hidden_description (secret)"

        locs = get_location_discoveries_for_controller('Charlie')
        assert 'Location A' in locs, \
            "Agent1 (diff=3) should be in known_locations"

        # Verify secret flag is 1
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT kl.found_secret FROM `{GAME_PREFIX}controller_known_locations` kl
            JOIN `{GAME_PREFIX}controllers` c ON c.id = kl.controller_id
            JOIN `{GAME_PREFIX}locations` l ON l.id = kl.location_id
            WHERE c.lastname = 'Charlie' AND l.name = 'Location A'
        """)
        assert cursor.fetchone()['found_secret'] == 1, \
            "Agent1 (diff=3) should have found_secret=1"
        conn.close()


# ---------------------------------------------------------------------------
# Test: location visibility on pages
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestLocationVisibilityOnPages:
    """Verify discovered locations appear on zones and controller pages."""

    def _login_and_select_controller(self, page, base_url, controller_id):
        """Login as gm and select a specific controller."""
        page.goto(f"{base_url}/connection/loginForm.php")
        page.wait_for_load_state("networkidle")
        page.locator("input[name='username']").fill("gm")
        page.locator("input[name='passwd']").fill("orga")
        page.locator("input[type='submit']").first.click()
        page.wait_for_load_state("networkidle")
        page.goto(f"{base_url}/base/accueil.php?controller_id={controller_id}")
        page.wait_for_load_state("networkidle")

    def test_discovered_location_on_zones_page(self, page: Page, base_url):
        """Controller Charlie (discovered Location A) should see it on zones page.
        Location info is inside hidden description divs — check HTML content."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"SELECT id FROM `{GAME_PREFIX}controllers` WHERE lastname = 'Charlie'")
        ctrl_id = cursor.fetchone()['id']
        conn.close()

        self._login_and_select_controller(page, base_url, ctrl_id)
        page.goto(f"{base_url}/zones/action.php")
        page.wait_for_load_state("networkidle")
        page_html = page.content()
        assert "Location A" in page_html, \
            "Charlie should see 'Location A' in zones page HTML"
        page.goto(f"{base_url}/connection/logout.php")

    def test_discovered_location_on_controller_page(self, page: Page, base_url):
        """Controller Charlie should see Location A in 'Lieux connus' on accueil."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"SELECT id FROM `{GAME_PREFIX}controllers` WHERE lastname = 'Charlie'")
        ctrl_id = cursor.fetchone()['id']
        conn.close()

        self._login_and_select_controller(page, base_url, ctrl_id)
        page_text = page.inner_text("body")
        assert "Location A" in page_text, \
            "Charlie should see 'Location A' on controller/accueil page"
        page.goto(f"{base_url}/connection/logout.php")

    def test_undiscovered_location_not_on_zones_page(self, page: Page, base_url):
        """Controller Alpha (Agent6, unfound) should NOT see Location A."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"SELECT id FROM `{GAME_PREFIX}controllers` WHERE lastname = 'Alpha'")
        ctrl_id = cursor.fetchone()['id']
        conn.close()

        self._login_and_select_controller(page, base_url, ctrl_id)
        page.goto(f"{base_url}/zones/action.php")
        page.wait_for_load_state("networkidle")
        page_html = page.content()
        assert "Location A" not in page_html, \
            "Alpha (unfound) should NOT see 'Location A' on zones page"
        page.goto(f"{base_url}/connection/logout.php")

    def test_name_only_location_not_on_zones_page(self, page: Page, base_url):
        """Controller Golf (Agent5, name-only) should NOT see Location A on zones page."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"SELECT id FROM `{GAME_PREFIX}controllers` WHERE lastname = 'Golf'")
        ctrl_id = cursor.fetchone()['id']
        conn.close()

        self._login_and_select_controller(page, base_url, ctrl_id)
        page.goto(f"{base_url}/zones/action.php")
        page.wait_for_load_state("networkidle")
        page_html = page.content()
        assert "Location A" not in page_html, \
            "Golf (name-only, not in known_locations) should NOT see Location A on zones page"
        page.goto(f"{base_url}/connection/logout.php")


# ---------------------------------------------------------------------------
# Test: end turn page structure
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestEndTurnPage:
    """Verify end turn page has all expected sections and no fatal errors."""

    def test_end_turn_page_no_fatal_errors(self):
        """The end turn output (stored from fixture) should have no fatal errors.
        Checked via DB: if turncounter incremented, end turn completed."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"SELECT turncounter FROM `{GAME_PREFIX}mechanics`")
        turn = cursor.fetchone()['turncounter']
        conn.close()
        assert turn >= 1, \
            f"Turn counter should be >= 1 after end turn, got {turn}"

    def test_turn_counter_incremented(self):
        """After end turn, turncounter should be 1 (started at 0)."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"SELECT turncounter FROM `{GAME_PREFIX}mechanics`")
        assert cursor.fetchone()['turncounter'] == 1
        conn.close()

    def test_new_turn_actions_created(self):
        """After end turn, new worker_action rows should exist for turn 1."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(
            f"SELECT COUNT(*) as c FROM `{GAME_PREFIX}worker_actions` WHERE turn_number = 1"
        )
        count = cursor.fetchone()['c']
        conn.close()
        assert count >= 7, \
            f"Expected at least 7 action rows for turn 1, got {count}"


# ---------------------------------------------------------------------------
# Test: worker report content — agent detection
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestWorkerReportAgentDetection:
    """Verify agent reports contain correct detection info per REPORTDIFF level.

    REPORTDIFF thresholds: 0=-1, 1=1, 2=2, 3=4.
    Report format per level:
        Diff0: agent name, job, hobby (Spotted <name> a <job> with <hobby>)
        Diff1: same with action details
        Diff2: + network/controller ID
        Diff3: + controller full name
    """

    def test_agent1_report_has_full_details_for_agent7(self):
        """Agent1 (enq=7) vs Agent7 (enq=3): diff=4 >= REPORTDIFF3.
        Report should contain agent name, job, hobby, and controller name."""
        report = get_worker_report('Agent1')
        assert 'Agent7' in report, "Agent1 should see Agent7 in report"
        assert 'Test Hobby Neg' in report, "Agent1 should see Agent7's hobby"
        assert 'Test Metier C' in report, "Agent1 should see Agent7's job"
        assert 'Lady Beta' in report, "Agent1 at REPORTDIFF3 should see controller name"

    def test_agent1_report_has_level2_for_agent6(self):
        """Agent1 (enq=7) vs Agent6 (enq=3): diff=4 >= REPORTDIFF3.
        Should include controller name."""
        report = get_worker_report('Agent1')
        assert 'Agent6' in report, "Agent1 should see Agent6 in report"
        assert 'Lord Alpha' in report, "Agent1 at REPORTDIFF3 should see Alpha controller"

    def test_agent1_report_has_basic_for_agent2(self):
        """Agent1 (enq=7) vs Agent2 (enq=7): diff=0 >= REPORTDIFF0 but < REPORTDIFF1.
        Should show name and basic info only."""
        report = get_worker_report('Agent1')
        assert 'Agent2' in report, "Agent1 should see Agent2 in report"

    def test_agent4_report_does_not_show_agent1(self):
        """Agent4 (enq=5) vs Agent1 (enq=7): diff=-2 < REPORTDIFF0(-1).
        Should NOT appear in report."""
        report = get_worker_report('Agent4')
        assert 'Agent1' not in report, \
            "Agent4 should NOT see Agent1 in report (diff=-2 < REPORTDIFF0)"

    def test_agent5_report_shows_agent4_basic(self):
        """Agent5 (enq=4) vs Agent4 (enq=5): diff=-1 >= REPORTDIFF0.
        Should see basic info."""
        report = get_worker_report('Agent5')
        assert 'Agent4' in report, "Agent5 should see Agent4 in report"

    def test_agent6_report_no_detections(self):
        """Agent6 (enq=3): can only detect agents with enq <= 4 (diff >= -1).
        Should see Agent7(3) and Agent5(4) but NOT Agent1-4."""
        report = get_worker_report('Agent6')
        assert 'Agent1' not in report, "Agent6 should NOT see Agent1"
        assert 'Agent2' not in report, "Agent6 should NOT see Agent2"
        assert 'Agent7' in report, "Agent6 should see Agent7 (diff=0)"


# ---------------------------------------------------------------------------
# Test: worker report content — location discovery
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestWorkerReportLocationDiscovery:
    """Verify agent reports contain correct location info per discovery level."""

    def test_agent1_report_has_location_description_and_secret(self):
        """Agent1 (diff=3 >= ARTEFACTSDIFF=2): should see name, description, and secret."""
        report = get_worker_report('Agent1')
        assert 'Location A' in report, "Agent1 should see location name"
        assert 'test location' in report.lower(), "Agent1 should see description"
        assert 'Secret details' in report, "Agent1 should see hidden_description"

    def test_agent4_report_has_location_description_no_secret(self):
        """Agent4 (diff=1 >= INFORMATIONDIFF=1, < ARTEFACTSDIFF=2): name+desc, no secret."""
        report = get_worker_report('Agent4')
        assert 'Location A' in report, "Agent4 should see location name"
        assert 'test location' in report.lower(), "Agent4 should see description"
        assert 'Secret details' not in report, "Agent4 should NOT see secret"

    def test_agent5_report_has_location_name_only(self):
        """Agent5 (diff=0 >= NAMEDIFF=0, < INFORMATIONDIFF=1): name only."""
        report = get_worker_report('Agent5')
        assert 'Location A' in report, "Agent5 should see location name in report"
        assert 'test location' not in report.lower(), "Agent5 should NOT see description"
        assert 'Secret details' not in report, "Agent5 should NOT see secret"

    def test_agent6_report_has_no_location(self):
        """Agent6 (diff=-1 < NAMEDIFF=0): no location info."""
        report = get_worker_report('Agent6')
        assert 'Location A' not in report, "Agent6 should NOT see location in report"


# ---------------------------------------------------------------------------
# Test: worker view page shows report
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestWorkerViewPageReport:
    """Verify the worker view page renders the report correctly."""

    def _get_worker_page_html(self, page, base_url, controller_lastname, worker_lastname):
        """Login, select controller, navigate to worker page, return HTML."""
        ctrl_id = get_controller_id(controller_lastname)
        worker_id = get_worker_id(worker_lastname)
        page.goto(f"{base_url}/connection/loginForm.php")
        page.wait_for_load_state("networkidle")
        page.locator("input[name='username']").fill("gm")
        page.locator("input[name='passwd']").fill("orga")
        page.locator("input[type='submit']").first.click()
        page.wait_for_load_state("networkidle")
        page.goto(f"{base_url}/base/accueil.php?controller_id={ctrl_id}")
        page.wait_for_load_state("networkidle")
        page.goto(f"{base_url}/workers/action.php?worker_id={worker_id}")
        page.wait_for_load_state("networkidle")
        return page

    def test_agent1_page_no_warnings(self, page: Page, base_url):
        """Agent1 worker page should load without PHP warnings."""
        p = self._get_worker_page_html(page, base_url, 'Charlie', 'Agent1')
        html = p.content()
        assert "<b>Warning</b>" not in html, "PHP warnings on Agent1 worker page"
        assert "<b>Fatal error</b>" not in html, "PHP fatal on Agent1 worker page"
        p.goto(f"{base_url}/connection/logout.php")

    def test_agent1_page_shows_report_section(self, page: Page, base_url):
        """Agent1 worker page should have a Rapport section with Tour 0."""
        p = self._get_worker_page_html(page, base_url, 'Charlie', 'Agent1')
        html = p.content()
        assert "Rapport" in html, "Worker page should have Rapport section"
        assert "Tour 0" in html, "Worker page should show Tour 0 report"
        p.goto(f"{base_url}/connection/logout.php")

    def test_agent1_page_report_shows_detected_agents(self, page: Page, base_url):
        """Agent1 page report should contain detected agent names and controller info."""
        p = self._get_worker_page_html(page, base_url, 'Charlie', 'Agent1')
        html = p.content()
        assert "Agent7" in html, "Agent1 page should show detected Agent7"
        assert "Lady Beta" in html, "Agent1 page should show Agent7's controller"
        assert "Location A" in html, "Agent1 page should show discovered location"
        assert "Secret details" in html, "Agent1 page should show location secret"
        p.goto(f"{base_url}/connection/logout.php")

    def test_agent5_page_shows_name_only_location(self, page: Page, base_url):
        """Agent5 page should show location name but not description."""
        p = self._get_worker_page_html(page, base_url, 'Golf', 'Agent5')
        html = p.content()
        assert "Location A" in html, "Agent5 page should show location name"
        # Description should NOT appear (only name level)
        report_section = html.split("Mes recherches")[1] if "Mes recherches" in html else ""
        assert "test location" not in report_section.lower(), \
            "Agent5 page should NOT show location description in recherches section"
        p.goto(f"{base_url}/connection/logout.php")

    def test_agent6_page_no_location(self, page: Page, base_url):
        """Agent6 page should not mention Location A at all in report."""
        p = self._get_worker_page_html(page, base_url, 'Alpha', 'Agent6')
        html = p.content()
        report_section = html.split("Mes recherches")[1] if "Mes recherches" in html else html
        assert "Location A" not in report_section, \
            "Agent6 page should NOT show Location A in recherches"
        p.goto(f"{base_url}/connection/logout.php")
