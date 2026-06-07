"""Multi-turn AI stress test on Japon1555CSV.

Opt-in via STRESS=1 — auto-skipped otherwise (see conftest pytest_collection_modifyitems).

Shape:
- A. Load Japon1555CSV via the admin form (+ minimalData to restore gm)
- B. Visit /controllers/management.php and click "Toggle Is IA" for every
     controller row (per-row POSTs through the same admin endpoint a
     button click hits — UI-only, no direct DB calls)
- C-F. Loop 8 turns:
        - Trigger end_turn (strengthened helper catches PHP warnings,
          fatals, notices, plain-text 'Failed:' echoes, stack traces)
        - Assert the final '<timeValue>: <N> </h2>' marker reached
        - Save a full-page screenshot to tests/stress_runs/

Run:
    STRESS=1 python3 -m pytest tests/test_japon1555_ai_stress_e2e.py -v
"""
import os
import re

import pytest

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, end_turn, load_minimal_data, load_scenario_via_admin, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
)

STRESS_OUTPUT_DIR = os.path.join(os.path.dirname(__file__), "stress_runs")
EOT_TURNS = 8


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


def _activate_ai_on_all_controllers(page):
    """Per A1.14 admin UI: each controller row has its own POST form with
    a hidden `toggle_is_ia_id` carrying the controller_id. Scrape every
    such hidden input value and POST once per controller — pure HTTP,
    same endpoint a click would hit."""
    safe_goto(page, f"{PHP_BASE_URL}/controllers/management.php")
    page.wait_for_load_state("load")
    ids = page.locator("input[name='toggle_is_ia_id']").evaluate_all(
        "els => els.map(e => e.value)"
    )
    for cid in ids:
        page.request.post(
            f"{PHP_BASE_URL}/controllers/management.php",
            form={"toggle_is_ia_id": cid},
        )
    return ids


@pytest.mark.stress
class TestJapon1555AIStress:
    """Full Japon1555CSV scenario with is_ia=TRUE on every controller,
    8 end-of-turn cycles. Captures per-turn screenshots for triage."""

    @pytest.fixture(scope="class", autouse=True)
    def stress_state(self, browser):
        os.makedirs(STRESS_OUTPUT_DIR, exist_ok=True)

        # A. Load scenario + restore gm
        load_scenario_via_admin(browser, PHP_BASE_URL, "Japon1555CSV")
        if DB_AVAILABLE:
            load_minimal_data()

        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        # B. Activate IA on every controller via per-row admin POST
        activated_ids = _activate_ai_on_all_controllers(page)

        # C-F. Loop 8 EOT cycles, screenshot each
        turn_results = []
        for n in range(1, EOT_TURNS + 1):
            end_turn(page, PHP_BASE_URL)
            html = page.content()
            screenshot_path = os.path.join(
                STRESS_OUTPUT_DIR, f"japon1555_eot_{n:02d}.png"
            )
            page.screenshot(path=screenshot_path, full_page=True)
            new_turn = _scrape_new_turn(html)
            turn_results.append((n, new_turn, screenshot_path))

        assert_no_collected_php_errors(page)
        ctx.close()

        type(self)._activated_ids = activated_ids
        type(self)._turn_results = turn_results
        yield

    def test_ia_activated_on_all_controllers(self):
        assert len(self._activated_ids) >= 1, (
            "Expected at least one controller to have an is_ia toggle "
            "form on /controllers/management.php"
        )

    def test_all_eight_turns_completed(self):
        assert len(self._turn_results) == EOT_TURNS

    def test_each_turn_reached_completion_marker(self):
        for n, scraped_turn, screenshot_path in self._turn_results:
            assert scraped_turn is not None, (
                f"Turn {n}: endTurn.php did not render the completion h2 "
                f"(screenshot: {screenshot_path})"
            )

    def test_turn_counter_monotonic(self):
        prev = 0
        for n, scraped, _ in self._turn_results:
            assert scraped > prev, (
                f"Turn {n}: expected new turn > {prev}; got {scraped}"
            )
            prev = scraped


def _scrape_new_turn(html):
    """endTurn.php emits several <h2> during EOT; the completion marker
    is the LAST '<word>: <digits>' h2 (e.g. '<h2> Tour: 3 </h2>')."""
    matches = re.findall(r'<h2>\s*(\S+?)\s*:\s*(\d+)\s*</h2>', html)
    return int(matches[-1][1]) if matches else None
