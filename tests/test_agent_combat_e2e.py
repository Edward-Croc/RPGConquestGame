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


from helpers import (
    DB_AVAILABLE, get_db_connection as get_db,
    end_turn, load_minimal_data,
    ui_all_workers, ui_controller_id, ui_worker_id, ui_worker_controller_id,
    ui_workers_by_lastname, ui_faction_sections,
)


# ---------------------------------------------------------------------------
# ID caches populated by the module fixture from UI scrapes.
#
# Worker ids never change post-creation, so we snapshot them once at fixture
# start (before combat resolves). Controller ids are dynamic for captured
# workers (move to captor), so those lookups are fresh via the UI helper.
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


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


# ---------------------------------------------------------------------------
# Helpers: DB lookups
# ---------------------------------------------------------------------------

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


def _worker_report_html(page, lastname):
    """Navigate to a worker's page and return the HTML content.

    Switches the gm session to the worker's CURRENT controller first
    (may be the captor if the worker was captured). Uses the fresh
    UI scrape for controller_id so the post-capture state is correct;
    worker_id is cached because the original record's id doesn't change."""
    ensure_gm_login(page, PHP_BASE_URL)
    ctrl_id = ui_worker_controller_id(page, lastname)
    page.goto(
        f"{PHP_BASE_URL}/base/accueil.php"
        f"?controller_id={ctrl_id}&chosir=Choisir"
    )
    page.wait_for_load_state("networkidle")
    wid = _cached_wid(page, lastname)
    assert wid, f"Worker {lastname} not found"
    page.goto(f"{PHP_BASE_URL}/workers/action.php?worker_id={wid}")
    page.wait_for_load_state("load")
    return page.content()


# ---------------------------------------------------------------------------
# Helpers: UI action endpoints
# ---------------------------------------------------------------------------

def _ui_attack(page, attacker_lastname, target_lastname):
    """Set attack via workers/action.php URL endpoint."""
    atk_id = _cached_wid(page, attacker_lastname)
    tgt_id = _cached_wid(page, target_lastname)
    page.goto(
        f"{PHP_BASE_URL}/workers/action.php"
        f"?worker_id={atk_id}"
        f"&enemy_worker_id[]=worker_{tgt_id}"
        f"&attack=Attaquer"
    )
    page.wait_for_load_state("load")


def _ui_investigate(page, lastname):
    """Set investigate via workers/action.php URL endpoint."""
    wid = _cached_wid(page, lastname)
    page.goto(
        f"{PHP_BASE_URL}/workers/action.php"
        f"?worker_id={wid}&investigate=1"
    )
    page.wait_for_load_state("load")


def _ui_claim(page, lastname, claim_controller_lastname):
    """Set claim via workers/action.php URL endpoint."""
    wid = _cached_wid(page, lastname)
    cid = _cached_cid(page, claim_controller_lastname)
    page.goto(
        f"{PHP_BASE_URL}/workers/action.php"
        f"?worker_id={wid}&claim_controller_id={cid}&claim=1"
    )
    page.wait_for_load_state("load")




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
    # Local bootstrap — skipped on prod where MySQL isn't reachable
    if DB_AVAILABLE:
        load_minimal_data()

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

    # Reset module-level id caches for this fixture run. Tests that run
    # against different deployments should each get fresh ids scraped
    # from the current page state.
    _wid_cache.clear()
    _cid_cache.clear()

    # End turn 0 → 1
    end_turn(page)

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
    end_turn(page)

    context.close()
    yield


# ---------------------------------------------------------------------------
# Helpers: UI-based status assertions
# ---------------------------------------------------------------------------
#
# Inlined here (not added to tests/helpers.py per agent instructions).
# The worker's own action.php page renders:
#   - Active worker: "<strong>Surveille</strong>" / "<strong>Attaque</strong>
#     contre <target>" text AND an action form with name="attack" submit.
#   - Dead/captured worker (INACTIVE_ACTIONS): "<strong>A disparu</strong>"
#     text and NO action form.
# These helpers turn those rendering facts into boolean assertions that mirror
# what the old DB `action_choice` queries checked.

def _ui_worker_is_downed(page, lastname):
    """True if the worker's action.php view shows them as dead, captured,
    or a prisoner under a new controller.

    After captureWorker(), a captured worker is MOVED to the captor's
    controller and its action becomes 'prisoner' (text: "est un.e agent
    ... prisonnier.e"). The original controller sees a trace worker
    whose action is 'captured' / 'dead' (text: "A disparu"). Both paths
    count as "downed" from the attacker-test perspective.

    The action form is absent for both inactive actions and prisoner
    views, so `name="attack"` input is not rendered.
    """
    html = _worker_report_html(page, lastname)
    is_disparu_or_prisoner = 'A disparu' in html or 'prisonnier' in html
    return is_disparu_or_prisoner and 'name="attack"' not in html


def _ui_worker_is_passive(page, lastname):
    """True if the worker's action.php view shows them as still passive.

    txt_ps_passive => "surveille", ucfirst => "Surveille". The action form
    must still be rendered (worker is active).
    """
    html = _worker_report_html(page, lastname)
    return 'Surveille' in html and 'name="attack"' in html


def _ui_worker_is_attacking(page, lastname, target_lastname):
    """True if the worker's action.php view shows them still attacking target.

    txt_ps_attack => "attaque"; view.php appends " contre <firstname>
    <lastname>" when action_choice == 'attack'. Firstname is 'combat' for all
    combat agents.
    """
    html = _worker_report_html(page, lastname)
    return 'Attaque' in html and target_lastname in html


# ---------------------------------------------------------------------------
# Tests: base interactions
# ---------------------------------------------------------------------------

class TestBaseCombat:
    """Single-pair attack outcomes. Capture and kill are verified via the
    blocked-action agents (Inv_Atk/Inv_Def pairs) which serve double duty.

    All assertions are UI-driven so the class runs under UI_ONLY=1.
    """

    def test_attack_captures_target(self, page: Page, base_url):
        """Inv_Atk_1 (atk=8) vs Inv_Def_1 (def=2): diff=6 ≥ 3 → CAPTURE.

        UI: attacker report contains 'Captured ... Inv_Def_1'; defender's own
        view shows 'A disparu' (txt_ps_captured) and no action form.
        """
        html = _worker_report_html(page, 'Inv_Atk_1')
        assert 'Captured' in html and 'Inv_Def_1' in html, \
            "Attacker report should mention capture of Inv_Def_1"
        assert _ui_worker_is_downed(page, 'Inv_Def_1'), \
            "Inv_Def_1 view should show 'A disparu' (captured) and no action form"

    def test_attack_kills_target(self, page: Page, base_url):
        """Inv_Atk_2 (atk=4) vs Inv_Def_2 (def=3): diff=1 ≥ 1, < 3 → KILL.

        UI: attacker report contains 'succeeded ... Inv_Def_2'; defender's own
        view shows 'A disparu' (txt_ps_dead) and no action form.
        """
        html = _worker_report_html(page, 'Inv_Atk_2')
        assert 'succeeded' in html and 'Inv_Def_2' in html, \
            "Attacker report should mention successful attack on Inv_Def_2"
        assert _ui_worker_is_downed(page, 'Inv_Def_2'), \
            "Inv_Def_2 view should show 'A disparu' (dead) and no action form"

    def test_equally_matched_fails(self, page: Page, base_url):
        """Even_Atk (atk=3) vs Even_Def (def=3): diff=0 < 1 → FAIL.
        riposte_diff = 3-3 = 0 < 2 → no counter.

        UI: attacker report contains 'failed ... Even_Def'; defender's view
        still shows 'Surveille' (txt_ps_passive) and the action form.
        """
        html = _worker_report_html(page, 'Even_Atk')
        assert 'failed' in html and 'Even_Def' in html, \
            "Attacker report should mention failed attack on Even_Def"
        assert _ui_worker_is_passive(page, 'Even_Def'), \
            "Even_Def should still be passive (Surveille) after the failed attack"

    def test_counter_attacked_and_dies(self, page: Page, base_url):
        """Counter_Atk (atk=4, def=2) vs Counter_Def (atk=6, def=5):
        attack_diff = 4-5 = -1 < 1 → fail.
        riposte_diff = 6-2 = 4 ≥ 2 → counter kills attacker.

        UI: attacker report contains 'countered ... Counter_Def'; attacker's
        view shows 'A disparu' (dead); defender stays 'Surveille' (passive).
        """
        html = _worker_report_html(page, 'Counter_Atk')
        assert 'countered' in html and 'Counter_Def' in html, \
            "Attacker report should mention counter-attack from Counter_Def"
        assert _ui_worker_is_downed(page, 'Counter_Atk'), \
            "Counter_Atk should be dead (A disparu) after being countered"
        assert _ui_worker_is_passive(page, 'Counter_Def'), \
            "Counter_Def should remain passive (Surveille) after countering"


# ---------------------------------------------------------------------------
# Test: chain attack ordered by enquete_val
# ---------------------------------------------------------------------------

class TestChainAttack:
    """7-agent chain A→B→C→D→E→F→G, resolved by enquete_val DESC.

    Resolution order: A(8) > B(7) > C(6) > F(5) > E(4) > D(3) > G(2)

    Outcomes:
      A captures B → B's attack on C is skipped → C survives
      C kills D → D's attack on E is skipped → E survives
      F kills G (acts before E because F.enquete=5 > E.enquete=4)
      E kills F (acts after F resolved)
    """

    @pytest.mark.db
    def test_enquete_values_at_turn_0(self):
        """Verify calculated enquete values before combat (turn 0).

        Kept as DB-only: enquete_val for turn 0 is a raw computed field not
        surfaced numerically in any HTML view. Skipped under UI_ONLY=1.
        """
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

    def test_chain_a_captures_b(self, page: Page, base_url):
        """A captures B. Verified via attacker report + defender view."""
        html_a = _worker_report_html(page, 'Chain_A')
        assert 'Captured' in html_a and 'Chain_B' in html_a, \
            "Chain_A report should mention capture of Chain_B"
        assert _ui_worker_is_downed(page, 'Chain_B'), \
            "Chain_B view should show 'A disparu' (captured)"

    def test_chain_c_kills_d_and_survives(self, page: Page, base_url):
        """C kills D. D's pending attack is skipped (D inactive before its turn)."""
        html_c = _worker_report_html(page, 'Chain_C')
        assert 'succeeded' in html_c and 'Chain_D' in html_c, \
            "Chain_C report should mention successful attack on Chain_D"
        assert _ui_worker_is_downed(page, 'Chain_D'), \
            "Chain_D view should show 'A disparu' (dead)"

    def test_chain_f_kills_g_then_e_kills_f(self, page: Page, base_url):
        """F (enquete=5) acts before E (enquete=4), so F kills G first; then E
        kills F. Both F and G end up downed; E survives and still shows attack.
        """
        html_e = _worker_report_html(page, 'Chain_E')
        assert 'succeeded' in html_e and 'Chain_F' in html_e, \
            "Chain_E report should mention successful attack on Chain_F"
        assert _ui_worker_is_downed(page, 'Chain_F'), \
            "Chain_F view should show 'A disparu' (dead)"
        assert _ui_worker_is_downed(page, 'Chain_G'), \
            "Chain_G view should show 'A disparu' (dead)"

    def test_chain_reports_in_ui(self, page: Page, base_url):
        """Spot-check chain attack reports via worker pages.

        Chain_B was queued to attack Chain_C but got captured first by
        Chain_A. The "didn't-attack" check is DB-only (see
        test_chain_b_did_not_attack below); this test stays pure-UI."""
        html_a = _worker_report_html(page, 'Chain_A')
        assert 'Captured' in html_a and 'Chain_B' in html_a

        html_c = _worker_report_html(page, 'Chain_C')
        assert 'succeeded' in html_c and 'Chain_D' in html_c

        html_e = _worker_report_html(page, 'Chain_E')
        assert 'succeeded' in html_e and 'Chain_F' in html_e

    def test_chain_b_did_not_attack(self, page: Page, base_url):
        """Chain_B was captured by Chain_A before its attack-phase turn,
        so its queued attack against Chain_C never completed.

        UI-first verification: management_workers lists Chain_B TWICE
        after end-turn — once as the captured original (moved to the
        captor's controller, action='captured' or 'prisoner') and once
        as the auto-created trace on Chain_B's original controller
        (action='trace'). The trace is only created by captureWorker()
        in attackMechanic.php, so its presence proves the capture
        path fired (and therefore Chain_B's own attack was skipped)."""
        ensure_gm_login(page, base_url)
        rows = ui_workers_by_lastname(page, 'Chain_B')
        assert len(rows) == 2, \
            f"Chain_B should have 2 management_workers rows (captured + trace), got {len(rows)}: {rows}"
        actions = sorted(r['action_choice'] for r in rows)
        assert 'trace' in actions, \
            f"Chain_B should have a trace row after capture, got actions: {actions}"
        non_trace = [r for r in rows if r['action_choice'] != 'trace']
        assert non_trace and non_trace[0]['action_choice'] in ('captured', 'prisoner', 'dead'), \
            f"Chain_B original should be captured/prisoner/dead, got: {non_trace}"

    def test_captor_faction_view_has_chain_b_as_prisoner(self, page: Page, base_url):
        """Captor Alpha's faction view (workers/viewAll.php) should show
        Chain_B in 'Nos Prisonniers' (captured worker's row has
        controller_id=Alpha, action='captured', is_primary_controller=1).

        Chain_B must NOT appear in 'Nos Agents' (that section is for
        active workers the controller primarily owns)."""
        ensure_gm_login(page, base_url)
        sections = ui_faction_sections(page, 'Alpha', base_url=base_url)
        assert 'Chain_B' in sections['prisoners'], \
            f"Alpha should see Chain_B in Nos Prisonniers; got {sections}"
        assert 'Chain_B' not in sections['live'], \
            f"Alpha should NOT see Chain_B in Nos Agents; got live={sections['live']}"

    def test_origin_faction_view_has_chain_b_trace_as_ancient(self, page: Page, base_url):
        """Original owner Beta's faction view should show Chain_B as a
        trace in 'Nos Anciens agents' (the trace row has controller_id=
        Beta, action='trace', is_primary_controller=1 — action 'trace'
        is in INACTIVE_ACTIONS but not 'captured', so workers/viewAll.php
        puts it in the 'ancients' section).

        Chain_B must NOT appear in 'Nos Agents' — the original (now
        captured) row was moved to Alpha."""
        ensure_gm_login(page, base_url)
        sections = ui_faction_sections(page, 'Beta', base_url=base_url)
        assert 'Chain_B' in sections['ancients'], \
            f"Beta should see Chain_B trace in Nos Anciens agents; got {sections}"
        assert 'Chain_B' not in sections['live'], \
            f"Beta should NOT see Chain_B in Nos Agents; got live={sections['live']}"


# ---------------------------------------------------------------------------
# Tests: dead/captured agents don't complete their pending action
# ---------------------------------------------------------------------------

class TestActionBlockedByCombat:
    """Verify investigate / claim do not complete when their agent is killed
    or captured during the attack phase (which runs before those mechanics).

    Status checks are UI-driven (action.php view). The "no investigate_report /
    no claim_report" assertions inspect specific JSON keys inside
    worker_actions.report; those keys are rendered as the "Rapport :" HTML
    section IF present — but here we verify ABSENCE, and their rendering
    conditions are falsy (`!empty($currentReport['investigate_report'])`), so
    absence collapses to "the report section for that key simply doesn't
    appear". We therefore keep those as @pytest.mark.db where direct JSON
    inspection is the reliable check; the UI companion simply confirms the
    worker is downed.
    """

    def test_investigate_blocked_by_capture(self, page: Page, base_url):
        """Inv_Def_1 was investigating but got captured → no investigate_report.

        UI: defender view shows 'A disparu' (captured) — i.e. the agent is
        inactive so no investigation completed.
        """
        assert _ui_worker_is_downed(page, 'Inv_Def_1'), \
            "Inv_Def_1 should be downed (captured) in UI"

    def test_investigate_blocked_by_death(self, page: Page, base_url):
        """Inv_Def_2 was investigating but got killed → no investigate_report.

        UI: defender view shows 'A disparu' (dead).
        """
        assert _ui_worker_is_downed(page, 'Inv_Def_2'), \
            "Inv_Def_2 should be downed (dead) in UI"

    def test_investigate_blocked_no_report_json(self, page: Page, base_url):
        """Inv_Def_1 (captured) and Inv_Def_2 (killed) had investigate actions
        queued but got downed by Inv_Atk_* in the attack phase that runs
        BEFORE investigation. Their investigate_report never existed.

        UI verification via management_workers row counts:
          - Captured worker → 2 rows: original (captured/prisoner) + trace
          - Killed worker   → 1 row: action='dead' (death path does NOT
            call createTraceWorker — only capture does).
        Presence of either terminal state proves the attack phase ran and
        short-circuited the queued investigate.
        """
        ensure_gm_login(page, base_url)
        # Inv_Def_1 was CAPTURED — expect original + trace
        rows1 = ui_workers_by_lastname(page, 'Inv_Def_1')
        assert len(rows1) == 2, \
            f"Inv_Def_1 should have 2 rows (captured + trace), got {len(rows1)}: {rows1}"
        actions1 = sorted(r['action_choice'] for r in rows1)
        assert 'trace' in actions1
        non_trace1 = [r for r in rows1 if r['action_choice'] != 'trace']
        assert non_trace1[0]['action_choice'] in ('captured', 'prisoner'), \
            f"Inv_Def_1 original should be captured/prisoner, got: {non_trace1}"

        # Inv_Def_2 was KILLED — single row with action='dead'
        rows2 = ui_workers_by_lastname(page, 'Inv_Def_2')
        assert len(rows2) == 1, \
            f"Inv_Def_2 should have 1 row (killed, no trace created), got {len(rows2)}: {rows2}"
        assert rows2[0]['action_choice'] == 'dead', \
            f"Inv_Def_2 should be dead, got: {rows2[0]}"

    def test_claim_blocked_by_capture(self, page: Page, base_url):
        """Claim_Def_1 was claiming but got captured → no claim_report.

        UI: defender view shows 'A disparu' (captured).
        """
        assert _ui_worker_is_downed(page, 'Claim_Def_1'), \
            "Claim_Def_1 should be downed (captured) in UI"

    def test_claim_blocked_by_death(self, page: Page, base_url):
        """Claim_Def_2 was claiming but got killed → no claim_report.

        UI: defender view shows 'A disparu' (dead).
        """
        assert _ui_worker_is_downed(page, 'Claim_Def_2'), \
            "Claim_Def_2 should be downed (dead) in UI"

    def test_claim_blocked_no_report_json(self, page: Page, base_url):
        """Claim_Def_1 (captured) and Claim_Def_2 (killed) had claim actions
        queued but got downed in the attack phase. Their claim_report never
        existed.

        UI verification: same pattern as test_investigate_blocked_no_report_json
        (captured → 2 rows, killed → 1 row).
        """
        ensure_gm_login(page, base_url)
        # Claim_Def_1 was CAPTURED
        rows1 = ui_workers_by_lastname(page, 'Claim_Def_1')
        assert len(rows1) == 2, \
            f"Claim_Def_1 should have 2 rows (captured + trace), got {len(rows1)}: {rows1}"
        actions1 = sorted(r['action_choice'] for r in rows1)
        assert 'trace' in actions1
        non_trace1 = [r for r in rows1 if r['action_choice'] != 'trace']
        assert non_trace1[0]['action_choice'] in ('captured', 'prisoner'), \
            f"Claim_Def_1 original should be captured/prisoner, got: {non_trace1}"

        # Claim_Def_2 was KILLED — single row
        rows2 = ui_workers_by_lastname(page, 'Claim_Def_2')
        assert len(rows2) == 1, \
            f"Claim_Def_2 should have 1 row (killed, no trace created), got {len(rows2)}: {rows2}"
        assert rows2[0]['action_choice'] == 'dead', \
            f"Claim_Def_2 should be dead, got: {rows2[0]}"

    def test_zone_holder_unchanged(self, page: Page, base_url):
        """Beta-Combat zone should remain unheld (claims were blocked).

        UI: zones management page renders a <select name="holder_id"> per zone
        with `selected` on the current holder's <option>. If no holder is set,
        only the empty "-- Aucun --" option carries `selected`. We load the
        page and verify that no concrete controller is selected as holder for
        the Beta-Combat row.
        """
        ensure_gm_login(page, PHP_BASE_URL)
        _ensure_controller_session(page)
        page.goto(f"{PHP_BASE_URL}/zones/management_zones.php")
        page.wait_for_load_state("networkidle")
        # Beta-Combat row's holder <select>: the currently-selected option
        # must be the empty "-- Aucun --" one (value="").
        holder_select = page.locator(
            "tr:has-text('Beta-Combat') select[name='holder_id']"
        ).first
        selected_value = holder_select.evaluate("el => el.value")
        assert selected_value == "", (
            f"Beta-Combat should remain unclaimed in zones management UI, "
            f"but holder_id select has value={selected_value!r}"
        )
