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
    DB_AVAILABLE, end_turn, load_minimal_data, load_scenario_via_admin, safe_goto,
    ui_turn_counter,
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
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
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
        submit_btn = page.locator("input[name='chosir'][value='Recruter et Affecter']")
        expect(submit_btn).to_be_visible()

    def test_form_dropdowns_populated(self, page: Page, base_url):
        """All required dropdowns should have options."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/admin.php")

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
        form = page.locator("form[action*='workers/action.php']")
        options_text = form.locator("select#zoneSelect option").all_inner_texts()
        assert any("Alpha-Investigation" in t for t in options_text), f"Should have Alpha-Investigation: {options_text}"
        assert any("Beta-Combat" in t for t in options_text), f"Should have Beta-Combat: {options_text}"

    def test_hobby_dropdown_includes_test_powers(self, page: Page, base_url):
        """Hobby dropdown should have Eagle Scout loaded from TestConfig CSV."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/admin.php")
        form = page.locator("form[action*='workers/action.php']")
        options_text = form.locator("select#power_hobby_id option").all_inner_texts()
        assert any("Eagle Scout" in t for t in options_text), \
            f"Hobby dropdown should include Eagle Scout: {options_text[:5]}"

    def test_metier_dropdown_includes_test_powers(self, page: Page, base_url):
        """Metier dropdown should have Veteran Tactician from TestConfig CSV."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/admin.php")
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
        form = page.locator("form[action*='workers/action.php']")
        form.locator("select#controllerSelect").select_option(target_controller_id)
        form.locator("select#origin_id").select_option("1")
        form.locator("select#firstname").select_option(firstname_val)
        form.locator("select#lastname").select_option(lastname_val)
        form.locator("select#power_hobby_id").select_option(index=1)
        form.locator("select#zoneSelect").select_option(index=1)
        form.locator("input[name='chosir'][value='Recruter et Affecter']").click()
        page.wait_for_load_state("load")

        # --- Switch gm's view to Lord Alpha's faction (Ma Faction page) ---
        safe_goto(page, f"{base_url}/controllers/action.php")
        page.locator("form select#controllerSelect").select_option(target_controller_id)
        page.locator("input[name='chosir'][value='Choisir']").click()
        page.wait_for_load_state("load")

        # --- Assert the new worker is visible in Alpha's agents view ---
        safe_goto(page, f"{base_url}/workers/viewAll.php")
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
        form = page.locator("form[action*='workers/action.php']")
        form.locator("select#controllerSelect").select_option(target_controller_id)
        form.locator("select#origin_id").select_option("1")
        form.locator("select#firstname").select_option(firstname_val)
        form.locator("select#lastname").select_option(lastname_val)
        form.locator("select#power_hobby_id").select_option(index=1)
        form.locator("select#zoneSelect").select_option(index=1)
        form.locator("input[name='chosir'][value='Recruter et Affecter']").click()
        page.wait_for_load_state("load")

        # --- Switch gm's view to Lord Beta's faction (Ma Faction page) ---
        safe_goto(page, f"{base_url}/controllers/action.php")
        page.locator("form select#controllerSelect").select_option(target_controller_id)
        page.locator("input[name='chosir'][value='Choisir']").click()
        page.wait_for_load_state("load")

        # --- Assert the new worker is visible in Beta's agents view ---
        safe_goto(page, f"{base_url}/workers/viewAll.php")
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
        export_btn = page.locator("input[value='Export BDD to file.sql']")
        expect(export_btn).to_be_visible()

    def test_export_triggers_download(self, page: Page, base_url):
        """Clicking export should trigger a file download."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/admin.php")

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
        file_input = page.locator("input[type='file'][name='bddFile']")
        expect(file_input).to_be_visible()
        import_btn = page.locator("input[value='Import BDD from file.sql']")
        expect(import_btn).to_be_visible()

    def test_import_form_uses_multipart(self, page: Page, base_url):
        """Import form must be enctype='multipart/form-data' for file upload."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/admin.php")
        form = page.locator("form:has(input[name='importBDD'])")
        expect(form).to_be_visible()
        enctype = form.get_attribute("enctype")
        assert enctype == "multipart/form-data", \
            f"Import form should be multipart/form-data, got '{enctype}'"

    def test_import_form_has_importBDD_hidden_field(self, page: Page, base_url):
        """Import form should have the importBDD hidden input."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/admin.php")
        hidden = page.locator("input[type='hidden'][name='importBDD']")
        assert hidden.count() >= 1, "importBDD hidden input should exist"


# ---------------------------------------------------------------------------
# Tests: turn report archive at end-turn + admin_turn_reports.php page
# ---------------------------------------------------------------------------

class TestTurnReportArchive:
    """The end-of-turn page is the only place the engine narrates a turn, and
    that narrative used to vanish with the page. endTurn.php now archives the
    rendered page under var/turn_reports, and the admin page lists, views and
    deletes those archives.

    One class-scoped end-turn feeds every test below."""

    @pytest.fixture(scope="class", autouse=True)
    def _fresh_report_state(self, browser, base_url):
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, base_url)

        safe_goto(page, f"{base_url}/base/admin_turn_reports.php")
        page.wait_for_load_state("load")
        purge_form = page.locator("form:has(input[name='purge_all'])")
        if purge_form.count() >= 1:
            page.on("dialog", lambda d: d.accept())
            purge_form.locator("button[type='submit']").click()
            page.wait_for_load_state("load")

        type(self)._turn_before = ui_turn_counter(page, base_url)
        end_turn(page, base_url)

        assert_no_collected_php_errors(page)
        context.close()

    def test_report_is_archived_after_end_turn(self, page: Page, base_url):
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/admin_turn_reports.php")
        page.wait_for_load_state("load")
        rows = page.locator("tbody tr:has(td:has-text('.html'))")
        assert rows.count() >= 1, (
            "After one end-turn, at least one turn report should be listed "
            "on admin_turn_reports.php ; got 0 rows"
        )
        # The archive is stamped with the counter BEFORE the increment, so the
        # narrative of turn N is filed under N. Capturing it after the increment
        # would shift every archive by one and leave every other assertion green.
        turn_cell = rows.first.locator("td").first.inner_text().strip()
        assert turn_cell == str(self._turn_before), (
            f"the archive must be filed under the turn it narrates "
            f"({self._turn_before}); got {turn_cell!r}"
        )

    def test_the_archive_holds_the_narrative_not_an_empty_page(self, page: Page, base_url):
        """Without this, an archive of the sidebar alone would satisfy the
        row-count assertion above."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/admin_turn_reports.php")
        page.wait_for_load_state("load")
        view_link = page.locator("tbody tr:has(td:has-text('.html'))").first.locator("a")
        safe_goto(page, view_link.get_attribute("href"))
        page.wait_for_load_state("load")
        html = page.content()
        assert "Starting END of Turn" in html, (
            "the archive must contain the opening marker of the resolution"
        )
        assert "attackMechanic" in html, (
            "the archive must contain the step-by-step narrative, not just the shell"
        )

    def test_delete_button_removes_the_archive(self, page: Page, base_url):
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/admin_turn_reports.php")
        page.wait_for_load_state("load")
        before = page.locator("tbody tr:has(td:has-text('.html'))").count()
        assert before >= 1, "Pre-condition failed: no archive to delete"

        page.on("dialog", lambda d: d.accept())
        page.locator("tbody tr:has(td:has-text('.html'))").first.locator(
            "form button.is-danger"
        ).click()
        page.wait_for_load_state("load")

        after = page.locator("tbody tr:has(td:has-text('.html'))").count()
        assert after == before - 1, (
            f"Delete must remove exactly 1 row ; before={before}, after={after}"
        )

    def test_reloading_a_scenario_purges_the_archives(self, browser, base_url):
        """The archives describe a game that the reset wipes, so they go with
        it — which also keeps them from piling up across a test campaign.

        Runs last in the class: it destroys the state the tests above read.
        """
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, base_url)

        # The delete test above consumed the fixture's archive, so make one.
        end_turn(page, base_url)
        safe_goto(page, f"{base_url}/base/admin_turn_reports.php")
        page.wait_for_load_state("load")
        before = page.locator("tbody tr:has(td:has-text('.html'))").count()
        assert before >= 1, "Pre-condition failed: no archive to purge"

        load_scenario_via_admin(browser, base_url, "TestConfig")

        safe_goto(page, f"{base_url}/base/admin_turn_reports.php")
        page.wait_for_load_state("load")
        after = page.locator("tbody tr:has(td:has-text('.html'))").count()
        html_after = page.content()
        assert_no_collected_php_errors(page)
        context.close()
        assert after == 0, (
            f"reloading a scenario must purge the turn archives; {after} left"
        )
        # Without this, a renamed page or a regressed guard would also count 0.
        assert "No turn report found." in html_after, (
            "the listing must still render after the purge, not redirect or 500"
        )


# ---------------------------------------------------------------------------
# Tests: auto-backup at end-turn + admin_backups.php management page
# ---------------------------------------------------------------------------

class TestBDDBackupAutoOnEndTurn:
    """Verify that end-turn writes a fresh backup file to var/backups
    and that the admin backup management page lists / can delete it.

    Class-scoped fixture purges existing backups and runs one end-turn
    so both tests share a clean baseline (no per-test EOT cost)."""

    @pytest.fixture(scope="class", autouse=True)
    def _fresh_backup_state(self, browser, base_url):
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, base_url)

        # Purge all existing backups (Purge-all button click)
        safe_goto(page, f"{base_url}/base/admin_backups.php")
        page.wait_for_load_state("load")
        purge_form = page.locator("form:has(input[name='purge_all'])")
        if purge_form.count() >= 1:
            page.on("dialog", lambda d: d.accept())
            purge_form.locator("button[type='submit']").click()
            page.wait_for_load_state("load")

        # One EOT triggers exportBDD(true) at endTurn.php top, dumps to
        # var/backups. Both tests then read the resulting listing.
        end_turn(page, base_url)

        assert_no_collected_php_errors(page)
        context.close()

    def test_backup_file_appears_after_end_turn(self, page: Page, base_url):
        """After the class fixture's purge + end-turn, at least one
        .sql row must appear in the admin backups table."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/admin_backups.php")
        page.wait_for_load_state("load")
        sql_rows = page.locator("tbody tr:has(td:has-text('.sql'))")
        assert sql_rows.count() >= 1, (
            "After one end-turn, at least one .sql backup file should be "
            "listed on admin_backups.php ; got 0 rows"
        )

    def test_delete_button_removes_row(self, page: Page, base_url):
        """Deleting a backup via the per-row Delete button must remove
        it from the listing on the reloaded page."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/admin_backups.php")
        page.wait_for_load_state("load")
        sql_rows_before = page.locator(
            "tbody tr:has(td:has-text('.sql'))"
        ).count()
        assert sql_rows_before >= 1, (
            "Pre-condition failed: no backup rows to delete "
            "(class fixture should have created at least one)"
        )

        # Accept the JS confirm() dialog then click the first row's
        # Delete button.
        page.on("dialog", lambda d: d.accept())
        first_delete = page.locator(
            "tbody tr:has(td:has-text('.sql'))"
        ).first.locator("form button.is-danger")
        first_delete.click()
        page.wait_for_load_state("load")

        sql_rows_after = page.locator(
            "tbody tr:has(td:has-text('.sql'))"
        ).count()
        assert sql_rows_after == sql_rows_before - 1, (
            f"Delete must remove exactly 1 row ; before={sql_rows_before}, "
            f"after={sql_rows_after}"
        )
