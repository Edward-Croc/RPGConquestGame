"""AI behaviour tests on TestConfig.

TestConfig's controllers carry ia_type + origin_zone_id values but
have is_ia=0 by default, so the AI gate stays off for every other
test in the suite. This file explicitly toggles is_ia=TRUE for the
specific controllers it wants to exercise.

UI-only. Run:
    python3 -m pytest tests/test_ai_behaviour_e2e.py -v
"""
import html as _html
import re

import pytest

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, end_turn, load_minimal_data, load_scenario_via_admin, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


def _fresh_test_config(browser):
    """Each test class needs a clean DB so its end_turn is EOT 1 with
    full recruit slots available. Module-level load isn't enough."""
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
    if DB_AVAILABLE:
        load_minimal_data()


def _activate_is_ia_for(page, lastnames):
    """Idempotent: ensure is_ia=Yes for each controller. Reads the
    current cell to decide whether to POST the toggle."""
    safe_goto(page, f"{PHP_BASE_URL}/controllers/management.php")
    page.wait_for_load_state("load")
    html = page.content()
    activated = {}
    for last in lastnames:
        row_re = re.compile(
            rf'<tr class="controller-row"[^>]*data-controller-name="{re.escape(last)}".*?</tr>',
            re.DOTALL,
        )
        row = row_re.search(html)
        if not row:
            continue
        cid_match = re.search(r'data-controller-id="(\d+)"', row.group(0))
        is_ia_cell = re.search(r'<td data-field="is_ia">([^<]+)</td>', row.group(0))
        if not cid_match or not is_ia_cell:
            continue
        cid = cid_match.group(1)
        already_on = "Yes" in is_ia_cell.group(1)
        if not already_on:
            page.request.post(
                f"{PHP_BASE_URL}/controllers/management.php",
                form={"toggle_is_ia_id": cid},
            )
        activated[last] = cid
    return activated


def _scrape_total_recruited(page, lastname):
    """Read the 'Total Recruited Workers' cell for a controller from
    the controllers admin table."""
    safe_goto(page, f"{PHP_BASE_URL}/controllers/management.php")
    page.wait_for_load_state("load")
    row_re = re.compile(
        rf'<tr class="controller-row"[^>]*data-controller-name="{re.escape(lastname)}".*?</tr>',
        re.DOTALL,
    )
    row = row_re.search(page.content())
    assert row is not None, f"No row for controller {lastname!r}"
    cell = re.search(
        r'<td data-field="recruited_workers">\s*(\d+)\s*</td>',
        row.group(0),
    )
    assert cell is not None, f"No recruited_workers cell for {lastname!r}"
    return int(cell.group(1))


def _scrape_controller_base_zone(page, lastname):
    """Switch active to the controller and read their base's current
    zone from the move-base select on Ma Faction."""
    safe_goto(page, f"{PHP_BASE_URL}/base/accueil.php")
    page.wait_for_load_state("load")
    value = page.locator(
        f"select[name='controller_id'] option:has-text('{lastname}')"
    ).first.get_attribute("value")
    safe_goto(page, f"{PHP_BASE_URL}/base/accueil.php?controller_id={value}")
    page.wait_for_load_state("load")
    safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
    page.wait_for_load_state("load")
    html = page.content()
    m = re.search(
        r'Votre Base.*?<option[^>]*selected[^>]*>\s*([^<]+?)\s*</option>',
        html, re.DOTALL,
    )
    if not m:
        return None
    text = _html.unescape(m.group(1).strip())
    # showZoneSelect appends " (zone_id)" — strip it
    return re.sub(r'\s*\(\d+\)\s*$', '', text)


class TestBasePlacement:
    """Each AI controller's base should land in their origin_zone_id
    after EOT. With aiEnsureBase preferring origin_zone_id, this should
    hold for all 4 test states."""

    # Limited to Alpha + Beta — TestConfig's Charlie + Delta start
    # with can_build_base=0, so aiEnsureBase no-ops for them.
    EXPECTED = {
        "Alpha": "Alpha-Investigation",  # passive
        "Beta":  "Beta-Combat",          # violent
    }

    @pytest.fixture(scope="class", autouse=True)
    def base_state(self, browser):
        _fresh_test_config(browser)
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        _activate_is_ia_for(page, list(self.EXPECTED.keys()))
        end_turn(page, PHP_BASE_URL)

        zones = {}
        for lastname in self.EXPECTED.keys():
            zones[lastname] = _scrape_controller_base_zone(page, lastname)

        assert_no_collected_php_errors(page)
        ctx.close()
        type(self)._zones = zones
        yield

    def test_each_base_in_origin_zone(self):
        for lastname, expected in self.EXPECTED.items():
            assert self._zones[lastname] == expected, (
                f"{lastname}: expected base in {expected!r}, "
                f"got {self._zones[lastname]!r}"
            )


class TestRecruitCountByState:
    """Recruit count per turn scales with ai_type:
    - passive/searching: 1
    - aggressive/violent: 2
    (assuming the scenario's slot config has enough capacity)."""

    EXPECTED_DELTA = {
        "Alpha": 1,   # passive
        "Beta":  2,   # violent
    }

    @pytest.fixture(scope="class", autouse=True)
    def recruit_state(self, browser):
        _fresh_test_config(browser)
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        _activate_is_ia_for(page, list(self.EXPECTED_DELTA.keys()))

        before = {n: _scrape_total_recruited(page, n) for n in self.EXPECTED_DELTA}
        end_turn(page, PHP_BASE_URL)
        after = {n: _scrape_total_recruited(page, n) for n in self.EXPECTED_DELTA}

        assert_no_collected_php_errors(page)
        ctx.close()
        type(self)._before = before
        type(self)._after = after
        yield

    def test_passive_alpha_recruits_at_least_one(self):
        delta = self._after["Alpha"] - self._before["Alpha"]
        assert delta >= 1, (
            f"Alpha (passive): expected delta >= 1, got {delta} "
            f"(before={self._before['Alpha']}, after={self._after['Alpha']})"
        )

    def test_violent_beta_recruits_more_than_passive_alpha(self):
        """Scaling property: aiRecruitsPerTurn('violent') > aiRecruitsPerTurn('passive')."""
        alpha_delta = self._after["Alpha"] - self._before["Alpha"]
        beta_delta = self._after["Beta"] - self._before["Beta"]
        assert beta_delta > alpha_delta, (
            f"Beta (violent) should recruit more than Alpha (passive); "
            f"got Beta delta={beta_delta}, Alpha delta={alpha_delta}"
        )
