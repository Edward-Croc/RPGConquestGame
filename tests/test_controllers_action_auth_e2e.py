"""Playwright E2E tests for /controllers/action.php auth + ownership guard.

Guard at the top of controllers/action.php:
  - Must be logged in (session.logged_in) — anonymous → loginForm.php redirect
  - Bare GET (no mutating action key) passes through to view.php so the
    "select foreign controller via accueil + render intel" flow keeps working
  - On any of the 6 mutating action keys
    (createBase, moveBase, attackLocation, repairLocation,
     giftInformationAgent, giftInformationLocation):
      - Privileged users (gm) bypass ownership entirely
      - Non-privileged: session.controller.id must equal URL controller_id
      - Otherwise: HTTP 403 + exit() before any state-mutating handler runs

Test users (TestConfig, shared with test_workers_action_auth_e2e.py):
  - gm / orga              — privileged, linked to all 7 controllers
  - single_player / test   — not privileged, linked to Alpha only
                             (auto-selected on login, loginForm.php:95-97)

UI-only / prod-DEMO-runnable.

Run:
    python3 -m pytest tests/test_controllers_action_auth_e2e.py -v
"""
import pytest
from playwright.sync_api import Page

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, load_minimal_data, login_as, safe_goto,
    ui_controller_id,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def setup_testconfig(browser):
    """Load TestConfig once per module so the test controllers exist."""
    if DB_AVAILABLE:
        load_minimal_data()
    context = browser.new_context()
    page = context.new_page()
    ensure_gm_login(page, PHP_BASE_URL)
    safe_goto(page, f"{PHP_BASE_URL}/base/admin.php")
    page.wait_for_load_state("networkidle")
    page.locator("select[name='config_name']").select_option("TestConfig")
    page.locator("input[name='submit'][value='Submit']").click()
    page.wait_for_timeout(5000)
    page.wait_for_load_state("load", timeout=90000)
    context.close()
    yield


def _resolve_controller_id(browser, base_url, lastname):
    """Helper: spin up a gm context, look up a controller_id, tear down."""
    ctx = browser.new_context()
    page = ctx.new_page()
    ensure_gm_login(page, base_url)
    cid = ui_controller_id(page, lastname, base_url=base_url)
    ctx.close()
    return cid


# ---------------------------------------------------------------------------
# 1. Anonymous direct GET — must redirect to loginForm.php
# ---------------------------------------------------------------------------

def test_anonymous_get_redirects_to_login(browser, base_url):
    """No session → action.php redirects (302) to /connection/loginForm.php
    rather than 403, matching the existing pattern used by admin pages and
    workers/action.php."""
    alpha_cid = _resolve_controller_id(browser, base_url, "Alpha")

    ctx = browser.new_context()
    page = ctx.new_page()
    page.goto(f"{base_url}/controllers/action.php?controller_id={alpha_cid}")
    assert "loginForm.php" in page.url, (
        f"Anonymous GET on /controllers/action.php must redirect to the login form; "
        f"landed on {page.url}"
    )
    ctx.close()


# ---------------------------------------------------------------------------
# 2. Non-privileged user, NOT owner — must 403
# ---------------------------------------------------------------------------

def test_non_owner_mutation_returns_403(browser, base_url):
    """single_player auto-selects Alpha on login; attempting a mutating
    action (createBase) on Beta's controller_id must 403. Bare GET on a
    foreign controller_id is allowed (view.php controls intel filtering);
    only mutations are gated."""
    beta_cid = _resolve_controller_id(browser, base_url, "Beta")

    ctx = browser.new_context()
    page = ctx.new_page()
    login_as(page, base_url, "single_player", "test")
    # createBase is the cheapest mutating key — zone_id=1 is bogus but the
    # 403 fires before the handler runs.
    response = page.goto(
        f"{base_url}/controllers/action.php"
        f"?controller_id={beta_cid}&createBase=1&zone_id=1"
    )
    assert response is not None
    assert response.status == 403, \
        f"Non-owner mutation on a foreign controller must 403; got {response.status}"
    ctx.close()


# ---------------------------------------------------------------------------
# 3. Non-privileged user, IS owner — must succeed
# ---------------------------------------------------------------------------

def test_owner_acts_on_own_controller(browser, base_url):
    """single_player auto-selects Alpha; hitting Alpha's own controller_id
    must render the page (200)."""
    alpha_cid = _resolve_controller_id(browser, base_url, "Alpha")

    ctx = browser.new_context()
    page = ctx.new_page()
    login_as(page, base_url, "single_player", "test")
    response = page.goto(f"{base_url}/controllers/action.php?controller_id={alpha_cid}")
    assert response is not None
    assert response.status == 200, \
        f"Owner action on own controller must succeed; got {response.status}"
    ctx.close()


# ---------------------------------------------------------------------------
# 4. Privileged gm — bypasses ownership
# ---------------------------------------------------------------------------

def test_gm_privileged_bypasses_ownership(browser, base_url):
    """gm has is_privileged=1 and is linked to all controllers; guard must
    let them through regardless of which controller_id is targeted."""
    beta_cid = _resolve_controller_id(browser, base_url, "Beta")

    ctx = browser.new_context()
    page = ctx.new_page()
    login_as(page, base_url, "gm", "orga")
    response = page.goto(f"{base_url}/controllers/action.php?controller_id={beta_cid}")
    assert response is not None
    assert response.status == 200, \
        f"Privileged gm must bypass ownership; got {response.status}"
    ctx.close()
