"""Scenario end-turn smoke tests.

Each class loads a full scenario CSV via the admin form and runs end_turn,
asserting the response is PHP-error-free and that the turn counter
advanced past 0. This is the broadest possible regression net for
end-turn behaviour under real (non-TestConfig) scenarios — config keys
specific to each scenario (gain_rules, location_types, faction layout,
etc.) all get exercised in a single pass.

Both scenarios run sequentially. Downstream test files reload TestConfig
in their own module fixture, so this test does not need to clean up.

Run:
    python3 -m pytest tests/test_scenario_smoke_e2e.py -v
"""
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


def _run_end_turn_smoke(browser, scenario_name):
    """Common: load scenario, restore the gm user (scenario CSVs do not
    include it — only minimalData.sql does), end_turn, return the HTML."""
    load_scenario_via_admin(browser, PHP_BASE_URL, scenario_name)
    if DB_AVAILABLE:
        load_minimal_data()
    ctx = browser.new_context()
    page = ctx.new_page()
    register_php_error_listener(page)
    ensure_gm_login(page, PHP_BASE_URL)
    end_turn(page, PHP_BASE_URL)
    html = page.content()
    assert_no_collected_php_errors(page)
    ctx.close()
    return html


# End-turn checks beyond the baseline end_turn() helper. The helper only
# guards the html_errors=On markers (<b>Warning</b> / <b>Fatal error</b>);
# most failures in this codebase are caught PDOExceptions that echo
# plain text like `funcName(): X Failed: <message><br />` which slip
# through unnoticed. These patterns close that gap.
EOT_FAILURE_PATTERNS = [
    "Failed:",          # __FUNCTION__."(): ... Failed: " . $e->getMessage()
    "<b>Notice</b>",    # PHP notices (undefined index, etc.)
    "Stack trace",      # uncaught PDOException dumps the trace
    "PDOException",     # raw exception bleed-through
]


def _assert_eot_clean(html, context=""):
    for pattern in EOT_FAILURE_PATTERNS:
        if pattern in html:
            # Surface a short window around the match for the failure msg
            idx = html.index(pattern)
            window = html[max(0, idx - 80):idx + 200].replace("\n", " ")
            raise AssertionError(
                f"EOT failure marker {pattern!r} in response{' ('+context+')' if context else ''}: …{window}…"
            )


def _scrape_new_turn(html):
    """endTurn.php emits several <h2> tags during EOT processing; the
    "new turn" header is the LAST <h2> matching `<word>: <digits>` (e.g.
    `<h2> Semaine: 2 </h2>`)."""
    matches = re.findall(r'<h2>\s*(\S+?)\s*:\s*(\d+)\s*</h2>', html)
    return int(matches[-1][1]) if matches else None


class TestJapon1555CSVEndTurnSmoke:
    """Full Japon1555CSV scenario (Koku gain_rules + location_types
    temple/fortress tags + multi-faction config) ends turn cleanly."""

    @pytest.fixture(scope="class", autouse=True)
    def smoke_state(self, browser):
        html = _run_end_turn_smoke(browser, "Japon1555CSV")
        type(self)._html = html
        yield

    def test_no_failure_markers(self):
        _assert_eot_clean(self._html, "Japon1555CSV")

    def test_turn_advanced(self):
        new_turn = _scrape_new_turn(self._html)
        if new_turn is None:
            h2s = re.findall(r'<h2>[^<]*</h2>', self._html)
            raise AssertionError(
                f"Japon1555 endTurn.php did not render a turn label; h2 tags found: {h2s[:6]}"
            )
        assert new_turn >= 1, f"Expected new turn >= 1; got {new_turn}"


class TestVampire1966CSVEndTurnSmoke:
    """Full Vampire1966CSV scenario (different faction layout +
    textForZoneType='quartier') ends turn cleanly."""

    @pytest.fixture(scope="class", autouse=True)
    def smoke_state(self, browser):
        html = _run_end_turn_smoke(browser, "Vampire1966CSV")
        type(self)._html = html
        yield

    def test_no_failure_markers(self):
        _assert_eot_clean(self._html, "Vampire1966CSV")

    def test_turn_advanced(self):
        new_turn = _scrape_new_turn(self._html)
        assert new_turn is not None, "endTurn.php did not render the turn label"
        assert new_turn >= 1, f"Expected new turn >= 1; got {new_turn}"
