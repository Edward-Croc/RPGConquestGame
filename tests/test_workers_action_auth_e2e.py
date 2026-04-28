"""Playwright E2E tests for /workers/action.php auth + ownership guard.

Guard at the top of workers/action.php:
  - Must be logged in (session.logged_in + session.user_id)
  - Privileged users (gm) bypass ownership entirely
  - Non-privileged: session.controller.id must own the target worker_id (any
    controller_worker row, covering primary + double-agent links)
  - Otherwise: HTTP 403 + exit() before any state-mutating code runs

Test users (TestConfig):
  - gm / orga              — privileged, linked to all 7 controllers
  - single_player / test   — not privileged, linked to Alpha only
                             (auto-selected on login, loginForm.php:95-97)
  - delta_player / test    — not privileged, linked to Delta only;
                             added to seed a dead-worker scenario for the
                             trace/dead state-mutation block.

Test workers (TestConfig):
  - Searcher_1   — Alpha, Alpha-Investigation, passive
  - Bystander_1  — Beta,  Alpha-Investigation, passive
  - Inv_Def_2    — Delta, Beta-Combat — killed by Inv_Atk_2 after end-turn

UI-only / prod-DEMO-runnable.

Run:
    python3 -m pytest tests/test_workers_action_auth_e2e.py -v
"""
import pytest
from playwright.sync_api import Page

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, load_minimal_data, login_as, safe_goto,
    ui_worker_id, ui_workers_by_lastname,
    ui_attack, end_turn,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def setup_testconfig(browser):
    """Load TestConfig once per module so the test workers exist."""
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


def _resolve_worker_id(browser, base_url, lastname):
    """Helper: spin up a gm context, look up a worker_id, tear down."""
    ctx = browser.new_context()
    page = ctx.new_page()
    ensure_gm_login(page, base_url)
    wid = ui_worker_id(page, lastname, base_url=base_url)
    ctx.close()
    return wid


# ---------------------------------------------------------------------------
# 1. Anonymous direct GET — must redirect to loginForm.php
# ---------------------------------------------------------------------------

def test_anonymous_get_redirects_to_login(browser, base_url):
    """No session → action.php redirects (302) to /connection/loginForm.php
    rather than 403, matching the existing pattern used by admin pages.
    Playwright follows the redirect by default; assert the final URL lands
    on the login form."""
    wid = _resolve_worker_id(browser, base_url, "Searcher_1")

    ctx = browser.new_context()
    page = ctx.new_page()
    page.goto(f"{base_url}/workers/action.php?worker_id={wid}")
    assert "loginForm.php" in page.url, (
        f"Anonymous GET on /workers/action.php must redirect to the login form; "
        f"landed on {page.url}"
    )
    ctx.close()


# ---------------------------------------------------------------------------
# 2. Non-privileged user, NOT owner — must 403
# ---------------------------------------------------------------------------

def test_non_owner_returns_403(browser, base_url):
    """single_player auto-selects Alpha on login; Bystander_1 is Beta's worker."""
    bystander_wid = _resolve_worker_id(browser, base_url, "Bystander_1")

    ctx = browser.new_context()
    page = ctx.new_page()
    login_as(page, base_url, "single_player", "test")
    response = page.goto(f"{base_url}/workers/action.php?worker_id={bystander_wid}")
    assert response is not None
    assert response.status == 403, \
        f"Non-owner action on a foreign worker must 403; got {response.status}"
    ctx.close()


# ---------------------------------------------------------------------------
# 3. Non-privileged user, IS owner — must succeed
# ---------------------------------------------------------------------------

def test_owner_acts_on_own_worker(browser, base_url):
    """single_player auto-selects Alpha; Searcher_1 is Alpha's worker."""
    searcher_wid = _resolve_worker_id(browser, base_url, "Searcher_1")

    ctx = browser.new_context()
    page = ctx.new_page()
    login_as(page, base_url, "single_player", "test")
    response = page.goto(f"{base_url}/workers/action.php?worker_id={searcher_wid}")
    assert response is not None
    assert response.status == 200, \
        f"Owner action on own worker must succeed; got {response.status}"
    ctx.close()


# ---------------------------------------------------------------------------
# 4. Privileged gm — bypasses ownership
# ---------------------------------------------------------------------------

def test_gm_privileged_bypasses_ownership(browser, base_url):
    """gm has is_privileged=1 and is linked to all controllers; guard must
    let them through regardless of whose worker is targeted."""
    bystander_wid = _resolve_worker_id(browser, base_url, "Bystander_1")

    ctx = browser.new_context()
    page = ctx.new_page()
    login_as(page, base_url, "gm", "orga")
    response = page.goto(f"{base_url}/workers/action.php?worker_id={bystander_wid}")
    assert response is not None
    assert response.status == 200, \
        f"Privileged gm must bypass ownership; got {response.status}"
    ctx.close()


# ---------------------------------------------------------------------------
# 5. State-mutation guard — 403'd attack must NOT change action_choice
# ---------------------------------------------------------------------------

def test_anonymous_attack_does_not_mutate_state(browser, base_url):
    """Belt-and-buckle: even though the guard exit()s before activateWorker,
    verify the attack URL anonymously really doesn't move worker_actions
    out of 'passive'. Reads pre/post action_choice via UI."""
    searcher_wid = _resolve_worker_id(browser, base_url, "Searcher_1")
    bystander_wid = _resolve_worker_id(browser, base_url, "Bystander_1")

    # Pre-state via gm
    gm_ctx = browser.new_context()
    gm_page = gm_ctx.new_page()
    ensure_gm_login(gm_page, base_url)
    pre_rows = ui_workers_by_lastname(gm_page, "Searcher_1", base_url=base_url)
    pre_live = [r for r in pre_rows if r['action_choice'] != 'trace']
    assert pre_live, "Searcher_1 should have a live row pre-attempt"
    pre_action = pre_live[0]['action_choice']
    gm_ctx.close()

    # Anonymous attack attempt — must be intercepted before any mutation runs.
    # Anonymous flow now redirects to loginForm.php (302) rather than 403'ing.
    anon_ctx = browser.new_context()
    anon_page = anon_ctx.new_page()
    attack_url = (
        f"{base_url}/workers/action.php"
        f"?worker_id={searcher_wid}&attack=Attaquer"
        f"&enemy_worker_id=worker_{bystander_wid}"
    )
    anon_page.goto(attack_url)
    assert "loginForm.php" in anon_page.url, (
        f"Anonymous attack must redirect to login; landed on {anon_page.url}"
    )
    anon_ctx.close()

    # Post-state via gm
    gm_ctx2 = browser.new_context()
    gm_page2 = gm_ctx2.new_page()
    ensure_gm_login(gm_page2, base_url)
    post_rows = ui_workers_by_lastname(gm_page2, "Searcher_1", base_url=base_url)
    post_live = [r for r in post_rows if r['action_choice'] != 'trace']
    assert post_live, "Searcher_1 should still have a live row post-attempt"
    post_action = post_live[0]['action_choice']
    gm_ctx2.close()

    assert post_action == pre_action, (
        f"403'd attack must NOT mutate state; action_choice "
        f"changed from '{pre_action}' to '{post_action}'"
    )


# ---------------------------------------------------------------------------
# Inactive-state block
#
# After ownership passes, workers/action.php blocks mutating actions on
# 'trace' and 'dead' workers. Carve-out: 'transform' is allowed on dead
# (vampire resurrect / ghost path). The dead+transform branch is the most
# likely to silently regress (a typo in `!isset($_GET['transform'])` or a
# refactor that drops the carve-out), so it gets the regression test.
# Trace-block has no carve-out — single condition, code-review covers it.
# ---------------------------------------------------------------------------

class TestInactiveStateBlock:
    """Belt-and-buckle for the dead-worker carve-out: attack on a dead worker
    must 403, but transform on the same dead worker must pass through (vampire
    resurrect path)."""

    @pytest.fixture(scope="class", autouse=True)
    def kill_inv_def_2(self, browser, base_url):
        """Seed: Inv_Atk_2 (Charlie) attacks Inv_Def_2 (Delta), end-turn → kill.
        Mirrors the existing combat-fixture pattern from test_agent_combat_e2e."""
        ctx = browser.new_context()
        page = ctx.new_page()
        ensure_gm_login(page, base_url)
        ui_attack(page, "Inv_Atk_2", "Inv_Def_2", base_url=base_url)
        end_turn(page, base_url=base_url)
        ctx.close()
        yield

    def test_dead_worker_blocks_attack_allows_transform(self, browser, base_url):
        """Same dead Inv_Def_2 — attack 403s, transform passes through (200).
        Logged in as delta_player (non-privileged, owns Delta) so the
        inactive-state block actually runs (privileged users bypass)."""
        wid = _resolve_worker_id(browser, base_url, "Inv_Def_2")

        # 1. Mutating-action that is NOT transform → must 403.
        ctx_attack = browser.new_context()
        page_attack = ctx_attack.new_page()
        login_as(page_attack, base_url, "delta_player", "test")
        attack_url = (
            f"{base_url}/workers/action.php?worker_id={wid}&attack=Attaquer"
            f"&enemy_worker_id=worker_1"
        )
        response = page_attack.goto(attack_url)
        assert response is not None
        assert response.status == 403, (
            f"Mutating non-transform action on a dead worker must 403; "
            f"got {response.status}"
        )
        ctx_attack.close()

        # 2. Same dead worker, transform action — must pass the block.
        # The downstream upgradeWorker call may itself fail (no real
        # transformation power supplied), but that happens AFTER the block;
        # for the block-regression assertion, status != 403 is enough.
        ctx_xform = browser.new_context()
        page_xform = ctx_xform.new_page()
        login_as(page_xform, base_url, "delta_player", "test")
        transform_url = f"{base_url}/workers/action.php?worker_id={wid}&transform=1"
        response = page_xform.goto(transform_url)
        assert response is not None
        assert response.status != 403, (
            f"Transform on a dead worker must pass the block (vampire "
            f"resurrect carve-out); got 403"
        )
        ctx_xform.close()
