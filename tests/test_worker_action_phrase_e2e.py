"""End-to-end coverage of the worker action phrase — issues #111 + #73.

Verifies the assembled phrase rendered by
`workers/functions.php:buildWorkerZoneActionPhrase` in TWO places :

  1. LIVE view (workers/view.php via workers/action.php) — 3rd-person,
     built from `$currentAction` for the current turn.
  2. ARCHIVED life_report (mechanics/endTurn.php) — 1st-person, written
     onto worker_actions.report during calculateValsReport (BEFORE
     locationAttackMechanic — so the phrase gets written regardless of
     the eventual combat resolution).

Coupled fixture : one module-scoped setup configures 5 workers with
distinct action_choices, snapshots their live phrase HTML, triggers
one EOT, then snapshots each worker's post-EOT page (which carries the
archived life_report). Tests read those pre-captured snapshots so no
navigation happens per test.

TODO : banner assertions (« qui est sous notre bannière » vs « qui est
sous la bannière des <lastname> ») skipped for v1 — TestConfig has no
worker in a claimed zone matching its own controller. Add via admin
zone claim + worker move once we have a claim/move helper.

Run :
    python3 -m pytest tests/test_worker_action_phrase_e2e.py -v
"""
import pytest
from playwright.sync_api import Page

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, end_turn, load_minimal_data, load_scenario_via_admin,
    register_php_error_listener, safe_goto, ui_controller_id,
    ui_workers_by_lastname, worker_report_html,
)


def _set_zone_claimer_via_ui(page, zone_name, claimer_lastname):
    """Admin UI path : set a zone's claimer_controller_id via
    zones/management_zones.php POST. UI-only shortcut, works on demo where
    direct DB access is not available. Used to fabricate the two banner
    cases (own vs enemy) without moving workers between turns.

    Pass claimer_lastname=None to clear the claim (« -- Aucun -- » option)."""
    safe_goto(page, f"{PHP_BASE_URL}/zones/management_zones.php")
    page.wait_for_load_state("load")
    target_row = None
    for row in page.locator("tr:has(form)").all():
        cells = row.locator("td")
        if cells.count() > 1 and cells.nth(1).inner_text().strip() == zone_name:
            target_row = row
            break
    if target_row is None:
        raise AssertionError(f"zone {zone_name!r} row not found on management_zones.php")
    if claimer_lastname is None:
        target_row.locator("select[name='claimer_id']").select_option(value="")
    else:
        target_row.locator("select[name='claimer_id']").select_option(label=(
            target_row.locator("select[name='claimer_id'] option").filter(
                has_text=claimer_lastname
            ).first.inner_text().strip()
        ))
    target_row.locator("button[type='submit']").click()
    page.wait_for_load_state("load")


# --- shared state captured by the fixture ---
_live_html: dict = {}
_post_eot_html: dict = {}


def _set_config_via_ui(page, name, value):
    safe_goto(page, f"{PHP_BASE_URL}/base/configuration.php")
    page.wait_for_load_state("load")
    for row in page.locator("tr:has(form)").all():
        if row.locator("td").nth(1).inner_text().strip() == name:
            row.locator("input[name='value']").fill(value)
            row.locator("input[name='update_config']").click()
            page.wait_for_load_state("load")
            return
    raise AssertionError(f"Config row {name!r} not found")


def _location_id_via_management(page, location_name):
    import re
    safe_goto(page, f"{PHP_BASE_URL}/zones/management_locations.php")
    page.wait_for_load_state("load")
    m = re.search(
        rf'<h3>[^<]*{re.escape(location_name)}[^<]*\(discovery[^<]+</h3>'
        rf'.*?name="toggle_destruction"\s+value="(\d+)"',
        page.content(), re.DOTALL,
    )
    if not m:
        raise AssertionError(f"location_id for '{location_name}' not found")
    return int(m.group(1))


def _seed_ckl_admin(page, controller_lastname, location_name):
    cid = ui_controller_id(page, controller_lastname, PHP_BASE_URL)
    location_id = _location_id_via_management(page, location_name)
    safe_goto(
        page,
        f"{PHP_BASE_URL}/controllers/management.php"
        f"?giftInformationLocation=1&target_controller_id={cid}&location_id={location_id}"
    )
    page.wait_for_load_state("load")
    return location_id


def _switch_controller(page, controller_lastname):
    cid = ui_controller_id(page, controller_lastname, PHP_BASE_URL)
    safe_goto(page, f"{PHP_BASE_URL}/base/accueil.php?controller_id={cid}&chosir=Choisir")
    page.wait_for_load_state("load")


@pytest.fixture(scope="module", autouse=True)
def phrase_snapshot(browser):
    """Setup + snapshot fixture. Runs once per module.

    Configures 5 workers, captures each's live phrase HTML, triggers
    one EOT, then captures each's post-EOT HTML.
    """
    global _live_html, _post_eot_html
    _live_html.clear()
    _post_eot_html.clear()

    if DB_AVAILABLE:
        load_minimal_data()
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")

    context = browser.new_context()
    page = context.new_page()
    register_php_error_listener(page)

    try:
        ensure_gm_login(page, PHP_BASE_URL)

        # 1. Enable agent_attack_defence mode for attack_location/defend_location
        _set_config_via_ui(page, "locationAttackMode", "agent_attack_defence")

        # 2. Seed CKL for Foxtrot → Echo-Base (so attack_location URL is accepted)
        echo_base_id = _seed_ckl_admin(page, "Foxtrot", "Echo-Base")
        foxtrot_outpost_id = _location_id_via_management(page, "Foxtrot-Outpost")

        # 3. Fabricate banner cases via admin UI (no worker moves needed)
        #    - Alpha-Investigation claimed by Alpha → Searcher_1 (Alpha's) sees « notre bannière »
        #    - Beta-Combat claimed by Alpha → Chain_B (Beta's) sees « bannière des Alpha »
        _set_zone_claimer_via_ui(page, "Alpha-Investigation", "Alpha")
        _set_zone_claimer_via_ui(page, "Beta-Combat", "Alpha")

        # 4. Configure each worker's action_choice for this turn.
        #    Chain_B (Beta) : keep passive (baseline verb)
        #    Searcher_1 (Alpha) : already 'investigate' via CSV
        #    Artefact_Worker_Foxtrot (Foxtrot) : attack_location Echo-Base
        #    Gift_Source_Foxtrot (Foxtrot) : defend_location Foxtrot-Outpost
        #
        # Note : worker-vs-worker attack phrase (« Attaque contre X ») is
        # already covered by test_agent_combat_e2e.py — skipped here to
        # avoid the detection-prerequisite setup.
        _switch_controller(page, "Foxtrot")
        awf = ui_workers_by_lastname(page, "Artefact_Worker_Foxtrot")[0]
        safe_goto(
            page,
            f"{PHP_BASE_URL}/workers/action.php?worker_id={awf['id']}"
            f"&attackLocation=1&target_location_id={echo_base_id}"
        )
        page.wait_for_load_state("load")

        gsf = ui_workers_by_lastname(page, "Gift_Source_Foxtrot")[0]
        safe_goto(
            page,
            f"{PHP_BASE_URL}/workers/action.php?worker_id={gsf['id']}"
            f"&defendLocation=1&target_location_id={foxtrot_outpost_id}"
        )
        page.wait_for_load_state("load")

        # 5. Snapshot LIVE HTML for each worker
        for name in ["Chain_B", "Searcher_1",
                     "Artefact_Worker_Foxtrot", "Gift_Source_Foxtrot"]:
            _live_html[name] = worker_report_html(page, name, base_url=PHP_BASE_URL)

        # 6. Trigger one EOT
        ensure_gm_login(page, PHP_BASE_URL)
        end_turn(page, base_url=PHP_BASE_URL)

        # 7. Snapshot POST-EOT HTML (life_report visible in worker view)
        #    Artefact_Searcher_Echo (Echo, investigate, same zone Theta-Artefacts
        #    as the 2 Foxtrot workers) is added here to cover its
        #    investigate_report — sub-step 5.A.I, issue #73.
        for name in ["Chain_B", "Searcher_1",
                     "Artefact_Worker_Foxtrot", "Gift_Source_Foxtrot",
                     "Artefact_Searcher_Echo"]:
            _post_eot_html[name] = worker_report_html(page, name, base_url=PHP_BASE_URL)

        yield
    finally:
        # Reset module-level config drift so any following test module
        # (or later pytest -k picking this ordering) starts clean. Runs
        # even if setup raises — ensure_scenario_loaded() skips reload
        # when _current_scenario is still 'TestConfig', so any leaked
        # mode/claimer would propagate silently.
        try:
            ensure_gm_login(page, PHP_BASE_URL)
            _set_config_via_ui(page, "locationAttackMode", "endTurn")
            _set_zone_claimer_via_ui(page, "Alpha-Investigation", None)
            _set_zone_claimer_via_ui(page, "Beta-Combat", None)
        except Exception:
            # best-effort teardown — never mask the original setup/test failure
            pass
        context.close()


class TestLivePhrase:
    """Assertions on the live view.php phrase — before any EOT."""

    def test_passive_verb_and_zone(self):
        """Chain_B is passive in Beta-Combat.
        Phrase must contain « Surveille » + « Beta-Combat »."""
        html = _live_html["Chain_B"]
        assert "Surveille" in html, "verb Surveille missing from Chain_B live view"
        assert "Beta-Combat" in html, "zone Beta-Combat missing from Chain_B live view"

    def test_enemy_banner(self):
        """Chain_B (Beta's worker) is in Beta-Combat which fixture-set claimer=Alpha.
        Phrase must contain « sous la bannière » + « Alpha »
        (NOT « notre bannière » — Beta ≠ Alpha)."""
        html = _live_html["Chain_B"]
        assert "sous la bannière" in html, (
            "« sous la bannière » missing on Chain_B (Beta) in zone claimed by Alpha"
        )
        assert "Alpha" in html, "enemy claimer lastname Alpha missing from banner clause"
        assert "sous notre bannière" not in html, (
            "Beta's worker in Alpha-claimed zone must NOT see « notre bannière »"
        )

    def test_investigate_verb(self):
        """Searcher_1 investigates in Alpha-Investigation.
        Phrase must contain « Enquête »."""
        html = _live_html["Searcher_1"]
        assert "Enquête" in html, "verb Enquête missing from Searcher_1 live view"
        assert "Alpha-Investigation" in html

    def test_own_banner(self):
        """Searcher_1 (Alpha's worker) is in Alpha-Investigation which fixture-set claimer=Alpha.
        Phrase must contain « sous notre bannière » (own controller matches claimer)."""
        html = _live_html["Searcher_1"]
        assert "sous notre bannière" in html, (
            "« sous notre bannière » missing on Searcher_1 (Alpha) in zone claimed by Alpha"
        )

    def test_attack_location_verb_and_target(self):
        """Artefact_Worker_Foxtrot attacks Echo-Base.
        Phrase must contain verb + target **anchored together** — bare
        « Echo-Base in html » would false-positive on the location <select>
        dropdown that view.php:168-259 renders unconditionally in
        agent_attack_defence mode. Anchor on the exact fragment emitted
        by buildWorkerActionInfo + buildWorkerZoneActionPhrase :
        `<strong>Attaque le lieu</strong> <strong>Echo-Base</strong>`."""
        html = _live_html["Artefact_Worker_Foxtrot"]
        assert "<strong>Attaque le lieu</strong>  <strong>Echo-Base</strong>" in html, (
            "verb+target anchor « Attaque le lieu … Echo-Base » missing — "
            "txt_ps_attack_location seed or buildWorkerActionInfo regression ?"
        )

    def test_defend_location_verb_and_target(self):
        """Gift_Source_Foxtrot defends Foxtrot-Outpost.
        Phrase must anchor verb + target together — see attack_location
        rationale (bare target substring false-positives on the dropdown)."""
        html = _live_html["Gift_Source_Foxtrot"]
        assert "<strong>Défend le lieu</strong>  <strong>Foxtrot-Outpost</strong>" in html, (
            "verb+target anchor « Défend le lieu … Foxtrot-Outpost » missing — "
            "txt_ps_defend_location seed or buildWorkerActionInfo regression ?"
        )


class TestEOTLifeReport:
    """Assertions on the archived life_report after EOT — 1st-person phrase."""

    def test_passive_1p(self):
        """Chain_B (passive) life_report must contain « Je surveille »
        (ucfirst — the phrase starts a new sentence after <br>)."""
        html = _post_eot_html["Chain_B"]
        assert "Je surveille" in html, (
            "1st-person « Je surveille » missing from Chain_B life_report — "
            "txt_ps_1p_passive config seed ?"
        )
        assert "Beta-Combat" in html

    def test_investigate_1p(self):
        """Searcher_1 (investigate) life_report must contain « J'enquête »."""
        html = _post_eot_html["Searcher_1"]
        assert ("J&#039;enquête" in html) or ("J'enquête" in html), (
            "1st-person « J'enquête » missing from Searcher_1 life_report"
        )

    def test_attack_location_1p_and_target(self):
        """Artefact_Worker_Foxtrot life_report must anchor « J'attaque le lieu » + « Echo-Base »
        (verb+target together — bare Echo-Base substring would false-positive on
        the location dropdown emitted alongside the report in view.php)."""
        html = _post_eot_html["Artefact_Worker_Foxtrot"]
        anchor = "<strong>J'attaque le lieu</strong>  <strong>Echo-Base</strong>"
        anchor_html = anchor.replace("'", "&#039;")
        assert (anchor in html) or (anchor_html in html), (
            "1st-person verb+target anchor « J'attaque le lieu … Echo-Base » missing — "
            "txt_ps_1p_attack_location seed or buildWorkerActionInfo regression ?"
        )

    def test_defend_location_1p_and_target(self):
        """Gift_Source_Foxtrot life_report must anchor « Je défends le lieu » + « Foxtrot-Outpost »."""
        html = _post_eot_html["Gift_Source_Foxtrot"]
        assert "<strong>Je défends le lieu</strong>  <strong>Foxtrot-Outpost</strong>" in html, (
            "1st-person verb+target anchor « Je défends le lieu … Foxtrot-Outpost » missing — "
            "txt_ps_1p_defend_location seed or buildWorkerActionInfo regression ?"
        )

    def test_archived_own_banner(self):
        """Searcher_1 life_report must contain « sous notre bannière »
        (same claimer rule applied on the archived phrase, viewer = owner)."""
        html = _post_eot_html["Searcher_1"]
        assert "sous notre bannière" in html, (
            "« sous notre bannière » missing from Searcher_1 archived life_report"
        )

    def test_archived_enemy_banner(self):
        """Chain_B life_report must contain « sous la bannière » + « Alpha »
        (enemy claimer resolved to lastname in archived phrase)."""
        html = _post_eot_html["Chain_B"]
        assert "sous la bannière" in html, (
            "« sous la bannière » missing from Chain_B archived life_report"
        )
        assert "Alpha" in html


class TestInvestigateReportTargetSuffix:
    """Sub-step 5.A.I, issue #73 : an investigator's report must name the
    location targeted by an attack_location / defend_location enemy worker,
    not just say « attaque le lieu » with no target.

    Artefact_Searcher_Echo (Echo, investigate) shares zone Theta-Artefacts
    with Artefact_Worker_Foxtrot (attack_location -> Echo-Base) and
    Gift_Source_Foxtrot (defend_location -> Foxtrot-Outpost). Both are
    detected on the same end-turn already triggered by the module fixture.

    TestConfig's textesDiff01Array is the debug key-value template
    (`action_ps - %4$s; action_inf - %5$s`), so the anchor below is exact
    and immune to the array_rand phrasing variants used by real scenarios.
    Anchoring on the `action_ps - `/`action_inf - ` label + verb + target
    together avoids the false-positive risk flagged in
    TestLivePhrase.test_attack_location_verb_and_target : Echo owns
    Echo-Base, so its own action.php page also renders a bare
    « Echo-Base » <option> in the defend-location dropdown."""

    def test_investigate_report_names_attack_location_target(self):
        """Echo's investigate report must contain the attacker's verb + Echo-Base."""
        html = _post_eot_html["Artefact_Searcher_Echo"]
        assert "action_ps - attaque le lieu Echo-Base" in html, (
            "investigate report missing verb+target anchor for attack_location "
            "on Echo-Base — investigateMechanic.php attack_location/defend_location "
            "branch regression ?"
        )
        assert "action_inf - attaquer le lieu Echo-Base" in html, (
            "investigate report missing infinitive verb+target anchor for "
            "attack_location on Echo-Base"
        )

    def test_investigate_report_names_defend_location_target(self):
        """Echo's investigate report must contain the defender's verb + Foxtrot-Outpost."""
        html = _post_eot_html["Artefact_Searcher_Echo"]
        assert "action_ps - défend le lieu Foxtrot-Outpost" in html, (
            "investigate report missing verb+target anchor for defend_location "
            "on Foxtrot-Outpost — investigateMechanic.php attack_location/defend_location "
            "branch regression ?"
        )
        assert "action_inf - défendre le lieu Foxtrot-Outpost" in html, (
            "investigate report missing infinitive verb+target anchor for "
            "defend_location on Foxtrot-Outpost"
        )
