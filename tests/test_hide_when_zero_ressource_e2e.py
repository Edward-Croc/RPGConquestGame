"""Playwright E2E tests for the hide_when_zero ressource_config flag.

Strict threshold (locked 2026-06-19 pt2 in tests/AUDIT_issue_67.md §9):
    a row is hidden iff hide_when_zero AND amount==0 AND amount_stored==0
    AND end_turn_gain==0. Any non-zero in those three surfaces it.

TestConfig seeds (added in this PR):
  Bronze   — hide_when_zero=0 (always visible)
  Platinum — hide_when_zero=1 (hidden when all-zero)

  Alpha: Bronze=0, Platinum=0 → Platinum hidden, Bronze visible.
  Beta:  Bronze=0, Platinum=5 → both visible (Platinum non-zero).

Run:
    python3 -m pytest tests/test_hide_when_zero_ressource_e2e.py -v
"""
import re

import pytest

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, load_minimal_data, load_scenario_via_admin, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def setup_testconfig(browser):
    if DB_AVAILABLE:
        load_minimal_data()
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
    yield


def _resolve_controller_id(page, lastname):
    safe_goto(page, f"{PHP_BASE_URL}/base/accueil.php")
    page.wait_for_load_state("load")
    return page.locator(
        f"select[name='controller_id'] option:has-text('{lastname}')"
    ).first.get_attribute("value")


def _set_active(page, lastname):
    cid = _resolve_controller_id(page, lastname)
    safe_goto(page, f"{PHP_BASE_URL}/base/accueil.php?controller_id={cid}")
    page.wait_for_load_state("load")
    return cid


def _ressources_html(page):
    safe_goto(page, f"{PHP_BASE_URL}/ressources/view.php")
    page.wait_for_load_state("load")
    return page.content()


def _summary_table_names(html):
    """Pull the ressource names from the 'Mes ressources' summary table."""
    table_match = re.search(
        r'<h3 class="title is-5">Mes ressources</h3>.*?</table>',
        html, re.DOTALL,
    )
    if not table_match:
        return []
    cells = re.findall(r"<td>([^<]+)</td>\s*<td", table_match.group(0))
    return cells


def test_alpha_platinum_hidden_bronze_visible(browser, base_url):
    """Alpha holds 0 of both Bronze (hide_when_zero=0) and Platinum
    (hide_when_zero=1). Platinum row must be filtered out of BOTH render
    surfaces (the Ressources page summary table AND the Vos Ressources
    block on the faction page); Bronze stays visible everywhere because
    its config flag is off. Confirms the shared filterVisibleRessources
    helper is wired into both call sites."""
    ctx = browser.new_context()
    page = ctx.new_page()
    register_php_error_listener(page)
    ensure_gm_login(page, base_url)
    alpha_cid = _set_active(page, "Alpha")

    # Surface 1: ressources/view.php — summary table
    html = _ressources_html(page)
    names = _summary_table_names(html)
    assert "Bronze" in names, (
        f"Bronze has hide_when_zero=0 so it must appear even at amount=0. "
        f"Got: {names}"
    )
    assert "Platinum" not in names, (
        f"Platinum has hide_when_zero=1 AND Alpha holds 0/0/0 → it must "
        f"be filtered out of the Ressources page. Got: {names}"
    )

    # Surface 2: controllers/view.php — Vos Ressources block
    safe_goto(page, f"{PHP_BASE_URL}/controllers/view.php?controller_id={alpha_cid}")
    page.wait_for_load_state("load")
    faction_html = page.content()
    block = re.search(r"Vos Ressources\s*:?.*?</div>", faction_html, re.DOTALL)
    block_html = block.group(0) if block else faction_html
    assert "Platinum" not in block_html, (
        "Platinum is hide_when_zero=1 AND Alpha holds 0/0/0 → must be "
        "filtered out of the controllers/view.php Vos Ressources block."
    )

    assert_no_collected_php_errors(page)
    ctx.close()


def test_beta_platinum_visible_when_nonzero(browser, base_url):
    """Beta holds Platinum=5 (amount>0). Even with hide_when_zero=1, the
    row must surface."""
    ctx = browser.new_context()
    page = ctx.new_page()
    ensure_gm_login(page, base_url)
    _set_active(page, "Beta")
    html = _ressources_html(page)
    ctx.close()

    names = _summary_table_names(html)
    assert "Platinum" in names, (
        f"Beta holds Platinum=5 → hide_when_zero filter must NOT hide it. "
        f"Got: {names}"
    )


def test_receive_side_unaffected_by_filter(browser, base_url):
    """Gift mechanic writes controller_ressources directly, ignoring the
    render filter. Sequence: Beta gifts Platinum=5 to Alpha; Alpha then
    sees Platinum in their summary table (now amount=5, no longer
    suppressed)."""
    ctx = browser.new_context()
    page = ctx.new_page()
    register_php_error_listener(page)
    ensure_gm_login(page, base_url)

    alpha_cid = _resolve_controller_id(page, "Alpha")
    _set_active(page, "Beta")

    # Resolve Platinum ressource_id from Beta's gift dropdown.
    safe_goto(page, f"{base_url}/ressources/view.php")
    page.wait_for_load_state("load")
    platinum_value = page.locator(
        "select#giftRessourceSelect option:has-text('Platinum')"
    ).first.get_attribute("value")
    assert platinum_value, "Beta must see Platinum in the gift dropdown"

    # POST the gift via the same form that view.php renders.
    page.request.post(
        f"{base_url}/ressources/action.php",
        form={
            "ressource_id": platinum_value,
            "target_controller_id": alpha_cid,
            "amount": "3",
            "giftRessource": "Donner",
        },
    )

    # Alpha now holds Platinum=3 → must appear.
    _set_active(page, "Alpha")
    html = _ressources_html(page)
    assert_no_collected_php_errors(page)
    ctx.close()

    names = _summary_table_names(html)
    assert "Platinum" in names, (
        f"After receiving a Platinum gift, Alpha's amount>0 → the row "
        f"must surface despite hide_when_zero=1. Got: {names}"
    )
