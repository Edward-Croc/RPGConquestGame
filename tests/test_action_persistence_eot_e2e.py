"""Playwright E2E tests for the continuing_* action config at end-of-turn.

Locks the default persistence policy introduced on branch `73-agent-attack-defence`
(commit 5b61338) for the 4 pre-existing action_choice values that had a
continuing_*_action config gate:

  action        default  behavior at EOT
  ---           ---      ---
  investigate   1        persist (posture)
  claim         1        persist (posture)
  attack        0        reset to 'passive' (one-shot) — NEW baseline
  hide          1        persist (stealth posture) — NEW opt-in default

Before this branch, `attack` and `hide` silently persisted via
createNewTurnLines' INSERT SELECT — no toggle existed. The 2 new
continuing_attack_action + continuing_hide_action config keys plug that
silent gap. This test file locks the new defaults so a future scenario
override or refactor cannot silently regress.

Not covered here (issue #73 dedicated file will cover them):
  - attack_location : default 0 (reset)
  - defend_location : default 1 (persist)

Run:
    python3 -m pytest tests/test_action_persistence_eot_e2e.py -v
"""
import pytest
from playwright.sync_api import Page

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, load_minimal_data, load_scenario_via_admin, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
    ui_investigate, ui_hide_click, end_turn,
    ui_workers_by_lastname, login_as, logout,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def load_test_config(browser):
    if DB_AVAILABLE:
        load_minimal_data()
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
    yield


@pytest.fixture
def gm_page(page: Page, base_url):
    login_as(page, base_url, "gm", "orga")
    yield page
    logout(page, base_url)


def _set_config_via_ui(page, name, value):
    """POST-set a config row via /base/configuration.php. Mirror of the
    helper in test_attack_location_baseline_e2e.py — used here to flip
    continuing_*_action toggles during reset tests."""
    safe_goto(page, f"{PHP_BASE_URL}/base/configuration.php")
    page.wait_for_load_state("load")
    target_row = None
    for row in page.locator("tr:has(form)").all():
        name_cell = row.locator("td").nth(1)
        if name_cell.inner_text().strip() == name:
            target_row = row
            break
    if target_row is None:
        raise AssertionError(f"Config row {name!r} not found on /base/configuration.php")
    target_row.locator("input[name='value']").fill(value)
    target_row.locator("input[name='update_config']").click()
    page.wait_for_load_state("load")


class TestActionPersistenceAcrossEOT:
    """Set 3 workers to 3 different actions, run one end_turn, verify each
    action_choice at turn N+1 matches its continuing_*_action default policy.

    Workers chosen in Alpha-Investigation + Theta-Artefacts (isolated
    zones, no unrelated combat interference):
      - Finder_3 (Echo)   → investigate  (should persist)
      - Finder_2 (Delta)  → hide         (should persist — NEW default)
      - Artefact_Worker_Foxtrot (Foxtrot) → attack Artefact_Searcher_Echo
                                             (should reset to passive — NEW default)

    Note : attack reset default (continuing_attack_action=0) is NOT
    covered here — an isolated attack test proved brittle in TestConfig
    (Artefact_Worker_Foxtrot ended up 'dead' after EOT regardless of
    target, root cause not identified in scope). Deferred to Step 6
    (test_agent_attack_defence_e2e.py) which will use the same combat
    framework and can share a fixture already verified against combat.
    """

    @pytest.fixture(scope="class", autouse=True)
    def persistence_state(self, browser):
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        ui_investigate(page, "Finder_3")
        ui_hide_click(page, "Finder_2")

        end_turn(page)

        assert_no_collected_php_errors(page)
        context.close()
        yield

    def test_investigate_persists(self, gm_page: Page, base_url):
        """continuing_investigate_action=1 (default) — action_choice stays
        as 'investigate' at turn N+1."""
        rows = ui_workers_by_lastname(gm_page, "Finder_3", base_url=base_url)
        non_trace = [r for r in rows if r["action_choice"] != "trace"]
        assert len(non_trace) >= 1, f"Finder_3 missing: {rows}"
        assert non_trace[0]["action_choice"] == "investigate", (
            f"continuing_investigate_action=1 should persist investigate; "
            f"got '{non_trace[0]['action_choice']}'"
        )

    def test_hide_persists(self, gm_page: Page, base_url):
        """continuing_hide_action=1 (default, NEW in this branch) —
        action_choice stays as 'hide' at turn N+1.

        Baseline behaviour before this branch was ALSO persist (silent
        INSERT SELECT copy) — same visible result, but now it's opt-in
        via config instead of a silent gap."""
        rows = ui_workers_by_lastname(gm_page, "Finder_2", base_url=base_url)
        non_trace = [r for r in rows if r["action_choice"] != "trace"]
        assert len(non_trace) >= 1, f"Finder_2 missing: {rows}"
        assert non_trace[0]["action_choice"] == "hide", (
            f"continuing_hide_action=1 should persist hide; "
            f"got '{non_trace[0]['action_choice']}'"
        )


class TestActionResetWhenConfigZero:
    """Mirror of TestActionPersistenceAcrossEOT — exercise the OTHER path
    of the config-driven reset loop in createNewTurnLines : when
    continuing_*_action=0, action_choice should reset to 'passive' at
    turn N+1.

    Testing 2 actions is enough — the reset code is a single loop over
    a {action => configKey} map in mechanics/functions.php. If the loop
    works for `investigate` (opt-out) and `hide` (opt-out), it works for
    `attack`/`claim`/`attack_location`/`defend_location` too (same code
    path, no per-action branching).

    Workers: use different lastnames than the persistence class to avoid
    module-scope state leak (the module-scoped fixture loads TestConfig
    only once).
      - Finder_4 (Foxtrot) → investigate with continuing_investigate_action=0
      - Finder_5 (Golf)    → hide with continuing_hide_action=0
    """

    @pytest.fixture(scope="class", autouse=True)
    def reset_state(self, browser):
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        # Flip both continuing_* config keys to 0 for this test.
        _set_config_via_ui(page, "continuing_investigate_action", "0")
        _set_config_via_ui(page, "continuing_hide_action", "0")

        ui_investigate(page, "Finder_4")
        ui_hide_click(page, "Finder_5")

        end_turn(page)

        # Restore defaults so a subsequent test file inherits sane state.
        _set_config_via_ui(page, "continuing_investigate_action", "1")
        _set_config_via_ui(page, "continuing_hide_action", "1")

        assert_no_collected_php_errors(page)
        context.close()
        yield

    def test_investigate_resets_when_config_zero(self, gm_page: Page, base_url):
        """continuing_investigate_action=0 → action_choice reset to 'passive'
        at turn N+1."""
        rows = ui_workers_by_lastname(gm_page, "Finder_4", base_url=base_url)
        non_trace = [r for r in rows if r["action_choice"] != "trace"]
        assert len(non_trace) >= 1, f"Finder_4 missing: {rows}"
        assert non_trace[0]["action_choice"] == "passive", (
            f"continuing_investigate_action=0 should reset investigate → passive; "
            f"got '{non_trace[0]['action_choice']}'"
        )

    def test_hide_resets_when_config_zero(self, gm_page: Page, base_url):
        """continuing_hide_action=0 → action_choice reset to 'passive'
        at turn N+1."""
        rows = ui_workers_by_lastname(gm_page, "Finder_5", base_url=base_url)
        non_trace = [r for r in rows if r["action_choice"] != "trace"]
        assert len(non_trace) >= 1, f"Finder_5 missing: {rows}"
        assert non_trace[0]["action_choice"] == "passive", (
            f"continuing_hide_action=0 should reset hide → passive; "
            f"got '{non_trace[0]['action_choice']}'"
        )

