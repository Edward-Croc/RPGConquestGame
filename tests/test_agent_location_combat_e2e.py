"""Playwright E2E tests for agent-versus-agent location combat (issue #73, step 5.C).

Covers the `agent_attack_defence` branch of mechanics/locationAttackMechanic.php:
grouping the turn's attack_location / defend_location actions by target, resolving
each group as a sequential ladder via resolveWorkerCombat(), and computing the
capture verdict from the survivors.

All assertions are UI-only (data-* attributes, no pymysql / direct SQL) so the
suite runs under UI_ONLY=1 against a remote deployment.

Observation surfaces
  - workers/management_combat.php via ui_combat_logs / ui_combat_unresolved_count
    / ui_combat_filter_options — one row per duel, carrying data-location-id.
  - the end-of-turn output itself, which prints one
    <p data-location-verdict="falls|holds" …> per resolved location.

Combat math (TestConfig)
  setupTestConfig_config.csv pins MINROLL=MAXROLL=3, equal to PASSIVEVAL=3, so the
  active-dice tier and the passive tier produce the same number and every stat is
  simply 3 + Σ(power.stat) whatever the action. Beta-Combat has no holder, so no
  zone bonus applies. Combat therefore reduces to the power totals:
    attack_difference  = attacker.attack_val − defender.defence_val
    riposte_difference = defender.attack_val − attacker.defence_val
  Thresholds: ATTACKDIFF0=1 (kill), ATTACKDIFF1=3 (capture), RIPOSTDIFF=2,
  RIPOSTACTIVE=1. Agent stats are the ones documented in test_agent_combat_e2e.py.

  One correction on top of that table: defend_location is a PASSIVE defence action
  carrying DEFEND_LOCATION_DEFENCE_FLAT_BONUS = 1, so a defender's defence_val is
  its documented value PLUS ONE. Attackers get no such bonus.

Groups built in the single resolved turn, with the arithmetic that makes each
outcome deterministic:

  Test-Future-Location  Chain_A a=8 vs Chain_B d=5+1=6 → 8−6=2  kill
                        riposte 4−7=−3, no riposte      → 1 alive vs 0 → falls
                        Chain_G also targets it but dies earlier to an ordinary
                        attack from Claim_Def_1 (4−2=2), so it never joins the
                        group (D17).

  Echo-Base             Inv_Atk_1 a=8 vs Inv_Def_2 d=3+1 → 8−4=4  capture
                                     vs Inv_Def_1 d=2+1 → 8−3=5  capture
                        one attacker, two rows            → 1 alive vs 0 → falls

  Foxtrot-Outpost       Claim_Atk_2 d=4 vs Counter_Def a=6 → riposte 6−4=2 kills
                        Counter_Atk d=2 vs Counter_Def a=6 → riposte 6−2=4 kills
                        attack side 4−6=−2 both times, so no defender death
                                                        → 0 alive vs 1 → holds

  Civic-Site            three 3/3/3 attackers vs Even_Def d=3+1=4
                        3−4=−1 misses, riposte 3−3=0    → 3 alive vs 1 → falls

  Location A            Inv_Atk_2 a=4, Chain_F a=3 vs Chain_C d=4+1=5
                        misses, riposte 4−4=0           → 2 alive vs 1 → holds

The last two bracket the multipliby threshold: 3 > 1×2 falls, 2 > 1×2 holds.

Expected WARNING entries: every group here is cross-zone (the combat agents live
in Beta-Combat, the locations do not), which the mechanic logs as a zone-coherence
warning. No location exists in Beta-Combat, so a same-zone group cannot be built
from these agents, and the rendered form only ever offers same-zone locations —
hence the gm URL bypass. WARNING entries do not fail a test.

Run:
    python3 -m pytest tests/test_agent_location_combat_e2e.py -v
"""
import json
import re

import pytest
from playwright.sync_api import Page

from conftest import PHP_BASE_URL, ensure_gm_login

from helpers import (
    DB_AVAILABLE, clear_ui_caches, end_turn, load_minimal_data,
    load_scenario_via_admin, register_php_error_listener,
    assert_no_collected_php_errors, set_config_via_ui,
    ui_attack_click, ui_attack_location, ui_combat_filter_options,
    ui_combat_logs, ui_combat_unresolved_count, ui_config_value,
    ui_controller_id, ui_defend_location, ui_location_id,
    ui_recruit_perfect_worker, ui_worker_id, ui_workers_by_lastname,
    worker_report_html, worker_report_section,
)

# Location name -> (attacker lastnames, defender lastnames), in the order queued.
GROUPS = {
    "Test-Future-Location": (["Chain_A", "Chain_G"], ["Chain_B"]),
    "Echo-Base":            (["Inv_Atk_1"], ["Inv_Def_1", "Inv_Def_2"]),
    "Foxtrot-Outpost":      (["Claim_Atk_2", "Counter_Atk"], ["Counter_Def"]),
    "Civic-Site":           (["Chain_D", "Even_Atk", "Claim_Def_2"], ["Even_Def"]),
    "Location A":           (["Inv_Atk_2", "Chain_F"], ["Chain_C"]),
}

_state = {}
_sabotage = {}


def _verdicts_from_end_turn(html):
    """Parse the <p data-location-verdict=…> lines the mechanic prints.

    `verdict` is the COMBAT outcome; `taken` is whether the place actually changed
    hands, which is false when the fight is won but no attacking controller can
    hold the spoils.

    Returns {location_id: {'verdict', 'alive_attackers', 'alive_defenders', 'taken'}}."""
    found = {}
    for m in re.finditer(
        r'data-location-verdict="(falls|holds)"\s+'
        r'data-location-id="(\d+)"\s+'
        r'data-alive-attackers="(\d+)"\s+'
        r'data-alive-defenders="(\d+)"\s+'
        r'data-location-taken="([01])"',
        html,
    ):
        found[int(m.group(2))] = {
            "verdict": m.group(1),
            "alive_attackers": int(m.group(3)),
            "alive_defenders": int(m.group(4)),
            "taken": m.group(5) == "1",
        }
    return found


def _assert_no_outcome_line(page, lastname):
    """A combatant out of the fight keeps its duel lines and learns nothing more.

    Paired with a positive : without it, the negatives below would also pass on a
    mechanic that wrote no report at all.
    """
    html = worker_report_html(page, lastname, base_url=PHP_BASE_URL)
    section = worker_report_section(html, "Attaque de lieu :")
    assert section, f"{lastname} fought a location duel and must have that section"
    # Every outcome pool TestConfig seeds, NoHolder included : leaving one out is
    # how the captured-defender half of this check first passed for the wrong reason.
    for line in ("location attack success", "location attack fail",
                 "location defended", "location fell", "never engaged",
                 "nowhere to hold it", "they took nothing",
                 "taken by us", "taken by network"):
        assert line not in section, (
            f"{lastname} left the fight before it ended and must not read {line!r}"
        )


@pytest.fixture(scope="module", autouse=True)
def location_combat_state(browser):
    """Queue five independent location groups, resolve them in one end of turn.

    The turn 0->1 end of turn is what seeds the mutual passive detection that
    ui_attack_click needs to render its form."""
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

        end_turn(page)

        location_ids = {name: ui_location_id(page, name, base_url=PHP_BASE_URL)
                        for name in GROUPS}
        _state["location_ids"] = location_ids
        _state["worker_ids"] = {
            lastname: ui_worker_id(page, lastname, base_url=PHP_BASE_URL)
            for attackers, defenders in GROUPS.values()
            for lastname in attackers + defenders
        }

        for name, (attackers, defenders) in GROUPS.items():
            for lastname in attackers:
                ui_attack_location(page, lastname, location_ids[name],
                                   base_url=PHP_BASE_URL)
            for lastname in defenders:
                ui_defend_location(page, lastname, location_ids[name],
                                   base_url=PHP_BASE_URL)

        # D17 : Chain_G holds attack_location but dies to an ordinary attack
        # first, so it must never reach the ladder. Claim_Def_1 a=4 vs its d=2
        # gives 2, inside [ATTACKDIFF0, ATTACKDIFF1[. Click-driven to honour the
        # once-per-file rule.
        ui_attack_click(page, "Claim_Def_1", "Chain_G", base_url=PHP_BASE_URL)
        _state["worker_ids"]["Claim_Def_1"] = ui_worker_id(page, "Claim_Def_1",
                                                           base_url=PHP_BASE_URL)

        end_turn(page)
        _state["verdicts"] = _verdicts_from_end_turn(page.content())
        _state["logs"] = ui_combat_logs(page, base_url=PHP_BASE_URL)

        # Second turn, same Civic-Site group, only the mode changed. All four
        # agents survived turn one, and defend_location persists by config
        # (continuing_defend_location_action = 1) so only the attackers are
        # re-queued. Same arithmetic, opposite verdict: 3 > 1 + 2 is false.
        set_config_via_ui(page, "locationOverwhelmMode", "morethan",
                          base_url=PHP_BASE_URL)
        for lastname in GROUPS["Civic-Site"][0]:
            ui_attack_location(page, lastname,
                               location_ids["Civic-Site"], base_url=PHP_BASE_URL)
        end_turn(page)
        _state["verdicts_morethan"] = _verdicts_from_end_turn(page.content())

        assert_no_collected_php_errors(page)
        yield
    finally:
        context.close()
        # Unconditional : ensure_scenario_loaded() would skip a reload here, and
        # this file leaves dead, captured and side-switched agents behind.
        if DB_AVAILABLE:
            load_minimal_data()
        load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
        clear_ui_caches()


def _rows_for(location_name):
    lid = _state["location_ids"][location_name]
    return [r for r in _state["logs"] if r["location_id"] == lid]


def _wid(lastname):
    return _state["worker_ids"][lastname]


class TestLocationCombatIsLogged:
    """The mechanic runs and every duel lands in worker_combat_logs with its place."""

    def test_single_pair_produces_one_row_carrying_the_location(self):
        rows = _rows_for("Test-Future-Location")
        assert len(rows) == 1, f"expected exactly one duel, got {rows}"
        assert rows[0]["attacker_worker_id"] == _wid("Chain_A")
        assert rows[0]["defender_worker_id"] == _wid("Chain_B")

    def test_computed_outcome_is_a_plain_kill(self):
        # 8 - 6 = 2 : above ATTACKDIFF0, below ATTACKDIFF1, so death without capture.
        assert _rows_for("Test-Future-Location")[0]["outcome"] == "kill"

    def test_every_row_is_resolved(self):
        assert _state["logs"], "no combat rows at all — the mechanic did not run"
        unresolved = [r for r in _state["logs"] if not r["resolved"]]
        assert unresolved == [], f"combats left open: {unresolved}"

    def test_no_unresolved_count_reported(self, page: Page):
        ensure_gm_login(page, PHP_BASE_URL)
        assert ui_combat_unresolved_count(page, base_url=PHP_BASE_URL) == 0


class TestLadderProgression:
    """A killer keeps going; a riposte rotates the attacker."""

    def test_one_attacker_walks_two_defenders(self, page: Page):
        rows = _rows_for("Echo-Base")
        assert len(rows) == 2, f"expected two duels for one attacker, got {rows}"
        assert {r["attacker_worker_id"] for r in rows} == {_wid("Inv_Atk_1")}
        assert {r["defender_worker_id"] for r in rows} == {
            _wid("Inv_Def_1"), _wid("Inv_Def_2")
        }
        # Those two duels are a location assault, so they are filed under
        # « Attaque de lieu » and not among ordinary agent attacks.
        html = worker_report_html(page, "Inv_Atk_1", base_url=PHP_BASE_URL)
        assert worker_report_section(html, "Attaque de lieu :"), (
            "Inv_Atk_1 fought two location duels and must have that report section"
        )
        assert "Inv_Def_1" not in worker_report_section(html, "Attaques :"), (
            "a location duel must not be filed in the agent-attack section"
        )

    def test_both_of_those_duels_are_captures(self, page: Page):
        # 8 - 3 and 8 - 2 both clear ATTACKDIFF1 = 3.
        assert [r["outcome"] for r in _rows_for("Echo-Base")] == ["capture", "capture"]
        # A captured defender is out of the fight before it ends : it keeps the duel
        # it lived through and is told nothing of the outcome.
        _assert_no_outcome_line(page, "Inv_Def_1")
        # 5.G : the ladder reuses resolveWorkerCombat, so a capture on a location
        # assault must leave a trace worker exactly as an ordinary capture does.
        # Nothing else asserts this on the location path.
        rows = ui_workers_by_lastname(page, "Inv_Def_1")
        assert len(rows) == 2, (
            f"Inv_Def_1 was captured defending a location and should have a captured "
            f"row plus a trace row, got {rows}"
        )
        actions = sorted(r["action_choice"] for r in rows)
        assert "trace" in actions, (
            f"capturing on the location path must create a trace worker, got {actions}"
        )

    def test_defender_order_follows_enquete_initiative(self):
        # Inv_Def_2 enquete 3 engages before Inv_Def_1 enquete 2.
        rows = sorted(_rows_for("Echo-Base"), key=lambda r: r["id"])
        assert [r["defender_worker_id"] for r in rows] == [
            _wid("Inv_Def_2"), _wid("Inv_Def_1")
        ]

    def test_riposte_spends_the_attacker_and_keeps_the_defender(self, page: Page):
        rows = sorted(_rows_for("Foxtrot-Outpost"), key=lambda r: r["id"])
        assert len(rows) == 2, f"expected two duels against one defender, got {rows}"
        assert {r["defender_worker_id"] for r in rows} == {_wid("Counter_Def")}
        assert [r["attacker_worker_id"] for r in rows] == [
            _wid("Claim_Atk_2"), _wid("Counter_Atk")
        ]
        assert [r["outcome"] for r in rows] == ["riposte_kill", "riposte_kill"]
        _assert_no_outcome_line(page, "Counter_Atk")


class TestVerdict:
    """Survivor counting and the strict multipliby comparison."""

    def test_three_survivors_against_one_takes_the_location(self):
        verdict = _state["verdicts"][_state["location_ids"]["Civic-Site"]]
        assert verdict["alive_attackers"] == 3
        assert verdict["alive_defenders"] == 1
        assert verdict["verdict"] == "falls", "3 > 1 * 2 should carry the location"

    def test_two_survivors_against_one_does_not(self):
        verdict = _state["verdicts"][_state["location_ids"]["Location A"]]
        assert verdict["alive_attackers"] == 2
        assert verdict["alive_defenders"] == 1
        assert verdict["verdict"] == "holds", "2 > 1 * 2 is false, the location holds"

    def test_a_lone_surviving_defender_holds_the_location(self):
        verdict = _state["verdicts"][_state["location_ids"]["Foxtrot-Outpost"]]
        assert verdict["alive_attackers"] == 0
        assert verdict["alive_defenders"] == 1
        assert verdict["verdict"] == "holds"

    def test_wiping_the_defenders_takes_the_location(self):
        verdict = _state["verdicts"][_state["location_ids"]["Test-Future-Location"]]
        assert verdict["alive_attackers"] == 1
        assert verdict["alive_defenders"] == 0
        assert verdict["verdict"] == "falls"

    def test_every_group_leaves_at_least_one_survivor(self):
        # The ladder spends at most |A|+|D|-1 duels, so a mutual wipeout cannot
        # happen. Replaces the abandoned TestMutualWipeout of the audit.
        for lid, verdict in _state["verdicts"].items():
            assert verdict["alive_attackers"] + verdict["alive_defenders"] >= 1, (
                f"location {lid} ended with nobody alive: {verdict}"
            )


class TestOverwhelmMode:
    """The same survivor counts give opposite verdicts under the other mode."""

    def test_multipliby_takes_the_location_with_three_against_one(self):
        verdict = _state["verdicts"][_state["location_ids"]["Civic-Site"]]
        assert (verdict["alive_attackers"], verdict["alive_defenders"]) == (3, 1)
        assert verdict["verdict"] == "falls", "3 > 1 * 2"

    def test_morethan_holds_it_with_the_same_counts(self):
        verdict = _state["verdicts_morethan"][_state["location_ids"]["Civic-Site"]]
        assert (verdict["alive_attackers"], verdict["alive_defenders"]) == (3, 1), (
            "the second turn must reproduce the first turn's survivor counts, "
            "otherwise the two verdicts are not comparable"
        )
        assert verdict["verdict"] == "holds", "3 > 1 + 2 is false"

    def test_defend_location_persisted_into_the_second_turn(self):
        # continuing_defend_location_action = 1, so Even_Def kept defending
        # without being re-queued — which is why the counts stayed comparable.
        assert _state["location_ids"]["Civic-Site"] in _state["verdicts_morethan"]


class TestNoAttackerNoAssault:
    """Defenders with nobody attacking them are not an assault.

    The second turn re-queued only Civic-Site's attackers. attack_location does
    not persist (continuing_attack_location_action = 0) while defend_location does
    (continuing_defend_location_action = 1), so every other group reaches the
    mechanic with a live defender and zero attackers. Before the guard in
    locationAttackMechanic those groups still produced a verdict and a
    location_attack_logs row telling the owner they had been attacked, every turn
    they defended."""

    def test_a_defended_place_with_no_attacker_gets_no_verdict(self):
        # Both held turn one with one surviving defender, so both carry a
        # persisting defender and no attacker into turn two.
        for name in ("Foxtrot-Outpost", "Location A"):
            assert _state["location_ids"][name] not in _state["verdicts_morethan"], (
                f"{name} had only defenders on the second turn and must not be "
                "resolved as an attack"
            )

    def test_winning_the_fight_is_not_always_taking_the_place(self):
        # Location A holds on turn one, so `taken` must be false there. Civic-Site
        # falls, so its own `taken` tells whether a controller could hold the
        # spoils — the two attributes are not redundant.
        held = _state["verdicts"][_state["location_ids"]["Location A"]]
        assert held["verdict"] == "holds" and held["taken"] is False, (
            "a place that never fell cannot have been taken"
        )
        fell = _state["verdicts"][_state["location_ids"]["Civic-Site"]]
        assert fell["verdict"] == "falls", "Civic-Site should win its fight"
        assert isinstance(fell["taken"], bool), "taken must be scraped, not absent"

    def test_a_place_that_holds_is_never_taken(self):
        # The invariant that makes the two attributes coherent : taken implies falls.
        for lid, v in _state["verdicts"].items():
            if v["verdict"] == "holds":
                assert v["taken"] is False, (
                    f"location {lid} holds yet is marked taken: {v}"
                )

    def test_the_guard_did_not_silence_a_real_assault(self):
        # Pairs the negative above : without it, a mechanic that resolved nothing
        # at all would satisfy the assertion.
        assert _state["location_ids"]["Civic-Site"] in _state["verdicts_morethan"], (
            "Civic-Site kept its attackers and must still be resolved"
        )


class TestGroupIsolation:
    """Locations resolved in the same turn do not leak into each other."""

    def test_all_five_locations_were_resolved(self):
        expected = set(_state["location_ids"].values())
        assert set(_state["verdicts"]) == expected

    def test_each_row_pairs_workers_of_its_own_group(self):
        for name, (attackers, defenders) in GROUPS.items():
            allowed_attackers = {_wid(n) for n in attackers}
            allowed_defenders = {_wid(n) for n in defenders}
            for row in _rows_for(name):
                assert row["attacker_worker_id"] in allowed_attackers, (
                    f"{name} row {row['id']} has a foreign attacker"
                )
                assert row["defender_worker_id"] in allowed_defenders, (
                    f"{name} row {row['id']} has a foreign defender"
                )

    def test_an_agent_killed_earlier_in_the_turn_never_fights(self):
        # D17 : Chain_G queued attack_location but Claim_Def_1 killed it during
        # attackMechanic, so its action_choice no longer matches the grouping
        # filter and it produces no location duel.
        assert all(r["attacker_worker_id"] != _wid("Chain_G")
                   for r in _state["logs"])


class TestLocationFilter:
    """The Lieu filter of the admin combat log finally has data to work on."""

    def test_filter_dropdown_offers_the_resolved_locations(self, page: Page):
        ensure_gm_login(page, PHP_BASE_URL)
        options = ui_combat_filter_options(page, base_url=PHP_BASE_URL)
        assert options["has_location_filter"] is True
        offered = {int(v) for v in options["locations"]}
        assert set(_state["location_ids"].values()) <= offered

    def test_filtering_by_location_returns_only_that_location(self, page: Page):
        ensure_gm_login(page, PHP_BASE_URL)
        lid = _state["location_ids"]["Echo-Base"]
        rows = ui_combat_logs(page, base_url=PHP_BASE_URL, location_id=lid)
        assert rows, "the location filter returned nothing"
        assert {r["location_id"] for r in rows} == {lid}


class TestSaboteurExcluded:
    """A double agent sent against its secret master's location never arrives."""

    @pytest.fixture(scope="class", autouse=True)
    def saboteur_turn(self, browser):
        """Recruit a traitor whose secondary controller is Echo, order it against
        Echo-Base which Echo owns, and resolve one more turn.

        No scenario reload: this class appends a turn to the state the module
        fixture already built, so the earlier live-reading tests keep their
        data."""
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        try:
            ensure_gm_login(page, PHP_BASE_URL)
            charlie_id = ui_controller_id(page, "Charlie", base_url=PHP_BASE_URL)
            # Blank Slate + the go_traitor job give a zero-stat 3/3/3 agent whose
            # secondary controller_worker link points at Echo.
            worker_id = ui_recruit_perfect_worker(
                page, charlie_id, "Beta-Combat", "Saboteur_Echo",
                "Blank Slate", "Test_Job_GoTraitor_Echo",
                base_url=PHP_BASE_URL,
            )
            _sabotage["worker_id"] = worker_id

            echo_base_id = _state["location_ids"]["Echo-Base"]
            ui_attack_location(page, "Saboteur_Echo", echo_base_id,
                               base_url=PHP_BASE_URL)
            end_turn(page)

            _sabotage["logs"] = ui_combat_logs(page, base_url=PHP_BASE_URL)
            _sabotage["report"] = worker_report_html(page, "Saboteur_Echo",
                                                     base_url=PHP_BASE_URL)
            _sabotage["unreachable_text"] = ui_config_value(
                page, "textLocationUnreachable", base_url=PHP_BASE_URL)

            assert_no_collected_php_errors(page)
            yield
        finally:
            context.close()

    def test_the_saboteur_fought_nobody(self):
        wid = _sabotage["worker_id"]
        assert all(r["attacker_worker_id"] != wid for r in _sabotage["logs"]), (
            "a saboteur must not appear in any combat row"
        )

    def test_the_saboteur_was_told_it_could_not_reach_the_place(self):
        # textLocationUnreachable is a JSON pool picked with array_rand, like the
        # other randomised text keys, so any one of its entries is a pass.
        pool = json.loads(_sabotage["unreachable_text"])
        assert pool, "textLocationUnreachable is not seeded"
        assert any(text in _sabotage["report"] for text in pool), (
            f"expected one of {pool!r} in the saboteur's report"
        )
