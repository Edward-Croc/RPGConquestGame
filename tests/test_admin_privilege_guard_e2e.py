"""Playwright E2E coverage of the `is_privileged` guard on the two
admin pages that used to have none (GitHub issue #121).

`base/baseHTML.php` only ever checked `logged_in`, and only after the
entry point had already run its POST handlers. So an ordinary logged-in
player could open `base/admin.php` and `base/configuration.php` in full.
The guard added right after `basePHP.php` on each page is what these
tests pin down: a logged-in non-gm session must be bounced, and gm must
still get through.

`base/docConfig.php` is deliberately NOT among them. It renders
docs/configuration.md read-only and systemPresentation.php links players
to it, so a logged-in player must reach it — that is asserted here too,
together with the absence of the admin CSV link it used to advertise.

The anonymous-GET counterparts live in test_direct_access_e2e.py. They
cannot discriminate for admin.php / configuration.php — baseHTML.php
already redirects an anonymous GET — which is exactly why the
non-privileged session is the case that goes red without the fix.

Loads TestConfig: it is the scenario that ships the non-gm
`single_player` account, and earlier files in the suite truncate the
users table. Not Demo-runnable for that reason (it resets the DB),
unlike test_direct_access_e2e.py.

Run:
    python3 -m pytest tests/test_admin_privilege_guard_e2e.py -v
"""
import pytest

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, assert_no_collected_php_errors, load_minimal_data,
    load_scenario_via_admin, register_php_error_listener, safe_goto,
)

# (path, a string present in the page body and nowhere on the login form)
ADMIN_PAGES = (
    ("base/admin.php", "BDD management"),
    ("base/configuration.php", "Add New Config Value"),
)

# Open to any logged-in player : it renders docs/configuration.md read-only.
CONFIG_GUIDE = ("base/docConfig.php", "docConfig section")

NON_PRIVILEGED_LOGIN = ("single_player", "test")  # TestConfig account, not gm


@pytest.fixture(scope="module", autouse=True)
def admin_guard_scenario(browser):
    """TestConfig, for its gm + non-privileged accounts."""
    if DB_AVAILABLE:
        load_minimal_data()
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


def _login(page, base_url, username, password):
    """Submit credentials and return the landing URL."""
    safe_goto(page, f"{base_url}/connection/loginForm.php")
    page.locator("input[name='username']").fill(username)
    page.locator("input[name='passwd']").fill(password)
    page.locator("input[type='submit']").first.click()
    page.wait_for_load_state("load")
    return page.url


def _assert_non_privileged_blocked(browser, base_url, path, body_marker):
    ctx = browser.new_context()
    page = ctx.new_page()
    register_php_error_listener(page)
    try:
        landed = _login(page, base_url, *NON_PRIVILEGED_LOGIN)
        assert "loginForm.php" not in landed, (
            f"{NON_PRIVILEGED_LOGIN[0]!r} must be able to log in for this "
            f"test to mean anything; login left us on {landed}"
        )
        page.goto(f"{base_url}/{path}")
        page.wait_for_load_state("load")
        landed, html = page.url, page.content()
        assert path not in landed, (
            f"non-privileged session must not reach /{path}; "
            f"landed on {landed}"
        )
        assert body_marker not in html, (
            f"non-privileged GET on /{path} leaked page body: found "
            f"{body_marker!r} in the rendered HTML"
        )
        assert_no_collected_php_errors(page)
    finally:
        ctx.close()


def test_non_privileged_player_cannot_reach_the_admin_hub(browser, base_url):
    """An ordinary player must be bounced off /base/admin.php, whose POST
    handlers wipe, export and import the database."""
    _assert_non_privileged_blocked(browser, base_url, *ADMIN_PAGES[0])


def test_non_privileged_player_cannot_reach_the_configuration_editor(
    browser, base_url
):
    """An ordinary player must be bounced off /base/configuration.php,
    which rewrites every config key including combat thresholds."""
    _assert_non_privileged_blocked(browser, base_url, *ADMIN_PAGES[1])


def test_non_privileged_player_can_read_the_config_guide(browser, base_url):
    """/base/docConfig.php is deliberately open : it only renders
    docs/configuration.md, and systemPresentation.php links players to it.
    The admin CSV tooling it used to advertise is now gated separately."""
    path, body_marker = CONFIG_GUIDE
    ctx = browser.new_context()
    page = ctx.new_page()
    register_php_error_listener(page)
    try:
        landed = _login(page, base_url, *NON_PRIVILEGED_LOGIN)
        assert "loginForm.php" not in landed, (
            f"{NON_PRIVILEGED_LOGIN[0]!r} must be able to log in for this "
            f"test to mean anything; login left us on {landed}"
        )
        page.goto(f"{base_url}/{path}")
        page.wait_for_load_state("load")
        landed, html = page.url, page.content()
        assert "loginForm.php" not in landed, (
            f"a logged-in player must reach /{path}; landed on {landed}"
        )
        assert body_marker in html, (
            f"the guide body must render for a player; {body_marker!r} missing"
        )
        assert "/base/admin_csv.php" not in html, (
            "the admin CSV link must stay hidden from a non-privileged reader"
        )
        assert_no_collected_php_errors(page)
    finally:
        ctx.close()


def test_gm_still_reaches_every_admin_page(page, base_url):
    """Positive counterpart: the guard must not lock the gm out."""
    ensure_gm_login(page, base_url)
    for path, body_marker in ADMIN_PAGES:
        safe_goto(page, f"{base_url}/{path}")
        assert "loginForm.php" not in page.url, (
            f"gm GET on /{path} must not be redirected; landed on {page.url}"
        )
        assert body_marker in page.content(), (
            f"gm GET on /{path} must render the page body; "
            f"{body_marker!r} missing"
        )
