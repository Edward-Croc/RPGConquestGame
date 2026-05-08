"""Regression test for the undefined-`save_to_json` warning at
zones/functions.php:665 (management_locations toggle path).

Surfaced 2026-05-06 in production when toggling destruction on
`chemin de l'illumination` via /zones/management_locations.php under
Japan1555-SQL config:

    Warning: Undefined array key "save_to_json" in zones/functions.php
    on line 665

Root cause: `updateLocation` reads
`$update_location_data['save_to_json']` unconditionally. Some scenario
seeds put `save_to_json` only inside a nested `future_location` block;
when `update_location` lacks the key at its top level the read warns,
even though the function falls through correctly to the elseif
`future_location` branch.

Fix: 1-line null-coalesce. Behaviour unchanged; only the warning
suppressed.

Self-contained TestConfig fixture: a dedicated unowned seed location
`Test-Future-Location` carries the `update_location` + nested
`future_location` shape (no top-level `save_to_json`). Toggling its
destruction via /zones/management_locations.php exercises the path
that warned pre-fix.

Run:
    python3 -m pytest tests/test_zones_management_locations_e2e.py -v
"""
import re

import pytest
from playwright.sync_api import Page

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, load_minimal_data, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
)


# Seeded in setupTestConfig_locations.csv with an `update_location`
# block that carries a nested `future_location` (no top-level
# `save_to_json`) — the shape that triggered the warning before the fix.
_FUTURE_LOCATION_SHAPED_NAME = "Test-Future-Location"


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def load_test_config(browser):
    """Load TestConfig so `Test-Future-Location` (the
    future_location-shaped seed) is available for the toggle test."""
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
    page.locator("input[name='submit'][value='Submit']").click()
    page.wait_for_timeout(5000)
    page.wait_for_load_state("load", timeout=90000)
    assert_no_collected_php_errors(page)
    context.close()
    yield


def _toggle_destruction_first_match(page, name_substring):
    """Find the first management_locations row whose <h3> contains
    `name_substring` and submit its toggle_destruction form via
    JS (the button sits inside `display:none;`, so click(force=True)
    fails — page.evaluate triggers the form submission directly)."""
    safe_goto(page, f"{PHP_BASE_URL}/zones/management_locations.php")
    page.wait_for_load_state("load")
    html = page.content()
    m = re.search(
        rf'<h3>[^<]*{re.escape(name_substring)}[^<]*\(discovery[^<]+</h3>'
        rf'.*?name="toggle_destruction"\s+value="(\d+)"',
        html,
        re.DOTALL,
    )
    if not m:
        raise AssertionError(
            f"toggle_destruction form for a location matching "
            f"'{name_substring}' not found on management_locations.php"
        )
    location_id = int(m.group(1))
    page.evaluate(
        f"""
        const inp = document.querySelector(
            'input[name="toggle_destruction"][value="{location_id}"]'
        );
        if (inp && inp.form) inp.form.submit();
        """
    )
    page.wait_for_load_state("load")
    return location_id


class TestSaveToJsonNullCoalesceFix:
    """zones/functions.php:665 read save_to_json without a null-coalesce
    check. Toggling a `future_location`-shaped location warned on the
    first toggle. The fix swaps to `($update_location_data['save_to_json']
    ?? '') == "TRUE"` — behaviour unchanged, warning suppressed."""

    def test_first_toggle_no_php_warning(self, browser):
        """Navigate to management_locations, submit the
        toggle_destruction form for `Test-Future-Location` (its
        update_location block carries only a nested future_location, no
        top-level save_to_json), and assert no `<b>Warning</b>` marker
        fires across the whole page lifetime (covered by the
        register_php_error_listener observer)."""
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        location_id = _toggle_destruction_first_match(
            page, _FUTURE_LOCATION_SHAPED_NAME
        )

        # Sanity: a location was found + toggled.
        assert location_id > 0, (
            f"Expected a positive location_id from the toggle, got {location_id}"
        )

        # The listener catches PHP_ERROR_MARKERS on every response in
        # the page lifetime. Under the pre-fix code, the toggle response
        # contained `<b>Warning</b> Undefined array key "save_to_json"`.
        assert_no_collected_php_errors(page)

        context.close()
