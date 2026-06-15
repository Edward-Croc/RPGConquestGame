"""End-to-end test for issue #64 — older enemies fold in the zone box.

Pushes CKE entries past `attackTimeWindow=1` by moving Searcher_1 out of
Alpha-Investigation (stops investigation refresh) and running multiple
EOTs. The "Plus anciens" `<details>` fold should then appear.

Why a separate file from the post-EOT tests: that file's module fixture
keeps Searcher_1 in place so its CKE stays recent (good for the recent-
enemies assertion). This file needs the opposite state, so the scenario
load + EOT sequence is different — module-scoped fixtures don't compose.

Run:
    python3 -m pytest tests/test_zone_agent_navigation_older_enemies_e2e.py -v
"""
import pytest
from playwright.sync_api import Page, expect

from conftest import PHP_BASE_URL
from helpers import (
    DB_AVAILABLE, load_minimal_data, login_as, logout, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
    as_controller, end_turn, ui_move_click,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def load_scenario_and_age_cke(browser):
    """Load TestConfig, EOT once to seed CKE, move Searcher_1 out of
    Alpha-Investigation (stops refresh), then EOT 3 more times. The
    initial CKE entries end up with `last_discovery_turn` from EOT 1
    while the current turn is 4 — with `attackTimeWindow=1`, those
    entries are 'older' (1 < 4 - 1)."""
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
    assert_no_collected_php_errors(page)

    end_turn(page, base_url=PHP_BASE_URL)
    assert_no_collected_php_errors(page)

    # Move Searcher_1 out of Alpha-Investigation so subsequent EOTs do not
    # refresh the CKE entries' last_discovery_turn (move sets action_choice
    # to 'passive').
    ui_move_click(page, "Searcher_1", "Beta-Combat", base_url=PHP_BASE_URL)
    assert_no_collected_php_errors(page)

    for _ in range(3):
        end_turn(page, base_url=PHP_BASE_URL)
        assert_no_collected_php_errors(page)

    context.close()
    yield


@pytest.fixture
def alpha_page(page: Page, base_url):
    login_as(page, base_url, "gm", "orga")
    as_controller(page, "Alpha", base_url=base_url)
    yield page
    logout(page, base_url)


# ---------------------------------------------------------------------------
# Audit test: zone_box_collapses_old_enemy_by_default
# ---------------------------------------------------------------------------

class TestOlderEnemiesFold:
    def test_plus_anciens_details_visible(self, alpha_page: Page, base_url):
        """After several EOTs without active investigation, the initial CKE
        entries should appear inside `<details>Plus anciens</details>`."""
        safe_goto(alpha_page, f"{base_url}/zones/action.php")
        alpha_page.wait_for_load_state("networkidle")
        alpha_inv_box = alpha_page.locator("div.box.mb-4").filter(has_text="Alpha-Investigation").first
        alpha_inv_box.locator("h3").click()
        plus_anciens = alpha_inv_box.locator("details summary").filter(has_text="Plus anciens")
        expect(plus_anciens).to_be_visible()

    def test_expand_plus_anciens_reveals_enemy_names(self, alpha_page: Page, base_url):
        safe_goto(alpha_page, f"{base_url}/zones/action.php")
        alpha_page.wait_for_load_state("networkidle")
        alpha_inv_box = alpha_page.locator("div.box.mb-4").filter(has_text="Alpha-Investigation").first
        alpha_inv_box.locator("h3").click()
        plus_anciens_summary = (
            alpha_inv_box.locator("details summary").filter(has_text="Plus anciens").first
        )
        plus_anciens_summary.click()
        details = alpha_inv_box.locator("details").filter(has_text="Plus anciens").first
        box_text = details.inner_text()
        plausible_enemies = ["Finder_1", "Finder_2", "Finder_3", "Finder_4",
                              "Finder_5", "Bystander_1"]
        found = [e for e in plausible_enemies if e in box_text]
        assert found, f"Expected enemy names in Plus anciens; got: {box_text[:300]}"
