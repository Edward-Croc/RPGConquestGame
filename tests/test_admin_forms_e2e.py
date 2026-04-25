"""Playwright E2E tests for admin page forms.

Covers:
- "Create Perfect Agent" form (worker creation via admin UI)
- BDD Export to file.sql
- BDD Import from file.sql

Run:
    python3 -m pytest tests/test_admin_forms_e2e.py -v
"""
import os
import re
import time
import pytest
from playwright.sync_api import Page, expect

from conftest import (
    PHP_BASE_URL, ensure_gm_login,
)


from helpers import (
    DB_AVAILABLE, load_minimal_data, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(autouse=True)
def _require_db():
    if not DB_AVAILABLE:
        pytest.skip("No local MySQL available")


# ---------------------------------------------------------------------------
# Module fixture: load TestConfig fresh
# ---------------------------------------------------------------------------

@pytest.fixture(scope="module", autouse=True)
def load_test_config(browser):
    """Load TestConfig once at module start."""
    if not DB_AVAILABLE:
        yield
        return

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
    context.close()
    yield


# ---------------------------------------------------------------------------
# Tests: Create Perfect Agent form
# ---------------------------------------------------------------------------

class TestCreatePerfectAgentForm:
    """Verify the 'Create Perfect Agent' admin form is functional."""

    def test_form_present_on_admin_page(self, page: Page, base_url):
        """The Recruter et Affecter button should be visible on admin page."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/admin.php")
        page.wait_for_load_state("networkidle")
        submit_btn = page.locator("input[name='chosir'][value='Recruter et Affecter']")
        expect(submit_btn).to_be_visible()

    def test_form_dropdowns_populated(self, page: Page, base_url):
        """All required dropdowns should have options."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/admin.php")
        page.wait_for_load_state("networkidle")

        # Use the second controllerSelect (the one inside the worker form)
        # The first one is the controller-switch dropdown at the top.
        # Both have the same name; we check the form-scoped one via the form action.
        form = page.locator("form[action*='workers/action.php']")
        expect(form).to_be_visible()

        for select_id in ['origin_id', 'firstname', 'lastname', 'zoneSelect',
                          'power_hobby_id', 'power_metier_id']:
            select = form.locator(f"select#{select_id}")
            options = select.locator("option").all()
            # Each select should have at least 1 option (placeholder + real options)
            assert len(options) >= 2, \
                f"Dropdown {select_id} should have at least 2 options, got {len(options)}"

    def test_origin_dropdown_has_test_data(self, page: Page, base_url):
        """Origin dropdown should contain TestConfig origins."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/admin.php")
        page.wait_for_load_state("networkidle")
        form = page.locator("form[action*='workers/action.php']")
        options_text = form.locator("select#origin_id option").all_inner_texts()
        assert any("Accessible" in t for t in options_text), \
            f"Should have 'origine Accessible', got: {options_text}"
        assert any("Commune" in t for t in options_text), \
            f"Should have 'origine Commune', got: {options_text}"

    def test_zone_dropdown_has_test_data(self, page: Page, base_url):
        """Zone dropdown should contain TestConfig zones."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/admin.php")
        page.wait_for_load_state("networkidle")
        form = page.locator("form[action*='workers/action.php']")
        options_text = form.locator("select#zoneSelect option").all_inner_texts()
        assert any("Alpha-Investigation" in t for t in options_text), f"Should have Alpha-Investigation: {options_text}"
        assert any("Beta-Combat" in t for t in options_text), f"Should have Beta-Combat: {options_text}"

    def test_hobby_dropdown_includes_test_powers(self, page: Page, base_url):
        """Hobby dropdown should have Eagle Scout loaded from TestConfig CSV."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/admin.php")
        page.wait_for_load_state("networkidle")
        form = page.locator("form[action*='workers/action.php']")
        options_text = form.locator("select#power_hobby_id option").all_inner_texts()
        assert any("Eagle Scout" in t for t in options_text), \
            f"Hobby dropdown should include Eagle Scout: {options_text[:5]}"

    def test_metier_dropdown_includes_test_powers(self, page: Page, base_url):
        """Metier dropdown should have Veteran Tactician from TestConfig CSV."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/admin.php")
        page.wait_for_load_state("networkidle")
        form = page.locator("form[action*='workers/action.php']")
        options_text = form.locator("select#power_metier_id option").all_inner_texts()
        assert any("Veteran Tactician" in t for t in options_text), \
            f"Metier dropdown should include Veteran Tactician"

    def test_create_worker_via_form_appears_in_faction_view(self, page: Page, base_url):
        """End-to-end: fill the form on admin.php, then verify the new worker
        appears in the target controller's agents view via the faction page.

        Flow mirrors what a gm does in the UI:
          1. admin.php → fill creation form → click Recruter et Affecter
          2. controllers/action.php (Ma Faction) → select Lord Alpha → Choisir
          3. workers/viewAll.php → assert new worker's name is listed
        """
        ensure_gm_login(page, base_url)

        firstname_val = "Sentinel"
        lastname_val = "Vanguard"
        target_controller_id = "1"  # Lord Alpha

        # --- Fill the worker-creation form on admin.php ---
        safe_goto(page, f"{base_url}/base/admin.php")
        page.wait_for_load_state("networkidle")
        form = page.locator("form[action*='workers/action.php']")
        form.locator("select#controllerSelect").select_option(target_controller_id)
        form.locator("select#origin_id").select_option("1")
        form.locator("select#firstname").select_option(firstname_val)
        form.locator("select#lastname").select_option(lastname_val)
        form.locator("select#power_hobby_id").select_option(index=1)
        form.locator("select#zoneSelect").select_option(index=1)
        form.locator("input[name='chosir'][value='Recruter et Affecter']").click()
        page.wait_for_load_state("networkidle")

        # --- Switch gm's view to Lord Alpha's faction (Ma Faction page) ---
        safe_goto(page, f"{base_url}/controllers/action.php")
        page.wait_for_load_state("networkidle")
        page.locator("form select#controllerSelect").select_option(target_controller_id)
        page.locator("input[name='chosir'][value='Choisir']").click()
        page.wait_for_load_state("networkidle")

        # --- Assert the new worker is visible in Alpha's agents view ---
        safe_goto(page, f"{base_url}/workers/viewAll.php")
        page.wait_for_load_state("networkidle")
        html = page.content()
        assert firstname_val in html and lastname_val in html, (
            f"Newly-created worker '{firstname_val} {lastname_val}' should appear "
            f"in Lord Alpha's agents view after faction switch"
        )

    def test_create_worker_links_to_controller(self, page: Page, base_url):
        """Created worker should be linked to the specified controller and visible in that
        controller's faction view.

        UI-first flow (mirrors the sibling test but targets Lord Beta):
          1. admin.php → fill creation form for controller_id=2 (Beta) → Recruter et Affecter
          2. controllers/action.php (Ma Faction) → select Lord Beta → Choisir
          3. workers/viewAll.php → assert new worker's name appears in Beta's agents view
        """
        ensure_gm_login(page, base_url)

        firstname_val = "Watcher"
        lastname_val = "Patrol"
        target_controller_id = "2"  # Lord Beta

        # --- Fill the worker-creation form on admin.php ---
        safe_goto(page, f"{base_url}/base/admin.php")
        page.wait_for_load_state("networkidle")
        form = page.locator("form[action*='workers/action.php']")
        form.locator("select#controllerSelect").select_option(target_controller_id)
        form.locator("select#origin_id").select_option("1")
        form.locator("select#firstname").select_option(firstname_val)
        form.locator("select#lastname").select_option(lastname_val)
        form.locator("select#power_hobby_id").select_option(index=1)
        form.locator("select#zoneSelect").select_option(index=1)
        form.locator("input[name='chosir'][value='Recruter et Affecter']").click()
        page.wait_for_load_state("networkidle")

        # --- Switch gm's view to Lord Beta's faction (Ma Faction page) ---
        safe_goto(page, f"{base_url}/controllers/action.php")
        page.wait_for_load_state("networkidle")
        page.locator("form select#controllerSelect").select_option(target_controller_id)
        page.locator("input[name='chosir'][value='Choisir']").click()
        page.wait_for_load_state("networkidle")

        # --- Assert the new worker is visible in Beta's agents view ---
        safe_goto(page, f"{base_url}/workers/viewAll.php")
        page.wait_for_load_state("networkidle")
        html = page.content()
        assert firstname_val in html and lastname_val in html, (
            f"Newly-created worker '{firstname_val} {lastname_val}' should appear "
            f"in Lord Beta's agents view after faction switch (confirming controller linkage)"
        )


# ---------------------------------------------------------------------------
# Tests: perfect-worker form validation (A1)
# ---------------------------------------------------------------------------

class TestPerfectWorkerValidation:
    """A1: createWorker now emits a French error per missing required
    field instead of failing silently. The 5 required fields per
    workers/functions.php createWorker() are: firstname, lastname,
    origin_id, controller_id, zone_id. The happy path is already
    covered by TestCreatePerfectAgentForm.test_create_worker_via_form
    so we only test the negative case here.
    """

    def test_missing_required_field_shows_error(self, page: Page, base_url):
        """Submit creation URL with `lastname` cleared while every other
        required field is set → response shows the French error pattern
        ('Champ obligatoire manquant : nom'). Symmetry across the 5
        required fields is asserted by code review of createWorker's
        loop, not by 5 separate tests (avoids test-suite bloat for a
        non-critical admin form)."""
        ensure_gm_login(page, base_url)
        safe_goto(page,
            f"{base_url}/workers/action.php"
            f"?creation=true"
            f"&firstname=Sentinel"
            f"&lastname="                      # cleared
            f"&origin_id=1"
            f"&controller_id=1"
            f"&zone_id=1"
            f"&chosir=Recruter+et+Affecter"
        )
        page.wait_for_load_state("load")
        body = page.content()
        assert "Champ obligatoire manquant" in body, (
            "createWorker should emit the French missing-field pattern "
            "when a required field is empty"
        )
        assert "nom" in body, (
            "Cleared field's French label ('nom') should be named in the error"
        )

    def test_create_worker_with_zero_powers_no_php_warnings(self, page: Page, base_url):
        """Regression test for workers/functions.php:202 typo that
        triggered 'Warning: Undefined variable $worker_id' when a worker
        was created with 0 powers (all 4 power-type fields empty).

        Reproduces by submitting the perfect-worker URL with all
        required fields populated but every optional power field empty,
        then asserts no PHP Warning / Fatal error on the post-creation
        worker view (action.php?worker_id=X) AND on the controller's
        faction-roster page (workers/viewAll.php under Lord Alpha).
        """
        ensure_gm_login(page, base_url)

        safe_goto(page,
            f"{base_url}/workers/action.php"
            f"?creation=true"
            f"&firstname=Zero"
            f"&lastname=NoPowers_Test"
            f"&origin_id=1"
            f"&controller_id=1"  # Lord Alpha
            f"&zone_id=1"
            f"&chosir=Recruter+et+Affecter"
        )
        page.wait_for_load_state("load")
        body_creation = page.content()
        assert "<b>Warning</b>" not in body_creation, \
            "PHP Warning on action.php after 0-powers creation"
        assert "<b>Fatal error</b>" not in body_creation, \
            "PHP Fatal error on action.php after 0-powers creation"

        safe_goto(page, f"{base_url}/base/accueil.php?controller_id=1&chosir=Choisir")
        page.wait_for_load_state("networkidle")
        safe_goto(page, f"{base_url}/workers/viewAll.php")
        page.wait_for_load_state("load")
        body_viewall = page.content()
        assert "<b>Warning</b>" not in body_viewall, \
            "PHP Warning on workers/viewAll.php with 0-powers worker visible"
        assert "<b>Fatal error</b>" not in body_viewall, \
            "PHP Fatal error on workers/viewAll.php"


# ---------------------------------------------------------------------------
# Tests: BDD Export
# ---------------------------------------------------------------------------

class TestBDDExport:
    """Verify the BDD export functionality."""

    def test_export_button_visible(self, page: Page, base_url):
        """Export BDD button should be visible on admin page."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/admin.php")
        page.wait_for_load_state("networkidle")
        export_btn = page.locator("input[value='Export BDD to file.sql']")
        expect(export_btn).to_be_visible()

    def test_export_triggers_download(self, page: Page, base_url):
        """Clicking export should trigger a file download."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/admin.php")
        page.wait_for_load_state("networkidle")

        # Set up download listener
        with page.expect_download(timeout=60000) as download_info:
            page.locator("input[value='Export BDD to file.sql']").click()
        download = download_info.value

        # Verify download has SQL file extension
        suggested_name = download.suggested_filename
        assert suggested_name.endswith('.sql'), \
            f"Download should be a .sql file, got '{suggested_name}'"

        # Verify content has SQL-like content
        path = download.path()
        with open(path, 'rb') as f:
            head = f.read(2048).decode('utf-8', errors='replace')
        assert 'CREATE TABLE' in head or 'INSERT' in head or 'DROP TABLE' in head, \
            f"Downloaded file should contain SQL, got first 200 chars: {head[:200]}"


# ---------------------------------------------------------------------------
# Tests: BDD Import
# ---------------------------------------------------------------------------

class TestBDDImport:
    """Verify the BDD import form is present and accepts files."""

    def test_import_form_visible(self, page: Page, base_url):
        """Import form with file input and submit button should be visible."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/admin.php")
        page.wait_for_load_state("networkidle")
        file_input = page.locator("input[type='file'][name='bddFile']")
        expect(file_input).to_be_visible()
        import_btn = page.locator("input[value='Import BDD from file.sql']")
        expect(import_btn).to_be_visible()

    def test_import_form_uses_multipart(self, page: Page, base_url):
        """Import form must be enctype='multipart/form-data' for file upload."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/admin.php")
        page.wait_for_load_state("networkidle")
        form = page.locator("form:has(input[name='importBDD'])")
        expect(form).to_be_visible()
        enctype = form.get_attribute("enctype")
        assert enctype == "multipart/form-data", \
            f"Import form should be multipart/form-data, got '{enctype}'"

    def test_import_form_has_importBDD_hidden_field(self, page: Page, base_url):
        """Import form should have the importBDD hidden input."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/admin.php")
        page.wait_for_load_state("networkidle")
        hidden = page.locator("input[type='hidden'][name='importBDD']")
        assert hidden.count() >= 1, "importBDD hidden input should exist"
