"""End-to-end tests for issue #63 — investigation report redundancy.

Verifies that re-investigating a previously-detected target on a later
turn collapses the report into a `<details>` fold instead of repeating
the same slabs, AND that artefact lists stay visible outside any fold.

Module fixture: load TestConfig + 2 EOTs.
- Turn 1: Searcher_1 investigates Alpha-Investigation
  + Artefact_Searcher_Echo investigates Theta-Artefacts.
  CKE / CKL seed during turn 1's EOT.
- Turn 2: both stay investigating (`continuing_investigate_action=1`),
  see the same targets again. The new variant-aware reports should now
  emit the "still here" `<details>` blocks instead of full text.

Run:
    python3 -m pytest tests/test_investigate_report_redundancy_e2e.py -v
"""
import pytest
from playwright.sync_api import Page, expect

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, load_minimal_data, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
    end_turn, _cached_wid, ui_worker_controller_id,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def load_scenario_two_eots(browser):
    """Load TestConfig and run two EOTs so the second turn's report
    exercises the variant-aware code paths (turn 1 seeds CKE/CKL, turn 2
    sees prior state)."""
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

    for _ in range(2):
        end_turn(page, base_url=PHP_BASE_URL)
        assert_no_collected_php_errors(page)

    context.close()
    yield


def _worker_action_page(page: Page, lastname: str, base_url: str):
    """Navigate as gm to a worker's action page so its multi-turn reports render."""
    ensure_gm_login(page, base_url)
    ctrl_id = ui_worker_controller_id(page, lastname, base_url=base_url)
    assert ctrl_id, f"Worker {lastname} has no controller"
    safe_goto(page, f"{base_url}/base/accueil.php?controller_id={ctrl_id}&chosir=Choisir")
    page.wait_for_load_state("networkidle")
    wid = _cached_wid(page, lastname)
    assert wid, f"Worker {lastname} not found"
    safe_goto(page, f"{base_url}/workers/action.php?worker_id={wid}")
    page.wait_for_load_state("load")


def _investigation_section_html(html: str) -> str:
    """Slice the HTML between the 'Mes investigations' h4 header and the next h4."""
    start = html.find("Mes investigations")
    if start < 0:
        return ""
    next_h4 = html.find("<h4", start + 1)
    return html[start:next_h4] if next_h4 > 0 else html[start:]


def _recherches_section_html(html: str) -> str:
    """Slice the HTML between the 'Mes recherches' h4 header and the next h4."""
    start = html.find("Mes recherches")
    if start < 0:
        return ""
    next_h4 = html.find("<h4", start + 1)
    return html[start:next_h4] if next_h4 > 0 else html[start:]


# ---------------------------------------------------------------------------
# Agent re-discovery: "still here" fold
# ---------------------------------------------------------------------------

class TestAgentStillHere:
    """After 2 EOTs, Searcher_1 has investigated the same Alpha-Investigation
    targets twice. The second turn's report should collapse into the
    "still here" `<details>` variant."""

    def test_still_here_template_appears(self, page: Page, base_url):
        _worker_action_page(page, "Searcher_1", base_url)
        html = page.content()
        section = _investigation_section_html(html)
        assert section, "Searcher_1 should have a 'Mes investigations' section after 2 EOTs"
        assert "est toujours présent" in section, (
            "Expected 'still here' template in Searcher_1's report section; "
            f"got: {section[:600]}"
        )

    def test_still_here_uses_details_fold(self, page: Page, base_url):
        _worker_action_page(page, "Searcher_1", base_url)
        html = page.content()
        section = _investigation_section_html(html)
        assert "<details>" in section.lower(), (
            f"Expected <details> fold for repeat investigation; got: {section[:600]}"
        )

    def test_click_summary_reveals_folded_slabs(self, page: Page, base_url):
        _worker_action_page(page, "Searcher_1", base_url)
        summary = (
            page.locator("details summary")
            .filter(has_text="est toujours présent")
            .first
        )
        expect(summary).to_be_visible()
        # Closed details body must not be visible inner_text; use text_content
        # instead per CODE_KNOWLEDGE §10 #20.
        details_handle = summary.locator("xpath=..")
        closed_inner = (summary.inner_text() or "").strip()
        # Open the fold then read the content
        summary.click()
        full_text = (details_handle.text_content() or "").strip()
        # Folded body should add content beyond the summary line.
        assert len(full_text) > len(closed_inner), (
            "Clicking the summary should reveal more text inside the fold; "
            f"summary='{closed_inner[:120]}' body='{full_text[:300]}'"
        )


# ---------------------------------------------------------------------------
# Regression: first-turn discovery still emits full text (no <details>)
# ---------------------------------------------------------------------------

class TestFirstTurnFullTextPreserved:
    """The turn 1 portion of the report (when CKE was empty) should still
    render as the original `<p>$slabs</p>` shape — no `<details>` wrapper
    for the first discovery."""

    def test_first_turn_paragraph_before_any_details(self, page: Page, base_url):
        _worker_action_page(page, "Searcher_1", base_url)
        html = page.content()
        section = _investigation_section_html(html)
        # The first piece of content in the report (turn 1's slabs) must
        # appear in a <p> before the first <details>. We check ordering:
        first_details = section.lower().find("<details>")
        first_p_close = section.lower().find("</p>", section.lower().find("mes investigations"))
        assert first_p_close >= 0, "Investigation section should contain a closing </p>"
        if first_details > 0:
            assert first_p_close < first_details, (
                "Turn 1's full-text slabs should close their </p> BEFORE "
                "turn 2's <details> fold opens (first discovery must stay "
                "outside any fold)."
            )


# ---------------------------------------------------------------------------
# Location-side: "still here" fold for repeat location discovery
# ---------------------------------------------------------------------------

class TestLocationStillHere:
    """Artefact_Searcher_Echo investigates Theta-Artefacts on both turns.
    The turn 2 report should show "Le lieu ... est toujours là" via
    `<details>` instead of repeating the description."""

    def test_location_still_here_template_appears(self, page: Page, base_url):
        _worker_action_page(page, "Artefact_Searcher_Echo", base_url)
        html = page.content()
        section = _recherches_section_html(html)
        if not section:
            pytest.skip("Artefact_Searcher_Echo has no 'Mes recherches' section "
                        "(possibly no enemy locations in zone) — covered by "
                        "the artefact-overlay test instead")
        assert "est toujours là" in section, (
            "Expected 'still here' template in Artefact_Searcher_Echo's "
            f"recherches section; got: {section[:600]}"
        )

    def test_location_still_here_uses_details_fold(self, page: Page, base_url):
        _worker_action_page(page, "Artefact_Searcher_Echo", base_url)
        html = page.content()
        section = _recherches_section_html(html)
        if not section:
            pytest.skip("No recherches section to inspect")
        assert "<details>" in section.lower(), (
            f"Expected <details> fold for repeat location discovery; "
            f"got: {section[:600]}"
        )


# ---------------------------------------------------------------------------
# Artefact list always visible (outside any fold)
# ---------------------------------------------------------------------------

class TestArtefactAlwaysVisible:
    """The artefact list must render OUTSIDE the `<details>` fold so the
    player never has artefact info hidden behind a summary, even on
    repeat discovery."""

    def test_artefact_marker_outside_fold(self, page: Page, base_url):
        _worker_action_page(page, "Artefact_Searcher_Echo", base_url)
        html = page.content()
        section = _recherches_section_html(html)
        if not section:
            pytest.skip("No recherches section to inspect")
        if "Ce lieu contient" not in section:
            pytest.skip("No artefact list emitted (scenario lacks artefacts "
                        "at this discovery level) — variant-only coverage "
                        "is sufficient")
        # Walk every <details>...</details> block. The artefact marker
        # must NOT appear inside any of them.
        lower = section.lower()
        cursor = 0
        while True:
            d_open = lower.find("<details>", cursor)
            if d_open < 0:
                break
            d_close = lower.find("</details>", d_open)
            assert d_close > 0, "Malformed <details> block"
            fold_body = section[d_open:d_close + len("</details>")]
            assert "Ce lieu contient" not in fold_body, (
                "Artefact list must stay VISIBLE outside any <details> fold; "
                f"found inside: {fold_body[:300]}"
            )
            cursor = d_close + 1
