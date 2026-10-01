"""Playwright E2E tests for the mass-action extension on /workers/massAction.php.

Issue #65 — adds mass_investigate / mass_passive / mass_hide alongside the
existing mass_move button on workers/viewAll.php. The dispatcher loops the
checked worker_ids[] and calls activateWorker() per worker with the matching
action_choice. Ownership guard is shared across all four mass actions.

Subjects: Beta's three combat-row workers (Chain_B, Inv_Def_1, Keep_Def) are
re-used across the happy-path classes — each class re-clicks the mass form
with a different action and asserts the post-state action_choice. Order
within the file is alphabetical (Hide → Investigate → Passive) so each class
overwrites the previous class's setting; this is the same chained-state
pattern the existing mass-move test relies on.

UI-only / prod-DEMO-runnable.

Run:
    python3 -m pytest tests/test_workers_mass_actions_e2e.py -v
"""
import pytest
from playwright.sync_api import Page

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, load_minimal_data, ensure_scenario_loaded, login_as, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
    ui_worker_id, ui_all_workers,
    ui_mass_hide_click, ui_mass_investigate_click, ui_mass_passive_click,
    ui_mass_claim_click, ui_worker_action_state, ui_controller_id,
    set_config_via_ui, ui_config_value,
)


_MASS_WORKERS = ["Chain_B", "Inv_Def_1", "Keep_Def"]


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def setup_testconfig(browser):
    """Load TestConfig once per module (skipped if already loaded)."""
    if DB_AVAILABLE:
        load_minimal_data()
    ensure_scenario_loaded(browser, PHP_BASE_URL, "TestConfig")
    yield


def _capture_action_choices(page):
    return {w["lastname"]: w["action_choice"]
            for w in ui_all_workers(page)
            if w["lastname"] in _MASS_WORKERS}


class TestMassHide:
    """Mass-hide the 3 Beta combat workers in a single submit;
    massAction.php loops worker_ids[] and calls activateWorker(_, 'hide')."""

    @pytest.fixture(scope="class", autouse=True)
    def mass_hide_state(self, browser):
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        ui_mass_hide_click(page, "Beta", _MASS_WORKERS)

        post = _capture_action_choices(page)

        assert_no_collected_php_errors(page)
        context.close()
        type(self)._post = post
        yield

    def test_chain_b_action_is_hide(self):
        assert self._post["Chain_B"] == "hide", (
            f"Chain_B action_choice should be 'hide' after mass-hide; "
            f"got {self._post['Chain_B']}"
        )

    def test_inv_def_1_action_is_hide(self):
        assert self._post["Inv_Def_1"] == "hide", (
            f"Inv_Def_1 action_choice should be 'hide' after mass-hide; "
            f"got {self._post['Inv_Def_1']}"
        )

    def test_keep_def_action_is_hide(self):
        assert self._post["Keep_Def"] == "hide", (
            f"Keep_Def action_choice should be 'hide' after mass-hide; "
            f"got {self._post['Keep_Def']}"
        )


class TestMassInvestigate:
    """Mass-investigate the 3 Beta combat workers."""

    @pytest.fixture(scope="class", autouse=True)
    def mass_investigate_state(self, browser):
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        ui_mass_investigate_click(page, "Beta", _MASS_WORKERS)

        post = _capture_action_choices(page)

        assert_no_collected_php_errors(page)
        context.close()
        type(self)._post = post
        yield

    def test_chain_b_action_is_investigate(self):
        assert self._post["Chain_B"] == "investigate", (
            f"Chain_B action_choice should be 'investigate' after mass-investigate; "
            f"got {self._post['Chain_B']}"
        )

    def test_inv_def_1_action_is_investigate(self):
        assert self._post["Inv_Def_1"] == "investigate", (
            f"Inv_Def_1 action_choice should be 'investigate' after mass-investigate; "
            f"got {self._post['Inv_Def_1']}"
        )

    def test_keep_def_action_is_investigate(self):
        assert self._post["Keep_Def"] == "investigate", (
            f"Keep_Def action_choice should be 'investigate' after mass-investigate; "
            f"got {self._post['Keep_Def']}"
        )


class TestMassPassive:
    """Mass-passive the 3 Beta combat workers."""

    @pytest.fixture(scope="class", autouse=True)
    def mass_passive_state(self, browser):
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        ui_mass_passive_click(page, "Beta", _MASS_WORKERS)

        post = _capture_action_choices(page)

        assert_no_collected_php_errors(page)
        context.close()
        type(self)._post = post
        yield

    def test_chain_b_action_is_passive(self):
        assert self._post["Chain_B"] == "passive", (
            f"Chain_B action_choice should be 'passive' after mass-passive; "
            f"got {self._post['Chain_B']}"
        )

    def test_inv_def_1_action_is_passive(self):
        assert self._post["Inv_Def_1"] == "passive", (
            f"Inv_Def_1 action_choice should be 'passive' after mass-passive; "
            f"got {self._post['Inv_Def_1']}"
        )

    def test_keep_def_action_is_passive(self):
        assert self._post["Keep_Def"] == "passive", (
            f"Keep_Def action_choice should be 'passive' after mass-passive; "
            f"got {self._post['Keep_Def']}"
        )


def _resolve_worker_id(browser, base_url, lastname):
    ctx = browser.new_context()
    page = ctx.new_page()
    ensure_gm_login(page, base_url)
    wid = ui_worker_id(page, lastname, base_url=base_url)
    ctx.close()
    return wid


@pytest.mark.parametrize("mass_action",
                         ["mass_investigate", "mass_passive", "mass_hide", "mass_claim"])
def test_mass_action_non_owner_returns_403(browser, base_url, mass_action):
    """single_player owns Alpha; Bystander_1 belongs to Beta. massAction.php
    must 403 before any activateWorker() call when a non-privileged controller
    attempts to mass-act on workers they do not own."""
    bystander_wid = _resolve_worker_id(browser, base_url, "Bystander_1")

    ctx = browser.new_context()
    page = ctx.new_page()
    login_as(page, base_url, "single_player", "test")
    url = (
        f"{base_url}/workers/massAction.php"
        f"?{mass_action}=1&worker_ids%5B%5D={bystander_wid}"
    )
    response = page.goto(url)
    assert response is not None
    assert response.status == 403, (
        f"Non-owner {mass_action} on a foreign worker must 403; "
        f"got {response.status}"
    )
    ctx.close()


# ---------------------------------------------------------------------------
# Issue #150 : mass claim, and the guards massAction.php was missing
# ---------------------------------------------------------------------------
#
# A claim is made on behalf of a faction, so unlike the three actions above it
# carries a parameter. Each selected agent claims the zone it already stands in;
# agents spread across several zones each claim their own, which is intended.


class TestMassClaim:
    """Mass-claim the 3 Beta combat workers on behalf of Alpha."""

    @pytest.fixture(scope="class", autouse=True)
    def mass_claim_state(self, browser):
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        alpha_id = ui_controller_id(page, "Alpha", base_url=PHP_BASE_URL)
        ui_mass_claim_click(page, "Beta", _MASS_WORKERS, "Alpha")

        post = _capture_action_choices(page)
        params = ui_worker_action_state(page, _MASS_WORKERS[0])["action_params"]

        assert_no_collected_php_errors(page)
        context.close()
        type(self)._post = post
        type(self)._params = params
        type(self)._alpha_id = alpha_id
        yield

    def test_every_selected_worker_claims(self):
        for lastname in _MASS_WORKERS:
            assert self._post[lastname] == "claim", (
                f"{lastname} action_choice should be 'claim' after mass-claim; "
                f"got {self._post[lastname]}"
            )

    def test_the_chosen_banner_is_carried(self):
        """Without this the claim would resolve for nobody : the banner is the
        one thing a claim needs that the other mass actions do not."""
        assert str(self._alpha_id) in self._params, (
            f"action_params must carry the chosen claim_controller_id "
            f"{self._alpha_id}; got {self._params!r}"
        )


def test_mass_claim_is_ignored_when_the_mode_forbids_it(browser, base_url):
    """claimMode is a scenario setting : the button is not rendered under an
    unsupported mode, and a stale form must be ignored rather than answered
    with an error page.
    """
    context = browser.new_context()
    page = context.new_page()
    ensure_gm_login(page, base_url)
    previous_mode = None
    try:
        worker_id = _resolve_worker_id(browser, base_url, _MASS_WORKERS[0])
        alpha_id = ui_controller_id(page, "Alpha", base_url=base_url)
        previous_mode = ui_config_value(page, "claimMode", base_url=base_url)

        ui_mass_passive_click(page, "Beta", [_MASS_WORKERS[0]], base_url=base_url)
        set_config_via_ui(page, "claimMode", "controller", base_url=base_url)

        response = page.request.get(
            f"{base_url}/workers/massAction.php"
            f"?worker_ids[]={worker_id}&claim_controller_id={alpha_id}&mass_claim=1"
        )
        assert response.status == 200, (
            f"an unsupported claim mode must be ignored, not refused; "
            f"got HTTP {response.status}"
        )
        state = ui_worker_action_state(page, _MASS_WORKERS[0], base_url=base_url)
        assert state["action_choice"] == "passive", (
            f"the action must be untouched under an unsupported claim mode; "
            f"got {state['action_choice']!r}"
        )
    finally:
        if previous_mode is not None:
            set_config_via_ui(page, "claimMode", previous_mode, base_url=base_url)
        context.close()


def test_mass_move_without_a_zone_is_refused(browser, base_url):
    """The zone select never renders an empty option, so a mass move carrying no
    zone cannot come from the form. It used to be ignored without a word.

    Paired with the claim above, which is ignored in SILENCE on purpose : an
    unsupported claim mode is a scenario setting, a missing zone is a forged call.
    """
    worker_id = _resolve_worker_id(browser, base_url, _MASS_WORKERS[0])

    ctx = browser.new_context()
    page = ctx.new_page()
    ensure_gm_login(page, base_url)
    response = page.goto(
        f"{base_url}/workers/massAction.php?mass_move=1&worker_ids%5B%5D={worker_id}")
    status = response.status if response is not None else None
    ctx.close()
    assert status == 400, f"a mass move without a zone must be refused; got {status}"
