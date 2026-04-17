"""Playwright E2E tests for combat resolution mechanics.

Each test inserts a fresh set of combat agents (via DB) on top of TestConfig,
then runs end-of-turn twice:
  - Turn 0 → 1: agents are in 'investigate' so they detect each other
  - Turn 1 → 2: agents are in 'attack' (target set after turn 0) — combat resolves

Combat thresholds (TestConfig):
  ATTACKDIFF0=1 (kill), ATTACKDIFF1=3 (capture), RIPOSTDIFF=2, RIPOSTACTIVE=1
  PASSIVEVAL=3, MINROLL=MAXROLL=3 → every action contributes +3 to enquete/attack/defence.
  So: enquete_val = 3 + Σ(power.enquete), attack_val = 3 + Σ(power.attack), etc.
  attack_difference = attacker.power.attack − defender.power.defence
  riposte_difference = defender.power.attack − attacker.power.defence

Resolution order: attackers act in DESC enquete_val. If a worker's
action_choice becomes INACTIVE ('dead'/'captured') before their turn,
their pending attack is skipped.

Run:
    python3 -m pytest tests/test_agent_combat_e2e.py -v
"""
import json

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
# Module fixture: load TestConfig once
# ---------------------------------------------------------------------------

@pytest.fixture(scope="module", autouse=True)
def load_test_config(browser):
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
    yield


# ---------------------------------------------------------------------------
# Per-test fixture: wipe combat state before each test
# ---------------------------------------------------------------------------

@pytest.fixture(autouse=True)
def reset_combat_state():
    """Clear all worker-related rows and reset turncounter to 0."""
    if not DB_AVAILABLE:
        yield
        return
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SET FOREIGN_KEY_CHECKS = 0")
    for table in (
        'worker_actions', 'worker_powers', 'controller_worker',
        'workers_trace_links', 'workers',
        'controllers_known_enemies', 'controller_known_locations',
        'location_attack_logs',
    ):
        cursor.execute(f"TRUNCATE TABLE `{GAME_PREFIX}{table}`")
    cursor.execute("SET FOREIGN_KEY_CHECKS = 1")
    cursor.execute(
        f"UPDATE `{GAME_PREFIX}mechanics` SET turncounter = 0, end_step = '', gamestate = 0"
    )
    conn.commit()
    conn.close()
    yield


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def _lookup_id(table, where_col, where_val):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute(
        f"SELECT id FROM `{GAME_PREFIX}{table}` WHERE `{where_col}` = %s",
        (where_val,),
    )
    row = cursor.fetchone()
    conn.close()
    return row['id'] if row else None


def _lookup_link_power_id(power_name):
    """Return link_power_type.id for a power name (any power_type)."""
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute(f"""
        SELECT lpt.id FROM `{GAME_PREFIX}link_power_type` lpt
        JOIN `{GAME_PREFIX}powers` p ON p.id = lpt.power_id
        WHERE p.name = %s LIMIT 1
    """, (power_name,))
    row = cursor.fetchone()
    conn.close()
    return row['id'] if row else None


def insert_combat_worker(lastname, controller_lastname, zone_name, powers,
                         action_choice='investigate'):
    """Create a worker with given controller, zone, powers, turn-0 action.

    Returns the new worker_id.
    """
    conn = get_db()
    cursor = conn.cursor()
    origin_id = _lookup_id('worker_origins', 'name', 'origine Accessible')
    zone_id = _lookup_id('zones', 'name', zone_name)
    controller_id = _lookup_id('controllers', 'lastname', controller_lastname)
    assert origin_id and zone_id and controller_id, \
        f"Missing lookup: origin={origin_id} zone={zone_id} ctrl={controller_id}"

    cursor.execute(
        f"INSERT INTO `{GAME_PREFIX}workers` (firstname, lastname, origin_id, zone_id) "
        f"VALUES (%s, %s, %s, %s)",
        ('Combat', lastname, origin_id, zone_id),
    )
    worker_id = cursor.lastrowid
    cursor.execute(
        f"INSERT INTO `{GAME_PREFIX}controller_worker` "
        f"(controller_id, worker_id, is_primary_controller) VALUES (%s, %s, 1)",
        (controller_id, worker_id),
    )
    cursor.execute(
        f"INSERT INTO `{GAME_PREFIX}worker_actions` "
        f"(worker_id, controller_id, turn_number, zone_id, action_choice, action_params) "
        f"VALUES (%s, %s, 0, %s, %s, '{{}}')",
        (worker_id, controller_id, zone_id, action_choice),
    )
    for power_name in powers:
        link_id = _lookup_link_power_id(power_name)
        assert link_id, f"Power not found: {power_name}"
        cursor.execute(
            f"INSERT INTO `{GAME_PREFIX}worker_powers` (worker_id, link_power_type_id) "
            f"VALUES (%s, %s)",
            (worker_id, link_id),
        )
    conn.commit()
    conn.close()
    return worker_id


def set_attack(worker_id, target_worker_id, turn=1):
    """Set a worker's action to 'attack' targeting another worker on a turn."""
    params = json.dumps([{"attackScope": "worker", "attackID": int(target_worker_id)}])
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute(
        f"UPDATE `{GAME_PREFIX}worker_actions` "
        f"SET action_choice = 'attack', action_params = %s "
        f"WHERE worker_id = %s AND turn_number = %s",
        (params, worker_id, turn),
    )
    conn.commit()
    conn.close()


def set_action(worker_id, action_choice, action_params='{}', turn=1):
    """Set a worker's action_choice and action_params on a turn."""
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute(
        f"UPDATE `{GAME_PREFIX}worker_actions` "
        f"SET action_choice = %s, action_params = %s "
        f"WHERE worker_id = %s AND turn_number = %s",
        (action_choice, action_params, worker_id, turn),
    )
    conn.commit()
    conn.close()


def get_status(worker_id, turn=1):
    """Return action_choice for a worker at a turn."""
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute(
        f"SELECT action_choice FROM `{GAME_PREFIX}worker_actions` "
        f"WHERE worker_id = %s AND turn_number = %s",
        (worker_id, turn),
    )
    row = cursor.fetchone()
    conn.close()
    return row['action_choice'] if row else None


def get_report(worker_id, turn=1):
    """Return the report JSON string for a worker at a turn."""
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute(
        f"SELECT report FROM `{GAME_PREFIX}worker_actions` "
        f"WHERE worker_id = %s AND turn_number = %s",
        (worker_id, turn),
    )
    row = cursor.fetchone()
    conn.close()
    return str(row['report'] or '') if row else ''


def end_turn(page: Page, base_url):
    """Trigger one full end-of-turn cycle."""
    ensure_gm_login(page, base_url)
    page.goto(f"{base_url}/mechanics/endTurn.php")
    page.wait_for_load_state("load", timeout=60000)
    html = page.content()
    assert "<b>Warning</b>" not in html, "PHP warning during end turn"
    assert "<b>Fatal error</b>" not in html, "PHP fatal error during end turn"


# ---------------------------------------------------------------------------
# Tests: base interactions (1 attacker + 1 defender)
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestBaseCombat:
    """Single-pair attack outcomes."""

    def test_attack_and_captures(self, page: Page, base_url):
        """High-attack agent capturing a low-defence target.

        Atk: Eagle Scout(1)+Veteran Tactician(2)+Offensive Stance(1)+War Gear(2) → attack=6, defence=6
        Def: Dark Impulse(-1)+Common Folk(0) → attack=4, defence=2
        attack_diff = 6 - 2 = 4 ≥ ATTACKDIFF1(3) → CAPTURE.
        """
        atk = insert_combat_worker(
            'Capt_Atk', 'Alpha', 'Beta-Combat',
            ['Eagle Scout', 'Veteran Tactician', 'Offensive Stance', 'War Gear'],
            action_choice='passive',
        )
        defn = insert_combat_worker(
            'Capt_Def', 'Beta', 'Beta-Combat',
            ['Dark Impulse', 'Common Folk'],
            action_choice='passive',
        )
        end_turn(page, base_url)
        set_attack(atk, defn, turn=1)
        end_turn(page, base_url)
        assert get_status(defn, turn=1) == 'captured', \
            f"Expected captured, got {get_status(defn, turn=1)}"
        assert get_status(atk, turn=1) == 'attack', \
            f"Attacker should remain 'attack', got {get_status(atk, turn=1)}"

    def test_attack_and_kills(self, page: Page, base_url):
        """Mid-range attack: kills target but doesn't capture.

        Atk: Eagle Scout(1)+Patrol Warden(0) → attack=4, defence=4
        Def: Blank Slate(0)+Common Folk(0) → attack=3, defence=3
        attack_diff = 4 - 3 = 1 → kill (>=ATTACKDIFF0(1), <ATTACKDIFF1(3)).
        riposte_diff = 3 - 4 = -1 < RIPOSTDIFF(2) → no counter.
        """
        atk = insert_combat_worker(
            'Kill_Atk', 'Alpha', 'Beta-Combat',
            ['Eagle Scout', 'Patrol Warden'],
            action_choice='passive',
        )
        defn = insert_combat_worker(
            'Kill_Def', 'Beta', 'Beta-Combat',
            ['Blank Slate', 'Common Folk'],
            action_choice='passive',
        )
        end_turn(page, base_url)
        set_attack(atk, defn, turn=1)
        end_turn(page, base_url)
        assert get_status(defn, turn=1) == 'dead', \
            f"Expected dead, got {get_status(defn, turn=1)}"
        assert get_status(atk, turn=1) == 'attack'

    def test_equally_matched_fails(self, page: Page, base_url):
        """Identical stats: attack_diff = 0 < ATTACKDIFF0(1) → fail, no kill.

        Both: Blank Slate(0)+Common Folk(0) → attack=3, defence=3
        attack_diff = 3-3 = 0 → fails. riposte_diff = 0 < 2 → no counter.
        """
        atk = insert_combat_worker(
            'Even_Atk', 'Alpha', 'Beta-Combat',
            ['Blank Slate', 'Common Folk'],
            action_choice='passive',
        )
        defn = insert_combat_worker(
            'Even_Def', 'Beta', 'Beta-Combat',
            ['Blank Slate', 'Common Folk'],
            action_choice='passive',
        )
        end_turn(page, base_url)
        set_attack(atk, defn, turn=1)
        end_turn(page, base_url)
        assert get_status(defn, turn=1) == 'passive', \
            f"Defender should survive (passive), got {get_status(defn, turn=1)}"
        assert get_status(atk, turn=1) == 'attack', \
            f"Attacker should survive (still 'attack'), got {get_status(atk, turn=1)}"

    def test_counter_attacked_and_dies(self, page: Page, base_url):
        """Weak attacker hits strong defender → attack fails, defender ripostes & kills attacker.

        Atk: Dark Impulse(-1/1/-1)+Common Folk(0/0/0) → attack=4, defence=2
        Def: Brute Force(0/3/2)+Common Folk(0/0/0) → attack=6, defence=5
        attack_diff = 4 - 5 = -1 < 1 → fail.
        riposte_diff = 6 - 2 = 4 ≥ 2 → counter kills attacker.
        """
        atk = insert_combat_worker(
            'Cntr_Atk', 'Alpha', 'Beta-Combat',
            ['Dark Impulse', 'Common Folk'],
            action_choice='passive',
        )
        defn = insert_combat_worker(
            'Cntr_Def', 'Beta', 'Beta-Combat',
            ['Brute Force', 'Common Folk'],
            action_choice='passive',
        )
        end_turn(page, base_url)
        set_attack(atk, defn, turn=1)
        end_turn(page, base_url)
        assert get_status(atk, turn=1) == 'dead', \
            f"Attacker should be killed by counter, got {get_status(atk, turn=1)}"
        assert get_status(defn, turn=1) == 'passive', \
            f"Defender should survive, got {get_status(defn, turn=1)}"


# ---------------------------------------------------------------------------
# Test: chain attack ordered by enquete_val
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestChainAttack:
    """7-agent chain attack with resolution by enquete_val DESC.

    Declarations: A→B, B→C, C→D, D→E, E→F, F→G

    Enquete order (so resolution order): A=8 > B=7 > C=6 > F=5 > E=4 > D=3 > G=2

    Expected outcomes:
      - A captures B → B's pending attack on C is skipped → C survives
      - C kills D → D's pending attack on E is skipped → E survives
      - F kills G (F acts before E because F.enquete > E.enquete)
      - E kills F (E acts after F's attack on G already resolved)
    """

    def test_chain_resolution_by_enquete(self, page: Page, base_url):
        # Powers per agent (enquete / attack / defence shown as power totals):
        # A: EagleScout(2/1/1)+VeteranTactician(2/2/2)+FocusedMind(1/0/0)+WarGear(0/2/1) = e=5,a=5,d=4 → enq_val=8
        # B: EagleScout(2/1/1)+ScoutRunner(2/0/1)                             = e=4,a=1,d=2 → enq_val=7
        # C: EagleScout(2/1/1)+PatrolWarden(1/0/0)                             = e=3,a=1,d=1 → enq_val=6
        # F: CarefulWatch(1/0/1)+PatrolWarden(1/0/0)                             = e=2,a=0,d=1 → enq_val=5
        # E: BruteForce(0/3/2)+PatrolWarden(1/0/0)                             = e=1,a=3,d=2 → enq_val=4
        # D: BlankSlate(0/0/0)+CommonFolk(0/0/0)                             = e=0,a=0,d=0 → enq_val=3
        # G: DarkImpulse(-1/1/-1)+CommonFolk(0/0/0)                         = e=-1,a=1,d=-1 → enq_val=2
        a = insert_combat_worker(
            'Chain_A', 'Alpha', 'Beta-Combat',
            ['Eagle Scout', 'Veteran Tactician', 'Focused Mind', 'War Gear'],
            action_choice='passive',
        )
        b = insert_combat_worker('Chain_B', 'Beta', 'Beta-Combat',
            ['Eagle Scout', 'Scout Runner'], action_choice='passive')
        c = insert_combat_worker('Chain_C', 'Charlie', 'Beta-Combat',
            ['Eagle Scout', 'Patrol Warden'], action_choice='passive')
        d = insert_combat_worker('Chain_D', 'Delta', 'Beta-Combat',
            ['Blank Slate', 'Common Folk'], action_choice='passive')
        e = insert_combat_worker('Chain_E', 'Echo', 'Beta-Combat',
            ['Brute Force', 'Patrol Warden'], action_choice='passive')
        f = insert_combat_worker('Chain_F', 'Foxtrot', 'Beta-Combat',
            ['Careful Watch', 'Patrol Warden'], action_choice='passive')
        g = insert_combat_worker('Chain_G', 'Golf', 'Beta-Combat',
            ['Dark Impulse', 'Common Folk'], action_choice='passive')

        end_turn(page, base_url)

        # Sanity: enquete values strictly descending A > B > C > F > E > D > G
        conn = get_db()
        cursor = conn.cursor()
        cursor.execute(
            f"SELECT w.lastname, wa.enquete_val FROM `{GAME_PREFIX}worker_actions` wa "
            f"JOIN `{GAME_PREFIX}workers` w ON w.id = wa.worker_id "
            f"WHERE wa.turn_number = 0 AND w.lastname LIKE 'Chain_%' "
            f"ORDER BY w.lastname"
        )
        enq = {r['lastname']: r['enquete_val'] for r in cursor.fetchall()}
        conn.close()
        assert enq['Chain_A'] == 8, f"Chain_A enquete: {enq}"
        assert enq['Chain_B'] == 7, f"Chain_B enquete: {enq}"
        assert enq['Chain_C'] == 6, f"Chain_C enquete: {enq}"
        assert enq['Chain_F'] == 5, f"Chain_F enquete: {enq}"
        assert enq['Chain_E'] == 4, f"Chain_E enquete: {enq}"
        assert enq['Chain_D'] == 3, f"Chain_D enquete: {enq}"
        assert enq['Chain_G'] == 2, f"Chain_G enquete: {enq}"

        # Set up the chain: A→B, B→C, C→D, D→E, E→F, F→G
        set_attack(a, b, turn=1)
        set_attack(b, c, turn=1)
        set_attack(c, d, turn=1)
        set_attack(d, e, turn=1)
        set_attack(e, f, turn=1)
        set_attack(f, g, turn=1)

        end_turn(page, base_url)

        # Assertions:
        assert get_status(a, turn=1) == 'attack', \
            f"A survived (captured B), got {get_status(a, turn=1)}"
        assert get_status(b, turn=1) == 'captured', \
            f"B was captured by A, got {get_status(b, turn=1)}"
        # C's pending attack on D resolved (D dies). C survives.
        assert get_status(c, turn=1) == 'attack', \
            f"C survived (killed D), got {get_status(c, turn=1)}"
        assert get_status(d, turn=1) == 'dead', \
            f"D was killed by C, got {get_status(d, turn=1)}"
        # E's attack on F resolves AFTER F killed G. E survives.
        assert get_status(e, turn=1) == 'attack', \
            f"E survived (killed F), got {get_status(e, turn=1)}"
        # F was killed by E (after F killed G).
        assert get_status(f, turn=1) == 'dead', \
            f"F was killed by E, got {get_status(f, turn=1)}"
        # G was killed by F.
        assert get_status(g, turn=1) == 'dead', \
            f"G was killed by F, got {get_status(g, turn=1)}"


# ---------------------------------------------------------------------------
# Tests: dead/captured agents don't complete their pending action
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestActionBlockedByCombat:
    """Verify investigate / claim do not complete when their agent is killed
    or captured during the attack phase (which runs before those mechanics).
    """

    def test_investigate_blocked_when_killed_or_captured(self, page: Page, base_url):
        """Two investigators, one captured + one killed → neither investigates.

        Setup: 2 investigators in Beta-Combat, plus 2 attackers each targeting one.
          - Atk1 captures Inv1 (high attack vs low defence)
          - Atk2 kills Inv2 (medium attack vs low defence)
        Both investigators were 'investigate' on turn 1 — but combat resolves
        before investigateMechanic, so their action_choice becomes
        captured/dead and the investigation is skipped (no investigate_report).
        """
        atk1 = insert_combat_worker(
            'Inv_Atk1', 'Alpha', 'Beta-Combat',
            ['Eagle Scout', 'Veteran Tactician', 'Offensive Stance', 'War Gear'],
            action_choice='passive',
        )
        inv1 = insert_combat_worker(
            'Inv_Def1', 'Beta', 'Beta-Combat',
            ['Dark Impulse', 'Common Folk'],
            action_choice='passive',
        )
        atk2 = insert_combat_worker(
            'Inv_Atk2', 'Charlie', 'Beta-Combat',
            ['Eagle Scout', 'Patrol Warden'],
            action_choice='passive',
        )
        inv2 = insert_combat_worker(
            'Inv_Def2', 'Delta', 'Beta-Combat',
            ['Blank Slate', 'Common Folk'],
            action_choice='passive',
        )
        end_turn(page, base_url)

        # Turn 1: attackers attack, investigators investigate.
        set_attack(atk1, inv1, turn=1)
        set_attack(atk2, inv2, turn=1)
        set_action(inv1, 'investigate', turn=1)
        set_action(inv2, 'investigate', turn=1)
        end_turn(page, base_url)

        assert get_status(inv1, turn=1) == 'captured', \
            f"Inv1 captured, got {get_status(inv1, turn=1)}"
        assert get_status(inv2, turn=1) == 'dead', \
            f"Inv2 dead, got {get_status(inv2, turn=1)}"
        # Investigation reports must NOT have been generated for the downed agents.
        report1 = get_report(inv1, turn=1)
        report2 = get_report(inv2, turn=1)
        assert 'investigate_report' not in report1, \
            f"Inv1 should not have investigate_report (was captured): {report1}"
        assert 'investigate_report' not in report2, \
            f"Inv2 should not have investigate_report (was killed): {report2}"

    def test_claim_blocked_when_killed_or_captured(self, page: Page, base_url):
        """Two claimers, one captured + one killed → neither claims.

        Setup: 2 agents claiming Beta-Combat's holder seat (both claim Alpha as
        target controller), plus 2 attackers killing them. After end-turn,
        Beta-Combat still has no holder (claim was skipped because claimers became
        captured/dead before claimMechanic runs).
        """
        atk1 = insert_combat_worker(
            'Cl_Atk1', 'Alpha', 'Beta-Combat',
            ['Eagle Scout', 'Veteran Tactician', 'Offensive Stance', 'War Gear'],
            action_choice='passive',
        )
        cl1 = insert_combat_worker(
            'Cl_Def1', 'Beta', 'Beta-Combat',
            ['Dark Impulse', 'Common Folk'],
            action_choice='passive',
        )
        atk2 = insert_combat_worker(
            'Cl_Atk2', 'Charlie', 'Beta-Combat',
            ['Eagle Scout', 'Patrol Warden'],
            action_choice='passive',
        )
        cl2 = insert_combat_worker(
            'Cl_Def2', 'Delta', 'Beta-Combat',
            ['Blank Slate', 'Common Folk'],
            action_choice='passive',
        )
        end_turn(page, base_url)

        # Turn 1: attackers attack, claimers claim.
        set_attack(atk1, cl1, turn=1)
        set_attack(atk2, cl2, turn=1)
        # Both claimers attempt to claim Beta-Combat — target their own controller.
        beta_id = _lookup_id('controllers', 'lastname', 'Beta')
        delta_id = _lookup_id('controllers', 'lastname', 'Delta')
        set_action(cl1, 'claim', json.dumps({'claim_controller_id': beta_id}), turn=1)
        set_action(cl2, 'claim', json.dumps({'claim_controller_id': delta_id}), turn=1)
        end_turn(page, base_url)

        assert get_status(cl1, turn=1) == 'captured', \
            f"Cl1 captured, got {get_status(cl1, turn=1)}"
        assert get_status(cl2, turn=1) == 'dead', \
            f"Cl2 dead, got {get_status(cl2, turn=1)}"
        # Claim reports should not be present, and Beta-Combat should still have no holder.
        report1 = get_report(cl1, turn=1)
        report2 = get_report(cl2, turn=1)
        assert 'claim_report' not in report1, \
            f"Cl1 should not have claim_report (captured): {report1}"
        assert 'claim_report' not in report2, \
            f"Cl2 should not have claim_report (killed): {report2}"
        # Beta-Combat holder unchanged (was NULL/empty in TestConfig)
        conn = get_db()
        cursor = conn.cursor()
        cursor.execute(
            f"SELECT holder_controller_id FROM `{GAME_PREFIX}zones` WHERE name = 'Beta-Combat'"
        )
        holder = cursor.fetchone()['holder_controller_id']
        conn.close()
        assert holder is None, \
            f"Beta-Combat should remain unclaimed (holder=NULL), got {holder}"
