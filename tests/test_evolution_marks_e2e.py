"""Evolution marks on the agent short card (issue #71).

After the name, « # » says the agent may be taught a discipline (a free slot),
« + » that it may receive a transformation — or, dead, be raised.

TestConfig agents of the Alpha player :
  - Searcher_1      — alive, no discipline ;
  - Chain_A         — alive, already holding one discipline (Focused Mind) ;
  - Revenant_Dead   — dead, a raising is open to it ;
  - Revenant_Twice  — dead, already raised once : nothing is open.

The module fixture reads the marks in three configurations, then reloads TestConfig.
"""
import pytest
from playwright.sync_api import Page

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    clear_ui_caches, end_turn, load_scenario_via_admin, login_as, safe_goto, set_config_via_ui,
    ui_attack, ui_controller_id, ui_workers_by_lastname,
)

AGENTS = ("Searcher_1", "Chain_A", "Revenant_Dead", "Revenant_Twice")


def _marks(page: Page) -> dict:
    """lastname -> list of (mark, title) on the Alpha agents list."""
    safe_goto(page, f"{PHP_BASE_URL}/workers/viewAll.php")
    marks = {}
    for card in page.locator("div.worker-short").all():
        name = card.get_attribute("data-worker-lastname")
        if name in AGENTS:
            marks[name] = [(mark.inner_text(), mark.get_attribute("title")) for mark in card.locator(".evolution-mark").all()]
    return marks


@pytest.fixture(scope="module")
def marks(browser):
    state = {}
    contexts = []
    try:
        load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
        clear_ui_caches()
        gm_context = browser.new_context()
        contexts.append(gm_context)
        gm = gm_context.new_page()
        ensure_gm_login(gm, PHP_BASE_URL)
        alpha_context = browser.new_context()
        contexts.append(alpha_context)
        alpha = alpha_context.new_page()
        login_as(alpha, PHP_BASE_URL, "single_player", "test")

        state["default"] = _marks(alpha)
        set_config_via_ui(gm, "recrutement_disciplines", "1", base_url=PHP_BASE_URL)
        state["one_slot"] = _marks(alpha)
        set_config_via_ui(gm, "age_transformation", "{}", base_url=PHP_BASE_URL)
        state["no_transformation"] = _marks(alpha)
        yield state
    finally:
        for context in contexts:
            context.close()
        load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
        clear_ui_caches()


def _symbols(entries) -> list:
    return [symbol for symbol, _title in entries]


class TestEvolutionMarks:
    def test_living_agent_marks_an_available_transformation(self, marks):
        assert _symbols(marks["default"]["Searcher_1"]) == ["+"], \
            f"a living agent with transformations open should carry « + » only, got {marks['default']['Searcher_1']}"

    def test_dead_agent_marks_a_raising(self, marks):
        assert marks["default"]["Revenant_Dead"] == [("+", "Peut être relevé.e")], \
            f"a dead agent that can be raised should say so, got {marks['default']['Revenant_Dead']}"

    def test_agent_with_nothing_open_carries_no_mark(self, marks):
        assert marks["default"]["Revenant_Twice"] == [], \
            f"a dead agent already raised once has nothing open, got {marks['default']['Revenant_Twice']}"

    def test_free_discipline_slot_marks_a_hash(self, marks):
        assert _symbols(marks["one_slot"]["Searcher_1"]) == ["#", "+"], \
            f"with a free discipline slot the agent should carry « # » then « + », got {marks['one_slot']['Searcher_1']}"
        assert _symbols(marks["one_slot"]["Chain_A"]) == ["+"], \
            f"an agent whose slot is taken carries no « # », got {marks['one_slot']['Chain_A']}"

    def test_dead_agent_is_taught_no_discipline(self, marks):
        assert "#" not in _symbols(marks["one_slot"]["Revenant_Dead"]), "a dead agent is taught no discipline"

    def test_marks_follow_the_transformation_switch(self, marks):
        assert _symbols(marks["no_transformation"]["Searcher_1"]) == ["#"], \
            f"without age_transformation the « + » should go, the « # » stay, got {marks['no_transformation']['Searcher_1']}"
        assert marks["no_transformation"]["Revenant_Dead"] == [], "without age_transformation no raising is offered"


def _card(page: Page, lastname: str) -> dict:
    """The viewer's card of an agent : its id, action and marks, read off viewAll."""
    safe_goto(page, f"{PHP_BASE_URL}/workers/viewAll.php")
    card = page.locator(f"div.worker-short[data-worker-lastname='{lastname}']").first
    return {
        "id": card.get_attribute("data-worker-id"),
        "action": card.get_attribute("data-action-choice"),
        "marks": [(mark.inner_text(), mark.get_attribute("title")) for mark in card.locator(".evolution-mark").all()],
    }


@pytest.fixture(scope="module")
def trace_card(browser):
    """Alpha gives Revenant_Gifted to Beta, Inv_Atk_2 kills it there, Alpha raises it from his trace."""
    state = {}
    contexts = []
    try:
        load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
        clear_ui_caches()
        gm_context = browser.new_context()
        contexts.append(gm_context)
        gm = gm_context.new_page()
        ensure_gm_login(gm, PHP_BASE_URL)
        end_turn(gm, base_url=PHP_BASE_URL)
        alpha_context = browser.new_context()
        contexts.append(alpha_context)
        alpha = alpha_context.new_page()
        login_as(alpha, PHP_BASE_URL, "single_player", "test")

        beta_id = ui_controller_id(gm, "Beta", base_url=PHP_BASE_URL)
        gifted = _card(alpha, "Revenant_Gifted")
        safe_goto(alpha, f"{PHP_BASE_URL}/workers/action.php?worker_id={gifted['id']}&gift_controller_id={beta_id}&gift=Donner")
        state["trace_of_living"] = _card(alpha, "Revenant_Gifted")

        ui_attack(gm, "Inv_Atk_2", "Revenant_Gifted", base_url=PHP_BASE_URL)
        end_turn(gm, base_url=PHP_BASE_URL)
        state["trace_of_dead"] = _card(alpha, "Revenant_Gifted")

        # Raise it from the page the card links to.
        safe_goto(alpha, f"{PHP_BASE_URL}/workers/action.php?worker_id={state['trace_of_dead']['id']}")
        options = {o.inner_text().split(" (")[0].strip(): o.get_attribute("value")
                   for o in alpha.locator("select#transformationSelect option").all() if o.get_attribute("value")}
        state["offered"] = set(options)
        state["raised_page"] = ""
        if "Test_Revenant" in options:
            alpha.locator("select#transformationSelect").select_option(value=options["Test_Revenant"])
            alpha.locator("input[name='transform']").click()
            alpha.wait_for_load_state("load")
            state["raised_page"] = alpha.content()
        state["raised_card"] = _card(alpha, "Revenant_Gifted")
        state["rows"] = ui_workers_by_lastname(gm, "Revenant_Gifted", base_url=PHP_BASE_URL)
        state["alpha_id"] = ui_controller_id(gm, "Alpha", base_url=PHP_BASE_URL)
        state["beta_id"] = beta_id
        yield state
    finally:
        for context in contexts:
            context.close()
        load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
        clear_ui_caches()


class TestTraceCard:
    def test_trace_of_a_living_agent_carries_no_mark(self, trace_card):
        assert trace_card["trace_of_living"]["action"] == "trace"
        assert trace_card["trace_of_living"]["marks"] == [], "the trace of a living agent offers nothing to raise"

    def test_trace_of_a_dead_agent_marks_its_raising(self, trace_card):
        assert trace_card["trace_of_dead"]["marks"] == [("+", "Peut être relevé.e")], \
            f"the trace of a dead agent should mark its raising, got {trace_card['trace_of_dead']['marks']}"
        assert "Test_Revenant" in trace_card["offered"], "the page the card links to offers the raising"

    def test_raising_from_the_trace_card_brings_the_agent_back(self, trace_card):
        card = trace_card["raised_card"]
        assert card["action"] == "passive" and card["id"] != trace_card["trace_of_dead"]["id"], \
            "Alpha's card now shows the real agent, alive, instead of the trace"
        assert "Test_Revenant" in trace_card["raised_page"], "the raised agent holds its transformation"
        rows = {(int(row["controller_id"]), row["action_choice"]) for row in trace_card["rows"]}
        assert rows == {(int(trace_card["alpha_id"]), "passive"), (int(trace_card["beta_id"]), "trace")}, \
            f"the agent serves Alpha again and Beta keeps only a trace of the dead one, got {rows}"
