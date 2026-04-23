"""Playwright end-to-end tests for agent detection mechanics (Issue #2).

Tests the investigation mechanic: after end turn, verify which agents
detect which other agents and locations based on investigation values.

Test data loaded via TestConfig CSVs (setupTestConfig_advanced.csv).

Agent stats (PASSIVEVAL=3, MINROLL=MAXROLL=3):
    Finder_1 (Charlie):  enquete=7  (passive 3 + power bonus 4)
    Finder_2 (Delta):    enquete=7  (passive 3 + power bonus 4)
    Finder_3 (Echo):     enquete=6  (passive 3 + power bonus 3)
    Finder_4 (Foxtrot):  enquete=5  (passive 3 + power bonus 2)
    Finder_5 (Golf):     enquete=4  (passive 3 + power bonus 1)
    Searcher_1 (Alpha):    enquete=3  (investigate roll 3 + power bonus 0)
    Bystander_1 (Beta):     enquete=3  (passive 3 + power bonus 0, negative defence)

Location A: discovery_diff=4, in Alpha-Investigation.

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

from helpers import (
    DB_AVAILABLE, get_db_connection, load_minimal_data,
    ui_controller_id, ui_worker_id, ui_worker_controller_id,
    ui_known_locations_for_controller,
    ui_known_secret_locations_for_controller,
    ui_worker_stats, ui_turn_counter, ui_detected_enemies_of,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


# ---------------------------------------------------------------------------
# ID caches — populated lazily via UI scrapes, so tests don't need DB
# ---------------------------------------------------------------------------

_wid_cache = {}
_cid_cache = {}


def _cached_wid(page, lastname):
    """Return the ORIGINAL worker_id for `lastname`, scraping once per lastname."""
    if lastname not in _wid_cache:
        _wid_cache[lastname] = ui_worker_id(page, lastname)
    return _wid_cache[lastname]


def _cached_cid(page, lastname):
    """Return the controller_id for `lastname`, scraping once per lastname."""
    if lastname not in _cid_cache:
        _cid_cache[lastname] = ui_controller_id(page, lastname)
    return _cid_cache[lastname]


# ---------------------------------------------------------------------------
# DB-direct helpers — kept ONLY for @pytest.mark.db tests that inspect
# internals with no UI counterpart (raw enquete_val, known_enemies rows,
# JSON fields of worker_actions.report).
# ---------------------------------------------------------------------------

def get_worker_report(worker_lastname, turn=0):
    """Return the report JSON string for a worker at a given turn. DB-direct."""
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


def _worker_report_html(page, worker_lastname, base_url=None):
    """UI counterpart of get_worker_report: navigate as gm, switch to the
    worker's CURRENT controller (may differ post-capture), then open the
    worker action page and return its HTML.

    Uses UI-scraped ids (ui_worker_controller_id + cached worker_id) so
    the call works against a remote deployment without DB access."""
    url = base_url or PHP_BASE_URL
    ensure_gm_login(page, url)
    ctrl_id = ui_worker_controller_id(page, worker_lastname, base_url=url)
    assert ctrl_id, f"Worker {worker_lastname} has no controller"
    page.goto(f"{url}/base/accueil.php?controller_id={ctrl_id}&chosir=Choisir")
    page.wait_for_load_state("networkidle")
    wid = _cached_wid(page, worker_lastname)
    assert wid, f"Worker {worker_lastname} not found"
    page.goto(f"{url}/workers/action.php?worker_id={wid}")
    page.wait_for_load_state("load")
    return page.content()


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
    """Load TestConfig, then trigger end turn to execute mechanics.

    Runs against local Docker and remote prod: local DB-direct seeding is
    skipped when MySQL isn't reachable; the admin-UI scenario load works
    over HTTP regardless.
    """
    if DB_AVAILABLE:
        load_minimal_data()

    # Reset id caches so each module run re-scrapes against the current target.
    _wid_cache.clear()
    _cid_cache.clear()

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

    # Trigger end turn and capture page for warning check
    page.goto(f"{PHP_BASE_URL}/mechanics/endTurn.php")
    page.wait_for_load_state("load", timeout=90000)

    context.close()
    yield


# ---------------------------------------------------------------------------
# Test: end turn completed correctly
# ---------------------------------------------------------------------------

class TestEndTurn:
    """Verify end turn completes without errors and advances game state.

    Per-test markers: DB tests hit mechanics/worker_actions tables directly
    (intermediate mechanic state; no user-facing rendering). The page-level
    smoke check is pure UI and runs under UI_ONLY=1.
    """

    def test_turn_counter_incremented(self, page: Page, base_url):
        """After end turn, turncounter should be 1 (started at 0).

        Reads the page header which shows `{game} : Tour N` — UI-runnable.
        """
        ensure_gm_login(page, base_url)
        assert ui_turn_counter(page, base_url=base_url) == 1

    @pytest.mark.db
    def test_new_turn_actions_created(self):
        """New worker_action rows should exist for turn 1."""
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute(
            f"SELECT COUNT(*) AS c FROM `{GAME_PREFIX}worker_actions` WHERE turn_number = 1"
        )
        count = cursor.fetchone()['c']
        conn.close()
        assert count >= 26, \
            f"Expected at least 26 action rows for turn 1, got {count}"

    def test_end_turn_page_no_warnings(self, page: Page, base_url):
        """Re-visit end turn page and verify no PHP warnings.
        Since turn already advanced, this tests the 'already done' path.
        UI-only smoke check — runs under UI_ONLY=1."""
        ensure_gm_login(page, base_url)
        page.goto(f"{base_url}/mechanics/endTurn.php")
        page.wait_for_load_state("load", timeout=30000)
        html = page.content()
        assert "<b>Warning</b>" not in html, "PHP warnings on end turn page"
        assert "<b>Fatal error</b>" not in html, "PHP fatal error on end turn page"


# ---------------------------------------------------------------------------
# Test: agent detection (calculated values + DB detection + report content)
# ---------------------------------------------------------------------------

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

    # --- Calculated values — scraped from workers/action.php "Changements :" ---

    def test_agent1_calculated_values(self, page: Page, base_url):
        """Finder_1: passive(3) + power(4,3,3) = enq=7, atk=6, def=6."""
        ensure_gm_login(page, base_url)
        vals = ui_worker_stats(page, 'Finder_1', base_url=base_url)
        assert vals == {'enquete_val': 7, 'attack_val': 6, 'defence_val': 6}, \
            f"Finder_1 stats mismatch: {vals}"

    def test_agent2_calculated_values(self, page: Page, base_url):
        """Finder_2: identical to Finder_1 (same powers)."""
        ensure_gm_login(page, base_url)
        vals = ui_worker_stats(page, 'Finder_2', base_url=base_url)
        assert vals == {'enquete_val': 7, 'attack_val': 6, 'defence_val': 6}, \
            f"Finder_2 stats mismatch: {vals}"

    def test_agent3_calculated_values(self, page: Page, base_url):
        """Finder_3: passive(3) + power(3,0,2) = enq=6, atk=3, def=5."""
        ensure_gm_login(page, base_url)
        vals = ui_worker_stats(page, 'Finder_3', base_url=base_url)
        assert vals == {'enquete_val': 6, 'attack_val': 3, 'defence_val': 5}, \
            f"Finder_3 stats mismatch: {vals}"

    def test_agent4_calculated_values(self, page: Page, base_url):
        """Finder_4: passive(3) + power(2,0,0) = enq=5, atk=3, def=3."""
        ensure_gm_login(page, base_url)
        vals = ui_worker_stats(page, 'Finder_4', base_url=base_url)
        assert vals == {'enquete_val': 5, 'attack_val': 3, 'defence_val': 3}, \
            f"Finder_4 stats mismatch: {vals}"

    def test_agent5_calculated_values(self, page: Page, base_url):
        """Finder_5: passive(3) + power(1,5,3) = enq=4, atk=8, def=6."""
        ensure_gm_login(page, base_url)
        vals = ui_worker_stats(page, 'Finder_5', base_url=base_url)
        assert vals == {'enquete_val': 4, 'attack_val': 8, 'defence_val': 6}, \
            f"Finder_5 stats mismatch: {vals}"

    def test_agent6_calculated_values(self, page: Page, base_url):
        """Searcher_1: investigate roll(3) + power(0,0,0) = enq=3, atk=3, def=3."""
        ensure_gm_login(page, base_url)
        vals = ui_worker_stats(page, 'Searcher_1', base_url=base_url)
        assert vals == {'enquete_val': 3, 'attack_val': 3, 'defence_val': 3}, \
            f"Searcher_1 stats mismatch: {vals}"

    def test_agent7_negative_defence(self, page: Page, base_url):
        """Bystander_1: passive(3) + power(0,1,-1) = enq=3, atk=4, def=2."""
        ensure_gm_login(page, base_url)
        vals = ui_worker_stats(page, 'Bystander_1', base_url=base_url)
        assert vals == {'enquete_val': 3, 'attack_val': 4, 'defence_val': 2}, \
            f"Bystander_1 stats mismatch: {vals}"

    # --- DB-level detection: who detects whom ---

    def test_agent1_detects_all_others(self, page: Page, base_url):
        """Finder_1 (enq=7) detects all other agents on different controllers.

        Scraped from `select#enemyWorkersSelect` on the worker action page —
        the UI exposure of controllers_known_enemies filtered by zone."""
        ensure_gm_login(page, base_url)
        detected = ui_detected_enemies_of(page, 'Finder_1', base_url=base_url)
        for agent in ['Finder_2', 'Finder_3', 'Finder_4', 'Finder_5', 'Searcher_1', 'Bystander_1']:
            assert agent in detected, \
                f"Finder_1 (Charlie, enq=7) should detect {agent}; detected={detected}"

    def test_agent2_detects_all_others(self, page: Page, base_url):
        """Finder_2 (enq=7) symmetric with Finder_1 — detects all others."""
        ensure_gm_login(page, base_url)
        detected = ui_detected_enemies_of(page, 'Finder_2', base_url=base_url)
        for agent in ['Finder_1', 'Finder_3', 'Finder_4', 'Finder_5', 'Searcher_1', 'Bystander_1']:
            assert agent in detected, \
                f"Finder_2 (Delta, enq=7) should detect {agent}; detected={detected}"

    def test_agent3_detects_all_within_threshold(self, page: Page, base_url):
        """Finder_3 (enq=6): diff >= -1 for Finder_1(7) and Finder_2(7)."""
        ensure_gm_login(page, base_url)
        detected = ui_detected_enemies_of(page, 'Finder_3', base_url=base_url)
        assert 'Finder_1' in detected, f"Finder_3 should detect Finder_1 (diff=-1); detected={detected}"
        assert 'Finder_2' in detected, f"Finder_3 should detect Finder_2 (diff=-1); detected={detected}"
        assert 'Finder_4' in detected
        assert 'Finder_5' in detected

    def test_agent4_cannot_detect_much_stronger(self, page: Page, base_url):
        """Finder_4 (enq=5): cannot detect Finder_1/2 (diff=-2 < -1)."""
        ensure_gm_login(page, base_url)
        detected = ui_detected_enemies_of(page, 'Finder_4', base_url=base_url)
        assert 'Finder_3' in detected, f"Finder_4 should detect Finder_3; detected={detected}"
        assert 'Finder_5' in detected, f"Finder_4 should detect Finder_5; detected={detected}"
        assert 'Searcher_1' in detected, f"Finder_4 should detect Searcher_1; detected={detected}"
        assert 'Finder_1' not in detected, f"Finder_4 should NOT detect Finder_1 (diff=-2); detected={detected}"
        assert 'Finder_2' not in detected, f"Finder_4 should NOT detect Finder_2 (diff=-2); detected={detected}"

    def test_agent5_detects_weaker_only(self, page: Page, base_url):
        """Finder_5 (enq=4): only detects Finder_4(5), Searcher_1(3), Bystander_1(3)."""
        ensure_gm_login(page, base_url)
        detected = ui_detected_enemies_of(page, 'Finder_5', base_url=base_url)
        assert 'Finder_4' in detected, f"Finder_5 should detect Finder_4; detected={detected}"
        assert 'Searcher_1' in detected, f"Finder_5 should detect Searcher_1; detected={detected}"
        assert 'Finder_1' not in detected, f"Finder_5 should NOT detect Finder_1; detected={detected}"
        assert 'Finder_2' not in detected, f"Finder_5 should NOT detect Finder_2; detected={detected}"
        assert 'Finder_3' not in detected, f"Finder_5 should NOT detect Finder_3; detected={detected}"

    def test_agent6_detects_equal_and_weaker(self, page: Page, base_url):
        """Searcher_1 (enq=3, Alpha): detects Bystander_1(3) and Finder_5(4)."""
        ensure_gm_login(page, base_url)
        detected = ui_detected_enemies_of(page, 'Searcher_1', base_url=base_url)
        assert 'Bystander_1' in detected, f"Searcher_1 should detect Bystander_1; detected={detected}"
        assert 'Finder_5' in detected, f"Searcher_1 should detect Finder_5; detected={detected}"
        assert 'Finder_1' not in detected, f"Searcher_1 should NOT detect Finder_1; detected={detected}"

    def test_agent7_detects_equal_and_weaker(self, page: Page, base_url):
        """Bystander_1 (enq=3, Beta): detects Searcher_1(3) and Finder_5(4)."""
        ensure_gm_login(page, base_url)
        detected = ui_detected_enemies_of(page, 'Bystander_1', base_url=base_url)
        assert 'Searcher_1' in detected, f"Bystander_1 should detect Searcher_1; detected={detected}"
        assert 'Finder_5' in detected, f"Bystander_1 should detect Finder_5; detected={detected}"
        assert 'Finder_1' not in detected, f"Bystander_1 should NOT detect Finder_1; detected={detected}"

    # --- Report content: agent detection (UI-first via rendered worker page) ---

    def test_agent1_report_full_details_for_agent7(self, page: Page, base_url):
        """Finder_1 vs Bystander_1: diff=4 >= REPORTDIFF3. Rendered worker page
        shows name, job, hobby, and controller full name."""
        html = _worker_report_html(page, 'Finder_1', base_url)
        assert 'Bystander_1' in html, "Finder_1 page should render Bystander_1 name"
        assert 'Dark Impulse' in html, "Finder_1 page should render Bystander_1's hobby"
        assert 'Patrol Warden' in html, "Finder_1 page should render Bystander_1's job"
        assert 'Lady Beta' in html, "Finder_1 at REPORTDIFF3 should render controller name"

    def test_agent1_report_full_details_for_agent6(self, page: Page, base_url):
        """Finder_1 vs Searcher_1: diff=4 >= REPORTDIFF3. Page includes controller."""
        html = _worker_report_html(page, 'Finder_1', base_url)
        assert 'Searcher_1' in html
        assert 'Lord Alpha' in html, "Finder_1 page should render Alpha controller name"

    def test_agent1_report_basic_for_agent2(self, page: Page, base_url):
        """Finder_1 vs Finder_2: diff=0 >= REPORTDIFF0 but < REPORTDIFF1. Basic info only."""
        html = _worker_report_html(page, 'Finder_1', base_url)
        assert 'Finder_2' in html, "Finder_1 page should render Finder_2"

    def test_agent4_report_excludes_agent1(self, page: Page, base_url):
        """Finder_4 vs Finder_1: diff=-2 < REPORTDIFF0. Absent from rendered report section."""
        html = _worker_report_html(page, 'Finder_4', base_url)
        # Scope to the report/recherches section so navbar/menu items don't
        # leak — mirrors TestWorkerViewPage split pattern.
        report_section = html.split("Mes recherches")[1] if "Mes recherches" in html else html
        assert 'Finder_1' not in report_section, \
            "Finder_4 report section should NOT mention Finder_1 (diff=-2)"

    def test_agent5_report_shows_agent4(self, page: Page, base_url):
        """Finder_5 vs Finder_4: diff=-1 >= REPORTDIFF0. Basic info in rendered page."""
        html = _worker_report_html(page, 'Finder_5', base_url)
        assert 'Finder_4' in html, "Finder_5 page should render Finder_4"

    def test_agent6_report_sees_agent7_not_agent1(self, page: Page, base_url):
        """Searcher_1: page shows Bystander_1 (diff=0) and Finder_5 (diff=-1),
        and NOT Finder_1/Finder_2 (diff=-4)."""
        html = _worker_report_html(page, 'Searcher_1', base_url)
        assert 'Bystander_1' in html, "Searcher_1 page should render Bystander_1"
        report_section = html.split("Mes recherches")[1] if "Mes recherches" in html else html
        assert 'Finder_1' not in report_section, \
            "Searcher_1 report section should NOT mention Finder_1"
        assert 'Finder_2' not in report_section, \
            "Searcher_1 report section should NOT mention Finder_2"


# ---------------------------------------------------------------------------
# Test: location detection (4 levels + page visibility)
# ---------------------------------------------------------------------------

class TestLocationDetection:
    """Verify location discovery at all 4 levels, in DB, reports, and pages.

    Location A: discovery_diff=4, in Alpha-Investigation.
    Thresholds: LOCATIONNAMEDIFF=0, LOCATIONINFORMATIONDIFF=1, LOCATIONARTEFACTSDIFF=2.

    Level -1 (unfound):  diff < 0 — nothing.
    Level  0 (name):     diff >= 0, < 1 — name in report only, NOT in known_locations.
    Level  1 (desc):     diff >= 1, < 2 — name+desc in report, in known_locations (secret=0).
    Level  2 (secret):   diff >= 2 — all info in report, known_locations (secret=1).
    """

    # --- Level -1: unfound (Searcher_1, enq=3, diff=-1) ---

    def test_unfound_not_in_report(self, page: Page, base_url):
        """Searcher_1 (diff=-1): rendered worker page's research section has no
        location info (UI-first — assertion via rendered HTML, not DB)."""
        html = _worker_report_html(page, 'Searcher_1', base_url)
        report_section = html.split("Mes recherches")[1] if "Mes recherches" in html else html
        assert 'Location A' not in report_section

    def test_unfound_not_in_known_locations(self, page: Page, base_url):
        """Searcher_1 (Alpha): Location A is NOT in Alpha's known-locations set.

        Scraped from /zones/managment_locations.php (admin "Discovered by"
        list). UI-runnable under UI_ONLY=1.
        """
        ensure_gm_login(page, base_url)
        known = ui_known_locations_for_controller(page, 'Alpha')
        assert 'Location A' not in known, \
            f"Alpha should not know Location A, known: {known}"

    # --- Level 0: name only (Finder_5, enq=4, diff=0) ---

    def test_name_only_in_report(self, page: Page, base_url):
        """Finder_5 (diff=0): rendered page shows location name, no description."""
        html = _worker_report_html(page, 'Finder_5', base_url)
        assert 'Location A' in html
        report_section = html.split("Mes recherches")[1] if "Mes recherches" in html else ""
        assert 'test location' not in report_section.lower(), \
            "Report section should NOT render location description at level 0"

    def test_name_only_not_in_known_locations(self, page: Page, base_url):
        """Finder_5 (Golf): NOT in Golf's known-locations set (requires diff>=1).

        Scraped from /zones/managment_locations.php — UI-runnable."""
        ensure_gm_login(page, base_url)
        known = ui_known_locations_for_controller(page, 'Golf')
        assert 'Location A' not in known, \
            f"Golf should not know Location A at diff=0, known: {known}"

    def test_name_only_not_on_zones_page(self, page: Page, base_url):
        """Golf (name-only) should NOT see Location A on zones page."""
        ensure_gm_login(page, base_url)
        page.goto(f"{base_url}/base/accueil.php?controller_id={_cached_cid(page, 'Golf')}")
        page.wait_for_load_state("networkidle")
        page.goto(f"{base_url}/zones/action.php")
        page.wait_for_load_state("networkidle")
        assert "Location A" not in page.content()

    # --- Level 1: description (Finder_4, enq=5, diff=1) ---

    def test_desc_in_report(self, page: Page, base_url):
        """Finder_4 (diff=1): rendered page shows name + description, no secret."""
        html = _worker_report_html(page, 'Finder_4', base_url)
        assert 'Location A' in html
        assert 'test location' in html.lower(), "Page should render description"
        report_section = html.split("Mes recherches")[1] if "Mes recherches" in html else html
        assert 'Secret details' not in report_section, \
            "Report section should NOT render secret at level 1"

    def test_desc_in_known_locations_no_secret(self, page: Page, base_url):
        """Finder_4 (Foxtrot) at diff=1: knows Location A but NOT the secret.

        Both flags scraped from /zones/managment_locations.php via
        data-known / data-secret attributes — UI-runnable.
        """
        ensure_gm_login(page, base_url)
        known = ui_known_locations_for_controller(page, 'Foxtrot')
        assert 'Location A' in known, \
            f"Foxtrot should know Location A at diff=1, known: {known}"
        known_secret = ui_known_secret_locations_for_controller(page, 'Foxtrot')
        assert 'Location A' not in known_secret, \
            f"Foxtrot at diff=1 should NOT have the secret, secret_known: {known_secret}"

    def test_desc_on_zones_page(self, page: Page, base_url):
        """Foxtrot should see Location A on zones page."""
        ensure_gm_login(page, base_url)
        page.goto(f"{base_url}/base/accueil.php?controller_id={_cached_cid(page, 'Foxtrot')}")
        page.wait_for_load_state("networkidle")
        page.goto(f"{base_url}/zones/action.php")
        page.wait_for_load_state("networkidle")
        assert "Location A" in page.content()

    def test_desc_on_controller_page(self, page: Page, base_url):
        """Foxtrot should see Location A on controller/accueil page."""
        ensure_gm_login(page, base_url)
        page.goto(f"{base_url}/base/accueil.php?controller_id={_cached_cid(page, 'Foxtrot')}")
        page.wait_for_load_state("networkidle")
        assert "Location A" in page.inner_text("body") or "Location A" in page.content()

    # --- Level 2: secret (Finder_1, enq=7, diff=3) ---

    def test_secret_in_report(self, page: Page, base_url):
        """Finder_1 (diff=3): rendered page shows name + description + secret."""
        html = _worker_report_html(page, 'Finder_1', base_url)
        assert 'Location A' in html
        assert 'test location' in html.lower()
        assert 'Secret details' in html, "Page should render hidden_description"

    def test_secret_in_known_locations_with_flag(self, page: Page, base_url):
        """Finder_1 (Charlie) at diff=2: knows Location A AND has the secret.

        Both flags scraped from /zones/managment_locations.php via
        data-known / data-secret attributes — UI-runnable.
        """
        ensure_gm_login(page, base_url)
        known = ui_known_locations_for_controller(page, 'Charlie')
        assert 'Location A' in known, \
            f"Charlie should know Location A at diff=2, known: {known}"
        known_secret = ui_known_secret_locations_for_controller(page, 'Charlie')
        assert 'Location A' in known_secret, \
            f"Charlie at diff=2 should have the secret, secret_known: {known_secret}"

    def test_secret_on_zones_page(self, page: Page, base_url):
        """Charlie should see Location A on zones page (in hidden description div)."""
        ensure_gm_login(page, base_url)
        page.goto(f"{base_url}/base/accueil.php?controller_id={_cached_cid(page, 'Charlie')}")
        page.wait_for_load_state("networkidle")
        page.goto(f"{base_url}/zones/action.php")
        page.wait_for_load_state("networkidle")
        assert "Location A" in page.content()

    def test_undiscovered_not_on_zones_page(self, page: Page, base_url):
        """Alpha (unfound) should NOT see Location A on zones page."""
        ensure_gm_login(page, base_url)
        page.goto(f"{base_url}/base/accueil.php?controller_id={_cached_cid(page, 'Alpha')}")
        page.wait_for_load_state("networkidle")
        page.goto(f"{base_url}/zones/action.php")
        page.wait_for_load_state("networkidle")
        assert "Location A" not in page.content()


# ---------------------------------------------------------------------------
# Test: worker view page renders report correctly
# ---------------------------------------------------------------------------

class TestWorkerViewPage:
    """Verify worker view pages render reports correctly for each detection level."""

    def _go_to_worker(self, page, base_url, controller_lastname, worker_lastname):
        """Login if needed, select controller, navigate to worker page."""
        ensure_gm_login(page, base_url)
        ctrl_id = _cached_cid(page, controller_lastname)
        worker_id = _cached_wid(page, worker_lastname)
        page.goto(f"{base_url}/base/accueil.php?controller_id={ctrl_id}")
        page.wait_for_load_state("networkidle")
        page.goto(f"{base_url}/workers/action.php?worker_id={worker_id}")
        page.wait_for_load_state("networkidle")

    def test_agent1_page_no_warnings(self, page: Page, base_url):
        """Finder_1 worker page loads without PHP warnings."""
        self._go_to_worker(page, base_url, 'Charlie', 'Finder_1')
        html = page.content()
        assert "<b>Warning</b>" not in html
        assert "<b>Fatal error</b>" not in html

    def test_agent1_page_has_report(self, page: Page, base_url):
        """Finder_1 page has Rapport section with Tour 0."""
        self._go_to_worker(page, base_url, 'Charlie', 'Finder_1')
        html = page.content()
        assert "Rapport" in html
        assert "Tour 0" in html

    def test_agent1_page_shows_detected_agents_and_location(self, page: Page, base_url):
        """Finder_1 page shows detected Bystander_1, controller name, location + secret."""
        self._go_to_worker(page, base_url, 'Charlie', 'Finder_1')
        html = page.content()
        assert "Bystander_1" in html
        assert "Lady Beta" in html
        assert "Location A" in html
        assert "Secret details" in html

    def test_agent4_page_shows_location_desc_no_secret(self, page: Page, base_url):
        """Finder_4 page shows Location A with description but no secret."""
        self._go_to_worker(page, base_url, 'Foxtrot', 'Finder_4')
        html = page.content()
        assert "Location A" in html
        report_section = html.split("Mes recherches")[1] if "Mes recherches" in html else html
        assert "Secret details" not in report_section

    def test_agent5_page_shows_location_name_only(self, page: Page, base_url):
        """Finder_5 page shows location name but not description."""
        self._go_to_worker(page, base_url, 'Golf', 'Finder_5')
        html = page.content()
        assert "Location A" in html
        report_section = html.split("Mes recherches")[1] if "Mes recherches" in html else ""
        assert "test location" not in report_section.lower()

    def test_agent6_page_no_location(self, page: Page, base_url):
        """Searcher_1 page has no mention of Location A in recherches."""
        self._go_to_worker(page, base_url, 'Alpha', 'Searcher_1')
        html = page.content()
        report_section = html.split("Mes recherches")[1] if "Mes recherches" in html else html
        assert "Location A" not in report_section

    def test_agent4_page_no_warnings(self, page: Page, base_url):
        """Finder_4 worker page loads without PHP warnings."""
        self._go_to_worker(page, base_url, 'Foxtrot', 'Finder_4')
        html = page.content()
        assert "<b>Warning</b>" not in html
        assert "<b>Fatal error</b>" not in html
