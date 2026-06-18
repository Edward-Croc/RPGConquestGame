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
    DB_AVAILABLE, load_minimal_data, load_scenario_via_admin, login_as, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
    ui_worker_id, ui_all_workers,
    ui_mass_hide_click, ui_mass_investigate_click, ui_mass_passive_click,
)


_MASS_WORKERS = ["Chain_B", "Inv_Def_1", "Keep_Def"]


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def setup_testconfig(browser):
    """Load TestConfig once per module."""
    if DB_AVAILABLE:
        load_minimal_data()
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
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


@pytest.mark.parametrize("mass_action", ["mass_investigate", "mass_passive", "mass_hide"])
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
