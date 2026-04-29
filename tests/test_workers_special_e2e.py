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
    ui_worker_id, ui_workers_by_lastname,
    ui_attack, ui_claim, ui_gift, end_turn,
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

        Locks in the UI surface that the URL-only `ui_gift` driver
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
        passive) + trace (Foxtrot, trace)."""
        ui_gift(gm_page, "Gift_Source_Foxtrot", "Echo", base_url=base_url)
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
