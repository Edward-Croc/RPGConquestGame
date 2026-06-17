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
`Test-Future-Location` carries an `update_location` block with a
nested `future_location` (no top-level `save_to_json`). The two halves
of the swap also differ in `name` so each toggle visibly flips the
location's heading on /zones/management_locations.php — letting the
test assert round-trip integrity (toggles 2 and 3) end-to-end:

  Toggle 1 (initial -> ruined):    name = Test-Future-Location-Ruined
  Toggle 2 (ruined -> restored):   name = Test-Future-Location
  Toggle 3 (restored -> ruined):   name = Test-Future-Location-Ruined

`register_php_error_listener` covers the `<b>Warning</b>` assertion
across all three toggles in one go.

Run:
    python3 -m pytest tests/test_zones_management_locations_e2e.py -v
"""
import re

import pytest
from playwright.sync_api import Page

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, load_minimal_data, load_scenario_via_admin, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
)


# Two halves of the toggle swap, both seeded in
# setupTestConfig_locations.csv via update_location.name +
# update_location.future_location.name.
_INITIAL_NAME = "Test-Future-Location"
_RUINED_NAME = "Test-Future-Location-Ruined"


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def load_test_config(browser):
    """Load TestConfig so `Test-Future-Location` (the
    future_location-shaped seed) is available for the toggle test."""
    if DB_AVAILABLE:
        load_minimal_data()
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
    yield


def _location_name_via_management(page, name_substring):
    """Scrape /zones/management_locations.php and return the H3
    location-name text for the row whose name contains
    `name_substring`. Returns None if not found.

    Stripped of the trailing ` (discovery N)` suffix that the page
    renders alongside the name."""
    safe_goto(page, f"{PHP_BASE_URL}/zones/management_locations.php")
    page.wait_for_load_state("load")
    html = page.content()
    m = re.search(
        rf'<h3>([^<]*{re.escape(name_substring)}[^<]*?)\s*\(discovery[^<]+</h3>',
        html,
    )
    return m.group(1).strip() if m else None


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
    ?? '') == "TRUE"` — behaviour unchanged, warning suppressed.

    Three sequential toggles verify both: (a) no PHP warning fires on
    any of the three swaps (covered by register_php_error_listener),
    and (b) the swap genuinely round-trips — the location's name
    alternates between the initial and ruined variants without
    silent corruption of the activate_json on the second swap."""

    def test_round_trip_three_toggles_no_php_warning(self, browser):
        """Toggle Test-Future-Location three times: initial->ruined
        ->restored->ruined. After each toggle, scrape the H3 on
        management_locations.php to confirm the name flipped to the
        expected half. The PHP-error listener watches all toggle
        responses for `<b>Warning</b>` markers; the round-trip
        catches the silent-corruption-on-second-swap class of bug
        a single-toggle test would miss."""
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        # Pre-toggle: row is in its CSV-seeded state.
        name_before = _location_name_via_management(page, _INITIAL_NAME)
        assert name_before == _INITIAL_NAME, (
            f"Pre-toggle H3 should read {_INITIAL_NAME!r}; got {name_before!r}"
        )

        # Toggle 1: initial -> ruined (the swap that warned pre-fix).
        _toggle_destruction_first_match(page, _INITIAL_NAME)
        name_after_1 = _location_name_via_management(page, _INITIAL_NAME)
        assert name_after_1 == _RUINED_NAME, (
            f"After toggle 1 H3 should read {_RUINED_NAME!r}; "
            f"got {name_after_1!r}"
        )

        # Toggle 2: ruined -> restored. The activate_json now carries
        # save_to_json=TRUE at the top level; this exercises the IF
        # branch (save current state into activate_json.update_location)
        # that the elseif handled on toggle 1.
        _toggle_destruction_first_match(page, _INITIAL_NAME)
        name_after_2 = _location_name_via_management(page, _INITIAL_NAME)
        assert name_after_2 == _INITIAL_NAME, (
            f"After toggle 2 H3 should read {_INITIAL_NAME!r}; "
            f"got {name_after_2!r}"
        )

        # Toggle 3: restored -> ruined. Confirms the swap is idempotent
        # across cycles and the saved-old payload from toggle 2 is
        # still well-formed.
        _toggle_destruction_first_match(page, _INITIAL_NAME)
        name_after_3 = _location_name_via_management(page, _INITIAL_NAME)
        assert name_after_3 == _RUINED_NAME, (
            f"After toggle 3 H3 should read {_RUINED_NAME!r}; "
            f"got {name_after_3!r}"
        )

        # The listener catches PHP_ERROR_MARKERS on every response in
        # the page lifetime — covers all three toggle responses in
        # a single assertion.
        assert_no_collected_php_errors(page)

        context.close()
