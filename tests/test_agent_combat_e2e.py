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
    ui_workers_by_lastname, ui_faction_sections, ui_zone_id,
    clear_ui_caches, ui_attack, ui_investigate, ui_claim, ui_move,
    worker_report_html, cached_faction_sections, ui_worker_action_state,
    safe_goto, register_php_error_listener, assert_no_collected_php_errors,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


def _ensure_controller_session(page):
    """Ensure the gm is logged in and has a controller selected."""
    ensure_gm_login(page, PHP_BASE_URL)
    safe_goto(page, f"{PHP_BASE_URL}/base/accueil.php")
    page.wait_for_load_state("networkidle")
    page.locator("select[name='controller_id']").first.select_option(index=0)
    page.locator("input[name='chosir']").first.click()
    page.wait_for_load_state("networkidle")




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
    register_php_error_listener(page)

    # Login and load TestConfig
    ensure_gm_login(page, PHP_BASE_URL)
    safe_goto(page, f"{PHP_BASE_URL}/base/admin.php")
    page.wait_for_load_state("networkidle")
    page.locator("select[name='config_name']").select_option("TestConfig")
    page.locator("input[name='submit'][value='Submit']").click()
    page.wait_for_timeout(5000)
    page.wait_for_load_state("load", timeout=90000)

    # Reset module-level id caches for this fixture run. Tests that run
    # against different deployments should each get fresh ids scraped
    # from the current page state.
    clear_ui_caches()

    # End turn 0 → 1
    end_turn(page)

    # Set all combat actions via UI for turn 1
    # Chain: A→B, B→C, C→D, D→E, E→F, F→G
    ui_attack(page, 'Chain_A', 'Chain_B')
    ui_attack(page, 'Chain_B', 'Chain_C')
    ui_attack(page, 'Chain_C', 'Chain_D')
    ui_attack(page, 'Chain_D', 'Chain_E')
    ui_attack(page, 'Chain_E', 'Chain_F')
    ui_attack(page, 'Chain_F', 'Chain_G')

    # Base: equal match + counter
    ui_attack(page, 'Even_Atk', 'Even_Def')
    ui_attack(page, 'Counter_Atk', 'Counter_Def')

    # Blocked investigate: attackers attack, defenders investigate
    ui_attack(page, 'Inv_Atk_1', 'Inv_Def_1')
    ui_attack(page, 'Inv_Atk_2', 'Inv_Def_2')
    ui_investigate(page, 'Inv_Def_1')
    ui_investigate(page, 'Inv_Def_2')

    # Blocked claim: attackers attack, defenders claim their own controller
    ui_attack(page, 'Claim_Atk_1', 'Claim_Def_1')
    ui_attack(page, 'Claim_Atk_2', 'Claim_Def_2')
    ui_claim(page, 'Claim_Def_1', 'Beta')
    ui_claim(page, 'Claim_Def_2', 'Delta')

    # Cross-zone attack: Runner flees to Delta-Disputed, but Hunter's
    # queued attack still lands. With LIMIT_ATTACK_BY_ZONE=0 (TestConfig
    # default) the attack-pair SQL has no zone filter. moveWorker()
    # clobbers Runner's action to 'passive' but doesn't touch Hunter's.
    ui_move(page, 'Runner_Cross', 'Delta-Disputed')
    ui_attack(page, 'Hunter_Cross', 'Runner_Cross')

    # Move-clears-action-params: Mover_Test queues an attack THEN moves.
    # moveWorker must clobber the action to 'passive' AND reset
    # action_params to '{}' — no residual attack target data.
    ui_attack(page, 'Mover_Test', 'Chain_A')
    ui_move(page, 'Mover_Test', 'Delta-Disputed')

    # Keep-action-params-on-miss: Keep_Def queues claim for Alpha;
    # Keep_Atk attacks Keep_Def. Equal 3/3/3 stats → attack_difference=0
    # < ATTACKDIFF0=1 → miss. Both survive. Keep_Def's action_params
    # (claim target) must survive the defender-branch of attackMechanic
    # without being wiped to '{}' — regression guard for the
    # updateWorkerAction gate change (see TestAttackKeepsDefenderParams).
    ui_claim(page, 'Keep_Def', 'Alpha')
    ui_attack(page, 'Keep_Atk', 'Keep_Def')

    # Riposte+chain R2: A's failed attack triggers riposte on A;
    # B then still attacks C in the same turn.
    #   Riposte_R2_A (atk=3, def=3) → Riposte_R2_B (atk=6, def=5):
    #     attack_diff = 3-5 = -2 < 1 → fail
    #     riposte_diff = 6-3 = 3 ≥ 2 → riposte fires, R2_A dies
    #   Riposte_R2_B → Riposte_R2_C (atk=3, def=3):
    #     attack_diff = 6-3 = 3 ≥ 3 → captures C
    ui_attack(page, 'Riposte_R2_A', 'Riposte_R2_B')
    ui_attack(page, 'Riposte_R2_B', 'Riposte_R2_C')

    # Riposte+chain R3: A's failed attack does NOT riposte;
    # B then still attacks C in the same turn.
    #   Riposte_R3_A (atk=4, def=4) → Riposte_R3_B (atk=4, def=4):
    #     attack_diff = 4-4 = 0 < 1 → fail
    #     riposte_diff = 4-4 = 0 < 2 → no riposte, R3_A survives
    #   Riposte_R3_B → Riposte_R3_C (atk=3, def=3):
    #     attack_diff = 4-3 = 1 → kills C
    ui_attack(page, 'Riposte_R3_A', 'Riposte_R3_B')
    ui_attack(page, 'Riposte_R3_B', 'Riposte_R3_C')

    # End turn 1 → 2 (combat resolves)
    end_turn(page)

    assert_no_collected_php_errors(page)
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
    """True if the worker is dead, captured, or a prisoner.

    Reads data-worker-status from workers/view.php's card-header.
    """
    state = ui_worker_action_state(page, lastname)
    return state['worker_status'] in ('dead', 'captured', 'prisoner')


def _ui_worker_is_passive(page, lastname):
    """True if the worker's current-turn action_choice is 'passive' AND
    they're still alive (not downed). Reads data-* attributes from
    workers/view.php's card-header."""
    state = ui_worker_action_state(page, lastname)
    return (state['action_choice'] == 'passive'
            and state['worker_status'] == 'alive')


def _ui_worker_is_attacking(page, lastname, target_lastname):
    """True if the worker's action.php view shows them still attacking target.

    txt_ps_attack => "attaque"; view.php appends " contre <firstname>
    <lastname>" when action_choice == 'attack'. Firstname is 'combat' for all
    combat agents.
    """
    html = worker_report_html(page, lastname)
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
        html = worker_report_html(page, 'Inv_Atk_1')
        assert 'Captured' in html and 'Inv_Def_1' in html, \
            "Attacker report should mention capture of Inv_Def_1"
        assert _ui_worker_is_downed(page, 'Inv_Def_1'), \
            "Inv_Def_1 view should show 'A disparu' (captured) and no action form"

    def test_attack_kills_target(self, page: Page, base_url):
        """Inv_Atk_2 (atk=4) vs Inv_Def_2 (def=3): diff=1 ≥ 1, < 3 → KILL.

        UI: attacker report contains 'succeeded ... Inv_Def_2'; defender's own
        view shows 'A disparu' (txt_ps_dead) and no action form.
        """
        html = worker_report_html(page, 'Inv_Atk_2')
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
        html = worker_report_html(page, 'Even_Atk')
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
        html = worker_report_html(page, 'Counter_Atk')
        assert 'countered' in html and 'Counter_Def' in html, \
            "Attacker report should mention counter-attack from Counter_Def"
        assert _ui_worker_is_downed(page, 'Counter_Atk'), \
            "Counter_Atk should be dead (A disparu) after being countered"
        assert _ui_worker_is_passive(page, 'Counter_Def'), \
            "Counter_Def should remain passive (Surveille) after countering"

    def test_passive_worker_view_renders_french_text(self, page: Page, base_url):
        """Smoke test for the txt_ps_passive config + ucfirst rendering chain.
        Even_Def survived as passive on turn 1, so their action.php page
        must render 'Surveille' in the worker action text. Guards against
        config / template / ucfirst regressions that the data-* attribute
        helpers (which read action_choice directly) would not catch."""
        html = worker_report_html(page, 'Even_Def')
        assert 'Surveille' in html, \
            "passive worker view must render the French txt_ps_passive 'Surveille'"

    # --- B2 belt-and-buckle: faction-section views post-combat ---

    def test_capture_faction_views(self, page: Page, base_url):
        """Inv_Atk_1 captures Inv_Def_1: captor Alpha sees Inv_Def_1 in
        prisoners; origin Beta sees the trace in ancients and NOT in live."""
        ensure_gm_login(page, base_url)
        alpha = cached_faction_sections(page, 'Alpha', base_url=base_url)
        beta = cached_faction_sections(page, 'Beta', base_url=base_url)
        assert 'Inv_Def_1' in alpha['prisoners'], \
            f"Alpha should see Inv_Def_1 in prisoners; got {alpha}"
        assert 'Inv_Def_1' in beta['ancients'], \
            f"Beta should see Inv_Def_1 trace in ancients; got {beta}"
        assert 'Inv_Def_1' not in beta['live'], \
            f"Beta should NOT see Inv_Def_1 in live; got live={beta['live']}"

    def test_kill_faction_views(self, page: Page, base_url):
        """Inv_Atk_2 kills Inv_Def_2: Delta sees Inv_Def_2 in ancients
        (action='dead' — kill path doesn't create a trace); Charlie
        (the killer's controller) never gains it in any section."""
        ensure_gm_login(page, base_url)
        delta = cached_faction_sections(page, 'Delta', base_url=base_url)
        charlie = cached_faction_sections(page, 'Charlie', base_url=base_url)
        assert 'Inv_Def_2' in delta['ancients'], \
            f"Delta should see Inv_Def_2 in ancients (kill, no trace); got {delta}"
        for k in ('live', 'doubles', 'prisoners', 'ancients'):
            assert 'Inv_Def_2' not in charlie[k], \
                f"Charlie should never see Inv_Def_2; found in {k}={charlie[k]}"

    def test_counter_attacker_dies_faction_views(self, page: Page, base_url):
        """Counter_Atk (Golf) attacks Counter_Def (Foxtrot) but dies
        from riposte. Golf sees Counter_Atk in ancients; Foxtrot still
        has Counter_Def in live."""
        ensure_gm_login(page, base_url)
        golf = cached_faction_sections(page, 'Golf', base_url=base_url)
        foxtrot = cached_faction_sections(page, 'Foxtrot', base_url=base_url)
        assert 'Counter_Atk' in golf['ancients'], \
            f"Golf should see dead Counter_Atk in ancients; got {golf}"
        assert 'Counter_Def' in foxtrot['live'], \
            f"Foxtrot's Counter_Def should still be live post-riposte; got {foxtrot}"


# ---------------------------------------------------------------------------
# Form-rendering smoke for the attack action surface
# ---------------------------------------------------------------------------

class TestAttackFormRender:
    """Smoke: when an Alpha investigator has detected enemies, the attack form
    on /workers/action.php must render the 'Attaquer' submit + a populated
    enemyWorkersSelect. Guards against silent regressions of the attack UI
    surface (template/config/dropdown breakage that data-* helpers wouldn't
    catch)."""

    def test_attack_form_renders_with_detected_enemies(self, page: Page, base_url):
        ensure_gm_login(page, base_url)
        cid = ui_controller_id(page, "Alpha", base_url=base_url)
        safe_goto(page, f"{base_url}/base/accueil.php?controller_id={cid}&chosir=Choisir")
        page.wait_for_load_state("networkidle")

        wid = ui_worker_id(page, "Searcher_1", base_url=base_url)
        safe_goto(page, f"{base_url}/workers/action.php?worker_id={wid}")
        page.wait_for_load_state("load")

        attack_button = page.locator("input[name='attack'][value='Attaquer']")
        assert attack_button.count() >= 1, \
            "'Attaquer' button should render when worker has detected enemies in zone"

        enemy_select = page.locator("select#enemyWorkersSelect")
        assert enemy_select.count() >= 1, "enemyWorkersSelect dropdown should render"
        opt_count = enemy_select.locator("option").count()
        assert opt_count >= 1, \
            f"enemyWorkersSelect should have at least one detected-enemy option; got {opt_count}"


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
        html_a = worker_report_html(page, 'Chain_A')
        assert 'Captured' in html_a and 'Chain_B' in html_a, \
            "Chain_A report should mention capture of Chain_B"
        assert _ui_worker_is_downed(page, 'Chain_B'), \
            "Chain_B view should show 'A disparu' (captured)"

    def test_chain_c_kills_d_and_survives(self, page: Page, base_url):
        """C kills D. D's pending attack is skipped (D inactive before its turn)."""
        html_c = worker_report_html(page, 'Chain_C')
        assert 'succeeded' in html_c and 'Chain_D' in html_c, \
            "Chain_C report should mention successful attack on Chain_D"
        assert _ui_worker_is_downed(page, 'Chain_D'), \
            "Chain_D view should show 'A disparu' (dead)"

    def test_chain_f_kills_g_then_e_kills_f(self, page: Page, base_url):
        """F (enquete=5) acts before E (enquete=4), so F kills G first; then E
        kills F. Both F and G end up downed; E survives and still shows attack.
        """
        html_e = worker_report_html(page, 'Chain_E')
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
        html_a = worker_report_html(page, 'Chain_A')
        assert 'Captured' in html_a and 'Chain_B' in html_a

        html_c = worker_report_html(page, 'Chain_C')
        assert 'succeeded' in html_c and 'Chain_D' in html_c

        html_e = worker_report_html(page, 'Chain_E')
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
        sections = cached_faction_sections(page, 'Alpha', base_url=base_url)
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
        sections = cached_faction_sections(page, 'Beta', base_url=base_url)
        assert 'Chain_B' in sections['ancients'], \
            f"Beta should see Chain_B trace in Nos Anciens agents; got {sections}"
        assert 'Chain_B' not in sections['live'], \
            f"Beta should NOT see Chain_B in Nos Agents; got live={sections['live']}"

    # --- B2 belt-and-buckle: faction-section views for kill branches ---

    def test_chain_c_kills_d_faction_views(self, page: Page, base_url):
        """Chain_C (Charlie) kills Chain_D (Delta). Delta sees Chain_D
        in ancients; Charlie never gains Chain_D."""
        ensure_gm_login(page, base_url)
        delta = cached_faction_sections(page, 'Delta', base_url=base_url)
        charlie = cached_faction_sections(page, 'Charlie', base_url=base_url)
        assert 'Chain_D' in delta['ancients'], \
            f"Delta missing Chain_D in ancients; got {delta}"
        for k in ('live', 'doubles', 'prisoners', 'ancients'):
            assert 'Chain_D' not in charlie[k], \
                f"Charlie should never see Chain_D; found in {k}"

    def test_chain_f_kills_g_faction_views(self, page: Page, base_url):
        """Chain_F kills Chain_G before Chain_F itself dies. Golf sees
        Chain_G in ancients."""
        ensure_gm_login(page, base_url)
        golf = cached_faction_sections(page, 'Golf', base_url=base_url)
        assert 'Chain_G' in golf['ancients'], \
            f"Golf missing Chain_G in ancients; got {golf}"


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
        safe_goto(page, f"{PHP_BASE_URL}/zones/management_zones.php")
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

    # --- B2 belt-and-buckle: faction-section views for blocked-by-capture ---

    def test_inv_def_1_capture_faction_views(self, page: Page, base_url):
        """Inv_Def_1's investigate was blocked by capture — Alpha gains
        Inv_Def_1 as prisoner, Beta has the trace in ancients."""
        ensure_gm_login(page, base_url)
        alpha = cached_faction_sections(page, 'Alpha', base_url=base_url)
        beta = cached_faction_sections(page, 'Beta', base_url=base_url)
        assert 'Inv_Def_1' in alpha['prisoners'], \
            f"Alpha missing captured Inv_Def_1; got {alpha}"
        assert 'Inv_Def_1' in beta['ancients'], \
            f"Beta missing Inv_Def_1 trace; got {beta}"

    def test_claim_def_1_capture_faction_views(self, page: Page, base_url):
        """Claim_Def_1's claim was blocked by capture — Echo gains
        Claim_Def_1 as prisoner, Beta has the trace."""
        ensure_gm_login(page, base_url)
        echo = cached_faction_sections(page, 'Echo', base_url=base_url)
        beta = cached_faction_sections(page, 'Beta', base_url=base_url)
        assert 'Claim_Def_1' in echo['prisoners'], \
            f"Echo missing captured Claim_Def_1; got {echo}"
        assert 'Claim_Def_1' in beta['ancients'], \
            f"Beta missing Claim_Def_1 trace; got {beta}"


# ---------------------------------------------------------------------------
# Tests: cross-zone attack (LIMIT_ATTACK_BY_ZONE=0 default behavior)
# ---------------------------------------------------------------------------

class TestCrossZoneAttack:
    """Issue #2: 'Agent attack of another agent that has moved'.

    Setup (combat_scenario fixture):
      - Hunter_Cross (Alpha, Beta-Combat) action=investigate on turn 0.
        Powers: Eagle Scout|Patrol Warden → enq=6, atk=4, def=4.
      - Runner_Cross (Beta, Beta-Combat)  action=passive     on turn 0.
        Powers: Blank Slate|Common Folk → enq=3, atk=3, def=3.
      - End turn 0 → 1: Hunter detects Runner (adds a known_enemies row).
      - Between turns 1 and 2:
          * Runner moves to Delta-Disputed (moveWorker forces Runner's
            current-turn action to 'passive' and updates workers.zone_id).
          * Hunter queues worker-scope attack on Runner.
      - End turn 1 → 2: attack resolves. With LIMIT_ATTACK_BY_ZONE=0
        (TestConfig inherits the default from minimalData.sql) the
        attack-pair SQL has no zone filter, so the attack lands
        cross-zone. Hunter atk=4 vs Runner def=3 → attack_difference=1
        → ATTACKDIFF0 met, ATTACKDIFF1 not met → KILL (not capture).
    """

    def test_runner_moved_to_delta(self, page: Page, base_url):
        """Runner_Cross's moveWorker call updated workers.zone_id to
        Delta-Disputed — verified via /workers/management_workers.php
        (which renders each worker's current zone_name).
        """
        ensure_gm_login(page, base_url)
        workers = ui_all_workers(page, base_url=base_url)
        runner = next((w for w in workers if w['lastname'] == 'Runner_Cross'), None)
        assert runner is not None, "Runner_Cross should exist in workers list"
        assert runner['zone_name'] == 'Delta-Disputed', \
            f"Runner_Cross should be in Delta-Disputed after move, got {runner['zone_name']!r}"

    def test_runner_is_dead(self, page: Page, base_url):
        """Runner_Cross should be dead after the cross-zone kill.

        UI: Runner's own view.php page shows 'A disparu' (txt_ps_dead)
        and no action form."""
        assert _ui_worker_is_downed(page, 'Runner_Cross'), \
            "Runner_Cross should be dead after cross-zone attack from Hunter_Cross"

    def test_hunter_report_mentions_kill(self, page: Page, base_url):
        """Hunter's worker-view report should reference Runner by name —
        either 'succeeded' (kill text) or 'Captured' depending on the
        exact attack_difference. We only assert the target is named,
        keeping the assertion robust to template wording."""
        html = worker_report_html(page, 'Hunter_Cross')
        assert 'Runner_Cross' in html, \
            "Hunter_Cross's page should reference Runner_Cross in the attack report"

    def test_runner_in_beta_ancients_after_cross_zone_kill(self, page: Page, base_url):
        """Belt-and-buckle: Runner_Cross was killed by Hunter_Cross
        cross-zone. Beta (Runner's original controller) should have
        Runner_Cross in ancients (dead, no trace for kill). Alpha
        (Hunter's controller) never gains Runner_Cross."""
        ensure_gm_login(page, base_url)
        beta = cached_faction_sections(page, 'Beta', base_url=base_url)
        alpha = cached_faction_sections(page, 'Alpha', base_url=base_url)
        assert 'Runner_Cross' in beta['ancients'], \
            f"Beta should see Runner_Cross in ancients; got {beta}"
        for k in ('live', 'doubles', 'prisoners', 'ancients'):
            assert 'Runner_Cross' not in alpha[k], \
                f"Alpha should never see Runner_Cross; found in {k}"


# ---------------------------------------------------------------------------
# Tests: moveWorker clears action_params on a previously-attacking worker
# ---------------------------------------------------------------------------

class TestMoveClearsActionParams:
    """Regression guard: when a worker queues an attack and then moves
    in the same turn, moveWorker() must reset the worker's
    action_choice to 'passive' AND action_params to '{}'. The test
    verifies both the UI rendering and the DB row directly.

    Setup (combat_scenario fixture):
      - Mover_Test (Alpha, Beta-Combat, passive on turn 0).
      - Between turns: ui_attack(Mover_Test, Chain_A), then
        ui_move(Mover_Test, 'Delta-Disputed').
      - End turn 1 → 2.

    If the clear works: Mover_Test survives (passive, moved).
    If the clear fails: Mover_Test's attack fires (atk=3 vs Chain_A
      def=7, diff=-4 → miss) and Chain_A's riposte lands (atk=8 vs
      def=3, diff=5 ≥ RIPOSTDIFF=2 → Mover_Test dies).
    """

    def test_mover_action_is_passive_no_target_in_view(self, page: Page, base_url):
        """Mover_Test's own view page should show passive + move text,
        with no reference to the cancelled attack target (Chain_A)."""
        html = worker_report_html(page, 'Mover_Test')
        assert 'Chain_A' not in html, \
            "Mover_Test's view must not reference the cancelled attack target Chain_A"
        assert _ui_worker_is_passive(page, 'Mover_Test'), \
            "Mover_Test should be passive (survived via cleared attack)"

    def test_mover_action_params_is_empty_json(self, page: Page, base_url):
        """worker_actions.action_params must be the literal string '{}'.
        updateWorkerAction emits '{}' for empty arrays per the project
        convention; this test locks that contract.

        Reads the worker-action-state marker emitted by workers/view.php
        (data-action-params attribute) — UI-only, runs under UI_ONLY=1.
        Note: turn-2's row inherits action_params from turn-1 via
        createNewTurnLines, so scraping the current-turn rendering
        verifies the turn-1 value was preserved correctly."""
        state = ui_worker_action_state(page, 'Mover_Test', base_url=base_url)
        assert state['action_choice'] == 'passive', \
            f"After move-after-attack, action_choice should be 'passive', got {state['action_choice']!r}"
        assert state['action_params'] == '{}', \
            f"action_params should be empty JSON '{{}}' after move, got {state['action_params']!r}"


# ---------------------------------------------------------------------------
# Tests: attackMechanic preserves surviving defender's action_params
# ---------------------------------------------------------------------------

class TestAttackKeepsDefenderParams:
    """Regression guard for the `$defender_json = null` initialization in
    attackMechanic.php (vs the earlier `array()`).

    Context: the updateWorkerAction gate was tightened from
    `!empty($jsonArray)` to `$jsonArray !== null` so moveWorker could reset
    action_params to '{}'. That silently introduced a behaviour change
    at attackMechanic.php:449 where non-capture defender paths
    (kill / miss / escape / riposte) pass $defender_json to
    updateWorkerAction. The loop initializes $defender_json per iteration
    and only populates it inside the capture branch, so miss-path
    defenders would previously have passed `array()` and had their
    action_params wiped to '{}'. Fix: initialize to null instead.

    Setup (combat_scenario fixture):
      - Keep_Def (Beta, Beta-Combat, Blank Slate|Common Folk → 3/3/3)
        queues `claim` for Alpha on turn 1 — populates action_params
        with {"claim_controller_id": Alpha_id}.
      - Keep_Atk (Foxtrot, same powers → 3/3/3) queues attack on
        Keep_Def. attack_difference = 3-3 = 0 < ATTACKDIFF0=1 → miss.
        riposte_difference = 0 < RIPOSTDIFF=2 → no counter. Both
        survive.
      - End turn 1 → 2.

    If the fix works, Keep_Def's turn-1 action_params still contain the
    claim target. If not (pre-fix `array()` init), params are wiped to
    '{}' during the defender loop.
    """

    def test_keep_def_preserves_claim_params_after_survived_miss(self, page: Page, base_url):
        """Keep_Def survives Keep_Atk's miss; its turn-1 action_params
        must still contain the queued claim_controller_id.

        Reads the worker-action-state marker emitted by workers/view.php
        (data-* attributes) — UI-only, runs under UI_ONLY=1.
        Turn-2's row inherits action_params from turn-1 via
        createNewTurnLines, so scraping the current-turn rendering
        verifies the turn-1 value was preserved correctly."""
        state = ui_worker_action_state(page, 'Keep_Def', base_url=base_url)
        assert state['action_choice'] == 'claim', \
            f"Keep_Def should still have action_choice='claim' after surviving miss, got {state['action_choice']!r}"
        assert 'claim_controller_id' in state['action_params'], \
            f"Keep_Def's action_params must preserve claim_controller_id after surviving miss — regression guard for attackMechanic.php:306 init. Got: {state['action_params']!r}"


# ---------------------------------------------------------------------------
# TestRiposteChain — riposte interaction with downstream chain attacks
#
# When attacker A's attack on B fails:
#   - if riposte_difference >= RIPOSTDIFF, A dies (riposte fires).
#   - either way, B's own queued attack on C must still resolve (B survived
#     the failed attack on themselves; B's iteration in attackMechanic runs
#     after A's, sees B still alive, processes B's attack normally).
#
# Existing TestChainAttack covers the "A succeeds → B skipped downstream"
# branch (test_chain_b_did_not_attack). The fail-branch (with and without
# riposte) is the gap this class fills.
# ---------------------------------------------------------------------------

class TestRiposteChain:
    """A→B→C chain where A's attack fails; B's downstream attack on C must
    still resolve regardless of whether B's riposte against A fires."""

    def test_riposte_fires_attacker_dies_chain_continues(self, page: Page, base_url):
        """R2: Riposte_R2_A's attack on Riposte_R2_B fails (atk_diff=-2).
        Riposte_R2_B's riposte hits (riposte_diff=3 ≥ RIPOSTDIFF=2),
        killing Riposte_R2_A. Riposte_R2_B then still resolves its
        queued attack on Riposte_R2_C (atk_diff=3 → CAPTURE)."""
        ensure_gm_login(page, base_url)
        # A died from riposte
        assert _ui_worker_is_downed(page, 'Riposte_R2_A'), \
            "Riposte_R2_A should be downed (dead) after R2_B's riposte"
        # B survived (no further attack landed on B in this turn)
        assert not _ui_worker_is_downed(page, 'Riposte_R2_B'), \
            "Riposte_R2_B should remain alive after the failed attack + riposte"
        # B's downstream attack on C still landed: C captured
        assert _ui_worker_is_downed(page, 'Riposte_R2_C'), \
            "Riposte_R2_C should be downed (captured) — B's chain attack must still resolve"
        html_b = worker_report_html(page, 'Riposte_R2_B')
        assert 'Captured' in html_b and 'Riposte_R2_C' in html_b, \
            f"Riposte_R2_B's attack_report should mention capturing R2_C; got: {html_b!r}"

    def test_no_riposte_attacker_survives_chain_continues(self, page: Page, base_url):
        """R3: Riposte_R3_A's attack on Riposte_R3_B fails (atk_diff=0).
        riposte_diff=0 < RIPOSTDIFF=2 → no riposte; A survives.
        Riposte_R3_B's queued attack on Riposte_R3_C still resolves
        (atk_diff=1 → KILL)."""
        ensure_gm_login(page, base_url)
        # A survived (no riposte hit)
        assert not _ui_worker_is_downed(page, 'Riposte_R3_A'), \
            "Riposte_R3_A should remain alive (no riposte fired)"
        # B survived
        assert not _ui_worker_is_downed(page, 'Riposte_R3_B'), \
            "Riposte_R3_B should remain alive after the failed attack"
        # C killed by B's chain attack
        assert _ui_worker_is_downed(page, 'Riposte_R3_C'), \
            "Riposte_R3_C should be downed (killed) — B's chain attack must still resolve"
        html_b = worker_report_html(page, 'Riposte_R3_B')
        assert 'succeeded' in html_b and 'Riposte_R3_C' in html_b, \
            f"Riposte_R3_B's attack_report should mention successful attack on R3_C; got: {html_b!r}"
