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
from playwright.sync_api import Page, expect

from conftest import (
    GAME_PREFIX, MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB,
    PHP_BASE_URL, ensure_gm_login,
)

from helpers import (
    DB_AVAILABLE, get_db_connection, load_minimal_data, load_scenario_via_admin,
    ui_controller_id, ui_worker_id, ui_worker_controller_id, ui_zone_id,
    ui_known_locations_for_controller,
    ui_known_secret_locations_for_controller,
    ui_worker_stats, ui_turn_counter, ui_detected_enemies_of,
    safe_goto, register_php_error_listener, assert_no_collected_php_errors,
    end_turn, ui_move, ui_teach_discipline_click,
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
    safe_goto(page, f"{url}/base/accueil.php?controller_id={ctrl_id}&chosir=Choisir")
    page.wait_for_load_state("networkidle")
    wid = _cached_wid(page, worker_lastname)
    assert wid, f"Worker {worker_lastname} not found"
    safe_goto(page, f"{url}/workers/action.php?worker_id={wid}")
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
    """Load TestConfig, then trigger end turn to execute mechanics."""
    if DB_AVAILABLE:
        load_minimal_data()
    _wid_cache.clear()
    _cid_cache.clear()
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")

    context = browser.new_context()
    page = context.new_page()
    register_php_error_listener(page)
    ensure_gm_login(page, PHP_BASE_URL)
    safe_goto(page, f"{PHP_BASE_URL}/mechanics/endTurn.php")
    page.wait_for_load_state("load", timeout=90000)
    assert_no_collected_php_errors(page)
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
        """Finder_1: passive(3) + power(4,3,3) = enq=7, atk=6, def=6.
        Spot-check the top-end stat calc; middle agents (Finder_2..Searcher_1)
        exercise the same code path."""
        ensure_gm_login(page, base_url)
        vals = ui_worker_stats(page, 'Finder_1', base_url=base_url)
        assert vals == {'enquete_val': 7, 'attack_val': 6, 'defence_val': 6}, \
            f"Finder_1 stats mismatch: {vals}"

    def test_agent7_negative_defence(self, page: Page, base_url):
        """Bystander_1: passive(3) + power(0,1,-1) = enq=3, atk=4, def=2.
        Negative-power-stat edge case — the only meaningful variant of the
        same calc."""
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

        Scraped from /zones/management_locations.php (admin "Discovered by"
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

        Scraped from /zones/management_locations.php — UI-runnable."""
        ensure_gm_login(page, base_url)
        known = ui_known_locations_for_controller(page, 'Golf')
        assert 'Location A' not in known, \
            f"Golf should not know Location A at diff=0, known: {known}"

    def test_name_only_not_on_zones_page(self, page: Page, base_url):
        """Golf (name-only) should NOT see Location A on zones page."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/accueil.php?controller_id={_cached_cid(page, 'Golf')}")
        page.wait_for_load_state("networkidle")
        safe_goto(page, f"{base_url}/zones/action.php")
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

        Both flags scraped from /zones/management_locations.php via
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
        safe_goto(page, f"{base_url}/base/accueil.php?controller_id={_cached_cid(page, 'Foxtrot')}")
        page.wait_for_load_state("networkidle")
        safe_goto(page, f"{base_url}/zones/action.php")
        page.wait_for_load_state("networkidle")
        assert "Location A" in page.content()

    def test_desc_on_controller_page(self, page: Page, base_url):
        """Foxtrot should see Location A on controller/accueil page."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/accueil.php?controller_id={_cached_cid(page, 'Foxtrot')}")
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

        Both flags scraped from /zones/management_locations.php via
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
        safe_goto(page, f"{base_url}/base/accueil.php?controller_id={_cached_cid(page, 'Charlie')}")
        page.wait_for_load_state("networkidle")
        safe_goto(page, f"{base_url}/zones/action.php")
        page.wait_for_load_state("networkidle")
        assert "Location A" in page.content()

    def test_undiscovered_not_on_zones_page(self, page: Page, base_url):
        """Alpha (unfound) should NOT see Location A on zones page."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/accueil.php?controller_id={_cached_cid(page, 'Alpha')}")
        page.wait_for_load_state("networkidle")
        safe_goto(page, f"{base_url}/zones/action.php")
        page.wait_for_load_state("networkidle")
        assert "Location A" not in page.content()

    # --- Artefact visibility (requires enquete_difference >= LOCATIONARTEFACTSDIFF=2) ---

    def test_artefact_visible_at_secret_level(self, page: Page, base_url):
        """Finder_1 (Charlie, enq=7, diff=3) has enquete_difference >=
        LOCATIONARTEFACTSDIFF (2), so their secrets_report lists the
        artefact linked to Location A (Artefact Alpha, loaded from
        setupTestConfig_artefacts.csv)."""
        html = _worker_report_html(page, 'Finder_1', base_url)
        assert 'Location A' in html, "Prerequisite: Finder_1 must see Location A"
        assert 'Artefact Alpha' in html, \
            "Finder_1 at enquete_difference=3 should see the artefact name in report"

    def test_artefact_visible_at_threshold(self, page: Page, base_url):
        """Finder_3 (Echo, enq=6, diff=2) is at exactly LOCATIONARTEFACTSDIFF (2),
        the inclusive threshold — the artefact MUST be visible."""
        html = _worker_report_html(page, 'Finder_3', base_url)
        assert 'Location A' in html, "Prerequisite: Finder_3 must see Location A"
        assert 'Artefact Alpha' in html, \
            "Finder_3 at enquete_difference=2 (== LOCATIONARTEFACTSDIFF) should see the artefact"

    def test_artefact_not_visible_at_desc_level(self, page: Page, base_url):
        """Finder_4 (Foxtrot, enq=5, diff=1) sees NAME + DESCRIPTION but
        enquete_difference=1 < LOCATIONARTEFACTSDIFF (2), so the
        artefact name must NOT appear in their report."""
        html = _worker_report_html(page, 'Finder_4', base_url)
        assert 'Location A' in html, "Prerequisite: Finder_4 must still see Location A"
        assert 'test location' in html.lower(), \
            "Prerequisite: Finder_4 must see the description"
        assert 'Artefact Alpha' not in html, \
            "Finder_4 at enquete_difference=1 must NOT see the artefact"

    def test_artefact_not_visible_at_name_only_level(self, page: Page, base_url):
        """Finder_5 (Golf, enq=4, diff=0) sees only the location NAME;
        artefact visibility requires enquete_difference >= 2 so it must
        not appear."""
        html = _worker_report_html(page, 'Finder_5', base_url)
        assert 'Artefact Alpha' not in html, \
            "Finder_5 at enquete_difference=0 must NOT see the artefact"


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
        safe_goto(page, f"{base_url}/base/accueil.php?controller_id={ctrl_id}")
        page.wait_for_load_state("networkidle")
        safe_goto(page, f"{base_url}/workers/action.php?worker_id={worker_id}")
        page.wait_for_load_state("networkidle")

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


# ---------------------------------------------------------------------------
# Issue #63: investigation/location-search report redundancy on repeat turn
# ---------------------------------------------------------------------------


def _investigation_section_html(html: str) -> str:
    """Slice the HTML between the 'Mes investigations' h4 header and the next h4."""
    start = html.find("Mes investigations")
    if start < 0:
        return ""
    next_h4 = html.find("<h4", start + 1)
    return html[start:next_h4] if next_h4 > 0 else html[start:]


def _recherches_section_html(html: str) -> str:
    """Slice the HTML between the 'Mes recherches' h4 header and the next h4."""
    start = html.find("Mes recherches")
    if start < 0:
        return ""
    next_h4 = html.find("<h4", start + 1)
    return html[start:next_h4] if next_h4 > 0 else html[start:]


class TestReportRedundancy:
    """After a 2nd EOT, repeat investigations on previously-known targets
    collapse into `<details>` folds, and the artefact list stays visible
    OUTSIDE any fold. Class-scoped fixture runs the extra EOT once."""

    @pytest.fixture(scope="class", autouse=True)
    def second_end_turn(self, browser):
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)
        end_turn(page, base_url=PHP_BASE_URL)
        assert_no_collected_php_errors(page)
        context.close()
        yield

    def _go_to_worker(self, page, base_url, controller_lastname, worker_lastname):
        ensure_gm_login(page, base_url)
        ctrl_id = _cached_cid(page, controller_lastname)
        worker_id = _cached_wid(page, worker_lastname)
        safe_goto(page, f"{base_url}/base/accueil.php?controller_id={ctrl_id}")
        page.wait_for_load_state("networkidle")
        safe_goto(page, f"{base_url}/workers/action.php?worker_id={worker_id}")
        page.wait_for_load_state("load")

    def test_agent_still_here_template_appears(self, page: Page, base_url):
        """Searcher_1 re-investigates Alpha-Investigation; the still-here
        template must surface in its 'Mes investigations' section."""
        self._go_to_worker(page, base_url, 'Alpha', 'Searcher_1')
        section = _investigation_section_html(page.content())
        assert section, "Searcher_1 should have a 'Mes investigations' section"
        assert "est toujours présent" in section, (
            f"Expected 'still here' template in repeat report; got: {section[:600]}"
        )

    def test_agent_click_summary_reveals_folded_slabs(self, page: Page, base_url):
        """Clicking the still-here summary expands the fold body."""
        self._go_to_worker(page, base_url, 'Alpha', 'Searcher_1')
        summary = (
            page.locator("details summary")
            .filter(has_text="est toujours présent")
            .first
        )
        expect(summary).to_be_visible()
        details_handle = summary.locator("xpath=..")
        closed_inner = (summary.inner_text() or "").strip()
        summary.click()
        full_text = (details_handle.text_content() or "").strip()
        assert len(full_text) > len(closed_inner), (
            "Click should reveal more text inside the fold; "
            f"summary='{closed_inner[:120]}' body='{full_text[:300]}'"
        )

    def test_first_turn_paragraph_before_any_details(self, page: Page, base_url):
        """Turn 1's full slabs stay in a `<p>` BEFORE turn 2's `<details>`."""
        self._go_to_worker(page, base_url, 'Alpha', 'Searcher_1')
        section = _investigation_section_html(page.content())
        lower = section.lower()
        first_details = lower.find("<details>")
        first_p_close = lower.find("</p>", lower.find("mes investigations"))
        assert first_p_close >= 0, "Investigation section should contain a closing </p>"
        if first_details > 0:
            assert first_p_close < first_details, (
                "Turn 1's full-text slabs must close their </p> BEFORE turn 2's <details> opens"
            )

    def test_location_still_here_template_appears(self, page: Page, base_url):
        """Artefact_Searcher_Echo's 'Mes recherches' section shows the
        location still-here template on the repeat turn."""
        self._go_to_worker(page, base_url, 'Echo', 'Artefact_Searcher_Echo')
        section = _recherches_section_html(page.content())
        assert section, "Artefact_Searcher_Echo should have a 'Mes recherches' section"
        assert "est toujours là" in section, (
            f"Expected location 'still here' template; got: {section[:600]}"
        )

    def test_artefact_marker_outside_fold(self, page: Page, base_url):
        """Civic-Site Token (discovery_diff=0) is reachable at ARTEFACTSDIFF;
        the artefact list must render OUTSIDE every `<details>` fold."""
        self._go_to_worker(page, base_url, 'Echo', 'Artefact_Searcher_Echo')
        section = _recherches_section_html(page.content())
        assert "Ce lieu contient" in section, (
            f"Expected artefact list marker in recherches section; got: {section[:600]}"
        )
        lower = section.lower()
        cursor = 0
        while True:
            d_open = lower.find("<details>", cursor)
            if d_open < 0:
                break
            d_close = lower.find("</details>", d_open)
            assert d_close > 0, "Malformed <details> block"
            fold_body = section[d_open:d_close + len("</details>")]
            assert "Ce lieu contient" not in fold_body, (
                f"Artefact list must stay OUTSIDE any <details> fold; found inside: {fold_body[:300]}"
            )
            cursor = d_close + 1


class TestReportRedundancyMoved:
    """V2 variant: prev CKE.zone_id != current row.zone_id → "moved from <prev>"
    summary + folded body. Fixture moves Searcher_1 + Bystander_1 to
    Theta-Artefacts, re-activates Searcher_1's investigate, runs a 3rd EOT."""

    @pytest.fixture(scope="class", autouse=True)
    def move_targets_and_third_eot(self, browser):
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)
        ui_move(page, "Bystander_1", "Theta-Artefacts", base_url=PHP_BASE_URL)
        ui_move(page, "Searcher_1", "Theta-Artefacts", base_url=PHP_BASE_URL)
        wid = _cached_wid(page, "Searcher_1")
        safe_goto(page, f"{PHP_BASE_URL}/workers/action.php?worker_id={wid}&investigate=1")
        page.wait_for_load_state("load")
        end_turn(page, base_url=PHP_BASE_URL)
        assert_no_collected_php_errors(page)
        context.close()
        yield

    def _go_to_searcher_1(self, page, base_url):
        ensure_gm_login(page, base_url)
        ctrl_id = _cached_cid(page, "Alpha")
        worker_id = _cached_wid(page, "Searcher_1")
        safe_goto(page, f"{base_url}/base/accueil.php?controller_id={ctrl_id}")
        page.wait_for_load_state("networkidle")
        safe_goto(page, f"{base_url}/workers/action.php?worker_id={worker_id}")
        page.wait_for_load_state("load")

    def test_moved_template_appears(self, page: Page, base_url):
        """Report contains the 'déplacé' template for re-detected Bystander_1."""
        self._go_to_searcher_1(page, base_url)
        section = _investigation_section_html(page.content())
        assert section, "Searcher_1 should have a 'Mes investigations' section"
        assert "déplacé" in section, (
            f"Expected 'moved' template in repeat-after-relocation report; got: {section[:600]}"
        )

    def test_moved_summary_names_prev_zone(self, page: Page, base_url):
        """The moved-from summary mentions the previous zone (Alpha-Investigation)."""
        self._go_to_searcher_1(page, base_url)
        summary = (
            page.locator("details summary")
            .filter(has_text="déplacé")
            .first
        )
        expect(summary).to_be_visible()
        text = (summary.inner_text() or "").strip()
        assert "Alpha-Investigation" in text, (
            f"Expected prev zone 'Alpha-Investigation' in moved summary; got: '{text}'"
        )

    def test_moved_body_folded_under_details(self, page: Page, base_url):
        """Clicking the moved summary expands additional content from the fold."""
        self._go_to_searcher_1(page, base_url)
        summary = (
            page.locator("details summary")
            .filter(has_text="déplacé")
            .first
        )
        details_handle = summary.locator("xpath=..")
        closed_inner = (summary.inner_text() or "").strip()
        summary.click()
        full_text = (details_handle.text_content() or "").strip()
        assert len(full_text) > len(closed_inner), (
            "Clicking moved summary should reveal folded body; "
            f"summary='{closed_inner[:120]}' body='{full_text[:300]}'"
        )


class TestReportRedundancyUpgrade:
    """V4 variant: prev CKE level < current level → "we have new info" visible
    paragraph + folded reminder. Fixture teaches Searcher_1 'Focused Mind'
    (+1 enquete) so Searcher_1's enquete jumps from 3 to 4. vs Bystander_1
    (enquete 3) the diff moves from 0 (level 0) to 1 (level 1, REPORTDIFF1=1)
    → delta > 0 → V4."""

    @pytest.fixture(scope="class", autouse=True)
    def teach_and_fourth_eot(self, browser):
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)
        ui_teach_discipline_click(page, "Searcher_1", "Focused Mind",
                                  base_url=PHP_BASE_URL)
        end_turn(page, base_url=PHP_BASE_URL)
        assert_no_collected_php_errors(page)
        context.close()
        yield

    def _go_to_searcher_1(self, page, base_url):
        ensure_gm_login(page, base_url)
        ctrl_id = _cached_cid(page, "Alpha")
        worker_id = _cached_wid(page, "Searcher_1")
        safe_goto(page, f"{base_url}/base/accueil.php?controller_id={ctrl_id}")
        page.wait_for_load_state("networkidle")
        safe_goto(page, f"{base_url}/workers/action.php?worker_id={worker_id}")
        page.wait_for_load_state("load")

    def test_upgrade_template_appears(self, page: Page, base_url):
        """The 'nouvelles informations' (new info) template surfaces in the report."""
        self._go_to_searcher_1(page, base_url)
        section = _investigation_section_html(page.content())
        assert section, "Searcher_1 should have a 'Mes investigations' section"
        assert "nouvelles informations" in section, (
            f"Expected upgrade template; got: {section[:600]}"
        )

    def test_upgrade_keeps_new_slabs_visible(self, page: Page, base_url):
        """The upgrade body sits in a `<p>` (visible), not folded under <details>."""
        self._go_to_searcher_1(page, base_url)
        section = _investigation_section_html(page.content())
        lower = section.lower()
        upgrade_pos = section.find("nouvelles informations")
        assert upgrade_pos >= 0
        next_details = lower.find("<details>", upgrade_pos)
        next_p_close = lower.find("</p>", upgrade_pos)
        assert next_p_close >= 0, "Upgrade text should close in a </p>"
        if next_details > 0:
            assert next_p_close < next_details, (
                "Upgrade new-info text must be visible (closed </p>) BEFORE "
                "the reminder <details> opens"
            )

    def test_upgrade_reminder_fold_present(self, page: Page, base_url):
        """A 'Rappel' reminder summary folds the previously-known info."""
        self._go_to_searcher_1(page, base_url)
        summary = (
            page.locator("details summary")
            .filter(has_text="Rappel")
            .first
        )
        expect(summary).to_be_visible()


@pytest.mark.db
class TestMonotonicCKEPreservation:
    """addWorkerToCKE UPDATE is monotonic (CODE_KNOWLEDGE §10 #22). A 5-arg
    call from a gift / attack / claim path must NOT downgrade the discovered_*
    flags set by a prior investigation. After the V4 upgrade EOT, Alpha's CKE
    row for Bystander_1 has discovered_powers=TRUE. The GM gift handler at
    controllers/management.php?giftInformationAgent=1 hits addWorkerToCKE
    with default args (discovered_controller_id=NULL, _name=NULL, _powers=false)
    — the conditional SET clauses must skip those columns and preserve the flag."""

    @pytest.fixture(scope="class", autouse=True)
    def trigger_5arg_addworker(self, browser):
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)
        alpha_id = _cached_cid(page, "Alpha")
        bystander_id = _cached_wid(page, "Bystander_1")
        theta_zone_id = ui_zone_id(page, "Theta-Artefacts", base_url=PHP_BASE_URL)
        safe_goto(
            page,
            f"{PHP_BASE_URL}/controllers/management.php"
            f"?giftInformationAgent=1&target_controller_id={alpha_id}"
            f"&enemy_worker_id={bystander_id}&zone_id={theta_zone_id}",
        )
        page.wait_for_load_state("load")
        assert_no_collected_php_errors(page)
        context.close()
        yield

    def test_discovered_powers_preserved_after_5arg_call(self):
        """Alpha's CKE row for Bystander_1 must still have discovered_powers=1
        after the 5-arg gift call."""
        conn = get_db_connection()
        try:
            with conn.cursor() as cursor:
                cursor.execute(f"""
                    SELECT cke.discovered_powers, cke.zone_id
                    FROM `{GAME_PREFIX}controllers_known_enemies` cke
                    JOIN `{GAME_PREFIX}controllers` c ON c.id = cke.controller_id
                    JOIN `{GAME_PREFIX}workers` w ON w.id = cke.discovered_worker_id
                    WHERE c.lastname = 'Alpha' AND w.lastname = 'Bystander_1'
                """)
                row = cursor.fetchone()
        finally:
            conn.close()
        assert row is not None, "Expected a CKE row for (Alpha, Bystander_1)"
        assert int(row['discovered_powers']) == 1, (
            f"Monotonic UPDATE broken: discovered_powers downgraded to "
            f"{row['discovered_powers']} by 5-arg gift call (should stay 1)"
        )

