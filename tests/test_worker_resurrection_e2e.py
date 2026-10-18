"""Raising a dead agent with a transformation (issue #38).

TestConfig carries two transformations open to the dead :
  - Test_Revenant  — worker_is_dead, FactionAlpha, exclusive of both ;
  - Test_Possessed — OR [age 99, worker_is_dead], open to « FactionGolf, FactionAlpha ».
and three Alpha agents in Beta-Combat :
  - Revenant_Dead   — seeded dead ;
  - Revenant_Twice  — seeded dead, already holding Test_Revenant ;
  - Revenant_Gifted — alive, given to Beta, then killed by Inv_Atk_2 (Charlie).

One module fixture plays the whole sequence from the Alpha player's session
and records what each test asserts, so the scenario loads once.
"""
import pytest
from playwright.sync_api import Page

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    clear_ui_caches, end_turn, load_scenario_via_admin, login_as, safe_goto,
    ui_attack, ui_controller_id, ui_workers_by_lastname,
)

DEAD_ONLY = {"Test_Revenant", "Test_Possessed"}


def _alpha_cards(page: Page) -> dict:
    """lastname -> (worker id, action_choice) of the Alpha agents, read off viewAll."""
    safe_goto(page, f"{PHP_BASE_URL}/workers/viewAll.php")
    return {
        card.get_attribute("data-worker-lastname"): (card.get_attribute("data-worker-id"), card.get_attribute("data-action-choice"))
        for card in page.locator("div.worker-short").all()
    }


def _open(page: Page, worker_id: str):
    safe_goto(page, f"{PHP_BASE_URL}/workers/action.php?worker_id={worker_id}")


def _transform_options(page: Page, worker_id: str) -> dict:
    """Transformation name -> option value on the worker page, placeholder excluded."""
    _open(page, worker_id)
    options = {}
    for option in page.locator("select#transformationSelect option").all():
        value = option.get_attribute("value")
        if value:
            options[option.inner_text().split(" (")[0].strip()] = value
    return options


def _transform(page: Page, worker_id: str, name: str) -> str:
    """Pick a transformation on the worker page, submit it, return the resulting page."""
    options = _transform_options(page, worker_id)
    page.locator("select#transformationSelect").select_option(value=options[name])
    page.locator("input[name='transform']").click()
    page.wait_for_load_state("load")
    return page.content()


@pytest.fixture(scope="module")
def resurrection(browser):
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

        cards = _alpha_cards(alpha)
        # Turn 0 : every agent is of age 0, which must not pass Test_Possessed's « age 99 » branch.
        state["age_zero_options"] = _transform_options(alpha, cards["Searcher_1"][0])
        end_turn(gm, base_url=PHP_BASE_URL)
        state["options"] = {name: _transform_options(alpha, cards[name][0]) for name in ("Revenant_Dead", "Revenant_Twice", "Searcher_1")}
        _open(alpha, cards["Revenant_Dead"][0])
        state["dead_discipline_selects"] = alpha.locator("select#disciplineSelect").count()

        # Alpha gives Revenant_Gifted to Beta : Alpha keeps a trace of a living agent.
        beta_id = ui_controller_id(gm, "Beta", base_url=PHP_BASE_URL)
        safe_goto(alpha, f"{PHP_BASE_URL}/workers/action.php?worker_id={cards['Revenant_Gifted'][0]}&gift_controller_id={beta_id}&gift=Donner")
        trace_id = _alpha_cards(alpha)["Revenant_Gifted"][0]
        state["trace_id"] = trace_id
        state["trace_of_living_options"] = _transform_options(alpha, trace_id)

        # Inv_Atk_2 kills it in Beta's service.
        ui_attack(gm, "Inv_Atk_2", "Revenant_Gifted", base_url=PHP_BASE_URL)
        end_turn(gm, base_url=PHP_BASE_URL)
        state["trace_of_dead_options"] = _transform_options(alpha, trace_id)

        # A forged request cannot raise a dead agent with an ordinary transformation.
        war_gear = state["options"]["Searcher_1"]["War Gear"]
        safe_goto(alpha, f"{PHP_BASE_URL}/workers/action.php?worker_id={cards['Revenant_Twice'][0]}&transformation={war_gear}&transform=Ajouter")
        state["forged_html"] = alpha.content()
        state["twice_after"] = _alpha_cards(alpha)["Revenant_Twice"][1]

        # A raising must come alone : another action in the same request would act on the dead.
        revenant = state["options"]["Revenant_Dead"]["Test_Revenant"]
        response = alpha.request.get(
            f"{PHP_BASE_URL}/workers/action.php?worker_id={cards['Revenant_Dead'][0]}"
            f"&transformation={revenant}&transform=Ajouter&attack=Attaquer")
        state["combined_status"] = response.status
        state["dead_after_combined"] = _alpha_cards(alpha)["Revenant_Dead"][1]

        state["raise_html"] = _transform(alpha, cards["Revenant_Dead"][0], "Test_Revenant")
        state["dead_after"] = _alpha_cards(alpha)["Revenant_Dead"][1]
        state["dead_after_options"] = _transform_options(alpha, cards["Revenant_Dead"][0])

        state["trace_raise_html"] = _transform(alpha, trace_id, "Test_Revenant")
        state["gifted_after"] = _alpha_cards(alpha)["Revenant_Gifted"]
        state["gifted_rows"] = ui_workers_by_lastname(gm, "Revenant_Gifted", base_url=PHP_BASE_URL)
        state["alpha_id"] = ui_controller_id(gm, "Alpha", base_url=PHP_BASE_URL)
        state["beta_id"] = beta_id
        beta_trace = [row["id"] for row in state["gifted_rows"] if row["action_choice"] == "trace"]
        raised = [row["id"] for row in state["gifted_rows"] if row["action_choice"] == "passive"]
        safe_goto(gm, f"{PHP_BASE_URL}/base/accueil.php?controller_id={beta_id}&chosir=Choisir")
        safe_goto(gm, f"{PHP_BASE_URL}/workers/action.php?worker_id={beta_trace[0]}")
        state["beta_trace_html"] = gm.content()
        _open(alpha, raised[0])
        state["raised_html"] = alpha.content()

        # The organiser gives anything : an ordinary transformation on a dead agent leaves it dead.
        organiser_context = browser.new_context()
        contexts.append(organiser_context)
        organiser = organiser_context.new_page()
        login_as(organiser, PHP_BASE_URL, "gm", "orga")
        safe_goto(organiser, f"{PHP_BASE_URL}/workers/action.php?worker_id={cards['Revenant_Twice'][0]}&transformation={war_gear}&transform=Ajouter")
        _open(alpha, cards["Revenant_Twice"][0])
        state["organiser_html"] = alpha.content()
        state["twice_after_organiser"] = _alpha_cards(alpha)["Revenant_Twice"][1]
        yield state
    finally:
        for context in contexts:
            context.close()
        load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
        clear_ui_caches()


class TestWhoIsOffered:
    def test_dead_agent_is_offered_only_the_transformations_that_raise(self, resurrection):
        offered = set(resurrection["options"]["Revenant_Dead"])
        assert offered == DEAD_ONLY, f"a dead agent should see only the raising transformations, got {offered}"
        assert resurrection["dead_discipline_selects"] == 0, "a dead agent is taught no discipline"

    def test_faction_list_opens_the_power_to_the_second_faction(self, resurrection):
        assert "Test_Possessed" in resurrection["options"]["Revenant_Dead"], \
            "« FactionGolf, FactionAlpha » should open Test_Possessed to FactionAlpha"

    def test_living_agent_is_not_offered_a_raising(self, resurrection):
        offered = set(resurrection["options"]["Searcher_1"])
        assert "War Gear" in offered, "a living agent keeps its ordinary transformations"
        assert not offered & DEAD_ONLY, f"a living agent must not see a raising, got {offered & DEAD_ONLY}"

    def test_agent_of_age_zero_does_not_pass_an_age_rule(self, resurrection):
        offered = set(resurrection["age_zero_options"])
        assert "War Gear" in offered, "an agent of age 0 keeps its ordinary transformations"
        assert "Test_Possessed" not in offered, "an agent of age 0 must not pass the « age 99 » branch"

    def test_agent_raised_once_cannot_rise_again(self, resurrection):
        assert resurrection["options"]["Revenant_Twice"] == {}, \
            "a dead agent already holding Test_Revenant is offered neither Test_Revenant nor Test_Possessed"

    def test_trace_offers_a_raising_only_once_its_agent_is_dead(self, resurrection):
        assert resurrection["trace_of_living_options"] == {}, "the trace of a living agent offers nothing"
        assert set(resurrection["trace_of_dead_options"]) == DEAD_ONLY, \
            "the trace of a dead agent offers the raisings evaluated on that agent"


class TestRaising:
    def test_ordinary_transformation_cannot_raise_a_dead_agent(self, resurrection):
        assert "Cette transformation n'est plus disponible" in resurrection["forged_html"]
        assert resurrection["twice_after"] == "dead", "the forged request must leave the agent dead"

    def test_raising_combined_with_another_action_is_refused(self, resurrection):
        assert resurrection["combined_status"] == 403, "a raising carrying another action must be refused"
        assert resurrection["dead_after_combined"] == "dead", "the refused request must leave the agent dead"

    def test_organiser_transformation_on_the_dead_does_not_raise(self, resurrection):
        assert "War Gear" in resurrection["organiser_html"], "the organiser can give any transformation, here War Gear"
        assert resurrection["twice_after_organiser"] == "dead", "an ordinary transformation does not raise the dead"

    def test_transformation_raises_a_dead_agent(self, resurrection):
        assert "revenu.e d'entre les morts" in resurrection["raise_html"]
        assert resurrection["dead_after"] == "passive", "the raised agent comes back watching"
        assert not set(resurrection["dead_after_options"]) & DEAD_ONLY, "once alive, the raisings are no longer offered"

    def test_raising_from_a_trace_brings_the_agent_back(self, resurrection):
        raised_id, raised_action = resurrection["gifted_after"]
        assert raised_id != resurrection["trace_id"] and raised_action == "passive", \
            "Alpha should hold the real agent again, alive"
        assert "revenu.e d'entre les morts" in resurrection["trace_raise_html"]
        rows = {(int(row["controller_id"]), row["action_choice"]) for row in resurrection["gifted_rows"]}
        assert rows == {(int(resurrection["alpha_id"]), "passive"), (int(resurrection["beta_id"]), "trace")}, \
            f"the agent is back with Alpha and Beta keeps only a trace, got {rows}"
        assert "Test_Revenant" in resurrection["raised_html"], "the raised agent holds its transformation"
        assert "Test_Revenant" not in resurrection["beta_trace_html"], \
            "the trace the foreign master keeps must not show the transformation that raised the agent"
