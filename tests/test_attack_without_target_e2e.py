"""Playwright E2E tests for an attack submitted without a target (issue #154).

The bug, seen in production on the Demo:

    [ERROR] getAttackerComparisons: SELECT compare attackers to defenders
    failed | ... near ')     )     SELECT a.attacker_id, ...

`WHERE w.id IN ()` is a MySQL syntax error. The end of turn caught it, logged it
and moved on — so the player lost the action without a single word.

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

Data: TestConfig, Beta-Combat zone. Turn 0 → 1 runs detection so the attack form
renders; the actions are queued; turn 1 → 2 resolves combat.

Run:
    python3 -m pytest tests/test_attack_without_target_e2e.py -v
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
    ui_attack,
    ui_worker_action_state,
    ui_worker_controller_id,
    ui_worker_id,
    worker_report_html,
    worker_report_section,
)

# The attacker that presses « Attaquer » without choosing anybody.
NO_TARGET_ATTACKER = 'Chain_A'
# The control pair : a normal attack queued in the same turn, which must still land.
CONTROL_ATTACKER = 'Inv_Atk_1'
CONTROL_DEFENDER = 'Inv_Def_1'


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


def _ui_attack_without_target(page, lastname, base_url):
    """Press « Attaquer » on the agent's own action page, selecting nobody.

    Deliberately a real button click rather than the URL driver : the point of
    this file is that the plain interface produced the faulty state.
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


@pytest.fixture(scope="module")
def no_target_scenario(browser):
    """Submit one targetless attack and one ordinary attack, then resolve them.

    Yields what could only be observed before the resolution consumed it : the
    action the interface actually stored for the targetless submit.
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

        _ui_attack_without_target(page, NO_TARGET_ATTACKER, PHP_BASE_URL)
        state = ui_worker_action_state(page, NO_TARGET_ATTACKER)
        observed['queued_choice'] = state['action_choice']
        observed['queued_params'] = state['action_params']

        ui_attack(page, CONTROL_ATTACKER, CONTROL_DEFENDER)

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


class TestAttackWithoutTarget:
    """The submit is refused, and the turn survives it."""

    def test_attack_without_selection_is_refused_at_submit(self, no_target_scenario):
        """First line of defence : workers/action.php must not record an attack
        that names nobody. Before the fix this stored action_choice 'attack'
        with action_params '[]', which no end of turn can resolve."""
        assert no_target_scenario['queued_choice'] == 'passive'
        assert no_target_scenario['queued_params'] == '{}'

    def test_no_error_logged_for_the_turn(self, page, base_url, no_target_scenario):
        """admin_logs.php must not carry a getAttackerComparisons ERROR line.

        Named rather than counted so a regression reads as itself : before the
        fix this is where `WHERE w.id IN ()` surfaced.
        """
        ensure_gm_login(page, base_url)
        safe_goto(
            page,
            f"{base_url}/admin/admin_logs.php?prefix={GAME_PREFIX}&level=ERROR",
        )
        page.wait_for_load_state("load")
        content = page.content()
        # Positive control : an unauthenticated page renders no log at all, and
        # the absence below would then be worth nothing.
        assert 'Game Errors Log' in content, "the log viewer did not render as gm"
        assert 'getAttackerComparisons' not in content

    def test_attacker_is_still_alive(self, page, base_url, no_target_scenario):
        """A refused attack must cost the agent its action, never its life."""
        state = ui_worker_action_state(page, NO_TARGET_ATTACKER, base_url=base_url)
        assert state['worker_status'] == 'alive'


class TestOrdinaryAttackUnaffected:
    """The refusal must catch exactly one submit and leave the mechanic alone."""

    def test_control_attack_still_resolves(self, page, base_url, no_target_scenario):
        """Inv_Atk_1 captures Inv_Def_1 : the refused submit queued in the same
        turn did not cost anybody else their action."""
        state = ui_worker_action_state(page, CONTROL_DEFENDER, base_url=base_url)
        assert state['worker_status'] in ('dead', 'captured', 'prisoner')

    def test_control_attacker_reads_a_real_outcome(self, page, base_url,
                                                   no_target_scenario):
        """Paired with the test above : the mechanic still writes a real attack
        report, so the guards did not quietly swallow the whole turn."""
        html = worker_report_html(page, CONTROL_ATTACKER, base_url=base_url)
        report = worker_report_section(html, "Attaques :")
        assert report, "the control attacker wrote no attack report at all"
        assert CONTROL_DEFENDER in report
