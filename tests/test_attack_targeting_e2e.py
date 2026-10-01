"""Playwright E2E tests for what an attack actually resolves against.

Two subjects share one scenario build, because they need the same setup and
touch no common agent — a second module fixture would cost another scenario
reload and another two-turn cycle for nothing.

--- 1. An attack submitted without a target (issue #154) -------------------

The bug, seen in production on the Demo:

    [ERROR] getAttackerComparisons: SELECT compare attackers to defenders
    failed | ... near ')     )     SELECT a.attacker_id, ...

`WHERE w.id IN ()` is a MySQL syntax error. The end of turn caught it, logged
it and moved on — so the player lost the action without a single word.

How a player gets there, in one click: open an agent's action page and press
« Attaquer » without selecting anybody. An unselected `<select multiple>`
submits no key at all, so `workers/action.php` left `$enemy_worker_id` at null,
still called activateWorker, and `workers/functions.php` stored
`json_encode([])` — the string `'[]'`. At end of turn `!empty('[]')` is true, so
the attacker entered the comparison loop carrying an empty target list.

The fix has two layers, and this file exercises both:
  - `workers/action.php` refuses the submit and leaves the agent passive, so the
    incoherent row is never written;
  - `getAttackerComparisons` keeps its own guard on an empty target list — the
    function that builds the `IN (...)` is also the one that must refuse to
    build it empty.

--- 2. A gift made while an attack is pending (issue #168) -----------------

CHARACTERISATION, NOT A SPECIFICATION. TestGiftDoesNotEscapeANetworkAttack
asserts what the game does today, pinned so a future change is noticed rather
than silently shipped. If #168 settles on letting the gift shield the agent, or
on re-reading the real owner at resolution time, that class must be rewritten.

The scenario, as reported: network A has discovered an agent of network C, an
agent of A attacks network C as a whole, and before the end of turn resolves,
network C gifts that agent to network B.

`controllers_known_enemies` is the only source a network-scope attack resolves
through (`getAttackerComparisons`, mechanics/attackMechanic.php). The gift
(`activateWorker` case 'gift', workers/functions.php) rewrites
`controller_worker.controller_id` and `worker_actions.controller_id` — and
nothing else. The whole codebase writes to `controllers_known_enemies` in
exactly two places, `addWorkerToCKE` and the trace-worker DELETE, neither of
which a gift reaches. So network A's record still files the agent under network
C, and the attack lands on an agent that now belongs to B. Even a later
re-affiliation could not help within the turn: `investigateMechanic` runs at
`mechanics/endTurn.php:238`, the attack at `:176`.

The reported scenario's other half — an attack aimed at the agent BY ID — needs
no coverage: the id is exactly what was aimed at, so a change of owner cannot
redirect it.

A network attack only reaches discoveries that carry an AFFILIATION. The query
filters on `discovered_controller_id`, so an agent known to exist but not yet
tied to a faction is invisible to it and reachable only by id. Chain_B is in
that state after turn 0 -> 1, which is why the gifted agent below is Even_Def.

Data: TestConfig, Beta-Combat zone. Turn 0 → 1 runs detection so the attack form
renders; the actions are queued; turn 1 → 2 resolves combat. Even_Def (Beta,
3/3/3) is the gifted agent, Inv_Atk_1 (Alpha, 8/8/7) the network attacker:
attack_difference = 8 − 3 = 5 passes ATTACKDIFF1 = 3, so the outcome is capture.

Run:
    python3 -m pytest tests/test_attack_targeting_e2e.py -v
"""
import pytest

from conftest import GAME_PREFIX, PHP_BASE_URL, ensure_gm_login
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

# Presses « Attaquer » without choosing anybody.
NO_TARGET_ATTACKER = 'Chain_A'
# Orders a network-scope attack : the ordinary attack this file checks still
# resolves, and the one that reaches the gifted agent.
NETWORK_ATTACKER = 'Inv_Atk_1'
# Belongs to the attacked network and is captured by it.
ORDINARY_DEFENDER = 'Inv_Def_1'
# Also belongs to the attacked network, and changes hands mid-turn.
GIFTED_AGENT = 'Even_Def'
# Receives the gift.
GIFT_RECIPIENT = 'Delta'


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


def _count_log_mentions(page, base_url, needle='getAttackerComparisons'):
    """Count ERROR lines naming `needle` in the admin log viewer.

    The viewer keeps only the last 1000 raw lines BEFORE applying the level
    filter (admin/admin_logs.php:50), and nothing clears the log between test
    files. An absolute absence would therefore prove nothing on a verbose run,
    so the caller brackets the end of turn and compares two readings.
    """
    ensure_gm_login(page, base_url)
    safe_goto(page, f"{base_url}/admin/admin_logs.php?prefix={GAME_PREFIX}&level=ERROR")
    page.wait_for_load_state("load")
    content = page.content()
    # Positive control : an unauthenticated page renders no log at all.
    assert 'Game Errors Log' in content, "the log viewer did not render as gm"
    return content.count(needle)


def _ui_attack_without_target(page, lastname, base_url):
    """Press « Attaquer » on the agent's own action page, selecting nobody.

    Deliberately a real button click rather than the URL driver : the point is
    that the plain interface produced the faulty state.
    """
    ensure_gm_login(page, base_url)
    ctrl_id = ui_worker_controller_id(page, lastname, base_url=base_url)
    safe_goto(page, f"{base_url}/base/accueil.php?controller_id={ctrl_id}&chosir=Choisir")
    page.wait_for_load_state("load")
    wid = ui_worker_id(page, lastname, base_url=base_url)
    safe_goto(page, f"{base_url}/workers/action.php?worker_id={wid}")
    page.wait_for_load_state("load")
    page.locator("input[name='attack']").click()
    page.wait_for_load_state("load")


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
def attack_targeting_scenario(browser):
    """One build for both subjects : a refused submit, a network attack, a gift.

    Yields what only exists before the resolution consumes it — the action the
    interface stored for the targetless submit, who owned the gifted agent on
    either side of the gift, and the log readings bracketing the end of turn.
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

        # Turn 0 -> 1 : detection fills controllers_known_enemies, so the form renders.
        end_turn(page)

        # #154 : the submit that used to store an attack aimed at nobody.
        _ui_attack_without_target(page, NO_TARGET_ATTACKER, PHP_BASE_URL)
        state = ui_worker_action_state(page, NO_TARGET_ATTACKER)
        observed['queued_choice'] = state['action_choice']
        observed['queued_params'] = state['action_params']

        # The ordinary attack : aimed at the network the gifted agent belongs to.
        observed['origin_cid'] = ui_worker_controller_id(page, GIFTED_AGENT,
                                                         base_url=PHP_BASE_URL)
        _ui_attack_network(page, NETWORK_ATTACKER, observed['origin_cid'],
                           PHP_BASE_URL)

        # #168 : the target changes hands between the order and the resolution.
        ui_gift_click(page, GIFTED_AGENT, GIFT_RECIPIENT, base_url=PHP_BASE_URL)
        observed['after_gift_cid'] = ui_worker_controller_id(page, GIFTED_AGENT,
                                                             base_url=PHP_BASE_URL)
        # controllerSelect is gm-only, and ui_gift_click left the session on the giver.
        ensure_gm_login(page, PHP_BASE_URL)
        observed['recipient_cid'] = ui_controller_id(page, GIFT_RECIPIENT,
                                                     base_url=PHP_BASE_URL)

        observed['log_before'] = _count_log_mentions(page, PHP_BASE_URL)

        # Turn 1 -> 2 : the attack mechanic resolves.
        end_turn(page)
        observed['log_after'] = _count_log_mentions(page, PHP_BASE_URL)
        assert_no_collected_php_errors(page)

        yield observed
    finally:
        if context is not None:
            context.close()
        # Unconditional : ensure_scenario_loaded() would skip a reload here.
        if DB_AVAILABLE:
            load_minimal_data()
        load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")


class TestAttackWithoutTarget:
    """The submit is refused, and the turn survives it."""

    def test_attack_without_selection_is_refused_at_submit(self, attack_targeting_scenario):
        """First line of defence : workers/action.php must not record an attack
        that names nobody. Before the fix this stored action_choice 'attack'
        with action_params '[]', which no end of turn can resolve."""
        assert attack_targeting_scenario['queued_choice'] == 'passive'
        assert attack_targeting_scenario['queued_params'] == '{}'

    def test_no_error_logged_for_the_turn(self, attack_targeting_scenario):
        """The end of turn must not add a getAttackerComparisons ERROR line.

        A delta across the turn rather than an absolute absence : the viewer
        only shows the last 1000 raw lines, so "not in the page" would pass on a
        verbose run whether or not the fix is present. Before the fix this is
        where `WHERE w.id IN ()` surfaced.
        """
        assert attack_targeting_scenario['log_after'] == attack_targeting_scenario['log_before']

    def test_attacker_is_still_alive(self, page, base_url, attack_targeting_scenario):
        """A refused attack must cost the agent its action, never its life."""
        state = ui_worker_action_state(page, NO_TARGET_ATTACKER, base_url=base_url)
        assert state['worker_status'] == 'alive'


class TestOrdinaryAttackUnaffected:
    """The refusal must catch exactly one submit and leave the mechanic alone."""

    def test_ordinary_attack_still_resolves(self, page, base_url,
                                           attack_targeting_scenario):
        """The refused submit queued in the same turn did not cost anybody else
        their action."""
        state = ui_worker_action_state(page, ORDINARY_DEFENDER, base_url=base_url)
        assert state['worker_status'] in ('dead', 'captured', 'prisoner')

    def test_ordinary_attacker_reads_a_real_outcome(self, page, base_url,
                                                    attack_targeting_scenario):
        """Paired with the test above : the mechanic still writes a real attack
        report, so the guards did not quietly swallow the whole turn."""
        html = worker_report_html(page, NETWORK_ATTACKER, base_url=base_url)
        report = worker_report_section(html, "Attaques :")
        assert report, "the network attacker wrote no attack report at all"
        assert ORDINARY_DEFENDER in report


class TestGiftDoesNotEscapeANetworkAttack:
    """Current behaviour, pinned. Open question, issue #168 : should a gift
    shield the agent from an attack already aimed at its former network?
    """

    def test_the_gift_moved_the_agent_before_the_turn_resolved(
            self, attack_targeting_scenario):
        """Without this the rest proves nothing : the agent really did change
        hands between the order and the resolution."""
        assert (attack_targeting_scenario['after_gift_cid']
                != attack_targeting_scenario['origin_cid'])
        assert (attack_targeting_scenario['after_gift_cid']
                == attack_targeting_scenario['recipient_cid'])

    def test_the_gifted_agent_is_attacked_anyway(self, page, base_url,
                                                 attack_targeting_scenario):
        """The known-enemies record still files the agent under its former
        network, so the attack ordered against that network reaches it."""
        state = ui_worker_action_state(page, GIFTED_AGENT, base_url=base_url)
        assert state['worker_status'] in ('dead', 'captured', 'prisoner')

    def test_the_attacker_report_names_the_gifted_agent(self, page, base_url,
                                                        attack_targeting_scenario):
        """Paired with the test above : the attacker really did fight it, rather
        than reading that it found nobody."""
        html = worker_report_html(page, NETWORK_ATTACKER, base_url=base_url)
        report = worker_report_section(html, "Attaques :")
        assert GIFTED_AGENT in report


class TestForgedAttackPayloads:
    """Issue #173 : a request the form cannot produce must not reach the turn.

    The multiple select always sends enemy_worker_id[] as an array of
    `worker_N` / `network_N`. Anything else is forged, and used to be stored or
    to end the request on a blank page.
    """

    def _state_after(self, page, base_url, query):
        """Queue a passive action, fire the forged request, return the state."""
        ensure_gm_login(page, base_url)
        ctrl_id = ui_worker_controller_id(page, NO_TARGET_ATTACKER, base_url=base_url)
        safe_goto(page, f"{base_url}/base/accueil.php?controller_id={ctrl_id}&chosir=Choisir")
        page.wait_for_load_state("load")
        wid = ui_worker_id(page, NO_TARGET_ATTACKER, base_url=base_url)
        safe_goto(page, f"{base_url}/workers/action.php?worker_id={wid}&passive=1")
        page.wait_for_load_state("load")
        response = page.goto(
            f"{base_url}/workers/action.php?worker_id={wid}&{query}&attack=Attaquer")
        status = response.status if response is not None else None
        return status, ui_worker_action_state(page, NO_TARGET_ATTACKER, base_url=base_url)

    def test_a_scalar_target_is_refused(self, page, base_url,
                                        attack_targeting_scenario):
        """A scalar passes the empty() guard, is cast to an int, and used to be
        stored as an attack carrying no target at all."""
        status, state = self._state_after(page, base_url, "enemy_worker_id=5")
        assert status == 400, f"a forged payload must be answered as such; got {status}"
        assert state['action_choice'] == 'passive', (
            f"the queued action must survive a refused attack; got {state['action_choice']!r}"
        )

    def test_a_malformed_target_is_refused_rather_than_thrown(self, page, base_url,
                                                              attack_targeting_scenario):
        """Paired with the test above : this one used to raise an uncaught
        exception, because activateWorker is called without a try."""
        status, state = self._state_after(page, base_url, "enemy_worker_id[]=x")
        assert status == 400, f"a malformed target must be refused, not thrown; got {status}"
        assert state['action_choice'] == 'passive', (
            f"the queued action must survive a refused attack; got {state['action_choice']!r}"
        )

    def test_a_move_without_a_zone_is_refused(self, page, base_url,
                                              attack_targeting_scenario):
        """The zone select never renders an empty option, so a move carrying no
        zone is forged too — and used to be ignored without a word.

        Unlike an attack with nothing selected, which the multiple select does
        produce and which deliberately falls back to passive.
        """
        ensure_gm_login(page, base_url)
        ctrl_id = ui_worker_controller_id(page, NO_TARGET_ATTACKER, base_url=base_url)
        safe_goto(page, f"{base_url}/base/accueil.php?controller_id={ctrl_id}&chosir=Choisir")
        page.wait_for_load_state("load")
        wid = ui_worker_id(page, NO_TARGET_ATTACKER, base_url=base_url)
        response = page.goto(f"{base_url}/workers/action.php?worker_id={wid}&move=1")
        status = response.status if response is not None else None
        assert status == 400, f"a move without a zone must be refused; got {status}"
