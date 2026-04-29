"""Playwright E2E tests for direct-access hardening.

Several PHP files are include-only partials or entry points that are not
meant to be hit directly from a browser. Each is hardened either with a
realpath include-guard (partials, returns 403) or a redirect to the
login form (navigational entry points like logout).

Files covered here:
  - /connection/logout.php        — entry point, anon → 302 to loginForm
  - /base/baseHTML.php            — include-only partial, direct GET → 403

UI-only / prod-DEMO-runnable.

Run:
    python3 -m pytest tests/test_direct_access_e2e.py -v
"""
import pytest

from conftest import PHP_BASE_URL


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
