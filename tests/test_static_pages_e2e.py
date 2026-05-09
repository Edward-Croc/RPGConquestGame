"""Smoke tests for static / sidebar / external-link UI.

Covers buttons whose code path is render-only (no server mutation):
- the three external links on /base/systemPresentation.php (anon-accessible)
- the 'Le Système' sidebar link click (logged-in)
- the sidebar open/close toggle (toggleSidebar() in baseScript.php)

Run:
    python3 -m pytest tests/test_static_pages_e2e.py -v
"""
import pytest
from playwright.sync_api import Page, expect

from conftest import PHP_BASE_URL
from helpers import DB_AVAILABLE, load_minimal_data, login_as, logout, safe_goto


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(autouse=True)
def ensure_base_data():
    if DB_AVAILABLE:
        load_minimal_data()
    yield


@pytest.fixture
def gm_page(page: Page, base_url):
    login_as(page, base_url, "gm", "orga")
    yield page
    logout(page, base_url)


# ---------------------------------------------------------------------------
# /base/systemPresentation.php — three external links (anon-accessible)
# ---------------------------------------------------------------------------

class TestSystemPresentationLinks:
    @pytest.fixture(autouse=True)
    def _open_page(self, page: Page, base_url):
        safe_goto(page, f"{base_url}/base/systemPresentation.php")
        yield

    def test_pdf_tutorial_link(self, page: Page):
        link = page.locator(
            'a[href*="drive.google.com/file/d/1oRmi4oy_D6cPm3zzd7wn-3uEVQ1u-XsP"]'
        )
        expect(link).to_have_count(1)
        expect(link).to_have_attribute("target", "_blank")

    def test_github_repo_link(self, page: Page):
        link = page.locator(
            'a[href="https://github.com/Edward-Croc/RPGConquestGame"]'
        )
        expect(link).to_have_count(1)
        expect(link).to_have_attribute("target", "_blank")

    def test_github_issues_link(self, page: Page):
        link = page.locator(
            'a[href="https://github.com/Edward-Croc/RPGConquestGame/issues"]'
        )
        expect(link).to_have_count(1)
        expect(link).to_have_attribute("target", "_blank")


# ---------------------------------------------------------------------------
# 'Le Système' sidebar link — UI click navigates to systemPresentation.php
# ---------------------------------------------------------------------------

class TestLeSystemeSidebarClick:
    def test_sidebar_link_navigates_to_system_presentation(
        self, gm_page: Page, base_url
    ):
        safe_goto(gm_page, f"{base_url}/base/accueil.php")
        # Sidebar is hidden by default — open it via the ☰ button first
        gm_page.locator("span.openbtn").click()
        gm_page.locator("a[href$='/base/systemPresentation.php']").click()
        gm_page.wait_for_load_state("load")
        assert "systemPresentation.php" in gm_page.url, (
            f"Sidebar 'Le Système' click did not navigate to "
            f"systemPresentation.php; current URL: {gm_page.url}"
        )
        expect(
            gm_page.locator("h2", has_text="Présentation du système")
        ).to_be_visible()


# ---------------------------------------------------------------------------
# Sidebar open/close toggle — toggleSidebar() flips '.active' class on #sidebar
# ---------------------------------------------------------------------------

class TestSidebarToggle:
    def test_openbtn_toggles_active_class(self, gm_page: Page, base_url):
        safe_goto(gm_page, f"{base_url}/base/accueil.php")
        sidebar = gm_page.locator("#sidebar")
        had_active = "active" in (sidebar.get_attribute("class") or "").split()
        gm_page.locator("span.openbtn").click()
        has_active = "active" in (sidebar.get_attribute("class") or "").split()
        assert has_active != had_active, (
            f"Sidebar '.active' class should toggle on openbtn click "
            f"(before={had_active}, after={has_active})"
        )


# ---------------------------------------------------------------------------
# App-version footer — renders on every entry-point that includes
# base/baseHTML.php (via register_shutdown_function) plus loginForm.php
# (manually). The version string is the constant in base/version.php.
# ---------------------------------------------------------------------------

class TestAppVersionFooter:
    def test_footer_visible_on_accueil(self, gm_page: Page, base_url):
        """Authenticated page (accueil) renders the footer with a
        non-empty version string."""
        safe_goto(gm_page, f"{base_url}/base/accueil.php")
        footer = gm_page.locator("footer.app-footer .app-version")
        assert footer.count() == 1, "Expected exactly one .app-version in footer"
        text = (footer.inner_text() or "").strip()
        assert text.startswith("v"), f"Footer should show 'v<version>'; got {text!r}"
        assert len(text) > 1, f"Footer version string should be non-empty after 'v'; got {text!r}"

    def test_footer_visible_on_login_page(self, page: Page, base_url):
        """Anonymous login page also renders the footer (loginForm.php
        does not go through baseHTML; the version + footer are emitted
        manually). This guards the parallel rendering path."""
        safe_goto(page, f"{base_url}/connection/loginForm.php")
        footer = page.locator("footer.app-footer .app-version")
        assert footer.count() == 1, (
            "Login page should render exactly one .app-version footer"
        )
        text = (footer.inner_text() or "").strip()
        assert text.startswith("v") and len(text) > 1, (
            f"Login footer should show 'v<version>'; got {text!r}"
        )
