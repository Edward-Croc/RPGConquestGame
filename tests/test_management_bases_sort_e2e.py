"""End-to-end coverage of the sort control on `zones/management_bases.php`
-- log-refactor.

`select[name='sort']` offers exactly `date` (default) | `location` |
`attacker`. Sorting happens in PHP (usort, strcasecmp, tie-break
`id DESC`) rather than in SQL -- an unknown ?sort= value falls back to
`date` silently, and there is no server-side stickiness. Default (no
param) order is `created_at DESC`, i.e. the ID column descending.

Data: two real `location_attack_logs` rows produced via the UI (immediate
`locationAttackMode`, TestConfig default) with two different location
names and two different attacker lastnames, so the location/attacker sort
tests have a real, deterministic order to check:
  - Foxtrot attacks Echo-Base      (location_name='Echo-Base',    attacker='Foxtrot')
  - Echo attacks Foxtrot-Outpost   (location_name='Foxtrot-Outpost', attacker='Echo')
Alphabetically Echo-Base < Foxtrot-Outpost and Echo < Foxtrot, while the
insertion order (and therefore the default date-DESC order) puts the
second attack first -- so sort=location / sort=attacker each produce an
order genuinely different from the default, making the assertions
meaningful rather than trivially true.

No pymysql / direct SQL: both attacks go through the same admin
CKL-seed + controllers/action.php click flow used by
test_attack_location_baseline_e2e.py, so the suite runs under UI_ONLY=1.

Run:
    python3 -m pytest tests/test_management_bases_sort_e2e.py -v
"""
import re
import urllib.parse

import pytest
from playwright.sync_api import Page

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, load_minimal_data, load_scenario_via_admin, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
    set_config_via_ui, ui_controller_id,
)


def _set_config_via_ui(page, name, value):
    """Thin wrapper so the fixture reads without repeating base_url."""
    set_config_via_ui(page, name, value, base_url=PHP_BASE_URL)


def _row_locator_by_name(page, base_name):
    """UI-only row lookup by the visible Base-column text (mirrors
    test_zones_management_zones_e2e.py's _row_locator_by_name)."""
    return page.locator(f"tr:has(td:text-is('{base_name}'))")


def _location_id_via_management(page, location_name):
    safe_goto(page, f"{PHP_BASE_URL}/zones/management_locations.php")
    page.wait_for_load_state("load")
    m = re.search(
        rf'<h3>[^<]*{re.escape(location_name)}[^<]*\(discovery[^<]+</h3>'
        rf'.*?name="toggle_destruction"\s+value="(\d+)"',
        page.content(), re.DOTALL,
    )
    if not m:
        raise AssertionError(f"location_id for '{location_name}' not found")
    return int(m.group(1))


def _seed_ckl_admin(page, controller_lastname, location_name):
    cid = ui_controller_id(page, controller_lastname, PHP_BASE_URL)
    location_id = _location_id_via_management(page, location_name)
    safe_goto(
        page,
        f"{PHP_BASE_URL}/controllers/management.php"
        f"?giftInformationLocation=1&target_controller_id={cid}&location_id={location_id}"
    )
    page.wait_for_load_state("load")
    return location_id


def _switch_controller(page, controller_lastname):
    cid = ui_controller_id(page, controller_lastname, PHP_BASE_URL)
    safe_goto(page, f"{PHP_BASE_URL}/base/accueil.php?controller_id={cid}&chosir=Choisir")
    page.wait_for_load_state("load")


def _attack_location_via_ui(page, controller_lastname, target_location_id):
    """Switch to `controller_lastname` and submit the attack-location form
    against `target_location_id` if it renders. Immediate mode logs a
    location_attack_logs row on either success or failure, so the exact
    math doesn't matter here -- we only need the row to exist."""
    _switch_controller(page, controller_lastname)
    safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
    page.wait_for_load_state("load")
    if page.locator("input[name='attackLocation']").count() > 0:
        form = page.locator("form:has(input[name='attackLocation'])").first
        form.locator("select[name='target_location_id']").select_option(
            value=str(target_location_id)
        )
        form.locator("input[name='attackLocation']").click()
        page.wait_for_load_state("load")


def _scrape_bases_table(page):
    """Read every data row of management_bases.php's history table into
    dicts: {id, base, attacker, turn, success, values, target_html,
    attacker_html}. target_html/attacker_html are raw innerHTML (not
    inner_text, which would already have decoded any HTML entity) so the
    raw-markup assertion can inspect the literal source."""
    rows = []
    trs = page.locator("div.management table tr").all()
    for tr in trs[1:]:
        tds = tr.locator("td")
        if tds.count() < 8:
            continue
        rows.append({
            "id": int(tds.nth(0).inner_text().strip()),
            "base": tds.nth(1).inner_text().strip(),
            "attacker": tds.nth(2).inner_text().strip(),
            "turn": tds.nth(3).inner_text().strip(),
            "success": tds.nth(4).inner_text().strip(),
            "values": tds.nth(5).inner_text().strip(),
            "target_html": tds.nth(6).inner_html(),
            "attacker_html": tds.nth(7).inner_html(),
        })
    return rows


@pytest.fixture(scope="module", autouse=True)
def bases_sort_scenario(browser):
    if DB_AVAILABLE:
        load_minimal_data()
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")

    context = browser.new_context()
    page = context.new_page()
    register_php_error_listener(page)
    try:
        ensure_gm_login(page, PHP_BASE_URL)

        # Give the outcome templates a literal <br /> so the result-text cells
        # carry markup. Without it nl2br() is a no-op on these single-line seeds
        # and test_result_text_cells_are_not_html_escaped skips itself. A literal
        # tag is used rather than a newline : /base/configuration.php edits the
        # value through an <input>, which cannot hold one.
        _set_config_via_ui(page, "textLocationDestroyed", 'Le lieu %s a été détruit.<br />Selon votre bon vouloir.')
        _set_config_via_ui(page, "textLocationPillaged", "Le lieu %s a été pillé.<br />Nous n'avons pas pu le détruire.")
        _set_config_via_ui(page, "textLocationNotDestroyed", "Le lieu %s n'a pas été détruit.<br />Nos excuses.")

        echo_base_id = _seed_ckl_admin(page, "Foxtrot", "Echo-Base")
        _attack_location_via_ui(page, "Foxtrot", echo_base_id)

        foxtrot_outpost_id = _seed_ckl_admin(page, "Echo", "Foxtrot-Outpost")
        _attack_location_via_ui(page, "Echo", foxtrot_outpost_id)

        assert_no_collected_php_errors(page)
        yield
    finally:
        # Restore the minimalData defaults : a following module that skips the
        # scenario reload would otherwise inherit the markup templates.
        try:
            ensure_gm_login(page, PHP_BASE_URL)
            _set_config_via_ui(page, "textLocationDestroyed", 'Le lieu %s a été détruit selon votre bon vouloir.')
            _set_config_via_ui(page, "textLocationPillaged", "Le lieu %s a été pillé, mais nous n'avons pas pu le détruire.")
            _set_config_via_ui(page, "textLocationNotDestroyed", "Le lieu %s n'a pas été détruit, nos excuses.")
        except Exception:
            # best-effort teardown — never mask the original setup/test failure
            pass
        context.close()


class TestBasesSortControl:
    def test_sort_control_present_with_whitelisted_values(self, page: Page):
        ensure_gm_login(page, PHP_BASE_URL)
        safe_goto(page, f"{PHP_BASE_URL}/zones/management_bases.php")
        assert page.locator("form[data-bases-sort='1']").count() == 1
        select = page.locator("select[name='sort']")
        values = [opt.get_attribute("value") for opt in select.locator("option").all()]
        assert values == ["date", "location", "attacker"], (
            f"expected exactly the whitelisted sort values in order; got {values}"
        )
        assert page.locator("a[data-bases-sort-reset='1']").count() == 1
        # Sanity: both seeded rows are actually present by name (per-row
        # locator pattern, per test_zones_management_zones_e2e.py).
        assert _row_locator_by_name(page, "Echo-Base").count() == 1
        assert _row_locator_by_name(page, "Foxtrot-Outpost").count() == 1

    def test_default_order_is_id_descending(self, page: Page):
        """Non-regression test for the whole change: with no ?sort= the
        table must render exactly as it did before this feature shipped."""
        ensure_gm_login(page, PHP_BASE_URL)
        safe_goto(page, f"{PHP_BASE_URL}/zones/management_bases.php")
        rows = _scrape_bases_table(page)
        ids = [r['id'] for r in rows]
        assert len(ids) >= 2, "expected at least the 2 seeded rows"
        assert ids == sorted(ids, reverse=True), (
            f"default order (no ?sort=) should be ID descending; got {ids}"
        )

    def test_sort_by_location_orders_base_column(self, page: Page):
        ensure_gm_login(page, PHP_BASE_URL)
        safe_goto(page, f"{PHP_BASE_URL}/zones/management_bases.php?sort=location")
        rows = _scrape_bases_table(page)
        names = [r['base'].lower() for r in rows]
        assert names == sorted(names), (
            f"Base column should be non-decreasing case-insensitively "
            f"under sort=location; got {names}"
        )
        # Meaningful, not vacuous: default order puts the more-recent
        # (Foxtrot-Outpost/Echo) row first; location order must differ.
        default_ids = [r['id'] for r in _scrape_bases_table_after(page, "")]
        assert [r['id'] for r in rows] != default_ids, (
            "sort=location should reorder rows relative to the default order"
        )

    def test_sort_by_attacker_orders_attaquant_column(self, page: Page):
        ensure_gm_login(page, PHP_BASE_URL)
        safe_goto(page, f"{PHP_BASE_URL}/zones/management_bases.php?sort=attacker")
        rows = _scrape_bases_table(page)
        attackers = [r['attacker'].lower() for r in rows]
        assert attackers == sorted(attackers), (
            f"Attaquant column should be non-decreasing case-insensitively "
            f"under sort=attacker (Inconnu sorted as displayed, if present); "
            f"got {attackers}"
        )
        default_ids = [r['id'] for r in _scrape_bases_table_after(page, "")]
        assert [r['id'] for r in rows] != default_ids, (
            "sort=attacker should reorder rows relative to the default order"
        )

    @pytest.mark.parametrize("bogus_sort", ["bogus", "", "location;DROP"])
    def test_unknown_sort_falls_back_to_default_order(self, page: Page, bogus_sort):
        ensure_gm_login(page, PHP_BASE_URL)
        default_ids = [r['id'] for r in _scrape_bases_table_after(page, "")]
        encoded = urllib.parse.quote(bogus_sort, safe="")
        bogus_ids = [r['id'] for r in _scrape_bases_table_after(page, f"?sort={encoded}")]
        assert bogus_ids == default_ids, (
            f"unknown sort={bogus_sort!r} should fall back to default "
            f"order with no PHP error; got {bogus_ids} vs default {default_ids}"
        )

    def test_sort_is_not_sticky_across_requests(self, page: Page):
        """Pins the deliberate no-session-persistence decision -- visiting
        with no ?sort= after a sorted visit must return to default order."""
        ensure_gm_login(page, PHP_BASE_URL)
        default_ids = [r['id'] for r in _scrape_bases_table_after(page, "")]
        _scrape_bases_table_after(page, "?sort=location")
        after_ids = [r['id'] for r in _scrape_bases_table_after(page, "")]
        assert after_ids == default_ids, (
            "revisiting with no ?sort= must return to default order -- "
            "no server-side stickiness"
        )

    def test_result_text_cells_are_not_html_escaped(self, page: Page):
        """target_result_text / attacker_result_text stay raw (nl2br only,
        no htmlspecialchars) -- GM-editable config templates may
        legitimately carry markup. Catches an over-eager future hardening
        pass that wraps them in htmlspecialchars after nl2br()."""
        ensure_gm_login(page, PHP_BASE_URL)
        rows = _scrape_bases_table_after(page, "")
        assert rows
        # Only rows whose text produced a line break can tell raw from escaped.
        cells = [c for r in rows for c in (r['target_html'], r['attacker_html'])
                 if "<br" in c or "&lt;br" in c]
        if not cells:
            pytest.skip(
                "no logged result text contains a line break, so nl2br() is a "
                "no-op here and the raw-vs-escaped distinction is unobservable"
            )
        for cell in cells:
            assert "&lt;br" not in cell, (
                f"result-text cell must not HTML-escape its line break; got {cell!r}"
            )


def _scrape_bases_table_after(page, query_suffix):
    safe_goto(page, f"{PHP_BASE_URL}/zones/management_bases.php{query_suffix}")
    return _scrape_bases_table(page)
