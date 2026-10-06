"""Playwright E2E tests for /base/configuration.php Add/Update/Delete buttons.

Covers UI buttons previously NOT COVERED in
tests/AUDIT_ui_buttons_outside_management.md (base/configuration.php).

Sequential CRUD on a unique row: add → update → delete. Tests run in
definition order (pytest default); the class-scope browser context +
config_name keep the chain pointed at the same row.

Run:
    python3 -m pytest tests/test_configuration_e2e.py -v
"""
import time
import pytest
from playwright.sync_api import Page, expect

from conftest import PHP_BASE_URL
from helpers import (
    DB_AVAILABLE, clear_ui_caches, end_turn, load_minimal_data, load_scenario_via_admin, login_as, safe_goto,
    set_config_via_ui, ui_turn_counter, worker_report_html,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(autouse=True)
def ensure_base_data():
    if DB_AVAILABLE:
        load_minimal_data()
    yield


@pytest.fixture(scope="class")
def config_name():
    """Unique name per class run — avoids collision with existing config rows."""
    return f"test_slice9_config_{int(time.time() * 1000)}"


@pytest.fixture(scope="class")
def gm_session(browser):
    """Class-scope gm browser context so the add → update → delete chain
    runs against a single session/row."""
    context = browser.new_context()
    page = context.new_page()
    login_as(page, PHP_BASE_URL, "gm", "orga")
    yield page
    context.close()


class TestConfigurationCRUD:
    """Add a row, update its value, delete it — exercising all three submit
    buttons on /base/configuration.php."""

    def test_add_config_value(self, gm_session: Page, base_url, config_name):
        safe_goto(gm_session, f"{base_url}/base/configuration.php")
        add_form = gm_session.locator("form:has(input[name='add_config'])")
        add_form.locator("input[name='name']").fill(config_name)
        add_form.locator("input[name='value']").fill("initial_value")
        add_form.locator("input[name='add_config']").click()
        gm_session.wait_for_load_state("load")
        expect(
            gm_session.locator(f"td:has-text('{config_name}')")
        ).to_have_count(1)

    def test_update_config_value(self, gm_session: Page, base_url, config_name):
        # Note: the row's `<form>` is inside `<tr>`, which is invalid HTML —
        # the parser strips/repositions it. Locate by the row's tr/td
        # instead; clicking the named submit still POSTs the row.
        safe_goto(gm_session, f"{base_url}/base/configuration.php")
        row = gm_session.locator(f"tr:has(td:has-text('{config_name}'))")
        expect(row).to_have_count(1)
        row.locator("input[name='value']").fill("updated_value")
        row.locator("input[name='update_config']").click()
        gm_session.wait_for_load_state("load")
        row_after = gm_session.locator(f"tr:has(td:has-text('{config_name}'))")
        expect(row_after.locator("input[name='value']")).to_have_value(
            "updated_value"
        )

    def test_delete_config_value(self, gm_session: Page, base_url, config_name):
        safe_goto(gm_session, f"{base_url}/base/configuration.php")
        row = gm_session.locator(f"tr:has(td:has-text('{config_name}'))")
        expect(row).to_have_count(1)
        row.locator("input[name='delete_config']").click()
        gm_session.wait_for_load_state("load")
        expect(
            gm_session.locator(f"td:has-text('{config_name}')")
        ).to_have_count(0)


class TestMalformedTemplate:
    """A template edited with one placeholder too many no longer stops the end
    of turn : the report shows the template as typed, followed by its values."""

    def test_end_of_turn_survives_a_malformed_template(self, browser, base_url):
        load_scenario_via_admin(browser, base_url, "TestConfig")
        clear_ui_caches()
        context = browser.new_context()
        page = context.new_page()
        try:
            login_as(page, base_url, "gm", "orga")
            turn_before = ui_turn_counter(page, base_url=base_url)
            # textesStartInvestigate receives the zone name only : %2$s has no value.
            set_config_via_ui(page, "textesStartInvestigate", "MALFORMED %1$s %2$s", base_url=base_url)
            end_turn(page, base_url=base_url)
            assert ui_turn_counter(page, base_url=base_url) == turn_before + 1, \
                "the end of turn must complete despite the malformed template"
            report_html = worker_report_html(page, "Searcher_1", base_url=base_url)
            assert "MALFORMED %1$s %2$s [" in report_html, \
                "the investigator's report should show the template as typed, followed by its values"
        finally:
            context.close()
            load_scenario_via_admin(browser, base_url, "TestConfig")
            clear_ui_caches()
