"""Playwright end-to-end tests for the bare 'Base' scenario.

Base is the empty canvas : the schema and minimalData, and no scenario file
at all. Nothing on disk is named setupBase_* — every loader tests for its
file and skips, so the game comes up with the gm account and nothing else.

The mechanics row records which scenario was loaded, which is the only place
that information survives a reset.

Run:
    python3 -m pytest tests/test_base_scenario_e2e.py -v
"""
import pytest

from conftest import PHP_BASE_URL, ensure_gm_login

from helpers import (
    DB_AVAILABLE, clear_ui_caches, load_scenario_via_admin, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def _bare_game(browser):
    if not DB_AVAILABLE:
        pytest.skip("No local MySQL available")
    load_scenario_via_admin(browser, PHP_BASE_URL, "Base")
    yield
    # Every other file expects TestConfig : this one must put it back.
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
    clear_ui_caches()


def test_the_bare_game_opens_without_a_scenario(browser, base_url):
    """A game with no zones, no factions and no agents must still render. A
    page that assumes a controller exists would blow up here."""
    ctx = browser.new_context()
    page = ctx.new_page()
    register_php_error_listener(page)
    ensure_gm_login(page, base_url)
    safe_goto(page, f"{base_url}/base/accueil.php")
    logged = page.locator("a.logout-btn").count()
    assert_no_collected_php_errors(page)
    ctx.close()
    assert logged >= 1, "the game master must reach the home page of a bare game"


def test_the_loaded_scenario_is_recorded(browser, base_url):
    """mechanics.scenario_name is written by the loader and shown in the
    admin Mechanics table : without it nothing remembers what was loaded."""
    ctx = browser.new_context()
    page = ctx.new_page()
    register_php_error_listener(page)
    ensure_gm_login(page, base_url)
    safe_goto(page, f"{base_url}/admin/admin.php")
    html = page.content()
    assert_no_collected_php_errors(page)
    ctx.close()
    assert "scenario_name" in html, "the Mechanics table must list the column"
    assert "Base" in html, f"the loaded scenario must read Base; got {html[:200]}"


def test_the_bare_game_seeds_only_the_game_master(browser, base_url):
    """No scenario file means no player beyond the gm account minimalData
    seeds. A Base that pulled in TestConfig players would fail here."""
    ctx = browser.new_context()
    page = ctx.new_page()
    ensure_gm_login(page, base_url)
    safe_goto(page, f"{base_url}/controllers/management.php")
    usernames = page.locator("select[name='reset_player_id'] option").all_inner_texts()
    ctx.close()

    seeded = [u.strip() for u in usernames if u.strip() and not u.startswith("--")]
    assert seeded == ["gm"], f"a bare game seeds only gm; got {seeded}"
