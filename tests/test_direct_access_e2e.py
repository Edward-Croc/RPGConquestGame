"""Playwright E2E tests for direct-access hardening.

Several PHP files are include-only partials or entry points that are not
meant to be hit directly from a browser. Each is hardened either with a
realpath include-guard (partials, returns 403) or a redirect to the
login form (navigational entry points like logout).

Files covered here:
  - /connection/logout.php        — entry point, anon → 302 to loginForm
  - /base/baseHTML.php            — include-only partial, direct GET → 403
  - /base/admin.php               — admin hub, anon → 302 to loginForm
  - /base/configuration.php       — config editor, anon → 302 to loginForm
  - /base/docConfig.php           — config guide, anon → 302 to loginForm

UI-only / prod-DEMO-runnable.

The logged-in-but-not-privileged half of the admin guard lives in
test_admin_privilege_guard_e2e.py, which needs a scenario load.

Run:
    python3 -m pytest tests/test_direct_access_e2e.py -v
"""
import pytest

from conftest import PHP_BASE_URL
from helpers import (
    assert_no_collected_php_errors,
    register_php_error_listener,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


def test_anonymous_logout_redirects_to_login(browser, base_url):
    """No session → /connection/logout.php must redirect to loginForm.php
    (relative Location header), not emit a PHP warning on undefined
    $_SESSION['FOLDER']."""
    ctx = browser.new_context()
    page = ctx.new_page()
    page.goto(f"{base_url}/connection/logout.php")
    assert "loginForm.php" in page.url, (
        f"Anonymous GET on /connection/logout.php must redirect to "
        f"loginForm.php; landed on {page.url}"
    )
    ctx.close()


def test_anonymous_basehtml_returns_403(browser, base_url):
    """Direct GET on /base/baseHTML.php must 403; the file is an
    include-only partial and would emit PHP warnings on $_SESSION
    and undefined $gameTitle / $mechanics if it ran standalone."""
    ctx = browser.new_context()
    page = ctx.new_page()
    response = page.goto(f"{base_url}/base/baseHTML.php")
    assert response is not None
    assert response.status == 403, (
        f"Direct GET on /base/baseHTML.php must 403; "
        f"got {response.status}"
    )
    ctx.close()


# ---------------------------------------------------------------------------
# Admin pages: an anonymous GET lands on the login form, never on the page.
# GET only — a POST probe would arm resetBDD / add_config against the live
# database on the very run where the guard is missing.
# ---------------------------------------------------------------------------

def _anonymous_visit(browser, base_url, path):
    """Fresh session-less context, GET one page, return (url, html)."""
    ctx = browser.new_context()
    page = ctx.new_page()
    register_php_error_listener(page)
    page.goto(f"{base_url}/{path}")
    page.wait_for_load_state("load")
    landed, html = page.url, page.content()
    assert_no_collected_php_errors(page)
    ctx.close()
    return landed, html


def _assert_guarded(browser, base_url, path, body_marker):
    landed, html = _anonymous_visit(browser, base_url, path)
    assert "loginForm.php" in landed, (
        f"anonymous GET on /{path} must redirect to the login form; "
        f"landed on {landed}"
    )
    assert body_marker not in html, (
        f"anonymous GET on /{path} leaked page body: found "
        f"{body_marker!r} in the rendered HTML"
    )


def test_anonymous_admin_hub_redirects_to_login(browser, base_url):
    """No session → /base/admin.php must land on the login form, not on
    the hub that exposes resetBDD / exportBDD / importBDD.

    Caught by baseHTML.php's logged_in guard, which predates the is_privileged
    guard on this page — so this test passes either way and does NOT protect it.
    The discriminating case is a logged-in non-privileged player, covered by
    tests/test_admin_privilege_guard_e2e.py.
    """
    _assert_guarded(browser, base_url, "base/admin.php", "BDD management")


def test_anonymous_configuration_page_redirects_to_login(browser, base_url):
    """No session → /base/configuration.php must redirect; the config
    editor rewrites combat thresholds and text templates.

    Same caveat as the admin hub above : the anonymous case was already covered
    by baseHTML.php, so this asserts the outer invariant, not the new guard.
    """
    _assert_guarded(
        browser, base_url, "base/configuration.php", "Add New Config Value"
    )


def test_anonymous_doc_config_page_redirects_to_login(browser, base_url):
    """No session → /base/docConfig.php must redirect; the configuration
    guide stays privileged-only like the pages that link to it.

    Unlike its two neighbours this one DOES discriminate : the page carried
    `$noConnection = true`, which told baseHTML.php to skip the login redirect
    entirely, so it was reachable by anyone until the guard landed.
    """
    _assert_guarded(browser, base_url, "base/docConfig.php", "docConfig section")

