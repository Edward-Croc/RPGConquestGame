"""Playwright E2E tests for the three "Action de mise en place" buttons on
workers/view.php: Investigate, Passive, Hide.

Covers two contracts per button (audit gap — workers/view.php):
  (a) Click → action_choice flips to the chosen action (immediate).
  (b) After end-turn, the life_report stats reflect the action's
      contribution to enquete/attack/defence (calculateVals math).

Bystander_1 (Beta, passive, Alpha-Investigation) is the test subject.
Powers: Dark Impulse (e=-1, a=1, d=-1) + Patrol Warden (e=1, a=0, d=0).
Power totals: e=0, a=1, d=-1.

Under TestConfig (PASSIVEVAL=3, MINROLL=MAXROLL=3, no zone bonus,
HIDE_ENQUETE_FLAT_BONUS=4, HIDE_DEFENCE_FLAT_BONUS=1):
  passive     → enquete=3   attack=4   defence=2
  hide        → enquete=7   attack=4   defence=3
  investigate → enquete=3   attack=4   defence=2  (dice=3 ≡ PASSIVEVAL)

The class fixture runs Bystander_1 through 3 turns (one action per turn),
capturing the action_choice immediately after each click and the
life_report stats after each end-turn.

Run:
    python3 -m pytest tests/test_actions_mise_en_place_e2e.py -v
"""
import pytest
from playwright.sync_api import Page

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, load_minimal_data, login_as, logout, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
    ui_hide_click, ui_passive_click, ui_investigate_click,
    ui_worker_action_state, ui_worker_stats, end_turn,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def load_test_config(browser):
    """Load TestConfig so Bystander_1 + the 'Action de mise en place'
    config rows exist."""
    if DB_AVAILABLE:
        load_minimal_data()

    context = browser.new_context()
    page = context.new_page()
    register_php_error_listener(page)
    safe_goto(page, f"{PHP_BASE_URL}/connection/loginForm.php")
    page.wait_for_load_state("load")
    page.locator("input[name='username']").fill("gm")
    page.locator("input[name='passwd']").fill("orga")
    page.locator("input[type='submit']").first.click()
    page.wait_for_load_state("load")
    safe_goto(page, f"{PHP_BASE_URL}/base/admin.php")
    page.wait_for_load_state("load")
    page.locator("select[name='config_name']").select_option("TestConfig")
    page.locator("input[type='submit'][value='Submit']").click()
    if page.locator("#confirmModalYes").is_visible():
        page.locator("#confirmModalYes").click()
    page.wait_for_timeout(5000)
    page.wait_for_load_state("load", timeout=90000)
    assert_no_collected_php_errors(page)
    context.close()
    yield


@pytest.fixture(scope="class")
def turn_results(browser):
    """Run Bystander_1 through 3 turns: passive (turn 0→1), hide (1→2),
    investigate (2→3). For each: click the button, capture the post-click
    action_choice, end-turn, capture the post-end-turn life_report stats.

    Returns dict[action] = {'action_choice': str, 'stats': {...}}."""
    context = browser.new_context()
    page = context.new_page()
    register_php_error_listener(page)
    ensure_gm_login(page, PHP_BASE_URL)

    results = {}
    sequence = [
        ("passive", ui_passive_click),
        ("hide", ui_hide_click),
        ("investigate", ui_investigate_click),
    ]
    for action_name, click_fn in sequence:
        click_fn(page, "Bystander_1")
        action_state = ui_worker_action_state(page, "Bystander_1")
        end_turn(page)
        stats = ui_worker_stats(page, "Bystander_1")
        results[action_name] = {
            "action_choice": action_state["action_choice"],
            "stats": stats,
        }

    assert_no_collected_php_errors(page)
    context.close()
    return results


# Bystander_1 power totals: Dark Impulse (e=-1, a=1, d=-1)
# + Patrol Warden (e=1, a=0, d=0) = (e=0, a=1, d=-1).
# TestConfig: PASSIVEVAL=3, MINROLL=MAXROLL=3, ENQUETE_ZONE_BONUS=0,
# ATTACK_ZONE_BONUS=0, DEFENCE_ZONE_BONUS=1 (only applies if zone held —
# Alpha-Investigation is unheld, so no zone bonus applies),
# HIDE_ENQUETE_FLAT_BONUS=4, HIDE_DEFENCE_FLAT_BONUS=1.

class TestActionMiseEnPlaceClicks:
    """Verify both the click → action_choice flip AND the post-end-turn
    calculateVals math for each of the three 'Action de mise en place'
    buttons on workers/view.php."""

    def test_passive_click_and_stats(self, turn_results):
        r = turn_results["passive"]
        assert r["action_choice"] == "passive", \
            f"Passive click should set action_choice='passive'; got {r['action_choice']!r}"
        # passive: vals = powers + PASSIVEVAL(3); no flat bonuses, no zone bonus
        assert r["stats"]["enquete_val"] == 3, r["stats"]
        assert r["stats"]["attack_val"] == 4, r["stats"]
        assert r["stats"]["defence_val"] == 2, r["stats"]

    def test_hide_click_and_stats(self, turn_results):
        r = turn_results["hide"]
        assert r["action_choice"] == "hide", \
            f"Hide click should set action_choice='hide'; got {r['action_choice']!r}"
        # hide: enquete += HIDE_ENQUETE_FLAT_BONUS=4, defence += HIDE_DEFENCE_FLAT_BONUS=1
        assert r["stats"]["enquete_val"] == 7, r["stats"]   # 0 + 3 + 4
        assert r["stats"]["attack_val"] == 4, r["stats"]    # 1 + 3
        assert r["stats"]["defence_val"] == 3, r["stats"]   # -1 + 3 + 1

    def test_investigate_click_and_stats(self, turn_results):
        r = turn_results["investigate"]
        assert r["action_choice"] == "investigate", \
            f"Investigate click should set action_choice='investigate'; got {r['action_choice']!r}"
        # investigate: uses dice (active) for enquete; under TestConfig MINROLL=MAXROLL=3
        # so dice ≡ PASSIVEVAL → numerically equal to passive but via active code path.
        assert r["stats"]["enquete_val"] == 3, r["stats"]   # 0 + dice(3)
        assert r["stats"]["attack_val"] == 4, r["stats"]    # 1 + 3 (passive group)
        assert r["stats"]["defence_val"] == 2, r["stats"]   # -1 + 3 (passive group)
