"""End-to-end tests for the zone-agent navigation feature (issue #64).

Covers:
  * Zone box agent lists (friendly + folded doubles + recent/older enemies)
  * workers/viewAll.php sort dropdown (?sort=age|zone|investigate|attack)
  * Agent view → zone box deep-link via #zone-N anchor + on-load JS expand

UI-only per `feedback_demo_ui_only`. Uses the existing TestConfig advanced
scenario for seed data (Alpha owns workers in Alpha-Investigation and
Beta-Combat).

Run:
    python3 -m pytest tests/test_zone_agent_navigation_e2e.py -v
"""
import pytest
from playwright.sync_api import Page, expect

from conftest import PHP_BASE_URL
from helpers import (
    DB_AVAILABLE, load_minimal_data, login_as, logout, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
    as_controller,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def load_test_config_advanced(browser):
    """Load TestConfig advanced (seeds workers across zones)."""
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
    context.close()
    yield


@pytest.fixture
def alpha_page(page: Page, base_url):
    """Logged in as gm, switched to controller Alpha."""
    login_as(page, base_url, "gm", "orga")
    as_controller(page, "Alpha", base_url=base_url)
    yield page
    logout(page, base_url)


# ---------------------------------------------------------------------------
# Part A — zone box agent lists
# ---------------------------------------------------------------------------

class TestZoneBoxFriendlyAgents:
    """showZoneAgents renders 'Nos Agents présents :' for friendlies in zone."""

    def test_friendly_header_visible_in_alpha_investigation(self, alpha_page: Page, base_url):
        """Alpha owns Searcher_1 in Alpha-Investigation → header should appear
        when the zone description is expanded."""
        safe_goto(alpha_page, f"{base_url}/zones/action.php")
        alpha_page.wait_for_load_state("networkidle")
        alpha_inv_box = alpha_page.locator("div.box.mb-4").filter(has_text="Alpha-Investigation").first
        alpha_inv_box.locator("h3").click()
        expect(alpha_inv_box.locator("div[id^='description-']")).to_be_visible()
        assert "Nos Agents présents" in alpha_inv_box.inner_text()

    def test_friendly_link_anchor_present_with_clickable_title_classes(self, alpha_page: Page, base_url):
        """Friendly anchors carry has-text-weight-semibold + role=button."""
        safe_goto(alpha_page, f"{base_url}/zones/action.php")
        alpha_page.wait_for_load_state("networkidle")
        alpha_inv_box = alpha_page.locator("div.box.mb-4").filter(has_text="Alpha-Investigation").first
        alpha_inv_box.locator("h3").click()
        anchors = alpha_inv_box.locator("a[href*='/workers/action.php?worker_id=']")
        assert anchors.count() >= 1, "Expected at least one friendly-agent anchor"
        first_class = anchors.first.get_attribute("class") or ""
        assert "has-text-weight-semibold" in first_class
        assert anchors.first.get_attribute("role") == "button"

    def test_friendly_stats_block_present(self, alpha_page: Page, base_url):
        """Each friendly item shows (e, a/d) stats in italic next to the link."""
        safe_goto(alpha_page, f"{base_url}/zones/action.php")
        alpha_page.wait_for_load_state("networkidle")
        alpha_inv_box = alpha_page.locator("div.box.mb-4").filter(has_text="Alpha-Investigation").first
        alpha_inv_box.locator("h3").click()
        first_li_text = alpha_inv_box.locator("ul li").first.inner_text()
        assert "(" in first_li_text and ")" in first_li_text, (
            f"Expected stats parentheses in the first friendly <li>; got: {first_li_text!r}"
        )

    def test_no_friendlies_no_header(self, alpha_page: Page, base_url):
        """A zone where Alpha has zero alive+active workers shows no
        'Nos Agents présents' header (section skip when empty)."""
        safe_goto(alpha_page, f"{base_url}/zones/action.php")
        alpha_page.wait_for_load_state("networkidle")
        theta_box = alpha_page.locator("div.box.mb-4").filter(has_text="Theta-Artefacts").first
        theta_box.locator("h3").click()
        assert "Nos Agents présents" not in theta_box.inner_text()


class TestZoneBoxDoubleAgents:
    """Doubles fold inside <details>Nos Agents doubles</details>. Hidden
    entirely when empty."""

    def test_no_doubles_no_details_widget(self, alpha_page: Page, base_url):
        """TestConfig advanced has no double agents seeded for Alpha → no
        zone box should render the Nos Agents doubles <details>."""
        safe_goto(alpha_page, f"{base_url}/zones/action.php")
        alpha_page.wait_for_load_state("networkidle")
        details = alpha_page.locator("details summary").filter(has_text="Nos Agents doubles")
        assert details.count() == 0, (
            "Empty doubles bucket should not render a <details> widget; "
            f"found {details.count()}"
        )

class TestZoneBoxEnemyAgents:
    """Recent enemies render in a <ul>; older fold inside <details>Plus anciens</details>."""

    def test_no_enemies_no_header(self, alpha_page: Page, base_url):
        """A fresh TestConfig advanced has no CKE rows for Alpha → no
        'Agents ennemis repérés' header in any zone."""
        safe_goto(alpha_page, f"{base_url}/zones/action.php")
        alpha_page.wait_for_load_state("networkidle")
        all_text = alpha_page.locator("div.section.zones").inner_text()
        assert "Agents ennemis repérés" not in all_text, (
            "No CKE entries should mean no enemy section; "
            "if this fires, a prior test seeded CKE for Alpha"
        )

    def test_plus_anciens_not_shown_when_older_empty(self, alpha_page: Page, base_url):
        """The 'Plus anciens' <details> widget must not render when there
        are no older discoveries in any zone."""
        safe_goto(alpha_page, f"{base_url}/zones/action.php")
        alpha_page.wait_for_load_state("networkidle")
        plus_anciens = alpha_page.locator("details summary").filter(has_text="Plus anciens")
        assert plus_anciens.count() == 0, (
            "Empty older bucket should not render its <details>; "
            f"found {plus_anciens.count()}"
        )

# ---------------------------------------------------------------------------
# Part B — workers/viewAll.php sort dropdown
# ---------------------------------------------------------------------------

class TestWorkersListSortDropdown:
    """?sort= GET param with whitelist + dropdown reflects selection."""

    def test_tri_form_present_with_four_options(self, alpha_page: Page, base_url):
        safe_goto(alpha_page, f"{base_url}/workers/viewAll.php")
        alpha_page.wait_for_load_state("networkidle")
        select = alpha_page.locator("select[name='sort']")
        expect(select).to_be_visible()
        option_values = set()
        for opt in select.locator("option").all():
            option_values.add(opt.get_attribute("value"))
        assert option_values == {"age", "zone", "investigate", "attack"}, (
            f"Expected exactly the four whitelisted sort values; got {option_values}"
        )

    def test_default_selected_is_age(self, alpha_page: Page, base_url):
        safe_goto(alpha_page, f"{base_url}/workers/viewAll.php")
        alpha_page.wait_for_load_state("networkidle")
        selected = alpha_page.locator("select[name='sort'] option[selected]")
        assert selected.get_attribute("value") == "age"

    def test_sort_zone_marks_zone_option_selected(self, alpha_page: Page, base_url):
        safe_goto(alpha_page, f"{base_url}/workers/viewAll.php?sort=zone")
        alpha_page.wait_for_load_state("networkidle")
        selected = alpha_page.locator("select[name='sort'] option[selected]")
        assert selected.get_attribute("value") == "zone"

    def test_invalid_sort_falls_back_to_age(self, alpha_page: Page, base_url):
        safe_goto(alpha_page, f"{base_url}/workers/viewAll.php?sort=bogus")
        alpha_page.wait_for_load_state("networkidle")
        selected = alpha_page.locator("select[name='sort'] option[selected]")
        assert selected.get_attribute("value") == "age", (
            "Whitelist must reject any non-whitelisted value back to 'age'"
        )

    def test_sort_zone_reorders_live_bucket_alphabetically(self, alpha_page: Page, base_url):
        """Alpha owns Searcher_1 (Alpha-Investigation) and Chain_A/Even_Atk/Inv_Atk_1
        (Beta-Combat). Under sort=zone, the first listed worker should be in
        Alpha-Investigation (alphabetically first zone among Alpha's workers)."""
        safe_goto(alpha_page, f"{base_url}/workers/viewAll.php?sort=zone")
        alpha_page.wait_for_load_state("networkidle")
        live_box = alpha_page.locator("div.box.mb-4").filter(has_text="Nos Agents :").first
        first_worker = live_box.locator("div.worker-short").first
        worker_text = first_worker.inner_text()
        assert "Alpha-Investigation" in worker_text, (
            f"With sort=zone, first live worker should be in Alpha-Investigation; "
            f"got: {worker_text[:200]}"
        )


# ---------------------------------------------------------------------------
# Part C — agent view → zone box anchor link
# ---------------------------------------------------------------------------

class TestAgentViewZoneLink:
    """workers/view.php wraps the zone name in a clickable-title anchor to
    /zones/action.php#zone-N. The on-load JS expands the matching box and
    scrolls to it."""

    def test_worker_view_zone_anchor_present(self, alpha_page: Page, base_url):
        """Open any of Alpha's workers and assert the zone label is now a
        link to zones/action.php#zone-N."""
        safe_goto(alpha_page, f"{base_url}/workers/viewAll.php")
        alpha_page.wait_for_load_state("networkidle")
        first_worker_link = alpha_page.locator("a[href*='/workers/action.php?worker_id=']").first
        first_worker_link.click()
        alpha_page.wait_for_load_state("networkidle")
        zone_anchor = alpha_page.locator("a[href*='/zones/action.php#zone-']").first
        expect(zone_anchor).to_be_visible()
        href = zone_anchor.get_attribute("href") or ""
        assert "#zone-" in href, f"Expected #zone-N anchor; got href={href!r}"

    def test_worker_view_zone_anchor_classes(self, alpha_page: Page, base_url):
        """Anchor uses the clickable-title style (no <strong>, no is-size-5
        — it is inline body text)."""
        safe_goto(alpha_page, f"{base_url}/workers/viewAll.php")
        alpha_page.wait_for_load_state("networkidle")
        first_worker_link = alpha_page.locator("a[href*='/workers/action.php?worker_id=']").first
        first_worker_link.click()
        alpha_page.wait_for_load_state("networkidle")
        zone_anchor = alpha_page.locator("a[href*='/zones/action.php#zone-']").first
        cls = zone_anchor.get_attribute("class") or ""
        assert "has-text-weight-semibold" in cls
        assert "is-size-5" not in cls, "Inline anchor should NOT carry is-size-5"
        assert zone_anchor.get_attribute("role") == "button"

    def test_zone_box_carries_anchor_id(self, alpha_page: Page, base_url):
        """Each rendered zone box should have id='zone-N' on its outer <div>."""
        safe_goto(alpha_page, f"{base_url}/zones/action.php")
        alpha_page.wait_for_load_state("networkidle")
        zone_boxes = alpha_page.locator("div.box.mb-4[id^='zone-']")
        assert zone_boxes.count() >= 1, (
            "Expected at least one outer .box div with id='zone-N'"
        )

    def test_hash_handler_opens_description_on_load(self, alpha_page: Page, base_url):
        """Clicking the zone link from a worker view lands on /zones/action.php#zone-N
        and the on-load JS opens the matching description."""
        import re
        safe_goto(alpha_page, f"{base_url}/workers/viewAll.php")
        alpha_page.wait_for_load_state("networkidle")
        alpha_page.locator("a[href*='/workers/action.php?worker_id=']").first.click()
        alpha_page.wait_for_load_state("networkidle")
        zone_anchor = alpha_page.locator("a[href*='/zones/action.php#zone-']").first
        href = zone_anchor.get_attribute("href") or ""
        m = re.search(r"#zone-(\w+)", href)
        assert m, f"Couldn't parse zone_id from href={href!r}"
        zone_id = m.group(1)
        zone_anchor.click()
        alpha_page.wait_for_load_state("networkidle")
        description = alpha_page.locator(f"#description-{zone_id}")
        assert description.evaluate("el => el.style.display") == "block", (
            "On-load JS should set the targeted description to display:block"
        )
