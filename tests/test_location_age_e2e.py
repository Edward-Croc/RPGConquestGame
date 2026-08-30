"""Playwright E2E tests for how a location reports its age (issue #115).

`setup_turn` used to have a single writer (`moveBase`), so every place read as
maximally old from the first minute. This file covers the fix and what it feeds:
`createBase` and `updateLocation` now stamp the turn, `updateLocation` also raises
`is_updated_location`, and `locationSearchMechanic` turns the pair into a sentence
appended to every discovery report.

The sentence is built from two pools (`buildLocationAgeSentence`):
  state  — textLocationAgeOriginal / Ruined / Restored, chosen from
           is_updated_location combined with can_be_repaired
  age    — textLocationAgeLongAgo / ThisTurn / TurnsAgo, chosen from setup_turn

With the minimalData seeds and TestConfig's `timeValue = Tour` that renders as
"Ce <name> a été construit il y a des années." and its variants.

Reading the reports
  A worker's page accumulates its reports turn after turn, so a phrase written on
  turn 2 is still on the page at turn 5. Every assertion below is therefore
  monotone — it checks that a wording HAS appeared, never that an older one is
  gone. The one exception is the no-leak test, which asserts a name the searcher
  never reached is absent from its page entirely.

The scripted timeline the module fixture walks (TestConfig starts at turn 0):

  turn 0   Alpha builds a base in Alpha-Investigation.
           setup_turn = 0 and is_updated_location = 0, so the sentinel rule makes
           it scenery — a place built during setup has "always been there".
  EOT → 1  snapshot "t1"  (Charlie's Finder_1, enquete 7, sees Alpha-Investigation),
           "t1_desc_tier" / "t1_name_tier" (Finder_4 at 5 and Finder_5 at 4, either
           side of the description threshold on Location A's discovery_diff 4),
           and "diff_fresh", the base's recalculated discovery_diff while young
  turn 1   admin toggles Test-Future-Location → it swaps to
           Test-Future-Location-Ruined, can_be_repaired = 1 → in ruins.
  EOT → 2  snapshot "t2"  (Echo's Artefact_Searcher_Echo sees Theta-Artefacts)
  EOT → 3  snapshot "t3"  — same ruin, one turn older
  turn 3   admin toggles it back → restored, can_be_repaired = 0
  EOT → 4  snapshot "t4"
  turn 4   "diff_aged" is read first, still in the original zone, so it differs
           from diff_fresh by the age term alone. Then Alpha's base moves to
           Theta-Artefacts — a move resets the age but not
           the state, so it must still read "construit", now dated.
  EOT → 5  snapshot "t5"

Expected WARNING entries: none. WARNING entries do not fail a test anyway
(conftest only fails on new ERROR lines).

Run:
    python3 -m pytest tests/test_location_age_e2e.py -v
"""
import re

import pytest

from conftest import PHP_BASE_URL, ensure_gm_login

from helpers import (
    DB_AVAILABLE, clear_ui_caches, end_turn, get_db_connection, load_minimal_data,
    load_scenario_via_admin, register_php_error_listener, safe_goto,
    assert_no_collected_php_errors, ui_controller_id, ui_worker_controller_id,
    ui_worker_id, ui_zone_id,
)

RUINED_NAME = "Test-Future-Location-Ruined"

_state = {}


def _locations_table(page, base_url=PHP_BASE_URL):
    """Map location name -> {'id', 'discovery_diff'} off zones/management_locations.php.

    The page renders `<h3>NAME (discovery N)</h3>` followed by that location's
    forms, so the delete_id hidden input that comes next carries its id. Anchoring
    on delete_id rather than toggle_destruction matters: the destruction form is
    only rendered for places that carry an activate_json payload.
    """
    safe_goto(page, f"{base_url}/zones/management_locations.php")
    page.wait_for_load_state("load")
    html = page.content()
    found = {}
    for match in re.finditer(
        r'<h3>(?P<name>[^<]*?)\s*\(discovery\s*(?P<diff>-?\d+)\)</h3>'
        r'(?P<tail>.*?)(?=<h3>|\Z)',
        html, re.S,
    ):
        ident = re.search(r'name="delete_id"\s+value="(\d+)"', match.group('tail'))
        if ident:
            found[match.group('name').strip()] = {
                'id': int(ident.group(1)),
                'discovery_diff': int(match.group('diff')),
            }
    return found


def _toggle_destruction(page, location_id, base_url=PHP_BASE_URL):
    """Fire the admin destruction toggle, which runs updateLocation on that place.

    Each location card keeps its action forms in a `display:none` span that its
    "Actions" h5 toggles, so the h5 has to be clicked before the button is
    reachable — same pattern as the details/summary folds elsewhere in the suite.
    """
    safe_goto(page, f"{base_url}/zones/management_locations.php")
    page.wait_for_load_state("load")
    form = page.locator(
        f"form:has(input[name='toggle_destruction'][value='{location_id}'])"
    ).first
    form.locator("xpath=ancestor::span[1]/preceding-sibling::h5[1]").click()
    form.locator("button[type='submit']").click()
    page.wait_for_load_state("load")


def _worker_report_html(page, lastname, base_url=PHP_BASE_URL):
    """Open a worker's action page as its own controller and return the HTML."""
    ensure_gm_login(page, base_url)
    ctrl_id = ui_worker_controller_id(page, lastname, base_url=base_url)
    safe_goto(page, f"{base_url}/base/accueil.php?controller_id={ctrl_id}")
    page.wait_for_load_state("load")
    worker_id = ui_worker_id(page, lastname, base_url=base_url)
    safe_goto(page, f"{base_url}/workers/action.php?worker_id={worker_id}")
    page.wait_for_load_state("load")
    return page.content()


@pytest.fixture(scope="module", autouse=True)
def age_state(browser):
    """Walk the scripted timeline once and snapshot the reports along the way."""
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

        # Built before the first end of turn, so setup_turn lands on 0 and the
        # sentinel rule applies. Built before any controller is selected too, so
        # createBase's gm visibility bypass lets it through.
        alpha_cid = ui_controller_id(page, "Alpha", base_url=PHP_BASE_URL)
        alpha_zid = ui_zone_id(page, "Alpha-Investigation", base_url=PHP_BASE_URL)
        theta_zid = ui_zone_id(page, "Theta-Artefacts", base_url=PHP_BASE_URL)
        before = set(_locations_table(page))
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php"
                        f"?createBase=1&controller_id={alpha_cid}&zone_id={alpha_zid}")
        page.wait_for_load_state("load")

        created = set(_locations_table(page)) - before
        assert len(created) == 1, (
            f"createBase produced {created or 'nothing'} — most likely Alpha's Gold "
            "stock was insufficient. Half the tests below depend on this base."
        )
        base_name = created.pop()
        _state["base_name"] = base_name

        end_turn(page)                                                   # -> turn 1
        _state["t1"] = _worker_report_html(page, "Finder_1")
        # Two probes either side of the description threshold, on one location.
        _state["t1_desc_tier"] = _worker_report_html(page, "Finder_4")
        _state["t1_name_tier"] = _worker_report_html(page, "Finder_5")
        _state["diff_fresh"] = _locations_table(page)[base_name]['discovery_diff']

        ensure_gm_login(page, PHP_BASE_URL)
        future_id = _locations_table(page)["Test-Future-Location"]['id']
        _state["future_id"] = future_id
        _toggle_destruction(page, future_id)

        end_turn(page)                                                   # -> turn 2
        _state["t2"] = _worker_report_html(page, "Artefact_Searcher_Echo")

        ensure_gm_login(page, PHP_BASE_URL)
        end_turn(page)                                                   # -> turn 3
        _state["t3"] = _worker_report_html(page, "Artefact_Searcher_Echo")

        ensure_gm_login(page, PHP_BASE_URL)
        _toggle_destruction(page, future_id)
        end_turn(page)                                                   # -> turn 4
        _state["t4"] = _worker_report_html(page, "Artefact_Searcher_Echo")

        ensure_gm_login(page, PHP_BASE_URL)
        table = _locations_table(page)
        base_id = table[base_name]['id']
        # Read while the base is still in its original zone, so only the age term
        # separates this from diff_fresh.
        _state["diff_aged"] = table[base_name]['discovery_diff']
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php"
                        f"?moveBase=1&base_id={base_id}&controller_id={alpha_cid}"
                        f"&zone_id={theta_zid}")
        page.wait_for_load_state("load")
        end_turn(page)                                                   # -> turn 5
        _state["t5"] = _worker_report_html(page, "Artefact_Searcher_Echo")

        assert_no_collected_php_errors(page)
        yield
    finally:
        context.close()
        # Unconditional : this file builds, ruins, restores and moves locations.
        if DB_AVAILABLE:
            load_minimal_data()
        load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
        clear_ui_caches()


class TestScenery:
    """setup_turn 0 with no state change reads as immemorial."""

    def test_a_seeded_place_has_always_been_there(self):
        assert "Ce Location A a été construit il y a des années." in _state["t1"]

    def test_a_base_built_during_setup_is_scenery_too(self):
        # Built at turn 0, so the sentinel rule applies and it must NOT be dated.
        assert f"Ce {_state['base_name']} a été construit il y a des années." in _state["t1"], (
            "a base built at turn 0 must read as scenery, not as freshly built"
        )


class TestTierBoundary:
    """The sentence discloses state, so it starts at the description tier.

    Location A has discovery_diff 4 and both probes share its zone, so their
    enquete_val alone decides the tier: Finder_4 has 5 (difference 1, which clears
    LOCATIONINFORMATIONDIFF) and Finder_5 has 4 (difference 0, exactly
    LOCATIONNAMEDIFF). One location, one turn, one threshold between them.
    """

    def test_the_description_tier_gets_the_sentence(self):
        assert "Ce Location A a été construit il y a des années." in _state["t1_desc_tier"], (
            "a searcher clearing LOCATIONINFORMATIONDIFF must read the age sentence"
        )

    def test_the_name_tier_gets_the_name_but_not_the_sentence(self):
        # The first assertion is what stops this from passing vacuously: it proves
        # Finder_5 really did discover the place before we check what it was told.
        assert "Location A" in _state["t1_name_tier"], (
            "Finder_5 should still reach the name tier on Location A"
        )
        assert "Ce Location A a été" not in _state["t1_name_tier"], (
            "the name tier must not disclose the location's state"
        )

    def test_an_undiscovered_place_is_not_named(self):
        # Paired with its positive : without it, this passes when nothing is emitted.
        assert "a été construit" in _state["t1"], "Finder_1 should get age sentences"
        assert "Civic-Site" not in _state["t1"], (
            "the age sentence must not surface a place the searcher never found"
        )


class TestRuined:
    """A place swapped into ruins says so, and says when."""

    def test_the_ruin_declares_itself(self):
        assert f"Ce {RUINED_NAME} a été détruit par une attaque" in _state["t2"], (
            "updateLocation must flip the wording to the ruined pool"
        )

    def test_the_change_is_dated_to_its_own_turn(self):
        assert f"Ce {RUINED_NAME} a été détruit par une attaque ce Tour." in _state["t2"], (
            "a state change must be dated to the turn it happened, not to turn 0"
        )

    def test_the_admin_toggle_counts_as_a_state_change(self):
        # The ruined name only exists from the toggle onwards, so it can never have
        # carried an "original" sentence — which is exactly what is_updated_location
        # buys. Stated on the ruined name to stay monotone against the page's
        # accumulated older reports, and paired with its positive so it cannot pass
        # when no sentence is emitted at all.
        assert f"Ce {RUINED_NAME} a été détruit par une attaque" in _state["t2"]
        assert f"Ce {RUINED_NAME} a été construit" not in _state["t2"], (
            "a place that changed state must have left the 'original' pool"
        )


class TestAgeing:
    """The elapsed-turn clause counts up."""

    def test_one_turn_later_it_is_dated_in_turns(self):
        assert f"Ce {RUINED_NAME} a été détruit par une attaque il y a 1 Tour." in _state["t3"], (
            "the age clause must switch from 'ce Tour' to the elapsed-turn form"
        )

    def test_ageing_makes_a_base_harder_to_find(self):
        # The same age term feeds Defence and DiscoveryDiff. Both readings are of
        # one base in one zone, so every other component of the formula is held
        # constant and only the elapsed turns can move the number.
        # diff_fresh is recalculated at turncounter 0 (age 0, no bonus);
        # diff_aged at turncounter 3 against setup_turn 0, i.e. ceil(3 * 0.5) = 2.
        assert _state["diff_aged"] > _state["diff_fresh"], (
            "an ageing base must become easier to discover over time "
            f"(fresh {_state['diff_fresh']} -> aged {_state['diff_aged']})"
        )


class TestRestored:
    """Repairing a ruin moves it to the restored pool, not back to original."""

    def test_the_restored_place_declares_itself(self):
        assert "a été relevé de ses ruines" in _state["t4"], (
            "a place ruined then restored must use the restored pool"
        )

    def test_the_restore_is_dated_to_its_own_turn(self):
        # is_updated_location is one-way, so the restore reads from the restored
        # pool and carries the turn it happened on, never the immemorial clause.
        assert "Ce Test-Future-Location a été relevé de ses ruines ce Tour." in _state["t4"], (
            "the restore must use the restored pool and be dated to its own turn"
        )


class TestMove:
    """moveBase resets the age but not the state."""

    def test_a_moved_base_still_reads_as_built(self):
        assert f"Ce {_state['base_name']} a été construit" in _state["t5"], (
            "a move changes where the base is, not what happened to it"
        )

    def test_a_moved_base_is_no_longer_scenery(self):
        assert f"Ce {_state['base_name']} a été construit ce Tour." in _state["t5"], (
            "moveBase stamps setup_turn, so the base stops being immemorial"
        )



@pytest.mark.db
class TestSeedImport:
    """The column must survive the CSV importer, not just the schema."""

    def test_the_daiho_ji_is_seeded_already_ruined(self, browser):
        load_scenario_via_admin(browser, PHP_BASE_URL, "Japon1555CSV")
        clear_ui_caches()
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT is_updated_location FROM {prefix}locations "
                    "WHERE name LIKE %s".replace("{prefix}", _db_prefix()),
                    ("Ruines du Daih%",),
                )
                row = cur.fetchone()
        finally:
            conn.close()
        assert row, "the Japon1555 CSV should seed the ruined Daihō-ji"
        assert int(list(row.values())[0] if isinstance(row, dict) else row[0]) == 1, (
            "a place seeded already in ruins must import with is_updated_location = 1"
        )


def _db_prefix():
    from conftest import GAME_PREFIX
    return GAME_PREFIX
