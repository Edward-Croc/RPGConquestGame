"""Blood, the resource of Vampire1966CSV (issue #212).

Every faction starts with one dose and no fixed gain ; a held zone yields 2,
a zone under its banner 1, before the claims are resolved, and the doses are
kept from one turn to the next. Goule costs 1 dose, every other transformation 3.

  - Mazzino (player1)  : the zone Railway Station is set held and under his banner ;
  - Trentini (player5) : Tremere, recruits an agent who may become a Gargouille.
"""
import pytest
from playwright.sync_api import Page

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import _wait_loaded, clear_ui_caches, end_turn, load_scenario_via_admin, login_as, safe_goto


def _blood(page: Page) -> tuple:
    """(usable amount, next turn estimate) of the Sang row on the player's resources page."""
    safe_goto(page, f"{PHP_BASE_URL}/ressources/view.php")
    cells = page.locator("table tr", has_text="Sang").first.locator("td")
    return int(cells.nth(1).inner_text().strip()), cells.nth(2).inner_text().strip()


def _set_zone_holder_and_banner(gm: Page, zone_name: str, controller_lastname: str):
    """Organiser page : the zone is held by, and flies the banner of, the named controller."""
    safe_goto(gm, f"{PHP_BASE_URL}/zones/management_zones.php")
    row = next(r for r in gm.locator("tr:has(form)").all()
               if r.locator("td").count() > 1 and r.locator("td").nth(1).inner_text().strip() == zone_name)
    for select in ("holder_id", "claimer_id"):
        label = row.locator(f"select[name='{select}'] option").filter(has_text=controller_lastname).first.inner_text().strip()
        row.locator(f"select[name='{select}']").select_option(label=label)
    row.locator("button[type='submit']").click()
    gm.wait_for_load_state("load")


def _set_blood(gm: Page, controller_lastname: str, amount: int):
    """Organiser page : the controller's usable Sang."""
    safe_goto(gm, f"{PHP_BASE_URL}/ressources/management.php")
    row = gm.locator("tr", has_text=controller_lastname).filter(has_text="Sang").first
    row.locator("input[name='amount']").fill(str(amount))
    row.locator("button[name='update_ressource']").click()
    gm.wait_for_load_state("load")


def _transformations(page: Page, worker_id: str) -> dict:
    """Offered transformation label -> option value on the worker page."""
    safe_goto(page, f"{PHP_BASE_URL}/workers/action.php?worker_id={worker_id}")
    return {o.inner_text().strip(): o.get_attribute("value")
            for o in page.locator("select#transformationSelect option").all() if o.get_attribute("value")}


@pytest.fixture(scope="module")
def blood(browser):
    state = {}
    contexts = []
    try:
        load_scenario_via_admin(browser, PHP_BASE_URL, "Vampire1966CSV")
        clear_ui_caches()
        gm_context = browser.new_context()
        contexts.append(gm_context)
        gm = gm_context.new_page()
        ensure_gm_login(gm, PHP_BASE_URL)
        mazzino_context = browser.new_context()
        contexts.append(mazzino_context)
        mazzino = mazzino_context.new_page()
        login_as(mazzino, PHP_BASE_URL, "player1", "one")
        trentini_context = browser.new_context()
        contexts.append(trentini_context)
        trentini = trentini_context.new_page()
        login_as(trentini, PHP_BASE_URL, "player5", "five")

        state["start"] = _blood(mazzino)
        _set_zone_holder_and_banner(gm, "Railway Station", "Mazzino")
        state["estimate"] = _blood(mazzino)
        end_turn(gm, base_url=PHP_BASE_URL)
        state["after_turn"] = _blood(mazzino)

        # Trentini recruits the first comer, who may become a Gargouille.
        safe_goto(trentini, f"{PHP_BASE_URL}/workers/viewAll.php")
        trentini.locator("input[name='first_come']").click()
        _wait_loaded(trentini, "input[name='chosir']")
        trentini.locator("input[name='chosir']").first.click()
        trentini.wait_for_load_state("load")
        safe_goto(trentini, f"{PHP_BASE_URL}/workers/viewAll.php")
        worker_id = trentini.locator("div.worker-short").first.get_attribute("data-worker-id")

        state["one_dose_offer"] = _transformations(trentini, worker_id)
        _set_blood(gm, "Trentini", 3)
        state["three_doses_offer"] = _transformations(trentini, worker_id)
        gargouille = next((value for label, value in state["three_doses_offer"].items() if label.startswith("Gargouille")), None)
        if gargouille is not None:
            trentini.locator("select#transformationSelect").select_option(value=gargouille)
            trentini.locator("input[name='transform']").click()
            trentini.wait_for_load_state("load")
        state["transformed_page"] = trentini.content()
        state["after_gargouille"] = _blood(trentini)
        yield state
    finally:
        for context in contexts:
            context.close()
        load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
        clear_ui_caches()


class TestBloodGain:
    def test_every_faction_starts_with_one_dose(self, blood):
        assert blood["start"] == (1, "+0"), f"one dose at the start and no fixed gain, got {blood['start']}"

    def test_held_zone_under_banner_yields_three(self, blood):
        assert blood["estimate"] == (1, "+3"), \
            f"a zone held (2) and under the banner (1) should announce +3, got {blood['estimate']}"

    def test_doses_are_kept_from_one_turn_to_the_next(self, blood):
        assert blood["after_turn"][0] == 4, f"1 kept + 3 gained should make 4 doses, got {blood['after_turn']}"


class TestBloodCost:
    def test_a_transformation_needs_its_doses(self, blood):
        assert not any(label.startswith("Gargouille") for label in blood["one_dose_offer"]), \
            "with 1 dose, Gargouille (3 doses) must not be offered"
        assert any(label.startswith("Gargouille") and "3" in label for label in blood["three_doses_offer"]), \
            f"with 3 doses, Gargouille should be offered with its cost, got {list(blood['three_doses_offer'])}"

    def test_the_transformation_spends_its_doses(self, blood):
        assert "Gargouille" in blood["transformed_page"], "the agent should now be a Gargouille"
        assert blood["after_gargouille"][0] == 0, f"Gargouille costs 3 doses, got {blood['after_gargouille']}"
