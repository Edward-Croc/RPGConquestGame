"""Playwright E2E tests for combat resolution mechanics.

Data: 19 combat agents loaded from setupTestConfig_advanced.csv in Beta-Combat
zone. All start passive on turn 0. After end-of-turn 0→1, actions are set via
the workers/action.php UI endpoint. End-of-turn 1→2 resolves all combat.

Data harmonization (see setupTestConfig_advanced.csv):
  Controllers: Lord/Lady + NATO phonetic (Alpha–Golf)
  Zone: Beta-Combat (unclaimed, no holder bonus)
  Agents: firstname=combat, lastname=role (Chain_A, Even_Atk, Inv_Def_1, etc.)

Combat thresholds (TestConfig):
  ATTACKDIFF0=1 (kill), ATTACKDIFF1=3 (capture), RIPOSTDIFF=2, RIPOSTACTIVE=1
  PASSIVEVAL=3, MINROLL=MAXROLL=3 → val = 3 + Σ(power.stat)
  attack_difference = attacker.attack_val − defender.defence_val
  riposte_difference = defender.attack_val − attacker.defence_val

Resolution order: attackers act in DESC enquete_val. If a worker becomes
INACTIVE ('dead'/'captured') before their turn, their pending attack is skipped.

Agent stats (power totals → final vals = total + 3):
  Chain_A  (Alpha):   EagleScout+VeteranTactician+FocusedMind+WarGear  e=5 a=5 d=4 → 8/8/7
  Chain_B  (Beta):    EagleScout+ScoutRunner                           e=4 a=1 d=2 → 7/4/5
  Chain_C  (Charlie): EagleScout+PatrolWarden                          e=3 a=1 d=1 → 6/4/4
  Chain_D  (Delta):   BlankSlate+CommonFolk                            e=0 a=0 d=0 → 3/3/3
  Chain_E  (Echo):    BruteForce+PatrolWarden                          e=1 a=3 d=2 → 4/6/5
  Chain_F  (Foxtrot): CarefulWatch+PatrolWarden                        e=2 a=0 d=1 → 5/3/4
  Chain_G  (Golf):    DarkImpulse+CommonFolk                           e=-1 a=1 d=-1 → 2/4/2
  Even_Atk (Alpha):   BlankSlate+CommonFolk                            e=0 a=0 d=0 → 3/3/3
  Even_Def (Beta):    BlankSlate+CommonFolk                            e=0 a=0 d=0 → 3/3/3
  Counter_Atk (Golf): DarkImpulse+CommonFolk                           e=-1 a=1 d=-1 → 2/4/2
  Counter_Def (Foxtrot): BruteForce+CommonFolk                         e=0 a=3 d=2 → 3/6/5
  Inv_Atk_1 (Alpha):  EagleScout+VeteranTactician+OffensiveStance+WarGear → 8/8/7
  Inv_Def_1 (Beta):   DarkImpulse+CommonFolk                           → 2/4/2
  Inv_Atk_2 (Charlie): EagleScout+PatrolWarden                         → 6/4/4
  Inv_Def_2 (Delta):  BlankSlate+CommonFolk                            → 3/3/3
  Claim_Atk_1 (Echo): EagleScout+VeteranTactician+OffensiveStance+WarGear → 8/8/7
  Claim_Def_1 (Beta): DarkImpulse+CommonFolk                           → 2/4/2
  Claim_Atk_2 (Charlie): EagleScout+PatrolWarden                       → 6/4/4
  Claim_Def_2 (Delta): BlankSlate+CommonFolk                           → 3/3/3

Run:
    python3 -m pytest tests/test_agent_combat_e2e.py -v
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
# Helpers: DB lookups
# ---------------------------------------------------------------------------

def _worker_id(lastname):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute(
        f"SELECT id FROM `{GAME_PREFIX}workers` WHERE lastname = %s",
        (lastname,),
    )
    row = cursor.fetchone()
    conn.close()
    return row['id'] if row else None


def _controller_id(lastname):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute(
        f"SELECT id FROM `{GAME_PREFIX}controllers` WHERE lastname = %s",
        (lastname,),
    )
    row = cursor.fetchone()
    conn.close()
    return row['id'] if row else None


def _worker_status(lastname, turn=1):
    """Return action_choice for a worker at a given turn."""
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute(f"""
        SELECT wa.action_choice FROM `{GAME_PREFIX}worker_actions` wa
        JOIN `{GAME_PREFIX}workers` w ON w.id = wa.worker_id
        WHERE w.lastname = %s AND wa.turn_number = %s
    """, (lastname, turn))
    row = cursor.fetchone()
    conn.close()
    return row['action_choice'] if row else None


def _worker_report_db(lastname, turn=1):
    """Return the report JSON string for a worker at a given turn."""
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute(f"""
        SELECT wa.report FROM `{GAME_PREFIX}worker_actions` wa
        JOIN `{GAME_PREFIX}workers` w ON w.id = wa.worker_id
        WHERE w.lastname = %s AND wa.turn_number = %s
    """, (lastname, turn))
    row = cursor.fetchone()
    conn.close()
    return str(row['report'] or '') if row else ''


def _ensure_controller_session(page):
    """Ensure the gm is logged in and has a controller selected."""
    ensure_gm_login(page, PHP_BASE_URL)
    page.goto(f"{PHP_BASE_URL}/base/accueil.php")
    page.wait_for_load_state("networkidle")
    page.locator("select[name='controller_id']").first.select_option(index=0)
    page.locator("input[name='chosir']").first.click()
    page.wait_for_load_state("networkidle")


def _worker_controller_id(lastname):
    """Return the primary controller_id for a worker."""
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute(f"""
        SELECT cw.controller_id FROM `{GAME_PREFIX}controller_worker` cw
        JOIN `{GAME_PREFIX}workers` w ON w.id = cw.worker_id
        WHERE w.lastname = %s AND cw.is_primary_controller = 1
    """, (lastname,))
    row = cursor.fetchone()
    conn.close()
    return row['controller_id'] if row else None


def _worker_report_html(page, lastname):
    """Navigate to a worker's page and return the HTML content.
    Switches the gm session to the worker's controller first."""
    ensure_gm_login(page, PHP_BASE_URL)
    ctrl_id = _worker_controller_id(lastname)
    page.goto(
        f"{PHP_BASE_URL}/base/accueil.php"
        f"?controller_id={ctrl_id}&chosir=Choisir"
    )
    page.wait_for_load_state("networkidle")
    wid = _worker_id(lastname)
    assert wid, f"Worker {lastname} not found"
    page.goto(f"{PHP_BASE_URL}/workers/action.php?worker_id={wid}")
    page.wait_for_load_state("load")
    return page.content()


# ---------------------------------------------------------------------------
# Helpers: UI action endpoints
# ---------------------------------------------------------------------------

def _ui_attack(page, attacker_lastname, target_lastname):
    """Set attack via workers/action.php URL endpoint."""
    atk_id = _worker_id(attacker_lastname)
    tgt_id = _worker_id(target_lastname)
    page.goto(
        f"{PHP_BASE_URL}/workers/action.php"
        f"?worker_id={atk_id}"
        f"&enemy_worker_id[]=worker_{tgt_id}"
        f"&attack=Attaquer"
    )
    page.wait_for_load_state("load")


def _ui_investigate(page, lastname):
    """Set investigate via workers/action.php URL endpoint."""
    wid = _worker_id(lastname)
    page.goto(
        f"{PHP_BASE_URL}/workers/action.php"
        f"?worker_id={wid}&investigate=1"
    )
    page.wait_for_load_state("load")


def _ui_claim(page, lastname, claim_controller_lastname):
    """Set claim via workers/action.php URL endpoint."""
    wid = _worker_id(lastname)
    cid = _controller_id(claim_controller_lastname)
    page.goto(
        f"{PHP_BASE_URL}/workers/action.php"
        f"?worker_id={wid}&claim_controller_id={cid}&claim=1"
    )
    page.wait_for_load_state("load")


def _end_turn(page):
    """Trigger end-of-turn via the sidebar link."""
    page.goto(f"{PHP_BASE_URL}/mechanics/endTurn.php")
    page.wait_for_load_state("load", timeout=90000)
    html = page.content()
    assert "<b>Warning</b>" not in html, "PHP warning during end turn"
    assert "<b>Fatal error</b>" not in html, "PHP fatal error during end turn"


# ---------------------------------------------------------------------------
# Module fixture: load TestConfig, run turn 0, set actions, run turn 1
# ---------------------------------------------------------------------------

@pytest.fixture(scope="module", autouse=True)
def combat_scenario(browser):
    """One-time setup: load TestConfig, advance 2 turns with combat actions.

    Turn 0 → 1: all 26 agents exist (7 detection passive/investigate in
    Alpha-Investigation, 19 combat passive in Beta-Combat). End-turn processes
    detection mechanics; combat agents are passive so nothing happens to them.

    Between turns: set all combat actions via UI endpoints:
      - Chain attacks: A→B, B→C, C→D, D→E, E→F, F→G
      - Base attacks: Even_Atk→Even_Def, Counter_Atk→Counter_Def
      - Blocked investigate: Inv_Atk_1→Inv_Def_1, Inv_Atk_2→Inv_Def_2
        + Inv_Def_1 investigate, Inv_Def_2 investigate
      - Blocked claim: Claim_Atk_1→Claim_Def_1, Claim_Atk_2→Claim_Def_2
        + Claim_Def_1 claims for Beta, Claim_Def_2 claims for Delta

    Turn 1 → 2: attack mechanic resolves all combats by enquete_val DESC.
    """
    if not DB_AVAILABLE:
        yield
        return

    conn = get_db()
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

    # Login and load TestConfig
    ensure_gm_login(page, PHP_BASE_URL)
    page.goto(f"{PHP_BASE_URL}/base/admin.php")
    page.wait_for_load_state("networkidle")
    page.locator("select[name='config_name']").select_option("TestConfig")
    page.locator("input[name='submit'][value='Submit']").click()
    page.wait_for_timeout(5000)
    page.wait_for_load_state("load", timeout=90000)

    # End turn 0 → 1
    _end_turn(page)

    # Set all combat actions via UI for turn 1
    # Chain: A→B, B→C, C→D, D→E, E→F, F→G
    _ui_attack(page, 'Chain_A', 'Chain_B')
    _ui_attack(page, 'Chain_B', 'Chain_C')
    _ui_attack(page, 'Chain_C', 'Chain_D')
    _ui_attack(page, 'Chain_D', 'Chain_E')
    _ui_attack(page, 'Chain_E', 'Chain_F')
    _ui_attack(page, 'Chain_F', 'Chain_G')

    # Base: equal match + counter
    _ui_attack(page, 'Even_Atk', 'Even_Def')
    _ui_attack(page, 'Counter_Atk', 'Counter_Def')

    # Blocked investigate: attackers attack, defenders investigate
    _ui_attack(page, 'Inv_Atk_1', 'Inv_Def_1')
    _ui_attack(page, 'Inv_Atk_2', 'Inv_Def_2')
    _ui_investigate(page, 'Inv_Def_1')
    _ui_investigate(page, 'Inv_Def_2')

    # Blocked claim: attackers attack, defenders claim their own controller
    _ui_attack(page, 'Claim_Atk_1', 'Claim_Def_1')
    _ui_attack(page, 'Claim_Atk_2', 'Claim_Def_2')
    _ui_claim(page, 'Claim_Def_1', 'Beta')
    _ui_claim(page, 'Claim_Def_2', 'Delta')

    # End turn 1 → 2 (combat resolves)
    _end_turn(page)

    context.close()
    yield


# ---------------------------------------------------------------------------
# Tests: base interactions
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestBaseCombat:
    """Single-pair attack outcomes. Capture and kill are verified via the
    blocked-action agents (Inv_Atk/Inv_Def pairs) which serve double duty.
    """

    def test_attack_captures_target(self, page: Page, base_url):
        """Inv_Atk_1 (atk=8) vs Inv_Def_1 (def=2): diff=6 ≥ 3 → CAPTURE.

        DB: Inv_Def_1 action_choice='captured', Inv_Atk_1 stays 'attack'.
        UI: attacker report contains 'Captured <strong>combat Inv_Def_1</strong>'.
        """
        assert _worker_status('Inv_Def_1') == 'captured'
        assert _worker_status('Inv_Atk_1') == 'attack'
        html = _worker_report_html(page, 'Inv_Atk_1')
        assert 'Captured' in html and 'Inv_Def_1' in html, \
            f"Attacker report should mention capture of Inv_Def_1"

    def test_attack_kills_target(self, page: Page, base_url):
        """Inv_Atk_2 (atk=4) vs Inv_Def_2 (def=3): diff=1 ≥ 1, < 3 → KILL.

        DB: Inv_Def_2 action_choice='dead', Inv_Atk_2 stays 'attack'.
        UI: attacker report contains 'Attack on <strong>combat Inv_Def_2</strong> succeeded'.
        """
        assert _worker_status('Inv_Def_2') == 'dead'
        assert _worker_status('Inv_Atk_2') == 'attack'
        html = _worker_report_html(page, 'Inv_Atk_2')
        assert 'succeeded' in html and 'Inv_Def_2' in html, \
            f"Attacker report should mention successful attack on Inv_Def_2"

    def test_equally_matched_fails(self, page: Page, base_url):
        """Even_Atk (atk=3) vs Even_Def (def=3): diff=0 < 1 → FAIL.
        riposte_diff = 3-3 = 0 < 2 → no counter.

        DB: Even_Def stays 'passive', Even_Atk stays 'attack'.
        UI: attacker report contains 'Attack on ... failed'.
        """
        assert _worker_status('Even_Def') == 'passive'
        assert _worker_status('Even_Atk') == 'attack'
        html = _worker_report_html(page, 'Even_Atk')
        assert 'failed' in html and 'Even_Def' in html, \
            f"Attacker report should mention failed attack on Even_Def"

    def test_counter_attacked_and_dies(self, page: Page, base_url):
        """Counter_Atk (atk=4, def=2) vs Counter_Def (atk=6, def=5):
        attack_diff = 4-5 = -1 < 1 → fail.
        riposte_diff = 6-2 = 4 ≥ 2 → counter kills attacker.

        DB: Counter_Atk='dead', Counter_Def='passive'.
        UI: attacker report contains 'countered'.
        """
        assert _worker_status('Counter_Atk') == 'dead'
        assert _worker_status('Counter_Def') == 'passive'
        html = _worker_report_html(page, 'Counter_Atk')
        assert 'countered' in html and 'Counter_Def' in html, \
            f"Attacker report should mention counter-attack from Counter_Def"


# ---------------------------------------------------------------------------
# Test: chain attack ordered by enquete_val
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestChainAttack:
    """7-agent chain A→B→C→D→E→F→G, resolved by enquete_val DESC.

    Resolution order: A(8) > B(7) > C(6) > F(5) > E(4) > D(3) > G(2)

    Outcomes:
      A captures B → B's attack on C is skipped → C survives
      C kills D → D's attack on E is skipped → E survives
      F kills G (acts before E because F.enquete=5 > E.enquete=4)
      E kills F (acts after F resolved)
    """

    def test_enquete_values_at_turn_0(self):
        """Verify calculated enquete values before combat (turn 0)."""
        conn = get_db()
        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT w.lastname, wa.enquete_val
            FROM `{GAME_PREFIX}worker_actions` wa
            JOIN `{GAME_PREFIX}workers` w ON w.id = wa.worker_id
            WHERE wa.turn_number = 0 AND w.lastname LIKE 'Chain_%'
            ORDER BY wa.enquete_val DESC
        """)
        enq = {r['lastname']: r['enquete_val'] for r in cursor.fetchall()}
        conn.close()
        assert enq == {
            'Chain_A': 8, 'Chain_B': 7, 'Chain_C': 6,
            'Chain_F': 5, 'Chain_E': 4, 'Chain_D': 3, 'Chain_G': 2,
        }, f"Unexpected enquete values: {enq}"

    def test_chain_a_captures_b(self):
        assert _worker_status('Chain_A') == 'attack'
        assert _worker_status('Chain_B') == 'captured'

    def test_chain_c_kills_d_and_survives(self):
        assert _worker_status('Chain_C') == 'attack'
        assert _worker_status('Chain_D') == 'dead'

    def test_chain_f_kills_g_then_e_kills_f(self):
        assert _worker_status('Chain_E') == 'attack'
        assert _worker_status('Chain_F') == 'dead'
        assert _worker_status('Chain_G') == 'dead'

    def test_chain_reports_in_ui(self, page: Page, base_url):
        """Spot-check chain attack reports via worker pages."""
        html_a = _worker_report_html(page, 'Chain_A')
        assert 'Captured' in html_a and 'Chain_B' in html_a

        html_c = _worker_report_html(page, 'Chain_C')
        assert 'succeeded' in html_c and 'Chain_D' in html_c

        html_e = _worker_report_html(page, 'Chain_E')
        assert 'succeeded' in html_e and 'Chain_F' in html_e

        html_b = _worker_report_html(page, 'Chain_B')
        assert 'Chain_C' not in html_b or 'not found' in html_b, \
            "Chain_B was captured before attacking Chain_C"


# ---------------------------------------------------------------------------
# Tests: dead/captured agents don't complete their pending action
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestActionBlockedByCombat:
    """Verify investigate / claim do not complete when their agent is killed
    or captured during the attack phase (which runs before those mechanics).
    """

    def test_investigate_blocked_by_capture(self):
        """Inv_Def_1 was investigating but got captured → no investigate_report."""
        assert _worker_status('Inv_Def_1') == 'captured'
        report = _worker_report_db('Inv_Def_1')
        assert 'investigate_report' not in report

    def test_investigate_blocked_by_death(self):
        """Inv_Def_2 was investigating but got killed → no investigate_report."""
        assert _worker_status('Inv_Def_2') == 'dead'
        report = _worker_report_db('Inv_Def_2')
        assert 'investigate_report' not in report

    def test_investigate_blocked_ui_report(self):
        """Downed investigators' DB reports should not contain investigation results."""
        report1 = _worker_report_db('Inv_Def_1')
        report2 = _worker_report_db('Inv_Def_2')
        assert 'investigate_report' not in report1, \
            "Captured investigator should not have investigation report"
        assert 'investigate_report' not in report2, \
            "Killed investigator should not have investigation report"
        assert 'life_report' in report1, \
            "Captured investigator should have a life_report (disappearance)"
        assert 'life_report' in report2, \
            "Killed investigator should have a life_report (disappearance)"

    def test_claim_blocked_by_capture(self):
        """Claim_Def_1 was claiming but got captured → no claim_report."""
        assert _worker_status('Claim_Def_1') == 'captured'
        report = _worker_report_db('Claim_Def_1')
        assert 'claim_report' not in report

    def test_claim_blocked_by_death(self):
        """Claim_Def_2 was claiming but got killed → no claim_report."""
        assert _worker_status('Claim_Def_2') == 'dead'
        report = _worker_report_db('Claim_Def_2')
        assert 'claim_report' not in report

    def test_claim_blocked_ui_report(self):
        """Downed claimers' DB reports should not contain claim results."""
        report1 = _worker_report_db('Claim_Def_1')
        report2 = _worker_report_db('Claim_Def_2')
        assert 'claim_report' not in report1, \
            "Captured claimer should not have claim report"
        assert 'claim_report' not in report2, \
            "Killed claimer should not have claim report"
        assert 'life_report' in report1, \
            "Captured claimer should have a life_report (disappearance)"

    def test_zone_holder_unchanged(self):
        """Beta-Combat zone should remain unheld (claims were blocked)."""
        conn = get_db()
        cursor = conn.cursor()
        cursor.execute(
            f"SELECT holder_controller_id FROM `{GAME_PREFIX}zones` "
            f"WHERE name = 'Beta-Combat'"
        )
        holder = cursor.fetchone()['holder_controller_id']
        conn.close()
        assert holder is None, \
            f"Beta-Combat should remain unclaimed, got holder={holder}"
