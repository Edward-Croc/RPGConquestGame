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
    PHP_BASE_URL, ensure_gm_login,
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


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

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
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute(f"SELECT id FROM `{GAME_PREFIX}workers` WHERE lastname = %s", (worker_lastname,))
    row = cursor.fetchone()
    conn.close()
    return row['id'] if row else None


def get_controller_id(controller_lastname):
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute(f"SELECT id FROM `{GAME_PREFIX}controllers` WHERE lastname = %s", (controller_lastname,))
    row = cursor.fetchone()
    conn.close()
    return row['id'] if row else None


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
        SELECT l.name, kl.found_secret
        FROM `{GAME_PREFIX}controller_known_locations` kl
        JOIN `{GAME_PREFIX}controllers` c ON c.id = kl.controller_id
        JOIN `{GAME_PREFIX}locations` l ON l.id = kl.location_id
        WHERE c.lastname = %s
    """, (controller_lastname,))
    result = cursor.fetchall()
    conn.close()
    return result


def get_calculated_values(turn=0):
    """Return dict of agent lastname -> {enquete_val, attack_val, defence_val}."""
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute(f"""
        SELECT w.lastname, wa.enquete_val, wa.attack_val, wa.defence_val
        FROM `{GAME_PREFIX}worker_actions` wa
        JOIN `{GAME_PREFIX}workers` w ON w.id = wa.worker_id
        WHERE wa.turn_number = %s
        ORDER BY w.lastname
    """, (turn,))
    vals = {r['lastname']: r for r in cursor.fetchall()}
    conn.close()
    return vals


# ---------------------------------------------------------------------------
# Module fixture: load TestConfig + run end turn once
# ---------------------------------------------------------------------------

@pytest.fixture(scope="module", autouse=True)
def load_and_end_turn(browser):
    """Load TestConfig, then trigger end turn to execute mechanics."""
    if not DB_AVAILABLE:
        yield
        return

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
    page.locator("input[name='submit'][value='Submit']").click(no_wait_after=True)
    page.wait_for_timeout(5000)
    page.wait_for_load_state("load", timeout=90000)

    # Trigger end turn and capture page for warning check
    page.goto(f"{PHP_BASE_URL}/mechanics/endTurn.php")
    page.wait_for_load_state("load", timeout=90000)

    page.goto(f"{PHP_BASE_URL}/connection/logout.php")
    page.wait_for_load_state("networkidle")
    context.close()
    yield


@pytest.fixture(autouse=True)
def _require_db():
    if not DB_AVAILABLE:
        pytest.skip("No local MySQL available")


# ---------------------------------------------------------------------------
# Test: end turn completed correctly
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestEndTurn:
    """Verify end turn completes without errors and advances game state."""

    def test_turn_counter_incremented(self):
        """After end turn, turncounter should be 1 (started at 0)."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(f"SELECT turncounter FROM `{GAME_PREFIX}mechanics`")
        assert cursor.fetchone()['turncounter'] == 1
        conn.close()

    def test_new_turn_actions_created(self):
        """New worker_action rows should exist for turn 1."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(
            f"SELECT COUNT(*) AS c FROM `{GAME_PREFIX}worker_actions` WHERE turn_number = 1"
        )
        count = cursor.fetchone()['c']
        conn.close()
        assert count >= 7, \
            f"Expected at least 7 action rows for turn 1, got {count}"

    def test_end_turn_page_no_warnings(self, page: Page, base_url):
        """Re-visit end turn page and verify no PHP warnings.
        Since turn already advanced, this tests the 'already done' path."""
        ensure_gm_login(page, base_url)
        page.goto(f"{base_url}/mechanics/endTurn.php")
        page.wait_for_load_state("load", timeout=30000)
        html = page.content()
        assert "<b>Warning</b>" not in html, "PHP warnings on end turn page"
        assert "<b>Fatal error</b>" not in html, "PHP fatal error on end turn page"


# ---------------------------------------------------------------------------
# Test: agent detection (calculated values + DB detection + report content)
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestAgentDetection:
    """Verify investigation mechanic: calculated values, who detects whom,
    and that agent reports contain correct detection info.

    Detection threshold REPORTDIFF0 = -1 (inclusive >=).
    Agents only detect workers from OTHER controllers.

    Report detail levels per REPORTDIFF:
        0 (-1): agent name, job, hobby
        1 (1):  + action details
        2 (2):  + network/controller ID
        3 (4):  + controller full name
    """

    # --- Calculated values ---

    def test_agent1_calculated_values(self):
        """Agent1: passive(3) + power(4,3,3) = enq=7, atk=6, def=6."""
        vals = get_calculated_values()
        assert vals['Agent1']['enquete_val'] == 7
        assert vals['Agent1']['attack_val'] == 6
        assert vals['Agent1']['defence_val'] == 6

    def test_agent2_calculated_values(self):
        """Agent2: identical to Agent1 (same powers)."""
        vals = get_calculated_values()
        assert vals['Agent2']['enquete_val'] == 7
        assert vals['Agent2']['attack_val'] == 6
        assert vals['Agent2']['defence_val'] == 6

    def test_agent3_calculated_values(self):
        """Agent3: passive(3) + power(3,0,2) = enq=6, atk=3, def=5."""
        vals = get_calculated_values()
        assert vals['Agent3']['enquete_val'] == 6
        assert vals['Agent3']['attack_val'] == 3
        assert vals['Agent3']['defence_val'] == 5

    def test_agent4_calculated_values(self):
        """Agent4: passive(3) + power(2,0,0) = enq=5, atk=3, def=3."""
        vals = get_calculated_values()
        assert vals['Agent4']['enquete_val'] == 5
        assert vals['Agent4']['attack_val'] == 3
        assert vals['Agent4']['defence_val'] == 3

    def test_agent5_calculated_values(self):
        """Agent5: passive(3) + power(1,5,3) = enq=4, atk=8, def=6."""
        vals = get_calculated_values()
        assert vals['Agent5']['enquete_val'] == 4
        assert vals['Agent5']['attack_val'] == 8
        assert vals['Agent5']['defence_val'] == 6

    def test_agent6_calculated_values(self):
        """Agent6: investigate roll(3) + power(0,0,0) = enq=3, atk=3, def=3."""
        vals = get_calculated_values()
        assert vals['Agent6']['enquete_val'] == 3
        assert vals['Agent6']['attack_val'] == 3
        assert vals['Agent6']['defence_val'] == 3

    def test_agent7_negative_defence(self):
        """Agent7: passive(3) + power(0,1,-1) = enq=3, atk=4, def=2."""
        vals = get_calculated_values()
        assert vals['Agent7']['enquete_val'] == 3
        assert vals['Agent7']['attack_val'] == 4
        assert vals['Agent7']['defence_val'] == 2

    # --- DB-level detection: who detects whom ---

    def test_agent1_detects_all_others(self):
        """Agent1 (enq=7) detects all other agents on different controllers."""
        detected = get_detections_for_controller('Charlie')
        for agent in ['Agent2', 'Agent3', 'Agent4', 'Agent5', 'Agent6', 'Agent7']:
            assert agent in detected, \
                f"Agent1 (Charlie, enq=7) should detect {agent}"

    def test_agent2_detects_all_others(self):
        """Agent2 (enq=7) symmetric with Agent1 — detects all others."""
        detected = get_detections_for_controller('Delta')
        for agent in ['Agent1', 'Agent3', 'Agent4', 'Agent5', 'Agent6', 'Agent7']:
            assert agent in detected, \
                f"Agent2 (Delta, enq=7) should detect {agent}"

    def test_agent3_detects_all_within_threshold(self):
        """Agent3 (enq=6): diff >= -1 for Agent1(7) and Agent2(7)."""
        detected = get_detections_for_controller('Echo')
        assert 'Agent1' in detected, "Agent3 should detect Agent1 (diff=-1 >= -1)"
        assert 'Agent2' in detected, "Agent3 should detect Agent2 (diff=-1 >= -1)"
        assert 'Agent4' in detected
        assert 'Agent5' in detected

    def test_agent4_cannot_detect_much_stronger(self):
        """Agent4 (enq=5): cannot detect Agent1/2 (diff=-2 < -1)."""
        detected = get_detections_for_controller('Foxtrot')
        assert 'Agent3' in detected, "Agent4 should detect Agent3 (diff=-1)"
        assert 'Agent5' in detected, "Agent4 should detect Agent5 (diff=1)"
        assert 'Agent6' in detected, "Agent4 should detect Agent6 (diff=2)"
        assert 'Agent1' not in detected, "Agent4 should NOT detect Agent1 (diff=-2)"
        assert 'Agent2' not in detected, "Agent4 should NOT detect Agent2 (diff=-2)"

    def test_agent5_detects_weaker_only(self):
        """Agent5 (enq=4): only detects Agent4(5), Agent6(3), Agent7(3)."""
        detected = get_detections_for_controller('Golf')
        assert 'Agent4' in detected, "Agent5 should detect Agent4 (diff=-1)"
        assert 'Agent6' in detected, "Agent5 should detect Agent6 (diff=1)"
        assert 'Agent1' not in detected, "Agent5 should NOT detect Agent1 (diff=-3)"
        assert 'Agent2' not in detected, "Agent5 should NOT detect Agent2 (diff=-3)"
        assert 'Agent3' not in detected, "Agent5 should NOT detect Agent3 (diff=-2)"

    def test_agent6_detects_equal_and_weaker(self):
        """Agent6 (enq=3, Alpha): detects Agent7(3) and Agent5(4)."""
        detected = get_detections_for_controller('Alpha')
        assert 'Agent7' in detected, "Agent6 should detect Agent7 (diff=0)"
        assert 'Agent5' in detected, "Agent6 should detect Agent5 (diff=-1)"
        assert 'Agent1' not in detected, "Agent6 should NOT detect Agent1 (diff=-4)"

    def test_agent7_detects_equal_and_weaker(self):
        """Agent7 (enq=3, Beta): detects Agent6(3) and Agent5(4)."""
        detected = get_detections_for_controller('Beta')
        assert 'Agent6' in detected, "Agent7 should detect Agent6 (diff=0)"
        assert 'Agent5' in detected, "Agent7 should detect Agent5 (diff=-1)"
        assert 'Agent1' not in detected, "Agent7 should NOT detect Agent1 (diff=-4)"

    # --- Report content: agent detection ---

    def test_agent1_report_full_details_for_agent7(self):
        """Agent1 vs Agent7: diff=4 >= REPORTDIFF3. Report has name, job, hobby, controller."""
        report = get_worker_report('Agent1')
        assert 'Agent7' in report, "Agent1 should see Agent7 name"
        assert 'Test Hobby Neg' in report, "Agent1 should see Agent7's hobby"
        assert 'Test Metier C' in report, "Agent1 should see Agent7's job"
        assert 'Lady Beta' in report, "Agent1 at REPORTDIFF3 should see controller name"

    def test_agent1_report_full_details_for_agent6(self):
        """Agent1 vs Agent6: diff=4 >= REPORTDIFF3. Should include controller."""
        report = get_worker_report('Agent1')
        assert 'Agent6' in report
        assert 'Lord Alpha' in report, "Agent1 should see Alpha controller name"

    def test_agent1_report_basic_for_agent2(self):
        """Agent1 vs Agent2: diff=0 >= REPORTDIFF0 but < REPORTDIFF1. Basic info only."""
        report = get_worker_report('Agent1')
        assert 'Agent2' in report, "Agent1 should see Agent2 in report"

    def test_agent4_report_excludes_agent1(self):
        """Agent4 vs Agent1: diff=-2 < REPORTDIFF0. Not in report."""
        report = get_worker_report('Agent4')
        assert 'Agent1' not in report, "Agent4 should NOT see Agent1 (diff=-2)"

    def test_agent5_report_shows_agent4(self):
        """Agent5 vs Agent4: diff=-1 >= REPORTDIFF0. Basic info."""
        report = get_worker_report('Agent5')
        assert 'Agent4' in report, "Agent5 should see Agent4"

    def test_agent6_report_sees_agent7_not_agent1(self):
        """Agent6: sees Agent7 (diff=0) and Agent5 (diff=-1), not Agent1 (diff=-4)."""
        report = get_worker_report('Agent6')
        assert 'Agent7' in report, "Agent6 should see Agent7"
        assert 'Agent1' not in report, "Agent6 should NOT see Agent1"
        assert 'Agent2' not in report, "Agent6 should NOT see Agent2"


# ---------------------------------------------------------------------------
# Test: location detection (4 levels + page visibility)
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestLocationDetection:
    """Verify location discovery at all 4 levels, in DB, reports, and pages.

    Location A: discovery_diff=4, in ZoneA.
    Thresholds: LOCATIONNAMEDIFF=0, LOCATIONINFORMATIONDIFF=1, LOCATIONARTEFACTSDIFF=2.

    Level -1 (unfound):  diff < 0 — nothing.
    Level  0 (name):     diff >= 0, < 1 — name in report only, NOT in known_locations.
    Level  1 (desc):     diff >= 1, < 2 — name+desc in report, in known_locations (secret=0).
    Level  2 (secret):   diff >= 2 — all info in report, known_locations (secret=1).
    """

    # --- Level -1: unfound (Agent6, enq=3, diff=-1) ---

    def test_unfound_not_in_report(self):
        """Agent6 (diff=-1): no location info in report."""
        report = get_worker_report('Agent6')
        assert 'Location A' not in report

    def test_unfound_not_in_known_locations(self):
        """Agent6: not in controller_known_locations."""
        locs = get_location_discoveries_for_controller('Alpha')
        assert not any(r['name'] == 'Location A' for r in locs)

    # --- Level 0: name only (Agent5, enq=4, diff=0) ---

    def test_name_only_in_report(self):
        """Agent5 (diff=0): location name in report, no description."""
        report = get_worker_report('Agent5')
        assert 'Location A' in report
        assert 'test location' not in report.lower(), "Should NOT see description"

    def test_name_only_not_in_known_locations(self):
        """Agent5: NOT in controller_known_locations (requires diff>=1)."""
        locs = get_location_discoveries_for_controller('Golf')
        assert not any(r['name'] == 'Location A' for r in locs)

    def test_name_only_not_on_zones_page(self, page: Page, base_url):
        """Golf (name-only) should NOT see Location A on zones page."""
        ensure_gm_login(page, base_url)
        page.goto(f"{base_url}/base/accueil.php?controller_id={get_controller_id('Golf')}")
        page.wait_for_load_state("networkidle")
        page.goto(f"{base_url}/zones/action.php")
        page.wait_for_load_state("networkidle")
        assert "Location A" not in page.content()

    # --- Level 1: description (Agent4, enq=5, diff=1) ---

    def test_desc_in_report(self):
        """Agent4 (diff=1): name + description in report, no secret."""
        report = get_worker_report('Agent4')
        assert 'Location A' in report
        assert 'test location' in report.lower(), "Should see description"
        assert 'Secret details' not in report, "Should NOT see secret"

    def test_desc_in_known_locations_no_secret(self):
        """Agent4: in controller_known_locations with found_secret=0."""
        locs = get_location_discoveries_for_controller('Foxtrot')
        match = [r for r in locs if r['name'] == 'Location A']
        assert len(match) == 1, "Foxtrot should have Location A in known_locations"
        assert match[0]['found_secret'] == 0, "found_secret should be 0 at level 1"

    def test_desc_on_zones_page(self, page: Page, base_url):
        """Foxtrot should see Location A on zones page."""
        ensure_gm_login(page, base_url)
        page.goto(f"{base_url}/base/accueil.php?controller_id={get_controller_id('Foxtrot')}")
        page.wait_for_load_state("networkidle")
        page.goto(f"{base_url}/zones/action.php")
        page.wait_for_load_state("networkidle")
        assert "Location A" in page.content()

    def test_desc_on_controller_page(self, page: Page, base_url):
        """Foxtrot should see Location A on controller/accueil page."""
        ensure_gm_login(page, base_url)
        page.goto(f"{base_url}/base/accueil.php?controller_id={get_controller_id('Foxtrot')}")
        page.wait_for_load_state("networkidle")
        assert "Location A" in page.inner_text("body") or "Location A" in page.content()

    # --- Level 2: secret (Agent1, enq=7, diff=3) ---

    def test_secret_in_report(self):
        """Agent1 (diff=3): name + description + secret in report."""
        report = get_worker_report('Agent1')
        assert 'Location A' in report
        assert 'test location' in report.lower()
        assert 'Secret details' in report, "Should see hidden_description"

    def test_secret_in_known_locations_with_flag(self):
        """Agent1: in controller_known_locations with found_secret=1."""
        locs = get_location_discoveries_for_controller('Charlie')
        match = [r for r in locs if r['name'] == 'Location A']
        assert len(match) == 1
        assert match[0]['found_secret'] == 1, "found_secret should be 1 at level 2"

    def test_secret_on_zones_page(self, page: Page, base_url):
        """Charlie should see Location A on zones page (in hidden description div)."""
        ensure_gm_login(page, base_url)
        page.goto(f"{base_url}/base/accueil.php?controller_id={get_controller_id('Charlie')}")
        page.wait_for_load_state("networkidle")
        page.goto(f"{base_url}/zones/action.php")
        page.wait_for_load_state("networkidle")
        assert "Location A" in page.content()

    def test_undiscovered_not_on_zones_page(self, page: Page, base_url):
        """Alpha (unfound) should NOT see Location A on zones page."""
        ensure_gm_login(page, base_url)
        page.goto(f"{base_url}/base/accueil.php?controller_id={get_controller_id('Alpha')}")
        page.wait_for_load_state("networkidle")
        page.goto(f"{base_url}/zones/action.php")
        page.wait_for_load_state("networkidle")
        assert "Location A" not in page.content()


# ---------------------------------------------------------------------------
# Test: worker view page renders report correctly
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestWorkerViewPage:
    """Verify worker view pages render reports correctly for each detection level."""

    def _go_to_worker(self, page, base_url, controller_lastname, worker_lastname):
        """Login if needed, select controller, navigate to worker page."""
        ensure_gm_login(page, base_url)
        ctrl_id = get_controller_id(controller_lastname)
        worker_id = get_worker_id(worker_lastname)
        page.goto(f"{base_url}/base/accueil.php?controller_id={ctrl_id}")
        page.wait_for_load_state("networkidle")
        page.goto(f"{base_url}/workers/action.php?worker_id={worker_id}")
        page.wait_for_load_state("networkidle")

    def test_agent1_page_no_warnings(self, page: Page, base_url):
        """Agent1 worker page loads without PHP warnings."""
        self._go_to_worker(page, base_url, 'Charlie', 'Agent1')
        html = page.content()
        assert "<b>Warning</b>" not in html
        assert "<b>Fatal error</b>" not in html

    def test_agent1_page_has_report(self, page: Page, base_url):
        """Agent1 page has Rapport section with Tour 0."""
        self._go_to_worker(page, base_url, 'Charlie', 'Agent1')
        html = page.content()
        assert "Rapport" in html
        assert "Tour 0" in html

    def test_agent1_page_shows_detected_agents_and_location(self, page: Page, base_url):
        """Agent1 page shows detected Agent7, controller name, location + secret."""
        self._go_to_worker(page, base_url, 'Charlie', 'Agent1')
        html = page.content()
        assert "Agent7" in html
        assert "Lady Beta" in html
        assert "Location A" in html
        assert "Secret details" in html

    def test_agent4_page_shows_location_desc_no_secret(self, page: Page, base_url):
        """Agent4 page shows Location A with description but no secret."""
        self._go_to_worker(page, base_url, 'Foxtrot', 'Agent4')
        html = page.content()
        assert "Location A" in html
        report_section = html.split("Mes recherches")[1] if "Mes recherches" in html else html
        assert "Secret details" not in report_section

    def test_agent5_page_shows_location_name_only(self, page: Page, base_url):
        """Agent5 page shows location name but not description."""
        self._go_to_worker(page, base_url, 'Golf', 'Agent5')
        html = page.content()
        assert "Location A" in html
        report_section = html.split("Mes recherches")[1] if "Mes recherches" in html else ""
        assert "test location" not in report_section.lower()

    def test_agent6_page_no_location(self, page: Page, base_url):
        """Agent6 page has no mention of Location A in recherches."""
        self._go_to_worker(page, base_url, 'Alpha', 'Agent6')
        html = page.content()
        report_section = html.split("Mes recherches")[1] if "Mes recherches" in html else html
        assert "Location A" not in report_section

    def test_agent4_page_no_warnings(self, page: Page, base_url):
        """Agent4 worker page loads without PHP warnings."""
        self._go_to_worker(page, base_url, 'Foxtrot', 'Agent4')
        html = page.content()
        assert "<b>Warning</b>" not in html
        assert "<b>Fatal error</b>" not in html
