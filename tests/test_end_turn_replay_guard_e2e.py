"""Playwright end-to-end tests for the end-of-turn replay guard.

mechanics/endTurn.php used to resolve a whole turn on a bare GET, so a
reload of the result page replayed the entire resolution and advanced
the turn a second time. It now answers only a POST carrying the
one-shot token minted beside the sidebar button, and the end-of-turn
page itself renders no trigger at all.

A single end of turn feeds every assertion below.

Run:
    python3 -m pytest tests/test_end_turn_replay_guard_e2e.py -v
"""
import re

import pytest

from conftest import PHP_BASE_URL, ensure_gm_login

from helpers import (
    DB_AVAILABLE, load_scenario_via_admin, safe_goto, ui_turn_counter,
    register_php_error_listener, assert_no_collected_php_errors,
)

REFUSAL_TEXT = "Fin de tour non déclenchée"
_TURN_H2_RE = re.compile(r":\s*(\d+)\s*$")
_EOT_URL = f"{PHP_BASE_URL}/mechanics/endTurn.php"


@pytest.fixture(scope="module", autouse=True)
def _scenario(browser):
    if not DB_AVAILABLE:
        pytest.skip("No local MySQL available")
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
    yield


class TestEndTurnReplayGuard:
    """One end of turn, driven through the real sidebar button and its
    confirmation modal, then replayed three ways."""

    @pytest.fixture(scope="class", autouse=True)
    def guard_state(self, browser):
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        try:
            ensure_gm_login(page, PHP_BASE_URL)

            # A bare GET, the shape endTurn.php used to accept.
            before = ui_turn_counter(page, PHP_BASE_URL)
            safe_goto(page, _EOT_URL)
            type(self)._get_html = page.content()
            type(self)._turn_after_get = ui_turn_counter(page, PHP_BASE_URL)

            # ui_turn_counter leaves the page on accueil, which renders the form.
            type(self)._token = page.locator(
                "#endTurnForm input[name='end_turn_token']"
            ).input_value()

            # The sidebar is width:0 until the ☰ button opens it.
            page.locator("span.openbtn").click()
            page.click("#endTurnBtn")
            page.wait_for_selector("#endTurnModal.is-active")
            page.click("#endTurnModalYes")
            page.wait_for_url(_EOT_URL, timeout=180000)
            page.wait_for_function(
                "() => Array.from(document.querySelectorAll('h2'))"
                ".some(h => /\\w+\\s*:\\s*\\d+/.test(h.textContent))",
                timeout=180000,
            )
            type(self)._resolved_html = page.content()
            type(self)._trigger_count = page.locator("#endTurnForm").count()
            type(self)._button_count = page.locator("#endTurnBtn").count()

            resolved = None
            for text in page.locator("h2").all_inner_texts():
                match = _TURN_H2_RE.search(text.strip())
                if match:
                    resolved = int(match.group(1))
            type(self)._turn_before = before
            type(self)._turn_resolved = resolved

            # The burnt token replayed, which is what an F5 re-POSTs.
            type(self)._replay_html = page.request.post(
                _EOT_URL,
                form={"end_turn_token": type(self)._token},
                timeout=180000,
            ).text()
            type(self)._turn_after_replay = ui_turn_counter(page, PHP_BASE_URL)

            # A forged token, to prove the refusal is the token and not the verb.
            type(self)._forged_html = page.request.post(
                _EOT_URL,
                form={"end_turn_token": "0" * 32},
                timeout=180000,
            ).text()
            type(self)._turn_after_forged = ui_turn_counter(page, PHP_BASE_URL)

            assert_no_collected_php_errors(page)
            yield
        finally:
            context.close()

    def test_bare_get_is_refused(self):
        assert REFUSAL_TEXT in self._get_html, (
            "A bare GET on endTurn.php was not refused"
        )
        assert self._turn_after_get == self._turn_before, (
            "A bare GET on endTurn.php resolved a turn"
        )

    def test_the_sidebar_button_resolves_one_turn(self):
        """Positive anchor: without it every refusal below could pass on a
        guard that simply blocks everything."""
        assert REFUSAL_TEXT not in self._resolved_html, (
            "A legitimate end of turn was refused"
        )
        assert self._turn_resolved == self._turn_before + 1, (
            f"End of turn announced {self._turn_resolved}, "
            f"expected {self._turn_before + 1}"
        )

    def test_result_page_offers_no_trigger(self):
        assert self._trigger_count == 0, (
            "The end-of-turn page still renders a trigger — a reload could replay it"
        )
        assert self._button_count == 0

    def test_replaying_the_burnt_token_is_refused(self):
        assert REFUSAL_TEXT in self._replay_html, (
            "Replaying the burnt token started a second end of turn"
        )
        assert self._turn_after_replay == self._turn_resolved, (
            "Replaying the burnt token advanced the turn a second time"
        )

    def test_forged_token_is_refused(self):
        assert REFUSAL_TEXT in self._forged_html, (
            "A POST carrying a forged token was accepted"
        )
        assert self._turn_after_forged == self._turn_resolved, (
            "A forged token resolved a turn"
        )
