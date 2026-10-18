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
from helpers import clear_ui_caches, load_scenario_via_admin, login_as, safe_goto, set_config_via_ui

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
