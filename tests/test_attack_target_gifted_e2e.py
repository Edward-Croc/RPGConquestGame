"""Playwright E2E characterisation of a gift made while an attack is pending.

CHARACTERISATION, NOT A SPECIFICATION. What this file asserts is what the game
does today, pinned so a future change is noticed rather than silently shipped.
Whether it is what the game SHOULD do is an open question, raised as issue #168.
If that issue settles on letting the gift shield the agent, or on re-reading the
real owner at resolution time, this file must be rewritten — it exists so the
change is noticed, not to forbid it.

The scenario, as reported:

    Network A has discovered one agent of network C in a zone. An agent of A
    attacks network C as a whole. Before the end of turn resolves, network C
    gifts that agent to network B.

What happens, and why:

`controllers_known_enemies` is the only source a network-scope attack resolves
through (`getAttackerComparisons`, mechanics/attackMechanic.php). The gift
(`activateWorker` case 'gift', workers/functions.php) rewrites
`controller_worker.controller_id` and `worker_actions.controller_id` — and
nothing else. The whole codebase writes to `controllers_known_enemies` in
exactly two places, `addWorkerToCKE` and the trace-worker DELETE, neither of
which a gift reaches.

So network A's record still reads « that agent belongs to network C », and the
attack ordered against C lands on an agent that now belongs to B. Even a later
re-affiliation could not help within the turn : `investigateMechanic` runs at
`mechanics/endTurn.php:238`, the attack at `:176`.

The reported scenario's other half — an attack aimed at the agent BY ID — needs
no coverage here : the id is exactly what was aimed at, so a change of owner
cannot redirect it.

The target has to be discovered WITH ITS AFFILIATION, which is not the same as
being discovered. The network query filters on `discovered_controller_id`, so an
agent known to exist but not yet tied to a faction is invisible to a network
attack and only reachable by id. Chain_B is in that state after turn 0 -> 1 and
would make this file measure nothing.

Data: TestConfig, Beta-Combat zone. Even_Def (Beta, 3/3/3) is the gifted agent,
Inv_Atk_1 (Alpha, 8/8/7) the network attacker. attack_difference = 8 − 3 = 5,
which passes ATTACKDIFF1 = 3, so the outcome is a capture.

Run:
    python3 -m pytest tests/test_attack_target_gifted_e2e.py -v
"""
import pytest

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE,
    assert_no_collected_php_errors,
    clear_ui_caches,
    end_turn,
    load_minimal_data,
    load_scenario_via_admin,
    register_php_error_listener,
    safe_goto,
    ui_controller_id,
    ui_gift_click,
    ui_worker_action_state,
    ui_worker_controller_id,
    ui_worker_id,
    worker_report_html,
    worker_report_section,
)

GIFTED_AGENT = 'Even_Def'         # network C : discovered by A *with its affiliation*
NETWORK_ATTACKER = 'Inv_Atk_1'    # network A : orders the attack on network C
GIFT_RECIPIENT = 'Delta'          # network B : receives the agent mid-turn


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


def _ui_attack_network(page, attacker_lastname, network_cid, base_url):
    """Select the « Réseau N » option on the attacker's own page and submit.

    select_option raises when the option is absent, so this doubles as proof
    that the interface really offers the network — the test would otherwise be
    exercising a state no player can reach.
    """
    ensure_gm_login(page, base_url)
    ctrl_id = ui_worker_controller_id(page, attacker_lastname, base_url=base_url)
    safe_goto(page, f"{base_url}/base/accueil.php?controller_id={ctrl_id}&chosir=Choisir")
    page.wait_for_load_state("load")
    wid = ui_worker_id(page, attacker_lastname, base_url=base_url)
    safe_goto(page, f"{base_url}/workers/action.php?worker_id={wid}")
    page.wait_for_load_state("load")
    page.locator("select#enemyWorkersSelect").select_option(value=f"network_{network_cid}")
    page.locator("input[name='attack']").click()
    page.wait_for_load_state("load")


@pytest.fixture(scope="module")
def gifted_target_scenario(browser):
    """Queue a network attack, gift the target away, then resolve the turn.

    Yields what only exists before the resolution overwrites it : who owned the
    agent right after the gift and right before the end of turn.
    """
    context = None
    observed = {}
    try:
        if DB_AVAILABLE:
            load_minimal_data()
        load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")

        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)
        clear_ui_caches()

        # Turn 0 -> 1 : detection fills controllers_known_enemies.
        end_turn(page)

        # The agent's owner before anything moves : the network being attacked.
        observed['origin_cid'] = ui_worker_controller_id(page, GIFTED_AGENT,
                                                         base_url=PHP_BASE_URL)
        _ui_attack_network(page, NETWORK_ATTACKER, observed['origin_cid'],
                           PHP_BASE_URL)

        ui_gift_click(page, GIFTED_AGENT, GIFT_RECIPIENT, base_url=PHP_BASE_URL)
        observed['after_gift_cid'] = ui_worker_controller_id(page, GIFTED_AGENT,
                                                             base_url=PHP_BASE_URL)
        # controllerSelect is gm-only, and ui_gift_click left the session on the giver.
        ensure_gm_login(page, PHP_BASE_URL)
        observed['recipient_cid'] = ui_controller_id(page, GIFT_RECIPIENT,
                                                     base_url=PHP_BASE_URL)

        # Turn 1 -> 2 : the attack mechanic resolves.
        end_turn(page)
        assert_no_collected_php_errors(page)

        yield observed
    finally:
        if context is not None:
            context.close()
        # Unconditional : ensure_scenario_loaded() would skip a reload here.
        if DB_AVAILABLE:
            load_minimal_data()
        load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")


class TestGiftDoesNotEscapeANetworkAttack:
    """Current behaviour, pinned. Open question, issue #168 : should a gift
    shield the agent from an attack already aimed at its former network?
    """

    def test_the_gift_moved_the_agent_before_the_turn_resolved(
            self, gifted_target_scenario):
        """Without this the rest proves nothing : the agent really did change
        hands between the order and the resolution."""
        assert (gifted_target_scenario['after_gift_cid']
                != gifted_target_scenario['origin_cid'])
        assert (gifted_target_scenario['after_gift_cid']
                == gifted_target_scenario['recipient_cid'])

    def test_the_gifted_agent_is_attacked_anyway(self, page, base_url,
                                                 gifted_target_scenario):
        """The known-enemies record still files the agent under its former
        network, so the attack ordered against that network reaches it."""
        state = ui_worker_action_state(page, GIFTED_AGENT, base_url=base_url)
        assert state['worker_status'] in ('dead', 'captured', 'prisoner')

    def test_the_attacker_report_names_the_gifted_agent(self, page, base_url,
                                                        gifted_target_scenario):
        """Paired with the test above : the attacker really did fight, rather
        than reading that it found nobody."""
        html = worker_report_html(page, NETWORK_ATTACKER, base_url=base_url)
        report = worker_report_section(html, "Attaques :")
        assert report, "the network attacker wrote no attack report at all"
        assert GIFTED_AGENT in report
