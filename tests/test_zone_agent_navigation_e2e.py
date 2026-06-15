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
    DB_AVAILABLE, load_minimal_data, load_scenario_via_admin, login_as, logout, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
    as_controller, end_turn, ui_zone_id, ui_controller_id, ui_move_click,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def load_test_config_advanced(browser):
    """Load TestConfig advanced (seeds workers across zones)."""
    if DB_AVAILABLE:
        load_minimal_data()
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
    yield


@pytest.fixture
def alpha_page(page: Page, base_url):
    """Logged in as gm, switched to controller Alpha."""
    login_as(page, base_url, "gm", "orga")
    as_controller(page, "Alpha", base_url=base_url)
    yield page
    logout(page, base_url)


@pytest.fixture
def echo_page(page: Page, base_url):
    """Logged in as gm, switched to controller Echo."""
    login_as(page, base_url, "gm", "orga")
    as_controller(page, "Echo", base_url=base_url)
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

    def test_sort_investigate_marks_investigate_option_selected(self, alpha_page: Page, base_url):
        safe_goto(alpha_page, f"{base_url}/workers/viewAll.php?sort=investigate")
        alpha_page.wait_for_load_state("networkidle")
        selected = alpha_page.locator("select[name='sort'] option[selected]")
        assert selected.get_attribute("value") == "investigate"

    def test_sort_attack_marks_attack_option_selected(self, alpha_page: Page, base_url):
        safe_goto(alpha_page, f"{base_url}/workers/viewAll.php?sort=attack")
        alpha_page.wait_for_load_state("networkidle")
        selected = alpha_page.locator("select[name='sort'] option[selected]")
        assert selected.get_attribute("value") == "attack"

    def _live_bucket_lastname_order(self, page: Page, base_url: str, watch: list[str]):
        """Return the subset of `watch` lastnames in the order they appear in
        Alpha's live bucket. Other workers are skipped — useful for stat-
        sort assertions that pivot on two known workers' relative position."""
        live_box = page.locator("div.box.mb-4").filter(has_text="Nos Agents :").first
        order = []
        for w in live_box.locator("div.worker-short").all():
            text = w.inner_text()
            for name in watch:
                if name in text:
                    order.append(name)
                    break
        return order

    def test_sort_attack_orders_chain_a_before_searcher_1(self, alpha_page: Page, base_url):
        """Chain_A carries Eagle Scout|Veteran Tactician|Focused Mind|War Gear
        (multi-power, attack-stacking); Searcher_1 has Blank Slate|Common Folk
        (zero-stat). Under sort=attack DESC, Chain_A must precede Searcher_1
        in Alpha's live bucket."""
        safe_goto(alpha_page, f"{base_url}/workers/viewAll.php?sort=attack")
        alpha_page.wait_for_load_state("networkidle")
        order = self._live_bucket_lastname_order(alpha_page, base_url, ["Chain_A", "Searcher_1"])
        assert order == ["Chain_A", "Searcher_1"], (
            f"Expected Chain_A before Searcher_1 under sort=attack; got: {order}"
        )

    def test_sort_investigate_orders_chain_a_before_searcher_1(self, alpha_page: Page, base_url):
        """Chain_A carries Eagle Scout (enquete-positive) plus other stacking
        powers; Searcher_1 has Blank Slate|Common Folk (zero enquete). Under
        sort=investigate DESC, Chain_A must precede Searcher_1."""
        safe_goto(alpha_page, f"{base_url}/workers/viewAll.php?sort=investigate")
        alpha_page.wait_for_load_state("networkidle")
        order = self._live_bucket_lastname_order(alpha_page, base_url, ["Chain_A", "Searcher_1"])
        assert order == ["Chain_A", "Searcher_1"], (
            f"Expected Chain_A before Searcher_1 under sort=investigate; got: {order}"
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


# ---------------------------------------------------------------------------
# Post-EOT state — recruit a double agent and run one EOT.
# Doubles fold (Echo's view) + recent enemies (Alpha's view).
# ---------------------------------------------------------------------------


class TestZoneBoxAfterFirstEot:
    """Class-scoped fixture recruits a Charlie/Echo double via the perfect-worker
    form URL, then runs one EOT so Searcher_1's investigate seeds Alpha's CKE."""

    @pytest.fixture(scope="class", autouse=True)
    def recruit_double_and_eot(self, browser):
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        login_as(page, PHP_BASE_URL, "gm", "orga")
        safe_goto(page, f"{PHP_BASE_URL}/base/admin.php")
        page.wait_for_load_state("networkidle")

        def _option_value(selector, text_match):
            for opt in page.locator(f"{selector} option").all():
                txt = (opt.inner_text() or "").strip()
                val = opt.get_attribute("value") or ""
                if text_match in txt and val:
                    return int(val)
            raise AssertionError(f"Option containing '{text_match}' not found in {selector}")

        blank_slate_id = _option_value("select#power_hobby_id", "Blank Slate")
        go_traitor_id = _option_value("select#power_metier_id", "Test_Job_GoTraitor_Echo")
        origin_id = _option_value("select#origin_id", "origine Accessible")
        charlie_id = ui_controller_id(page, "Charlie", base_url=PHP_BASE_URL)
        theta_id = ui_zone_id(page, "Theta-Artefacts", base_url=PHP_BASE_URL)

        url = (
            f"{PHP_BASE_URL}/workers/action.php?creation=true"
            f"&controller_id={charlie_id}&zone_id={theta_id}&origin_id={origin_id}"
            f"&firstname=double&lastname=DoubleNav_W"
            f"&power_hobby_id={blank_slate_id}&power_metier_id={go_traitor_id}"
            f"&chosir=Recruter+et+Affecter"
        )
        page.goto(url)
        page.wait_for_load_state("load")
        assert_no_collected_php_errors(page)

        end_turn(page, base_url=PHP_BASE_URL)
        assert_no_collected_php_errors(page)
        context.close()
        yield

    def test_doubles_fold_visible_in_theta_box(self, echo_page: Page, base_url):
        """Echo's recruited double-agent worker appears under Theta-Artefacts'
        <details>Nos Agents doubles</details> fold."""
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
        """The double-agent name renders as a clickable-title anchor (we control them)."""
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

    def test_enemy_header_visible_in_alpha_investigation(self, alpha_page: Page, base_url):
        """Alpha's CKE populated by Searcher_1's turn-0 investigation."""
        safe_goto(alpha_page, f"{base_url}/zones/action.php")
        alpha_page.wait_for_load_state("networkidle")
        alpha_inv_box = alpha_page.locator("div.box.mb-4").filter(has_text="Alpha-Investigation").first
        alpha_inv_box.locator("h3").click()
        assert "Agents ennemis repérés" in alpha_inv_box.inner_text()

    def test_at_least_one_enemy_name_appears(self, alpha_page: Page, base_url):
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
            f"in Alpha-Investigation box; got: {box_text[:300]}"
        )

    def test_no_plus_anciens_fold_after_one_eot(self, alpha_page: Page, base_url):
        """attackTimeWindow=1 + only one EOT → no Plus anciens widget."""
        safe_goto(alpha_page, f"{base_url}/zones/action.php")
        alpha_page.wait_for_load_state("networkidle")
        plus_anciens = alpha_page.locator("details summary").filter(has_text="Plus anciens")
        assert plus_anciens.count() == 0, (
            f"No CKE entry should be 'older' after a single EOT; "
            f"found {plus_anciens.count()} Plus anciens widget(s)"
        )


# ---------------------------------------------------------------------------
# After several EOTs without active investigation, initial CKE entries
# fall outside attackTimeWindow → "Plus anciens" fold appears.
# ---------------------------------------------------------------------------


class TestZoneBoxAfterAgingCke:
    """Move Searcher_1 out of Alpha-Investigation (stops CKE refresh) and run
    3 more EOTs, pushing the initial CKE entries past attackTimeWindow=1."""

    @pytest.fixture(scope="class", autouse=True)
    def age_cke_with_extra_eots(self, browser):
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        login_as(page, PHP_BASE_URL, "gm", "orga")
        ui_move_click(page, "Searcher_1", "Beta-Combat", base_url=PHP_BASE_URL)
        assert_no_collected_php_errors(page)
        for _ in range(3):
            end_turn(page, base_url=PHP_BASE_URL)
            assert_no_collected_php_errors(page)
        context.close()
        yield

    def test_plus_anciens_details_visible(self, alpha_page: Page, base_url):
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
