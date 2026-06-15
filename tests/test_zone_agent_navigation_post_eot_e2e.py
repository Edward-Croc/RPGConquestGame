"""End-to-end tests for issue #64 — zone-agent navigation after EOT.

Covers the audit's deferred tests for double agents and recent enemies,
which require post-recruitment / post-investigation state.

Module fixture loads TestConfig, recruits a double-agent worker via the
perfect-worker form URL (Charlie primary, Echo secondary via
Test_Job_GoTraitor_Echo metier), then runs one EOT so Searcher_1's
investigation populates Alpha's CKE.

Run:
    python3 -m pytest tests/test_zone_agent_navigation_post_eot_e2e.py -v
"""
import pytest
from playwright.sync_api import Page, expect

from conftest import PHP_BASE_URL
from helpers import (
    DB_AVAILABLE, load_minimal_data, login_as, logout, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
    as_controller, end_turn, ui_zone_id, ui_controller_id,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def load_scenario_recruit_double_and_eot(browser):
    """Load TestConfig, recruit a double-agent via /workers/action.php?creation=true
    with Test_Job_GoTraitor_Echo metier (Charlie primary, Echo secondary),
    then run one EOT so Searcher_1's pre-seeded investigate action populates
    Alpha's controllers_known_enemies."""
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

    # Scrape perfect-worker form dropdown ids for stable recruitment URL.
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
    go_traitor_id = _scrape_option_value("select#power_metier_id", "Test_Job_GoTraitor_Echo")
    origin_id = _scrape_option_value("select#origin_id", "origine Accessible")

    charlie_id = ui_controller_id(page, "Charlie", base_url=PHP_BASE_URL)
    theta_id = ui_zone_id(page, "Theta-Artefacts", base_url=PHP_BASE_URL)

    url = (
        f"{PHP_BASE_URL}/workers/action.php"
        f"?creation=true"
        f"&controller_id={charlie_id}"
        f"&zone_id={theta_id}"
        f"&origin_id={origin_id}"
        f"&firstname=double"
        f"&lastname=DoubleNav_W"
        f"&power_hobby_id={blank_slate_id}"
        f"&power_metier_id={go_traitor_id}"
        f"&chosir=Recruter+et+Affecter"
    )
    page.goto(url)
    page.wait_for_load_state("load")
    assert_no_collected_php_errors(page)

    end_turn(page, base_url=PHP_BASE_URL)
    assert_no_collected_php_errors(page)

    context.close()
    yield


@pytest.fixture
def echo_page(page: Page, base_url):
    login_as(page, base_url, "gm", "orga")
    as_controller(page, "Echo", base_url=base_url)
    yield page
    logout(page, base_url)


@pytest.fixture
def alpha_page(page: Page, base_url):
    login_as(page, base_url, "gm", "orga")
    as_controller(page, "Alpha", base_url=base_url)
    yield page
    logout(page, base_url)


# ---------------------------------------------------------------------------
# Audit test: zone_box_folds_double_agents
# ---------------------------------------------------------------------------

class TestDoubleAgentFolded:
    """The recruited double-agent worker (Echo secondary) appears under
    Echo's `<details>Nos Agents doubles</details>` fold."""

    def test_doubles_fold_visible_in_theta_box(self, echo_page: Page, base_url):
        safe_goto(echo_page, f"{base_url}/zones/action.php")
        echo_page.wait_for_load_state("networkidle")
        theta_box = echo_page.locator("div.box.mb-4").filter(has_text="Theta-Artefacts").first
        theta_box.locator("h3").click()
        details = theta_box.locator("details").filter(has_text="Nos Agents doubles")
        expect(details).to_be_visible()

    def test_double_agent_name_in_expanded_fold(self, echo_page: Page, base_url):
        safe_goto(echo_page, f"{base_url}/zones/action.php")
        echo_page.wait_for_load_state("networkidle")
        theta_box = echo_page.locator("div.box.mb-4").filter(has_text="Theta-Artefacts").first
        theta_box.locator("h3").click()
        details = theta_box.locator("details").filter(has_text="Nos Agents doubles")
        details.locator("summary").click()
        assert "DoubleNav_W" in details.inner_text()

    def test_double_agent_link_uses_clickable_title_classes(self, echo_page: Page, base_url):
        """The double-agent name renders as a clickable-title anchor like
        a friendly (we control them)."""
        safe_goto(echo_page, f"{base_url}/zones/action.php")
        echo_page.wait_for_load_state("networkidle")
        theta_box = echo_page.locator("div.box.mb-4").filter(has_text="Theta-Artefacts").first
        theta_box.locator("h3").click()
        details = theta_box.locator("details").filter(has_text="Nos Agents doubles")
        details.locator("summary").click()
        anchor = details.locator("a[href*='/workers/action.php?worker_id=']").first
        expect(anchor).to_be_visible()
        cls = anchor.get_attribute("class") or ""
        assert "has-text-weight-semibold" in cls


# ---------------------------------------------------------------------------
# Audit test: zone_box_lists_known_enemy_recent
# ---------------------------------------------------------------------------

class TestRecentEnemies:
    """After 1 EOT, Alpha's CKE for enemies detected by Searcher_1 in
    Alpha-Investigation populates the zone box's enemy section."""

    def test_enemy_header_visible_in_alpha_investigation(self, alpha_page: Page, base_url):
        safe_goto(alpha_page, f"{base_url}/zones/action.php")
        alpha_page.wait_for_load_state("networkidle")
        alpha_inv_box = alpha_page.locator("div.box.mb-4").filter(has_text="Alpha-Investigation").first
        alpha_inv_box.locator("h3").click()
        assert "Agents ennemis repérés" in alpha_inv_box.inner_text()

    def test_at_least_one_enemy_name_appears(self, alpha_page: Page, base_url):
        """The recent enemy list must include at least one known seeded
        enemy lastname (the exact set depends on the investigation roll)."""
        safe_goto(alpha_page, f"{base_url}/zones/action.php")
        alpha_page.wait_for_load_state("networkidle")
        alpha_inv_box = alpha_page.locator("div.box.mb-4").filter(has_text="Alpha-Investigation").first
        alpha_inv_box.locator("h3").click()
        box_text = alpha_inv_box.inner_text()
        plausible_enemies = ["Finder_1", "Finder_2", "Finder_3", "Finder_4",
                              "Finder_5", "Bystander_1"]
        found = [e for e in plausible_enemies if e in box_text]
        assert found, (
            f"Expected at least one detected enemy from {plausible_enemies} "
            f"to appear in Alpha-Investigation box; got: {box_text[:300]}"
        )

    def test_no_plus_anciens_fold_after_one_eot(self, alpha_page: Page, base_url):
        """attackTimeWindow=1 + only one EOT → no entries are old enough
        to be 'older'; the Plus anciens <details> should not render."""
        safe_goto(alpha_page, f"{base_url}/zones/action.php")
        alpha_page.wait_for_load_state("networkidle")
        plus_anciens = alpha_page.locator("details summary").filter(has_text="Plus anciens")
        assert plus_anciens.count() == 0, (
            "No CKE entry should be 'older' after a single EOT; "
            f"found {plus_anciens.count()} Plus anciens widget(s)"
        )
