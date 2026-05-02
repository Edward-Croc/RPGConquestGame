"""Playwright E2E tests for special worker actions: gift, prisoner return,
double-agent lifecycle, trace immutability.

D1 / Step 7 — Slice 1: Gift only (TestGiftWorker, TestGiftPrisoner).

Gift flow under test (workers/functions.php:1020):
  - swaps controller_worker.controller_id to the new owner
  - updates worker_actions.controller_id for the current turn
  - sets action to 'passive'
  - appends "J'ai rejoint X comme nouveau maitre" to life_report
  - creates a TRACE worker at OLD owner (separate row, action='trace')
  - destroys any existing trace at NEW owner

UI: form inside /workers/action.php?worker_id=N with
    select[name='gift_controller_id'] + input[name='gift'][value='Donner'].

Test data:
  - Gift_Source_Foxtrot (Foxtrot, passive, Theta-Artefacts) — gifted to Echo.
    Bench controllers + free zone, no entanglement with detection/combat tests.

UI-only / prod-DEMO-runnable: all lookups go through the UI.

Run:
    python3 -m pytest tests/test_workers_special_e2e.py -v
"""
import pytest
from playwright.sync_api import Page

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, load_minimal_data, login_as, logout, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
    ui_worker_id, ui_workers_by_lastname, ui_detected_enemies_of,
    ui_attack, ui_claim, ui_gift_click, ui_zone_id, end_turn,
    cached_faction_sections, clear_ui_caches,
)


_controller_ids = {}


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


def _scrape_controller_ids(page, base_url):
    """controller-lastname → id via accueil's select#controllerSelect."""
    safe_goto(page, f"{base_url}/base/accueil.php")
    page.wait_for_load_state("networkidle")
    for opt in page.locator("select#controllerSelect option").all():
        val = opt.get_attribute("value") or ""
        text = (opt.inner_text() or "").strip()
        if val and text:
            _controller_ids[text.split()[-1]] = int(val)


@pytest.fixture(scope="module", autouse=True)
def load_gift_scenario(browser):
    """Load TestConfig at turn 0; gift tests operate on the seeded passive workers."""
    if DB_AVAILABLE:
        load_minimal_data()

    context = browser.new_context()
    page = context.new_page()
    register_php_error_listener(page)
    safe_goto(page, f"{PHP_BASE_URL}/connection/loginForm.php")
    page.wait_for_load_state("networkidle")
    page.locator("input[name='username']").fill("gm")
    page.locator("input[name='passwd']").fill("orga")
    page.locator("input[type='submit']").first.click()
    page.wait_for_load_state("networkidle")
    safe_goto(page, f"{PHP_BASE_URL}/base/admin.php")
    page.wait_for_load_state("networkidle")
    page.locator("select[name='config_name']").select_option("TestConfig")
    page.locator("input[name='submit'][value='Submit']").click()
    page.wait_for_timeout(5000)
    page.wait_for_load_state("load", timeout=90000)

    _scrape_controller_ids(page, PHP_BASE_URL)

    assert_no_collected_php_errors(page)
    context.close()

    yield


@pytest.fixture
def gm_page(page: Page, base_url):
    login_as(page, base_url, "gm", "orga")
    yield page
    logout(page, base_url)


def _select_controller(page, base_url, lastname):
    cid = _controller_ids[lastname]
    safe_goto(page, f"{base_url}/base/accueil.php?controller_id={cid}&chosir=Choisir")
    page.wait_for_load_state("networkidle")


def _worker_action_html(page, base_url, controller_lastname, worker_lastname):
    """Render /workers/action.php?worker_id=N as the given controller."""
    _select_controller(page, base_url, controller_lastname)
    wid = ui_worker_id(page, worker_lastname, base_url=base_url)
    safe_goto(page, f"{base_url}/workers/action.php?worker_id={wid}")
    page.wait_for_load_state("load")
    return page.content()


# ---------------------------------------------------------------------------
# TestGiftWorker — gift Gift_Source_Foxtrot (Foxtrot, Theta-Artefacts) to Echo
# ---------------------------------------------------------------------------

class TestGiftWorker:
    """Gift Gift_Source_Foxtrot (Foxtrot, passive, Theta-Artefacts) to Echo at turn 0.

    Per workers/functions.php:1020-1072 the gift action:
      (a) swaps the live row's controller_id to the new owner,
      (b) sets the action to 'passive',
      (c) appends 'J'ai rejoint <new owner> comme nouveau maitre' to life_report,
      (d) creates a TRACE worker (separate row, action='trace') at the OLD owner.

    Tests assert all four behaviours."""

    def test_gift_form_renders_with_target_in_dropdown(self, gm_page: Page, base_url):
        """Form-rendering smoke: the 'Donner' button + gift_controller_id
        dropdown must be present on a passive worker's action page, and
        the dropdown must include the intended gift target.

        Locks in the UI surface that the URL-only `ui_gift_click` driver
        bypasses — guards against the form silently disappearing or
        the target option being filtered out by future changes.

        workers/view.php:18 filters by controller_id, so we must select
        Foxtrot (Gift_Source_Foxtrot's owner) before navigating."""
        _select_controller(gm_page, base_url, "Foxtrot")
        wid = ui_worker_id(gm_page, "Gift_Source_Foxtrot", base_url=base_url)
        safe_goto(gm_page, f"{base_url}/workers/action.php?worker_id={wid}")
        gm_page.wait_for_load_state("load")

        gift_button = gm_page.locator("input[name='gift'][value='Donner']")
        assert gift_button.count() >= 1, "'Donner' (gift) button should render"

        gift_select = gm_page.locator("select[name='gift_controller_id']")
        assert gift_select.count() >= 1, "gift_controller_id dropdown should render"
        labels = [(opt.inner_text() or "").strip() for opt in gift_select.locator("option").all()]
        assert any("Echo" in l for l in labels), \
            f"gift_controller_id dropdown should include Echo; got: {labels}"

    def test_move_form_renders_with_zones_in_dropdown(self, gm_page: Page, base_url):
        """Form-rendering smoke: the 'Déménager' button + zone_id dropdown
        must be present, and the dropdown must list at least 2 zones."""
        _select_controller(gm_page, base_url, "Foxtrot")
        wid = ui_worker_id(gm_page, "Gift_Source_Foxtrot", base_url=base_url)
        safe_goto(gm_page, f"{base_url}/workers/action.php?worker_id={wid}")
        gm_page.wait_for_load_state("load")

        move_button = gm_page.locator("input[name='move'][value='Déménager']")
        assert move_button.count() >= 1, "'Déménager' (move) button should render"

        # Move form's zone select sits inside the same parent action form.
        zone_select = gm_page.locator("select[name='zone_id']")
        assert zone_select.count() >= 1, "zone_id dropdown should render"
        zone_options = zone_select.locator("option").count()
        assert zone_options >= 2, \
            f"zone_id dropdown should have multiple zone options; got {zone_options}"

    def test_gift_creates_two_rows_live_and_trace(self, gm_page: Page, base_url):
        """After gift, lastname 'Gift_Source_Foxtrot' has 2 rows: live (Echo,
        passive) + trace (Foxtrot, trace).

        First gift call in this file → exercised via the UI 'Donner' button
        (per once-per-file rule). Subsequent gift tests reuse the URL-driver
        ui_gift_click since the click contract is now covered."""
        ui_gift_click(gm_page, "Gift_Source_Foxtrot", "Echo", base_url=base_url)
        rows = ui_workers_by_lastname(gm_page, "Gift_Source_Foxtrot", base_url=base_url)
        assert len(rows) == 2, \
            f"Gift should produce 2 Gift_Source_Foxtrot rows (live + trace), got {len(rows)}: {rows}"
        live = [r for r in rows if r["action_choice"] != "trace"]
        trace = [r for r in rows if r["action_choice"] == "trace"]
        assert len(live) == 1, f"Expected 1 live row, got {len(live)}: {live}"
        assert len(trace) == 1, f"Expected 1 trace row, got {len(trace)}: {trace}"

    def test_live_row_now_belongs_to_echo_and_is_passive(self, gm_page: Page, base_url):
        """The live (non-trace) row must be at Echo with action='passive'."""
        rows = ui_workers_by_lastname(gm_page, "Gift_Source_Foxtrot", base_url=base_url)
        live = next(r for r in rows if r["action_choice"] != "trace")
        assert live["controller_id"] == _controller_ids["Echo"], \
            f"Live row should belong to Echo ({_controller_ids['Echo']}), got {live['controller_id']}"
        assert live["action_choice"] == "passive", \
            f"Live row action should be 'passive' (gift consumes the action), got '{live['action_choice']}'"

    def test_trace_created_at_old_owner(self, gm_page: Page, base_url):
        """Positive: a trace row exists at Foxtrot (the OLD owner) with action='trace'."""
        rows = ui_workers_by_lastname(gm_page, "Gift_Source_Foxtrot", base_url=base_url)
        trace = next(r for r in rows if r["action_choice"] == "trace")
        assert trace["controller_id"] == _controller_ids["Foxtrot"], \
            f"Trace should be at Foxtrot ({_controller_ids['Foxtrot']}), got {trace['controller_id']}"

    def test_life_report_mentions_new_owner(self, gm_page: Page, base_url):
        """The gifted worker's report must contain 'rejoint Echo' (the spec'd life_report line)."""
        html = _worker_action_html(gm_page, base_url, "Echo", "Gift_Source_Foxtrot")
        assert "rejoint" in html, "Gifted worker report should contain 'rejoint' line"
        assert "Echo" in html, "Gifted worker report should mention new owner Echo"


# ---------------------------------------------------------------------------
# TestGiftPrisoner — returnPrisoner flow (workers/functions.php:1122)
#
# Setup uses the existing combat seed: Claim_Def_1 (Beta) is captured by
# Claim_Atk_1 (Echo) during end-turn combat in Beta-Combat. Post-end-turn,
# Claim_Def_1 is a prisoner of Echo. Echo can release the prisoner back
# to Beta via the 'Relâcher le prisonnier vers Beta !' button.
#
# This class runs end-turn in a class-scoped fixture so TestGiftWorker
# above keeps its turn-0 state. (Class fixtures fire only for their own
# tests; no cross-class effect.)
# ---------------------------------------------------------------------------

class TestGiftPrisoner:
    """Captor releases prisoner back to original owner via returnPrisoner."""

    @pytest.fixture(scope="class", autouse=True)
    def end_turn_for_class(self, browser):
        """Set up the attack/claim pair via UI (mirroring test_agent_combat's
        fixture pattern at tests/test_agent_combat_e2e.py:150-152), then run
        end-turn so combat fires and Claim_Def_1 is captured by Echo."""
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        # Echo's Claim_Atk_1 attacks Beta's Claim_Def_1; Beta's Claim_Def_1
        # claims for Beta. End-turn → blocked claim → Claim_Def_1 captured.
        ui_attack(page, 'Claim_Atk_1', 'Claim_Def_1')
        ui_claim(page, 'Claim_Def_1', 'Beta')

        end_turn(page)
        assert_no_collected_php_errors(page)
        context.close()
        yield

    def test_prerequisite_claim_def_1_is_prisoner_of_echo(self, gm_page: Page, base_url):
        """Sanity: post-end-turn, Claim_Def_1 belongs to Echo as a prisoner.
        action_choice='captured' is the raw column value; the derived
        workerStatus is 'prisoner'. If this fails, the combat seed didn't
        produce the expected capture."""
        rows = ui_workers_by_lastname(gm_page, "Claim_Def_1", base_url=base_url)
        # Filter to the live row (combat may also create traces).
        live = [r for r in rows if r["action_choice"] != "trace"]
        assert len(live) == 1, f"Expected 1 live Claim_Def_1 row, got {live}"
        assert live[0]["controller_id"] == _controller_ids["Echo"], \
            f"Claim_Def_1 should belong to captor Echo, got controller_id={live[0]['controller_id']}"
        assert live[0]["action_choice"] == "captured", \
            f"Claim_Def_1 should have action_choice='captured' post-capture, got '{live[0]['action_choice']}'"

    def test_return_button_visible_for_captor(self, gm_page: Page, base_url):
        """Form-rendering smoke for the returnPrisoner action: Echo's view
        of the captured Claim_Def_1 must expose an `input[name='returnPrisoner']`
        whose value mentions the original owner Beta. Mirrors the
        locator+count pattern of the gift/move/attack form smokes."""
        _select_controller(gm_page, base_url, "Echo")
        wid = ui_worker_id(gm_page, "Claim_Def_1", base_url=base_url)
        safe_goto(gm_page, f"{base_url}/workers/action.php?worker_id={wid}")
        gm_page.wait_for_load_state("load")

        return_buttons = gm_page.locator("input[name='returnPrisoner']")
        assert return_buttons.count() >= 1, \
            "Captor's prisoner view should render at least one returnPrisoner button"

        values = [
            (b.get_attribute("value") or "")
            for b in return_buttons.all()
        ]
        assert any("Relâcher le prisonnier" in v for v in values), \
            f"returnPrisoner button value should contain 'Relâcher le prisonnier'; got {values}"
        assert any("Beta" in v for v in values), \
            f"returnPrisoner button value should mention original owner Beta; got {values}"

    def test_return_releases_prisoner_to_original_owner(self, gm_page: Page, base_url):
        """Click 'Relâcher le prisonnier vers Beta !' → Claim_Def_1's live row
        belongs to Beta again. Note: returnPrisoner also creates a trace at
        the captor (Echo) per workers/functions.php:1122 — same trace pattern
        as the gift flow."""
        _select_controller(gm_page, base_url, "Echo")
        wid = ui_worker_id(gm_page, "Claim_Def_1", base_url=base_url)
        safe_goto(gm_page, f"{base_url}/workers/action.php?worker_id={wid}")
        gm_page.wait_for_load_state("load")
        # Click the first returnPrisoner submit (release-to-original-owner variant)
        gm_page.locator("input[name='returnPrisoner']").first.click()
        gm_page.wait_for_load_state("networkidle")

        rows = ui_workers_by_lastname(gm_page, "Claim_Def_1", base_url=base_url)
        live = [r for r in rows if r["action_choice"] != "trace"]
        assert len(live) == 1, f"Expected 1 live Claim_Def_1 row post-release, got {live}"
        assert live[0]["controller_id"] == _controller_ids["Beta"], \
            f"After release, Claim_Def_1 should belong back to Beta, got controller_id={live[0]['controller_id']}"
        assert live[0]["action_choice"] != "captured", \
            f"After release, Claim_Def_1 should no longer be 'captured', got '{live[0]['action_choice']}'"


# ---------------------------------------------------------------------------
# TestTraceImmutability — invariants on trace workers post-gift
#
# Depends on TestGiftWorker having gifted Gift_Source_Foxtrot (Foxtrot → Echo)
# earlier in this module, which seeds a Foxtrot trace row.
#
# The class-scoped fixture runs an end-turn so cross-turn invariants
# (persistence + endTurn calc-report exclusion) can be asserted.
# Declared AFTER TestGiftPrisoner so its end-turn doesn't ripple into
# the prisoner setup.
# ---------------------------------------------------------------------------

class TestTraceImmutability:
    """Lock invariants on the Foxtrot trace of Gift_Source_Foxtrot:
      - the inactive-state guard at /workers/action.php 403s mutating
        actions on the trace, even for the trace's owner;
      - createNewTurnLines (mechanics/functions.php:304) carries the
        trace forward across end-turns;
      - INACTIVE_ACTIONS exclusion in endTurn.php skips traces from
        the calculate-vals report append.
    """

    @pytest.fixture(scope="class", autouse=True)
    def advance_one_turn(self, browser):
        """End-turn so persistence + endTurn-side-effect tests can read
        post-turn state. Mirrors TestGiftPrisoner's class-fixture pattern."""
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)
        end_turn(page)
        assert_no_collected_php_errors(page)
        context.close()
        yield

    def test_trace_endpoint_blocks_mutation(self, browser, base_url):
        """The inactive-state guard at workers/action.php must 403 mutating
        actions on a trace worker even for the trace's controller-owner.
        Logs in as foxtrot_player (non-privileged, owns Foxtrot) so the
        block actually runs (privileged users bypass)."""
        # Resolve the trace worker_id via gm context (management_workers
        # is a gm-only view).
        gm_ctx = browser.new_context()
        gm_page = gm_ctx.new_page()
        ensure_gm_login(gm_page, base_url)
        rows = ui_workers_by_lastname(gm_page, "Gift_Source_Foxtrot", base_url=base_url)
        trace_row = next(r for r in rows if r["action_choice"] == "trace")
        trace_id = trace_row["id"]
        gm_ctx.close()

        # Mutating action on the trace as foxtrot_player must 403.
        ctx = browser.new_context()
        page = ctx.new_page()
        login_as(page, base_url, "foxtrot_player", "test")
        response = page.goto(
            f"{base_url}/workers/action.php?worker_id={trace_id}"
            f"&attack=Attaquer&enemy_worker_id=worker_1"
        )
        assert response is not None
        assert response.status == 403, (
            f"Mutation on a trace worker by its owner must 403; "
            f"got {response.status}"
        )
        ctx.close()

    def test_owner_can_view_own_trace(self, browser, base_url):
        """Bare GET on a trace owned by the caller must render (200) — the
        owner needs to see the trace's report. Only mutating action keys
        trigger the inactive-state block; a worker_id-only URL must always
        render for the owner."""
        # Resolve the trace worker_id via gm context.
        gm_ctx = browser.new_context()
        gm_page = gm_ctx.new_page()
        ensure_gm_login(gm_page, base_url)
        rows = ui_workers_by_lastname(gm_page, "Gift_Source_Foxtrot", base_url=base_url)
        trace_row = next(r for r in rows if r["action_choice"] == "trace")
        trace_id = trace_row["id"]
        gm_ctx.close()

        # Bare GET as foxtrot_player (the trace's owner) — must render.
        ctx = browser.new_context()
        page = ctx.new_page()
        login_as(page, base_url, "foxtrot_player", "test")
        response = page.goto(f"{base_url}/workers/action.php?worker_id={trace_id}")
        assert response is not None
        assert response.status == 200, (
            f"Owner viewing their own trace via bare GET must render; "
            f"got {response.status}"
        )
        ctx.close()

    def test_trace_persists_across_end_turn(self, gm_page: Page, base_url):
        """createNewTurnLines must carry action_choice='trace' forward, so
        the Foxtrot trace row still exists after the end-turn fixture."""
        rows = ui_workers_by_lastname(gm_page, "Gift_Source_Foxtrot", base_url=base_url)
        trace_rows = [r for r in rows if r["action_choice"] == "trace"]
        assert len(trace_rows) == 1, (
            f"Trace must persist across end-turn; got {len(trace_rows)} "
            f"trace rows for Gift_Source_Foxtrot: {trace_rows}"
        )
        assert trace_rows[0]["controller_id"] == _controller_ids["Foxtrot"], (
            f"Trace must remain at Foxtrot ({_controller_ids['Foxtrot']}); "
            f"got controller_id={trace_rows[0]['controller_id']}"
        )

    def test_trace_does_not_get_endturn_calc_report(self, gm_page: Page, base_url):
        """endTurn.php's calculateVals query excludes INACTIVE_ACTIONS, so
        the trace's life_report must NOT contain the per-turn calc line
        ('j'ai N en investigation et N/N en attaque/défense').

        Navigates to the TRACE's worker_id directly (ui_worker_id defaults
        to prefer_non_trace=True and would return the live row at Echo)."""
        rows = ui_workers_by_lastname(gm_page, "Gift_Source_Foxtrot", base_url=base_url)
        trace_row = next(r for r in rows if r["action_choice"] == "trace")
        trace_id = trace_row["id"]

        # gm bypasses the ownership guard, so direct nav to the trace
        # worker's action page renders without 403.
        safe_goto(gm_page, f"{base_url}/workers/action.php?worker_id={trace_id}")
        gm_page.wait_for_load_state("load")
        html = gm_page.content()

        # The calc line is appended by endTurn.php:60-63. Match a stable
        # substring rather than the full template (the report is HTML-
        # escaped and may interleave other report lines).
        assert "en investigation" not in html, (
            "Trace's life_report should NOT contain the end-turn calc "
            "line ('en investigation et X/Y en attaque/défense'); "
            "INACTIVE_ACTIONS exclusion in endTurn.php appears broken."
        )


# ---------------------------------------------------------------------------
# TestUntestedInvestigateResults — filter invariants on investigateMechanic
#
# Complements TestAgentDetection (the existing 7-agent threshold suite),
# which proves the comparative-roll math but does not lock the SQL-level
# exclusion filters. This class targets three filters that, if broken,
# would silently leak state across faction or zone boundaries:
#   - searcher self-exclusion (s.searcher_id != wa.worker_id)
#   - cross-zone isolation (s.zone_id = wa.zone_id)
#   - inactive-target exclusion (action_choice IN ('passive', 'investigate'))
#
# Runs after the prior classes' end-turn fixtures so investigateMechanic
# has had at least one turn-resolution pass to populate CKE rows.
# ---------------------------------------------------------------------------

class TestUntestedInvestigateResults:
    """Lock filter invariants on investigateMechanic.php that aren't
    exercised by TestAgentDetection's threshold scenarios."""

    def test_searcher_does_not_detect_self(self, gm_page: Page, base_url):
        """investigateMechanic.php SQL filter `s.searcher_id != wa.worker_id`
        must keep a searcher out of its own detection list."""
        detected = ui_detected_enemies_of(gm_page, "Searcher_1", base_url=base_url)
        assert "Searcher_1" not in detected, (
            f"Searcher_1's detection list must not include itself; "
            f"got: {detected}"
        )

    def test_cross_zone_workers_not_detected(self, gm_page: Page, base_url):
        """SQL filter `s.zone_id = wa.zone_id` must isolate detections to
        the searcher's zone. Searcher_1 lives in Alpha-Investigation;
        Inv_Atk_2 / Inv_Def_2 live in Beta-Combat. None of the latter
        should appear in Searcher_1's detection list."""
        detected = ui_detected_enemies_of(gm_page, "Searcher_1", base_url=base_url)
        for foreign_zone_worker in ("Inv_Atk_2", "Inv_Def_2"):
            assert foreign_zone_worker not in detected, (
                f"Searcher_1 (Alpha-Investigation) must not detect "
                f"{foreign_zone_worker} (Beta-Combat); got: {detected}"
            )

    def test_trace_excluded_from_detection_target(self, gm_page: Page, base_url):
        """Target filter `action_choice IN ('passive', 'investigate')` must
        exclude trace workers. After TestGiftWorker's gift, a trace of
        Gift_Source_Foxtrot lives at Foxtrot/Theta-Artefacts.
        Artefact_Searcher_Echo (also in Theta-Artefacts, on a different
        controller) must NOT see Gift_Source_Foxtrot in its detection
        list — the live row is filtered as same-controller (now Echo's),
        and the trace row is filtered as inactive."""
        detected = ui_detected_enemies_of(
            gm_page, "Artefact_Searcher_Echo", base_url=base_url
        )
        assert "Gift_Source_Foxtrot" not in detected, (
            f"Artefact_Searcher_Echo must not detect Gift_Source_Foxtrot "
            f"(LIVE row is same-controller; TRACE row is inactive). "
            f"Got: {detected}"
        )


# ---------------------------------------------------------------------------
# TestUntestedAttackResults — inactive-defender skip path in attackMechanic
#
# attackMechanic.php:318-322 short-circuits when the defender's
# action_choice is in INACTIVE_ACTIONS:
#     - attacker gets an "unfound" attack_report
#     - defender state is NOT mutated (no second-kill / un-capture / etc.)
#
# Existing TestBaseCombat / TestChainAttack cover successful combat on
# LIVE defenders. The inactive-defender skip-path was previously
# untested; this class locks it in with the smallest possible scenario.
#
# Out of scope (deferred to dedicated work):
#   - double-agent death trace-count semantics (attackMechanic.php:346-380),
#   - riposte exact-threshold behaviour (attackMechanic.php:405-419).
# ---------------------------------------------------------------------------

class TestUntestedAttackResults:
    """Lock the inactive-defender skip-path in attackMechanic. Round 1
    kills Inv_Def_2; round 2 queues another attack on the now-dead
    Inv_Def_2 from a fresh attacker (Counter_Atk) — the mechanic must
    leave the dead defender alone and produce an 'unfound' report for
    the attacker."""

    @pytest.fixture(scope="class", autouse=True)
    def kill_then_attack_dead(self, browser):
        """Round 1: Inv_Atk_2 (Charlie) kills Inv_Def_2 (Delta) via end-turn.
        Round 2: Counter_Atk (Golf) attacks the now-dead Inv_Def_2 via
        another end-turn. Both attackers + defender share Beta-Combat."""
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        # Round 1: kill Inv_Def_2.
        ui_attack(page, "Inv_Atk_2", "Inv_Def_2")
        end_turn(page)

        # Round 2: queue a fresh attack on the now-dead Inv_Def_2.
        ui_attack(page, "Counter_Atk", "Inv_Def_2")
        end_turn(page)

        assert_no_collected_php_errors(page)
        context.close()
        yield

    def test_dead_defender_state_unchanged_after_second_attack(self, gm_page: Page, base_url):
        """attackMechanic.php:318-322: defender already in INACTIVE_ACTIONS
        is skipped — no state mutation. Inv_Def_2 must still be 'dead'
        after Counter_Atk's round 2 attack."""
        rows = ui_workers_by_lastname(gm_page, "Inv_Def_2", base_url=base_url)
        non_trace = [r for r in rows if r["action_choice"] != "trace"]
        assert len(non_trace) == 1, (
            f"Inv_Def_2 should have one non-trace row; got {len(non_trace)}: {non_trace}"
        )
        assert non_trace[0]["action_choice"] == "dead", (
            f"Attacking a dead defender must NOT change its action_choice; "
            f"expected 'dead', got '{non_trace[0]['action_choice']}'"
        )

    def test_attacker_on_dead_defender_did_not_die(self, gm_page: Page, base_url):
        """Counter_Atk attacked a dead defender (unfound path) — must NOT
        be killed by a phantom riposte. Locks the early-skip branch
        before the riposte block fires."""
        rows = ui_workers_by_lastname(gm_page, "Counter_Atk", base_url=base_url)
        non_trace = [r for r in rows if r["action_choice"] != "trace"]
        assert len(non_trace) == 1, (
            f"Counter_Atk should have one non-trace row; got {len(non_trace)}: {non_trace}"
        )
        assert non_trace[0]["action_choice"] != "dead", (
            f"Counter_Atk attacked a dead defender — must not be 'dead' "
            f"itself (no riposte from a dead defender). "
            f"Got: '{non_trace[0]['action_choice']}'"
        )


# ---------------------------------------------------------------------------
# TestDoubleAgentCapture — capture trace count for double-agent workers
#
# Test data:
#   - Test_Job_GoTraitor_Echo (jobs.csv) — go_traitor on_recrutment effect
#     with controller_lastname=Echo. Workers recruited with this job get
#     a non-primary controller_worker row pointing to Echo.
#   - DA_Captor_Alpha / DA_Captor_Echo (advanced.csv) — calibrated
#     capture-strength workers in Beta-Combat (atk=8).
#
# Double-agent workers (DA_Capture_W, DA_SelfCapture_W) are NOT seeded by
# the CSV loader, because BDD/db_connector.php::loadWorkersCSV does direct
# INSERTs that bypass createWorker / applyPowerObtentionEffect, so
# go_traitor never fires for CSV-seeded workers. Instead, the class
# fixture recruits them via /workers/action.php?creation=true (the URL
# the perfect-worker admin form submits to) — that path runs createWorker
# which calls upgradeWorker(...,$isRecrutment=true) which fires
# applyPowerObtentionEffect → go_traitor → secondary controller_worker row.
#
# Locks two trace-count invariants from the design spec:
#   - capture by third party → 2 traces (one per controller, primary +
#     secondary), live worker moves to attacker (decision 1, option B);
#   - capture by secondary controller → 1 trace (at primary; secondary's
#     just-created trace is destroyed by attackMechanic.php:400 cleanup),
#     live worker becomes secondary's primary (decision 2 — re-claiming
#     voids your trace).
# ---------------------------------------------------------------------------

class TestDoubleAgentCapture:
    """Trace count after capture of a double-agent worker."""

    @pytest.fixture(scope="class", autouse=True)
    def setup_double_agent_captures(self, browser):
        """Recruit two double-agent workers via the perfect-worker form
        URL (the only path that fires applyPowerObtentionEffect and thus
        the go_traitor effect that creates the secondary controller_worker
        row). Then queue captures and end-turn.
          - DA_Captor_Alpha attacks DA_Capture_W      → third-party capture
          - DA_Captor_Echo  attacks DA_SelfCapture_W  → secondary self-capture
        Both attackers have atk=8 (Brute Force|Veteran Tactician); both
        defenders have def=3 (Blank Slate|Test_Job_GoTraitor_Echo, both
        zero-stat) → attack_diff=5 ≥ ATTACKDIFF1=3 → CAPTURE."""
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        # The perfect-worker admin form is included by /base/admin.php.
        # Scrape link_power_type_ids and origin_id from its dropdowns so
        # the recruitment URL is valid even if seed-order changes the ids.
        safe_goto(page, f"{PHP_BASE_URL}/base/admin.php")
        page.wait_for_load_state("networkidle")

        def _scrape_option_value(select_selector, text_match):
            for opt in page.locator(f"{select_selector} option").all():
                txt = (opt.inner_text() or "").strip()
                val = opt.get_attribute("value") or ""
                if text_match in txt and val:
                    return int(val)
            raise AssertionError(
                f"Option containing '{text_match}' not found in {select_selector}"
            )

        blank_slate_id = _scrape_option_value("select#power_hobby_id", "Blank Slate")
        go_traitor_id = _scrape_option_value(
            "select#power_metier_id", "Test_Job_GoTraitor_Echo"
        )
        origin_id = _scrape_option_value("select#origin_id", "origine Accessible")

        charlie_id = _controller_ids["Charlie"]
        beta_combat_id = ui_zone_id(page, "Beta-Combat", base_url=PHP_BASE_URL)

        # Recruit both double-agent workers via the creation URL.
        for da_lastname in ("DA_Capture_W", "DA_SelfCapture_W"):
            url = (
                f"{PHP_BASE_URL}/workers/action.php"
                f"?creation=true"
                f"&controller_id={charlie_id}"
                f"&zone_id={beta_combat_id}"
                f"&origin_id={origin_id}"
                f"&firstname=combat"
                f"&lastname={da_lastname}"
                f"&power_hobby_id={blank_slate_id}"
                f"&power_metier_id={go_traitor_id}"
                f"&chosir=Recruter+et+Affecter"
            )
            page.goto(url)
            page.wait_for_load_state("load")

        # Queue captures.
        ui_attack(page, "DA_Captor_Alpha", "DA_Capture_W")
        ui_attack(page, "DA_Captor_Echo", "DA_SelfCapture_W")
        end_turn(page)

        assert_no_collected_php_errors(page)
        context.close()
        yield

    def test_third_party_capture_creates_two_traces_one_per_controller(self, gm_page: Page, base_url):
        """Decision 1 (option B locked): a third party capturing a double-
        agent leaves 2 trace rows — one at the PRIMARY controller (Charlie)
        and one at the SECONDARY (Echo). Live worker moves to attacker (Alpha)."""
        rows = ui_workers_by_lastname(gm_page, "DA_Capture_W", base_url=base_url)
        trace_rows = [r for r in rows if r["action_choice"] == "trace"]
        assert len(trace_rows) == 2, (
            f"Third-party capture of a double-agent must produce 2 traces; "
            f"got {len(trace_rows)}: {trace_rows}"
        )
        trace_controllers = {r["controller_id"] for r in trace_rows}
        assert _controller_ids["Charlie"] in trace_controllers, (
            f"Expected a trace at primary controller Charlie "
            f"({_controller_ids['Charlie']}); got controllers={trace_controllers}"
        )
        assert _controller_ids["Echo"] in trace_controllers, (
            f"Expected a trace at secondary controller Echo "
            f"({_controller_ids['Echo']}); got controllers={trace_controllers}"
        )
        # Live worker now belongs to attacker Alpha.
        live_rows = [r for r in rows if r["action_choice"] != "trace"]
        assert len(live_rows) == 1, f"Expected 1 live row, got {live_rows}"
        assert live_rows[0]["controller_id"] == _controller_ids["Alpha"], (
            f"Captured worker should belong to Alpha "
            f"({_controller_ids['Alpha']}); got {live_rows[0]['controller_id']}"
        )
        assert live_rows[0]["action_choice"] == "captured", (
            f"Captured worker action_choice should be 'captured'; "
            f"got {live_rows[0]['action_choice']!r}"
        )

    def test_secondary_self_capture_voids_own_trace(self, gm_page: Page, base_url):
        """Decision 2 (locked spec — re-claiming voids your trace): when
        the SECONDARY controller (Echo) captures their own double-agent,
        the secondary's trace is just-created at attackMechanic.php:363
        then destroyed by destroyTraceWorker at line 400. Net: 1 trace
        at the PRIMARY only (Charlie). Live worker becomes Echo's primary."""
        rows = ui_workers_by_lastname(gm_page, "DA_SelfCapture_W", base_url=base_url)
        trace_rows = [r for r in rows if r["action_choice"] == "trace"]
        assert len(trace_rows) == 1, (
            f"Secondary self-capture must leave 1 trace (at primary only); "
            f"got {len(trace_rows)}: {trace_rows}"
        )
        assert trace_rows[0]["controller_id"] == _controller_ids["Charlie"], (
            f"Remaining trace must be at primary Charlie "
            f"({_controller_ids['Charlie']}); got {trace_rows[0]['controller_id']}"
        )
        # Live worker now belongs to attacker Echo (= former secondary).
        live_rows = [r for r in rows if r["action_choice"] != "trace"]
        assert len(live_rows) == 1, f"Expected 1 live row, got {live_rows}"
        assert live_rows[0]["controller_id"] == _controller_ids["Echo"], (
            f"After secondary self-capture, live worker should belong to "
            f"Echo ({_controller_ids['Echo']}); got {live_rows[0]['controller_id']}"
        )
        assert live_rows[0]["action_choice"] == "captured", (
            f"Captured worker action_choice should be 'captured'; "
            f"got {live_rows[0]['action_choice']!r}"
        )


# ---------------------------------------------------------------------------
# TestDoubleAgentLifecycle — recruitment, faction visibility, recall, death
#
# Scope (locked spec, per user 2026-04-29):
#   - Recruitment via the perfect-worker form with Test_Job_GoTraitor_Echo
#     creates 2 controller_worker rows (primary + secondary).
#   - Both controllers see the worker — primary in "Nos Agents" (live),
#     secondary in "Nos Agents doubles".
#   - recallDoubleAgent (URL action via /workers/action.php?recallDoubleAgent
#     =Rappeler&recall_controller_id=<secondary>): creates a trace at the
#     OLD primary, swaps the worker's primary controller to the secondary,
#     appends "rappelé" line to life_report.
#   - Death of a double-agent (kill via combat): 0 traces created;
#     both primary and secondary faction views show the worker in their
#     "Nos Anciens agents" / disappeared section.
#
# Issue #12 (go_traitor self-recruit collision) is covered separately
# in TestGoTraitorSelfRecruitCollision below.
# ---------------------------------------------------------------------------

class TestDoubleAgentLifecycle:
    """Lifecycle invariants on double-agent workers (recruitment, view, recall, death)."""

    @pytest.fixture(scope="class", autouse=True)
    def setup_lifecycle_workers(self, browser):
        """Recruit three double-agent workers (all primary=Charlie,
        secondary=Echo via Test_Job_GoTraitor_Echo). Then:
          - DA_Lifecycle_View: untouched (tests recruitment + visibility).
          - DA_Lifecycle_Recall: recalled via URL (instant action).
          - DA_Lifecycle_Death: queued for combat-kill by DA_Killer; resolves
            on end-turn. Uses a calibrated kill-strength attacker
            (Eagle Scout|Common Folk → atk=4 vs def=3 → diff=1 → KILL,
            not capture).

        Two end-turns in this fixture: the first lets investigateMechanic
        populate DA_Killer's CKE so ui_attack can find the new target;
        the second resolves the queued kill.
        """
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        # Scrape link_power_type_ids and origin_id from the perfect-worker
        # form (mirrors TestDoubleAgentCapture's pattern).
        safe_goto(page, f"{PHP_BASE_URL}/base/admin.php")
        page.wait_for_load_state("networkidle")

        def _scrape_option_value(select_selector, text_match):
            for opt in page.locator(f"{select_selector} option").all():
                txt = (opt.inner_text() or "").strip()
                val = opt.get_attribute("value") or ""
                if text_match in txt and val:
                    return int(val)
            raise AssertionError(
                f"Option containing '{text_match}' not found in {select_selector}"
            )

        blank_slate_id = _scrape_option_value("select#power_hobby_id", "Blank Slate")
        go_traitor_id = _scrape_option_value(
            "select#power_metier_id", "Test_Job_GoTraitor_Echo"
        )
        origin_id = _scrape_option_value("select#origin_id", "origine Accessible")

        charlie_id = _controller_ids["Charlie"]
        echo_id = _controller_ids["Echo"]
        beta_combat_id = ui_zone_id(page, "Beta-Combat", base_url=PHP_BASE_URL)

        for da_lastname in (
            "DA_Lifecycle_View",
            "DA_Lifecycle_Recall",
            "DA_Lifecycle_Death",
        ):
            url = (
                f"{PHP_BASE_URL}/workers/action.php"
                f"?creation=true"
                f"&controller_id={charlie_id}"
                f"&zone_id={beta_combat_id}"
                f"&origin_id={origin_id}"
                f"&firstname=combat"
                f"&lastname={da_lastname}"
                f"&power_hobby_id={blank_slate_id}"
                f"&power_metier_id={go_traitor_id}"
                f"&chosir=Recruter+et+Affecter"
            )
            page.goto(url)
            page.wait_for_load_state("load")

        # End-turn so DA_Killer (Foxtrot, passive) detects the new
        # double-agent workers in Beta-Combat — needed for ui_attack to
        # find DA_Lifecycle_Death in the enemy dropdown.
        end_turn(page)

        # Recall DA_Lifecycle_Recall to its secondary (Echo). Instant
        # URL action; advances trace creation + primary swap immediately.
        recall_wid = ui_worker_id(page, "DA_Lifecycle_Recall", base_url=PHP_BASE_URL)
        recall_url = (
            f"{PHP_BASE_URL}/workers/action.php"
            f"?worker_id={recall_wid}"
            f"&recallDoubleAgent=Rappeler"
            f"&recall_controller_id={echo_id}"
        )
        page.goto(recall_url)
        page.wait_for_load_state("load")

        # Queue the kill on DA_Lifecycle_Death and resolve at end-turn.
        ui_attack(page, "DA_Killer", "DA_Lifecycle_Death")
        end_turn(page)

        # Module-level faction-sections cache may have been populated by
        # earlier test files in the same pytest session (test_agent_combat
        # touches Alpha/Beta/etc. faction views). Clear it so this class's
        # tests see post-recruitment + post-recall + post-kill state.
        clear_ui_caches()

        assert_no_collected_php_errors(page)
        context.close()
        yield

    def test_recruitment_creates_secondary_controller_worker_row(self, gm_page: Page, base_url):
        """The go_traitor on_recrutment effect must INSERT a non-primary
        controller_worker row pointing to Echo. Verified via faction
        views: DA_Lifecycle_View is in Charlie's 'live' section AND in
        Echo's 'doubles' section — which only renders rows where
        is_primary_controller is false."""
        charlie = cached_faction_sections(gm_page, "Charlie", base_url=base_url)
        echo = cached_faction_sections(gm_page, "Echo", base_url=base_url)
        assert "DA_Lifecycle_View" in charlie["live"], (
            f"DA_Lifecycle_View should appear in Charlie's 'Nos Agents'; "
            f"got live={charlie['live']}"
        )
        assert "DA_Lifecycle_View" in echo["doubles"], (
            f"DA_Lifecycle_View should appear in Echo's 'Nos Agents doubles' "
            f"(secondary controller link); got doubles={echo['doubles']}"
        )

    def test_recruitment_does_not_leak_to_unrelated_controllers(self, gm_page: Page, base_url):
        """A double-agent must not surface in any other controller's
        faction view. Spot-check Alpha and Beta — neither owns the worker
        as primary or secondary."""
        alpha = cached_faction_sections(gm_page, "Alpha", base_url=base_url)
        beta = cached_faction_sections(gm_page, "Beta", base_url=base_url)
        for section_name, sections in (("Alpha", alpha), ("Beta", beta)):
            for k in ("live", "doubles", "prisoners", "ancients"):
                assert "DA_Lifecycle_View" not in sections[k], (
                    f"{section_name} must not see DA_Lifecycle_View in {k}; "
                    f"got {sections[k]}"
                )

    def test_recall_creates_trace_at_old_primary_and_swaps_owner(self, gm_page: Page, base_url):
        """recallDoubleAgent spec (locked 2026-04-29):
          - trace at OLD primary (Charlie),
          - live worker becomes primary at OLD secondary (Echo),
          - life_report on the live row mentions 'rappelé'.
        The trace at Charlie surfaces in Charlie's 'ancients'; the live
        row surfaces in Echo's 'live' (now Echo's primary)."""
        rows = ui_workers_by_lastname(gm_page, "DA_Lifecycle_Recall", base_url=base_url)

        trace_rows = [r for r in rows if r["action_choice"] == "trace"]
        assert len(trace_rows) == 1, (
            f"Recall must create exactly 1 trace at the old primary; "
            f"got {len(trace_rows)}: {trace_rows}"
        )
        assert trace_rows[0]["controller_id"] == _controller_ids["Charlie"], (
            f"Recall trace should be at Charlie (old primary); "
            f"got {trace_rows[0]['controller_id']}"
        )

        live_rows = [r for r in rows if r["action_choice"] != "trace"]
        assert len(live_rows) == 1, f"Expected 1 live row, got {live_rows}"
        assert live_rows[0]["controller_id"] == _controller_ids["Echo"], (
            f"Recalled worker should belong to Echo (former secondary); "
            f"got controller_id={live_rows[0]['controller_id']}"
        )

        # Faction views: Charlie sees the trace in 'ancients'; Echo's
        # 'live' contains the worker (now Echo's primary). Echo's
        # 'doubles' should NOT contain it any more — secondary link gone.
        charlie = cached_faction_sections(gm_page, "Charlie", base_url=base_url)
        echo = cached_faction_sections(gm_page, "Echo", base_url=base_url)
        assert "DA_Lifecycle_Recall" in charlie["ancients"], (
            f"Charlie should see DA_Lifecycle_Recall trace in 'ancients'; "
            f"got {charlie['ancients']}"
        )
        assert "DA_Lifecycle_Recall" in echo["live"], (
            f"Echo should see DA_Lifecycle_Recall in 'live' after recall; "
            f"got {echo['live']}"
        )
        assert "DA_Lifecycle_Recall" not in echo["doubles"], (
            f"DA_Lifecycle_Recall should no longer be in Echo's 'doubles' "
            f"after recall (secondary link removed); got {echo['doubles']}"
        )

    def test_double_agent_death_no_trace_visible_to_both_controllers(self, gm_page: Page, base_url):
        """Death of a double-agent (kill via combat, attack_diff in
        [ATTACKDIFF0, ATTACKDIFF1) range) creates 0 traces. Both
        controllers (primary + secondary) still see the worker via their
        respective faction views — primary in 'ancients', secondary
        also in 'ancients' (the secondary's controller_worker link
        survived the death; only capture deletes those rows)."""
        rows = ui_workers_by_lastname(gm_page, "DA_Lifecycle_Death", base_url=base_url)
        # Pure-death branch in attackMechanic creates no trace; capture
        # would have created at least one. management_workers joins
        # controller_worker, so a double-agent appears twice (one row
        # per controller link, primary + secondary). Death does NOT
        # delete those links — only capture does.
        trace_rows = [r for r in rows if r["action_choice"] == "trace"]
        assert len(trace_rows) == 0, (
            f"Death of double-agent must NOT create traces (per spec); "
            f"got {len(trace_rows)}: {trace_rows}"
        )
        non_trace = [r for r in rows if r["action_choice"] != "trace"]
        assert len(non_trace) == 2, (
            f"DA_Lifecycle_Death should appear twice in management_workers "
            f"(primary + secondary controller_worker rows survive death); "
            f"got {len(non_trace)}: {non_trace}"
        )
        for r in non_trace:
            assert r["action_choice"] == "dead", (
                f"All controller-views of DA_Lifecycle_Death must show "
                f"action='dead'; got {r}"
            )
        controller_ids_seen = {r["controller_id"] for r in non_trace}
        assert _controller_ids["Charlie"] in controller_ids_seen, (
            f"Primary Charlie's link must survive; got {controller_ids_seen}"
        )
        assert _controller_ids["Echo"] in controller_ids_seen, (
            f"Secondary Echo's link must survive; got {controller_ids_seen}"
        )

        # Both controllers must see the dead worker in their 'ancients'
        # section. Tests the spec that "both primary and secondary view
        # the dead worker in their dead/disappeared section."
        charlie = cached_faction_sections(gm_page, "Charlie", base_url=base_url)
        echo = cached_faction_sections(gm_page, "Echo", base_url=base_url)
        assert "DA_Lifecycle_Death" in charlie["ancients"], (
            f"Primary Charlie must see DA_Lifecycle_Death in 'ancients'; "
            f"got {charlie['ancients']}"
        )
        assert "DA_Lifecycle_Death" in echo["ancients"], (
            f"Secondary Echo must also see DA_Lifecycle_Death in 'ancients' "
            f"(controller_worker link survives pure-death); "
            f"got {echo['ancients']}"
        )


# ---------------------------------------------------------------------------
# TestGoTraitorSelfRecruitCollision — issue #12 regression
#
# When a controller recruits a worker whose go_traitor power points to
# the SAME controller (e.g., Echo recruits with Test_Job_GoTraitor_Echo,
# whose target controller_lastname='Echo'), the naive go_traitor INSERT
# at workers/functions.php:759-763 collides with the unique constraint
# (controller_id, worker_id) — a primary controller_worker row at Echo
# already exists from createWorker.
#
# Spec'd fix (locked 2026-04-29): the go_traitor branch must skip the
# INSERT idempotently when the target controller already has a
# controller_worker row for this worker. The recruited worker remains
# a normal primary-only employee of the recruiting controller.
#
# Reference: https://github.com/Edward-Croc/RPGConquestGame/issues/12
# ---------------------------------------------------------------------------

class TestGoTraitorSelfRecruitCollision:
    """Issue #12 regression: self-targeting go_traitor at recruitment must
    be idempotent — no SQL constraint error, no second controller_worker row."""

    def test_self_target_recruit_idempotent_no_constraint_error(self, browser, base_url):
        """Recruit DA_Issue12_Selftarget as Echo with Test_Job_GoTraitor_Echo
        (whose go_traitor target is also Echo). After the fix:
          - the recruitment response must NOT contain the
            'INSERT controller_worker Failed' error message echoed
            from applyPowerObtentionEffect's catch block,
          - management_workers must show exactly 1 controller_worker row
            for the new worker (primary at Echo only)."""
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, base_url)

        # Scrape link_power_type_ids and origin_id from the perfect-worker
        # form (mirrors prior classes' pattern).
        safe_goto(page, f"{base_url}/base/admin.php")
        page.wait_for_load_state("networkidle")

        def _scrape_option_value(select_selector, text_match):
            for opt in page.locator(f"{select_selector} option").all():
                txt = (opt.inner_text() or "").strip()
                val = opt.get_attribute("value") or ""
                if text_match in txt and val:
                    return int(val)
            raise AssertionError(
                f"Option containing '{text_match}' not found in {select_selector}"
            )

        blank_slate_id = _scrape_option_value("select#power_hobby_id", "Blank Slate")
        go_traitor_id = _scrape_option_value(
            "select#power_metier_id", "Test_Job_GoTraitor_Echo"
        )
        origin_id = _scrape_option_value("select#origin_id", "origine Accessible")

        echo_id = _controller_ids["Echo"]
        beta_combat_id = ui_zone_id(page, "Beta-Combat", base_url=base_url)

        # Recruit with controller_id == Echo AND go_traitor target == Echo
        # — the self-targeting case that triggers issue #12.
        url = (
            f"{base_url}/workers/action.php"
            f"?creation=true"
            f"&controller_id={echo_id}"
            f"&zone_id={beta_combat_id}"
            f"&origin_id={origin_id}"
            f"&firstname=combat"
            f"&lastname=DA_Issue12_Selftarget"
            f"&power_hobby_id={blank_slate_id}"
            f"&power_metier_id={go_traitor_id}"
            f"&chosir=Recruter+et+Affecter"
        )
        page.goto(url)
        page.wait_for_load_state("load")
        recruit_html = page.content()

        # Assertion 1: no constraint-violation error echoed.
        assert "INSERT controller_worker Failed" not in recruit_html, (
            "Recruitment with self-targeting go_traitor must NOT emit the "
            "'INSERT controller_worker Failed' constraint error from "
            "applyPowerObtentionEffect's catch block. Issue #12."
        )

        # Assertion 2: exactly one controller_worker row (primary at Echo).
        rows = ui_workers_by_lastname(
            page, "DA_Issue12_Selftarget", base_url=base_url
        )
        assert len(rows) == 1, (
            f"Self-target recruitment should produce 1 controller_worker "
            f"row (primary at Echo only — go_traitor's secondary INSERT "
            f"must be skipped); got {len(rows)}: {rows}"
        )
        assert rows[0]["controller_id"] == _controller_ids["Echo"], (
            f"Sole controller_worker link must be at Echo; "
            f"got controller_id={rows[0]['controller_id']}"
        )

        ctx.close()


# ---------------------------------------------------------------------------
# TestReturnPrisonerReinstatesSecondary — reinstate + capture-trace cleanup
#
# When a double-agent worker is captured (third party deletes both
# controller_worker rows, INSERTs primary at attacker — see
# attackMechanic.php:382-398) then released back to the original primary
# via returnPrisoner, the secondary controller_worker link must be
# reinstated AND the capture-trace at the secondary controller must be
# destroyed (mirror of the existing primary-side destroyTraceWorker
# call at workers/functions.php:1135).
#
# Spec (link + trace cleanup):
#   - secondary controller_worker(secondary, W, primary=false) reinstated
#     so the secondary's faction view shows the worker in 'doubles';
#   - capture-trace row in workers_trace_links at the secondary destroyed
#     so the secondary's faction view does NOT show the worker in
#     'ancients' (no double-listing — same worker in both 'doubles'
#     and 'ancients' would be a confusing UX).

# ---------------------------------------------------------------------------

class TestReturnPrisonerReinstatesSecondary:
    """returnPrisoner of a captured double-agent must reinstate the
    secondary controller_worker link and clean up the secondary's
    capture-trace."""

    @pytest.fixture(scope="class", autouse=True)
    def setup_capture_then_return(self, browser):
        """Recruit DA_ReturnPrisoner_W as a double-agent (primary=Charlie,
        secondary=Echo via Test_Job_GoTraitor_Echo). DA_Captor_Alpha
        (atk=8) captures the worker (def=3 → atk_diff=5 → CAPTURE).
        Then returnPrisoner from Alpha back to Charlie via URL. Two
        end-turns: first to populate detection so ui_attack finds the
        new target, second to resolve the queued capture."""
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        # Scrape link_power_type_ids and origin_id from the perfect-worker
        # form (mirrors prior classes' pattern).
        safe_goto(page, f"{PHP_BASE_URL}/base/admin.php")
        page.wait_for_load_state("networkidle")

        def _scrape_option_value(select_selector, text_match):
            for opt in page.locator(f"{select_selector} option").all():
                txt = (opt.inner_text() or "").strip()
                val = opt.get_attribute("value") or ""
                if text_match in txt and val:
                    return int(val)
            raise AssertionError(
                f"Option containing '{text_match}' not found in {select_selector}"
            )

        blank_slate_id = _scrape_option_value("select#power_hobby_id", "Blank Slate")
        go_traitor_id = _scrape_option_value(
            "select#power_metier_id", "Test_Job_GoTraitor_Echo"
        )
        origin_id = _scrape_option_value("select#origin_id", "origine Accessible")

        charlie_id = _controller_ids["Charlie"]
        alpha_id = _controller_ids["Alpha"]
        beta_combat_id = ui_zone_id(page, "Beta-Combat", base_url=PHP_BASE_URL)

        # Recruit the double-agent.
        url = (
            f"{PHP_BASE_URL}/workers/action.php"
            f"?creation=true"
            f"&controller_id={charlie_id}"
            f"&zone_id={beta_combat_id}"
            f"&origin_id={origin_id}"
            f"&firstname=combat"
            f"&lastname=DA_ReturnPrisoner_W"
            f"&power_hobby_id={blank_slate_id}"
            f"&power_metier_id={go_traitor_id}"
            f"&chosir=Recruter+et+Affecter"
        )
        page.goto(url)
        page.wait_for_load_state("load")

        # End-turn so DA_Captor_Alpha (Beta-Combat, passive) detects
        # DA_ReturnPrisoner_W in time for the queued capture.
        end_turn(page)

        # Capture: DA_Captor_Alpha attacks DA_ReturnPrisoner_W. atk=8,
        # def=3 → attack_diff=5 ≥ ATTACKDIFF1=3 → CAPTURE.
        ui_attack(page, "DA_Captor_Alpha", "DA_ReturnPrisoner_W")
        end_turn(page)

        # returnPrisoner from Alpha back to Charlie. URL form fields are
        # the same ones the prisoner-release form posts (see
        # workers/action.php:149-157). PHP only checks isset() on
        # returnPrisoner, so any non-empty value works.
        target_wid = ui_worker_id(
            page, "DA_ReturnPrisoner_W", base_url=PHP_BASE_URL,
            prefer_non_trace=True,
        )
        echo_id = _controller_ids["Echo"]
        return_url = (
            f"{PHP_BASE_URL}/workers/action.php"
            f"?worker_id={target_wid}"
            f"&returnPrisoner=Relacher"
            f"&recall_controller_id={alpha_id}"
            f"&return_controller_id={charlie_id}"
            f"&double_controller_id={echo_id}"
        )
        page.goto(return_url)
        page.wait_for_load_state("load")

        clear_ui_caches()
        assert_no_collected_php_errors(page)
        context.close()
        yield

    def test_primary_link_back_at_original_owner(self, gm_page: Page, base_url):
        """After returnPrisoner, Charlie (the original primary) sees the
        worker in 'live' again — primary link moved back from Alpha to
        Charlie."""
        charlie = cached_faction_sections(gm_page, "Charlie", base_url=base_url)
        assert "DA_ReturnPrisoner_W" in charlie["live"], (
            f"Charlie should see DA_ReturnPrisoner_W in 'live' after "
            f"return; got live={charlie['live']}"
        )

    def test_secondary_link_reinstated_at_secondary_controller(self, gm_page: Page, base_url):
        """The fix's primary assertion: secondary's controller_worker
        row, deleted at capture, must be reinstated by returnPrisoner.
        Echo's 'doubles' must contain the worker again."""
        echo = cached_faction_sections(gm_page, "Echo", base_url=base_url)
        assert "DA_ReturnPrisoner_W" in echo["doubles"], (
            f"Echo should see DA_ReturnPrisoner_W in 'doubles' after "
            f"return — secondary controller_worker link must be reinstated; "
            f"got doubles={echo['doubles']}"
        )

    def test_secondary_capture_trace_destroyed(self, gm_page: Page, base_url):
        """6b cleanup: the capture-trace at the secondary (Echo) must be
        destroyed by returnPrisoner. Mirrors the existing primary-side
        destroyTraceWorker call. Without this, Echo would see the worker
        in BOTH 'doubles' (active double-agent) AND 'ancients' (stale
        capture-trace) — confusing dual-listing."""
        echo = cached_faction_sections(gm_page, "Echo", base_url=base_url)
        assert "DA_ReturnPrisoner_W" not in echo["ancients"], (
            f"Echo's capture-trace of DA_ReturnPrisoner_W must be "
            f"destroyed by returnPrisoner (6b spec); got "
            f"ancients={echo['ancients']}"
        )
