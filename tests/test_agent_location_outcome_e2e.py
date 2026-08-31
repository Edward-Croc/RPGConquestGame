"""Playwright E2E tests for what happens to a location after agent combat (issue #73, step 5.D + 5.E).

Step 5.C resolved the fight and produced a verdict; this file covers what 5.D does
with it: plunder the artefacts, destroy / swap / pillage the place, write the
location_attack_logs row, and free the agents whose target was lost (5.E).

All assertions are UI-only (data-* attributes and rendered names, no pymysql /
direct SQL) so the suite runs under UI_ONLY=1.

Why a base is built in the fixture
  TestConfig alone cannot exercise any of this. Only Echo and Foxtrot own a
  destructible location, so every other controller is ineligible for spoils and the
  whole success path is skipped. And all three destructible seeds carry an
  activate_json swap payload, so none of them can actually be deleted. One
  createBase call fixes both: buildBase writes can_be_destroyed = 1 with NO
  activate_json (controllers/functions.php:334), giving a clean deletion target,
  and it makes its owner eligible. Built in Beta-Combat, it also sits in the same
  zone as the combat agents.

  createBase spends resources and returns false on an empty stock, printing only a
  message. The fixture therefore ASSERTS the base exists before going on: without
  that guard every test below would pass while testing nothing.

Combat math (TestConfig)
  MINROLL = MAXROLL = 3 = PASSIVEVAL, so every stat is 3 + Σ(power.stat) whatever
  the action, and Beta-Combat has no holder so no zone bonus applies. One exception:
  defend_location is a passive defence action carrying
  DEFEND_LOCATION_DEFENCE_FLAT_BONUS = 1, so a defender's defence_val is 3 + Σdefence + 1.
  Thresholds: ATTACKDIFF0 = 1 (kill), ATTACKDIFF1 = 3 (capture), RIPOSTDIFF = 2.
  Verdict: multipliby with locationOverwhelmValue = 2, strict comparison.

  Power sums used below are read from the live scenario, not from the table in
  test_agent_combat_e2e.py — that table is stale for Inv_Atk_1 and Claim_Atk_1.

The four groups, and the arithmetic that makes each outcome deterministic:

  Alpha's new base   Mover_Test (Echo, a=0), Keep_Atk (Foxtrot, a=0),
  (Beta-Combat)      Riposte_R3_C (Foxtrot, a=0) vs Even_Def (Beta, d=0)
                     attack 3−4 = −1 → all miss; riposte 3−3 = 0 → all survive
                     3 alive vs 1 → 3 > 1×2 → falls
                     spoils: Foxtrot 2 survivors beats Echo 1 → Foxtrot, eligible
                     no activate_json → DELETED. No artefacts → attacker_id NULL
                     Even_Def survives and is freed by 5.E

  Civic-Site         Chain_E (Echo, a=3), Chain_F + DA_Killer (Foxtrot), no
                     defender → 3 > 0 → falls
                     spoils: Foxtrot 2 survivors beats Echo 1 → Foxtrot
                     can_be_destroyed = 0 → PILLAGED, the place survives
                     carries "Civic-Site Token" → artefacts move → attacker_id = Foxtrot

  Test-Future-Loc.   Claim_Atk_1 (Echo), no defender → 1 > 0 → falls
                     activate_json.update_location renames it → SWAPPED, survives
                     no artefacts → attacker_id NULL

  Location A         Chain_D (Delta), no defender → 1 > 0 → falls
                     Delta owns nothing → no eligible winner → the place HOLDS,
                     logged with success = 0

Expected WARNING entries: the three Theta-Artefacts / Alpha-Investigation targets are
attacked by Beta-Combat agents, which the mechanic logs as a zone-coherence warning.
WARNING entries do not fail a test.

Run:
    python3 -m pytest tests/test_agent_location_outcome_e2e.py -v
"""
import re

import pytest
from playwright.sync_api import Page

from conftest import PHP_BASE_URL, ensure_gm_login

from helpers import (
    DB_AVAILABLE, clear_ui_caches, end_turn, load_minimal_data,
    load_scenario_via_admin, register_php_error_listener,
    assert_no_collected_php_errors, safe_goto, set_config_via_ui,
    ui_attack_location, ui_controller_id, ui_defend_location, ui_location_id,
    ui_worker_action_state, ui_zone_id, worker_report_html,
)

SWAPPED_NAME = "Test-Future-Location-Ruined"

_state = {}


def _location_names(page, base_url=PHP_BASE_URL):
    """Every location name currently listed on zones/management_locations.php."""
    safe_goto(page, f"{base_url}/zones/management_locations.php")
    page.wait_for_load_state("load")
    return {m.group(1).strip() for m in
            re.finditer(r'<h3>([^<]*?)\s*\(discovery[^<]*</h3>', page.content())}


def _attack_logs(page, base_url=PHP_BASE_URL):
    """Scrape the location_attack_logs table off zones/management_bases.php."""
    safe_goto(page, f"{base_url}/zones/management_bases.php")
    page.wait_for_load_state("load")
    rows = []
    for row in page.locator("tr.attack-log-row").all():
        attacker = row.get_attribute("data-attacker-controller-id")
        rows.append({
            "id": int(row.get_attribute("data-attack-log-id")),
            "location_name": row.get_attribute("data-location-name"),
            "turn": int(row.get_attribute("data-turn")),
            "success": row.get_attribute("data-success") == "1",
            "values": row.locator("td").nth(5).inner_text().strip(),
            "attacker_controller_id": int(attacker) if attacker else None,
            "attacker_text": row.locator("td").nth(7).inner_text(),
        })
    return rows


def _verdict_for(html, location_id):
    """Read the <p data-location-verdict=…> paragraph for one location.

    `taken` is the half the combat-test file cannot exercise: no falling location
    there has a winner able to hold the spoils, so it only ever sees taken=0.
    """
    m = re.search(
        r'data-location-verdict="(falls|holds)"\s+'
        rf'data-location-id="{location_id}"\s+'
        r'data-alive-attackers="(\d+)"\s+'
        r'data-alive-defenders="(\d+)"\s+'
        r'data-location-taken="([01])"',
        html,
    )
    assert m, f"no verdict paragraph found for location {location_id}"
    return {"verdict": m.group(1), "alive_attackers": int(m.group(2)),
            "alive_defenders": int(m.group(3)), "taken": m.group(4) == "1"}


def _log_for(location_name):
    matching = [r for r in _state["attack_logs"] if r["location_name"] == location_name]
    assert len(matching) == 1, f"expected exactly one log row for {location_name}, got {matching}"
    return matching[0]


@pytest.fixture(scope="module", autouse=True)
def outcome_state(browser):
    """Build Alpha a base, queue four independent groups, resolve them in one turn."""
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
        set_config_via_ui(page, "locationAttackMode", "agent_attack_defence",
                          base_url=PHP_BASE_URL)

        # Built before any controller is selected, so the gm visibility bypass in
        # createBase applies, and before the first end of turn while Alpha's stock
        # is still untouched.
        before = _location_names(page)
        alpha_cid = ui_controller_id(page, "Alpha", base_url=PHP_BASE_URL)
        beta_zid = ui_zone_id(page, "Beta-Combat", base_url=PHP_BASE_URL)
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php"
                        f"?createBase=1&controller_id={alpha_cid}&zone_id={beta_zid}")
        page.wait_for_load_state("load")

        created = _location_names(page) - before
        assert len(created) == 1, (
            f"createBase produced {created or 'nothing'} — most likely Alpha's stock "
            "was insufficient. Every test below depends on this base existing."
        )
        alpha_base = created.pop()
        _state["alpha_base"] = alpha_base
        _state["alpha_cid"] = alpha_cid
        _state["foxtrot_cid"] = ui_controller_id(page, "Foxtrot", base_url=PHP_BASE_URL)

        end_turn(page)

        targets = {
            name: ui_location_id(page, name, base_url=PHP_BASE_URL)
            for name in (alpha_base, "Civic-Site", "Test-Future-Location", "Location A")
        }
        _state["targets"] = targets

        for lastname in ("Mover_Test", "Keep_Atk", "Riposte_R3_C"):
            ui_attack_location(page, lastname, targets[alpha_base], base_url=PHP_BASE_URL)
        ui_defend_location(page, "Even_Def", targets[alpha_base], base_url=PHP_BASE_URL)

        for lastname in ("Chain_E", "Chain_F", "DA_Killer"):
            ui_attack_location(page, lastname, targets["Civic-Site"], base_url=PHP_BASE_URL)
        ui_attack_location(page, "Claim_Atk_1", targets["Test-Future-Location"],
                           base_url=PHP_BASE_URL)
        ui_attack_location(page, "Chain_D", targets["Location A"], base_url=PHP_BASE_URL)

        end_turn(page)
        _state["eot_html"] = page.content()

        _state["locations_after"] = _location_names(page)
        _state["attack_logs"] = _attack_logs(page)
        _state["even_def_after"] = ui_worker_action_state(page, "Even_Def",
                                                          base_url=PHP_BASE_URL)
        # Echo's searcher sits in Theta-Artefacts, where the swapped place stands.
        _state["searcher_report"] = worker_report_html(page, "Artefact_Searcher_Echo",
                                                       base_url=PHP_BASE_URL)

        assert_no_collected_php_errors(page)
        yield
    finally:
        context.close()
        # Unconditional : this file deletes locations, renames one and moves
        # artefacts between owners.
        if DB_AVAILABLE:
            load_minimal_data()
        load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
        clear_ui_caches()


class TestLocationDestroyed:
    """A place with no activate_json and a winner who can stash the loot is razed."""

    def test_the_base_is_gone(self):
        assert _state["alpha_base"] not in _state["locations_after"], (
            "the attacked base should have been deleted"
        )

    def test_it_is_logged_as_a_success(self):
        assert _log_for(_state["alpha_base"])["success"] is True

    def test_an_empty_place_credits_nobody(self):
        # The base carried no artefact, so count stayed 0 and nobody is credited.
        assert _log_for(_state["alpha_base"])["attacker_controller_id"] is None


class TestLocationSwapped:
    """update_location swaps the place instead of razing it."""

    def test_the_original_name_is_gone(self):
        assert "Test-Future-Location" not in _state["locations_after"]

    def test_the_swapped_name_is_there(self):
        assert SWAPPED_NAME in _state["locations_after"], (
            f"expected the swap to rename it to {SWAPPED_NAME}"
        )

    def test_the_swap_is_logged_as_a_success(self):
        assert _log_for("Test-Future-Location")["success"] is True


class TestSwappedPlaceReportedAsRuined:
    """The place the assault swapped is described as a ruin, dated to the assault.

    Theta-Artefacts holds the swapped place and Echo's Artefact_Searcher_Echo
    investigates there every turn. Test-Future-Location has discovery_diff 0 and
    the searcher rolls a fixed 3 plus its power sums, so its enquete difference
    clears LOCATIONINFORMATIONDIFF = 1 and the report reaches the description
    tier — the only tier that discloses state.

    The swap runs in locationAttackMechanic and the report in
    locationSearchMechanic, both inside the same end of turn against the same
    turncounter, so setup_turn equals the reporting turn and the age clause is
    "ce Tour" (timeValue = Tour).

    Two properties of the swap payload this test silently rides on, either of
    which would break it for a reason unrelated to what it checks :
      can_be_repaired = 1, which is what selects the ruined pool over the
        restored one (locationSearchMechanic picks on that flag) ;
      no controller_id, so the place stays unowned — credited to Echo, the
        searcher's own controller, the report would be suppressed entirely.
    """

    def test_the_searcher_calls_the_stormed_place_a_ruin_of_this_turn(self):
        report = _state["searcher_report"]
        assert (f"Ce.tte {SWAPPED_NAME} a été détruit.e par une attaque ce Tour."
                in report), (
            "an agent assault that swaps a place must stamp its turn and its ruined "
            "state, so the next discovery report reads from the ruined age pool"
        )
        # Paired with the positive : the swapped name only exists from the assault
        # on, so it must never have carried an "original" sentence.
        assert f"Ce.tte {SWAPPED_NAME} a été construit.e" not in report, (
            "a place swapped by an assault must have left the 'original' pool"
        )


class TestLocationPillaged:
    """A place flagged not destroyable is looted, never razed."""

    def test_civic_site_survives(self):
        assert "Civic-Site" in _state["locations_after"], (
            "can_be_destroyed = 0 must keep the place standing"
        )

    def test_the_pillage_is_logged_as_a_success(self):
        assert _log_for("Civic-Site")["success"] is True

    def test_the_looter_is_credited(self):
        # Civic-Site carries an artefact, so artefacts really moved and Echo is named.
        log = _log_for("Civic-Site")
        assert log["attacker_controller_id"] is not None, (
            "moving an artefact must credit the winner"
        )

    def test_the_attacker_text_reports_the_haul(self):
        assert "ramené des prisonniers" in _log_for("Civic-Site")["attacker_text"]


class TestNoEligibleWinner:
    """A winner with nowhere to stash the loot cannot take the place."""

    def test_location_a_still_stands(self):
        assert "Location A" in _state["locations_after"]

    def test_it_is_logged_as_a_failure(self):
        log = _log_for("Location A")
        assert log["success"] is False, (
            "Delta owns no destructible location, so the attack cannot succeed"
        )
        assert log["attacker_controller_id"] is None


class TestLoggedCombatValues:
    """The log records both survivor counts, not just the attackers'."""

    def test_both_sides_of_the_tally_are_logged(self):
        # Alpha's base was stormed by three attackers that all survived, against
        # one defender that also survived — the exact pair that produced the
        # verdict. defence_val used to default to 0, rendering "3 / 0" and making
        # a 3-against-1 indistinguishable from a 3-against-nobody.
        assert _log_for(_state["alpha_base"])["values"] == "3 / 1", (
            "attack_val / defence_val must carry the surviving attacker and "
            "defender counts"
        )


class TestVerdictAndTaken:
    """A won fight with an eligible winner must report the place as taken."""

    def test_the_taken_place_is_reported_as_taken(self):
        v = _verdict_for(_state["eot_html"], _state["targets"][_state["alpha_base"]])
        assert v["verdict"] == "falls", "three survivors against one carries the place"
        assert v["taken"] is True, (
            "Foxtrot owns a destructible location, so the spoils have a destination "
            "and the place really changes hands — this is the case a hardcoded "
            "data-location-taken=0 would slip past"
        )

    def test_a_won_fight_without_a_holder_is_not_taken(self):
        # Delta owns nothing destructible, so Location A's assault wins nothing.
        v = _verdict_for(_state["eot_html"], _state["targets"]["Location A"])
        assert v["taken"] is False, (
            "no eligible winner means the place is not taken, whatever the fight said"
        )


class TestSpoilsRanking:
    """The controller with the most survivors carries off the spoils."""

    def test_the_larger_surviving_network_takes_the_loot(self):
        # Civic-Site was stormed by two Foxtrot agents and one from Echo; both
        # networks are eligible, so only the survivor count separates them.
        assert _log_for("Civic-Site")["attacker_controller_id"] == _state["foxtrot_cid"], (
            "the spoils must go to Foxtrot, who fielded two survivors against Echo's one"
        )

    def test_each_resolved_location_wrote_exactly_one_row(self):
        names = [_state["alpha_base"], "Civic-Site", "Test-Future-Location", "Location A"]
        for name in names:
            _log_for(name)


class TestOrphanReset:
    """5.E : agents still targeting a lost place are freed."""

    def test_the_surviving_defender_is_passive_again(self):
        assert _state["even_def_after"]["action_choice"] == "passive", (
            "a defender of a razed location must not keep defending it"
        )

    def test_its_action_params_were_emptied(self):
        params = _state["even_def_after"]["action_params"] or "{}"
        assert "location_id" not in params, (
            f"the stale target should be gone, got {params!r}"
        )
