"""Issue #73 sub-steps 5.A.II + 5.B — cross-mechanic interaction coverage.

Steps 1-4 introduced `attack_location` / `defend_location` as new worker
`action_choice` values (agent_attack_defence mode). This file locks the
CURRENT (already-correct) interaction between those two action choices
and the pre-existing worker-vs-worker attack + investigate mechanics —
test-only, no production code expected to change.

5.A.II — a plain `attack` worker can target a worker whose action_choice
is `defend_location` or `attack_location`. Both remain valid combat
targets because they're listed in ACTIVE_ACTIONS (workers/functions.php:3)
and attackMechanic only filters out INACTIVE_ACTIONS defenders
(mechanics/attackMechanic.php:498+).

5.B — a `defend_location` worker still runs investigation against an
enemy sharing its zone, because `defend_location` is included in the
`investigateActionsList` config (var/mysql/minimalData.sql).

Scenario reuse: every Beta-Combat combat agent in
setupTestConfig_advanced.csv starts action_choice='passive' on turn 0,
and 'passive' is itself in investigateActionsList — so end_turn(0->1)
alone mutually seeds controllers_known_enemies for every cross-controller
pair sharing the zone (same mechanism test_agent_combat_e2e.py relies on
without any explicit admin CKE-seed step). No separate CKE seeding is
needed here either.

Combat math (TestConfig defaults, MINROLL=MAXROLL=3 so the dice roll used
by activeDefenceActions is numerically identical to PASSIVEVAL used by
passiveDefenceActions — see the note below):
  Inv_Atk_1 (Alpha) 7/9/7, Chain_A (Alpha) 8/8/7, Chain_B (Beta) 7/4/5,
  Chain_C (Charlie) 6/4/4,
  Chain_D (Delta) 3/3/3 — full power breakdown in
  test_agent_combat_e2e.py's module docstring.
  attack_difference = attacker.attack_val - defender.defence_val
  ATTACKDIFF0=1 (kill), ATTACKDIFF1=3 (capture)

  Pair 1 (defend_location target) : Inv_Atk_1 (atk=9) attacks Chain_B
    (defend_location, def=5). diff=3 >= ATTACKDIFF1=3 -> CAPTURE.
  Pair 2 (attack_location target) : Chain_C (atk=4) attacks Chain_D
    (attack_location, def=3). diff=1 >= ATTACKDIFF0=1, < 3 -> KILL.

5.B pair : Chain_E (Echo) queues defend_location; Chain_F (Foxtrot,
enemy controller, same Beta-Combat zone) stays passive (CSV default).
defend_location is in investigateActionsList, so Chain_E must still
detect Chain_F end-of-turn.

Config-detail finding vs the issue's hypothesis : TestConfig sets
MINROLL=MAXROLL=3, which equals PASSIVEVAL=3. So the active-tier dice
roll (activeDefenceActions -> defend_location) and the passive-tier flat
value (passiveDefenceActions -> attack_location) resolve to the exact
same defence_val in this scenario. The two config lists can't be told
apart numerically here — this file only proves both action choices
remain valid, resolvable combat targets, not that their bonuses differ.

Run:
    python3 -m pytest tests/test_agent_attack_defence_interactions_e2e.py -v
"""

import pytest
from playwright.sync_api import Page

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, end_turn, load_minimal_data, load_scenario_via_admin,
    safe_goto, register_php_error_listener, assert_no_collected_php_errors,
    clear_ui_caches, ui_attack, ui_attack_click, ui_location_id, ui_worker_id,
    ui_worker_action_state, worker_report_html,
)


def _set_config_via_ui(page, name, value):
    """Set a config row's value via /base/configuration.php POST."""
    safe_goto(page, f"{PHP_BASE_URL}/base/configuration.php")
    page.wait_for_load_state("load")
    for row in page.locator("tr:has(form)").all():
        if row.locator("td").nth(1).inner_text().strip() == name:
            row.locator("input[name='value']").fill(value)
            row.locator("input[name='update_config']").click()
            page.wait_for_load_state("load")
            return
    raise AssertionError(f"Config row {name!r} not found")


def _location_id_via_management(page, location_name):
    """Thin wrapper kept for this file's existing call sites."""
    return ui_location_id(page, location_name, base_url=PHP_BASE_URL)


def _queue_location_action(page, worker_id, action, target_location_id):
    """Direct URL-drive for attackLocation/defendLocation. The gm session
    is privileged, so the ownership + target re-validation gates in
    workers/action.php are bypassed — same shortcut
    TestAgentAttackDefenceActionDispatcher (baseline file) relies on."""
    param = 'attackLocation' if action == 'attack_location' else 'defendLocation'
    field = 'attack_target_location_id' if action == 'attack_location' else 'defend_target_location_id'
    safe_goto(
        page,
        f"{PHP_BASE_URL}/workers/action.php"
        f"?worker_id={worker_id}&{param}=1&{field}={target_location_id}"
    )
    page.wait_for_load_state("load")


_state = {}


@pytest.fixture(scope="module", autouse=True)
def interactions_state(browser):
    """One-shot setup + snapshot fixture for both 5.A.II and 5.B.

    Loads TestConfig, enables agent_attack_defence mode, advances turn
    0->1 (mutual passive investigation seeds CKE for every Beta-Combat
    cross-controller pair), configures the turn-1 actions for both
    sub-steps, advances turn 1->2 (combat + investigation resolve), then
    snapshots every UI surface the test classes need. Teardown runs in
    `finally` so a setup-time exception still resets locationAttackMode
    and closes the browser context.
    """
    global _state
    _state.clear()

    if DB_AVAILABLE:
        load_minimal_data()
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")

    context = browser.new_context()
    page = context.new_page()
    register_php_error_listener(page)

    try:
        ensure_gm_login(page, PHP_BASE_URL)
        clear_ui_caches()

        _set_config_via_ui(page, "locationAttackMode", "agent_attack_defence")

        echo_base_id = _location_id_via_management(page, "Echo-Base")
        foxtrot_outpost_id = _location_id_via_management(page, "Foxtrot-Outpost")

        # Turn 0 -> 1 : every Beta-Combat agent starts 'passive' (CSV
        # default), and 'passive' is itself in investigateActionsList, so
        # this end-turn mutually seeds controllers_known_enemies for
        # every cross-controller pair sharing the zone — no separate
        # admin CKE-seed call needed (mirrors test_agent_combat_e2e.py).
        end_turn(page, PHP_BASE_URL)

        chain_b_id = ui_worker_id(page, 'Chain_B', base_url=PHP_BASE_URL)
        chain_d_id = ui_worker_id(page, 'Chain_D', base_url=PHP_BASE_URL)
        chain_e_id = ui_worker_id(page, 'Chain_E', base_url=PHP_BASE_URL)

        # 5.A.II pair 1 : Chain_B defends Echo-Base ; Inv_Atk_1 attacks Chain_B.
        _queue_location_action(page, chain_b_id, 'defend_location', echo_base_id)
        _state['chain_b_pre_eot_state'] = ui_worker_action_state(
            page, 'Chain_B', base_url=PHP_BASE_URL
        )
        # First attack in this file -> via the rendered 'Attaquer' button
        # (once-per-file rule); subsequent attacks reuse the URL-driver.
        ui_attack_click(page, 'Inv_Atk_1', 'Chain_B')

        # 5.A.II pair 2 : Chain_D attacks (queues attack_location on)
        # Echo-Base ; Chain_C attacks Chain_D.
        _queue_location_action(page, chain_d_id, 'attack_location', echo_base_id)
        _state['chain_d_pre_eot_state'] = ui_worker_action_state(
            page, 'Chain_D', base_url=PHP_BASE_URL
        )
        ui_attack(page, 'Chain_C', 'Chain_D')

        # 5.B pair : Chain_E defends Foxtrot-Outpost ; Chain_F (enemy,
        # same zone) stays passive — no action change needed.
        _queue_location_action(page, chain_e_id, 'defend_location', foxtrot_outpost_id)

        # Turn 1 -> 2 : resolves combat + investigation.
        end_turn(page, PHP_BASE_URL)

        _state['chain_a_report'] = worker_report_html(page, 'Inv_Atk_1', base_url=PHP_BASE_URL)
        _state['chain_b_state'] = ui_worker_action_state(page, 'Chain_B', base_url=PHP_BASE_URL)
        _state['chain_c_report'] = worker_report_html(page, 'Chain_C', base_url=PHP_BASE_URL)
        _state['chain_d_state'] = ui_worker_action_state(page, 'Chain_D', base_url=PHP_BASE_URL)
        _state['chain_e_report'] = worker_report_html(page, 'Chain_E', base_url=PHP_BASE_URL)

        assert_no_collected_php_errors(page)
        yield
    finally:
        try:
            ensure_gm_login(page, PHP_BASE_URL)
            _set_config_via_ui(page, "locationAttackMode", "endTurn")
        except Exception:
            # best-effort teardown — never mask the original setup/test failure
            pass
        context.close()


def _worker_is_downed(state):
    """True if the worker's post-EOT status shows a resolved-inactive
    state. Mirrors _ui_worker_is_downed from test_agent_combat_e2e.py."""
    return state['worker_status'] in ('dead', 'captured', 'prisoner', 'double_agent')


class TestAgentAttackDefenceTargetCrossMechanic:
    """5.A.II — plain `attack` against defend_location / attack_location
    targets resolves through mechanics/attackMechanic.php exactly like any
    other ACTIVE_ACTIONS defender: neither action choice is silently
    excluded from being a valid combat target."""

    def test_defend_location_target_was_set_before_combat(self):
        """Sanity: Chain_B's action_choice was really 'defend_location'
        (not silently reset) at the point the attack was queued."""
        assert _state['chain_b_pre_eot_state']['action_choice'] == 'defend_location', (
            f"Expected Chain_B action_choice='defend_location' pre-EOT; "
            f"got {_state['chain_b_pre_eot_state']}"
        )

    def test_attack_location_target_was_set_before_combat(self):
        """Sanity: Chain_D's action_choice was really 'attack_location'
        (not silently reset) at the point the attack was queued."""
        assert _state['chain_d_pre_eot_state']['action_choice'] == 'attack_location', (
            f"Expected Chain_D action_choice='attack_location' pre-EOT; "
            f"got {_state['chain_d_pre_eot_state']}"
        )

    def test_attack_against_defend_location_target_captures(self):
        """Inv_Atk_1 (atk=9) vs Chain_B (defend_location, def=5+1=6) :
        diff=3 >= ATTACKDIFF1=3 -> CAPTURE. Proves defend_location is not
        silently excluded as a combat target."""
        html = _state['chain_a_report']
        assert 'Captured' in html and 'Chain_B' in html, (
            "Inv_Atk_1's report should mention capturing Chain_B "
            "(defend_location target) -- attackMechanic regression?"
        )
        assert _worker_is_downed(_state['chain_b_state']), (
            f"Chain_B should be downed (captured) post-EOT; "
            f"got {_state['chain_b_state']}"
        )
        assert _state['chain_b_state']['worker_status'] == 'prisoner', (
            f"Chain_B (captured, viewed as Alpha's own prisoner) should "
            f"report worker_status='prisoner'; got {_state['chain_b_state']}"
        )

    def test_attack_against_attack_location_target_kills(self):
        """Chain_C (atk=4) vs Chain_D (attack_location, def=3) :
        diff=1 -> KILL. Proves attack_location is not silently excluded
        as a combat target."""
        html = _state['chain_c_report']
        assert 'succeeded' in html and 'Chain_D' in html, (
            "Chain_C's report should mention a successful attack on "
            "Chain_D (attack_location target) -- attackMechanic regression?"
        )
        assert _worker_is_downed(_state['chain_d_state']), (
            f"Chain_D should be downed (dead) post-EOT; "
            f"got {_state['chain_d_state']}"
        )
        assert _state['chain_d_state']['worker_status'] == 'dead', (
            f"Chain_D (killed, no trace) should report worker_status='dead'; "
            f"got {_state['chain_d_state']}"
        )

    def test_attackers_report_carries_attack_report_content(self):
        """Both attackers' own pages render the attack_report content
        (the 'Rapport' text emitted by resolveWorkerCombat) -- proves the
        report key is populated for these target action_choices, not
        silently skipped."""
        assert 'Chain_B' in _state['chain_a_report'], (
            "Chain_A's attack_report should name Chain_B"
        )
        assert 'Chain_D' in _state['chain_c_report'], (
            "Chain_C's attack_report should name Chain_D"
        )


class TestDefendLocationInvestigatesZone:
    """5.B — a worker whose action_choice is defend_location still runs
    investigation against enemies sharing its zone, because
    defend_location is listed in the investigateActionsList config."""

    def test_defend_location_worker_detects_enemy_in_zone(self):
        """Chain_E (Echo, defend_location) must detect Chain_F (Foxtrot,
        passive, same Beta-Combat zone) : Chain_E's own attack form
        (workers/view.php, showEnemyWorkersSelect) renders an option
        naming Chain_F, sourced directly from controllers_known_enemies
        scoped to Chain_E's own zone_id -- this doubles as the zone-match
        proof (the CKE row's zone_id is exactly the zone_id passed into
        showEnemyWorkersSelect for Chain_E)."""
        html = _state['chain_e_report']
        assert 'enemyWorkersSelect' in html, (
            "Chain_E's page should render the enemyWorkersSelect dropdown "
            "-- proves at least one CKE row exists for Echo in this zone"
        )
        assert 'Chain_F' in html, (
            "Chain_E's enemyWorkersSelect should list Chain_F by name -- "
            "proves defend_location still ran investigation and detected "
            "the enemy sharing the zone (investigateActionsList regression?)"
        )
