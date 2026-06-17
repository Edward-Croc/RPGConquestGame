"""Playwright E2E tests for Issue #61 — information gift log.

`controllers/action.php` calls `logInformationGift()` after the existing
`addWorkerToCKE` / `addLocationToCKL` actions. Ma Faction view renders
an "Informations reçues" panel for the active controller listing every
log row where they are the recipient.

Tests stay UI-only: IDs scraped from rendered HTML, no direct DB access.

Run:
    python3 -m pytest tests/test_information_gift_log_e2e.py -v
"""
import re

import pytest

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, load_minimal_data, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def load_test_config(browser):
    if DB_AVAILABLE:
        load_minimal_data()
    context = browser.new_context()
    page = context.new_page()
    ensure_gm_login(page, PHP_BASE_URL)
    safe_goto(page, f"{PHP_BASE_URL}/base/admin.php")
    page.wait_for_load_state("networkidle")
    page.locator("select[name='config_name']").select_option("TestConfig")
    page.locator("input[type='submit'][value='Submit']").click()
    if page.locator("#confirmModalYes").is_visible():
        page.locator("#confirmModalYes").click()
    page.wait_for_timeout(5000)
    page.wait_for_load_state("load", timeout=90000)
    context.close()
    yield


def _resolve_controller_id_via_ui(page, lastname):
    safe_goto(page, f"{PHP_BASE_URL}/base/accueil.php")
    page.wait_for_load_state("load")
    return page.locator(
        f"select[name='controller_id'] option:has-text('{lastname}')"
    ).first.get_attribute("value")


def _set_active_via_ui(page, lastname):
    value = _resolve_controller_id_via_ui(page, lastname)
    safe_goto(page, f"{PHP_BASE_URL}/base/accueil.php?controller_id={value}")
    page.wait_for_load_state("load")
    return value


def _resolve_location_id_via_ui(page, location_name):
    safe_goto(page, f"{PHP_BASE_URL}/zones/management_locations.php")
    page.wait_for_load_state("load")
    html = page.content()
    m = re.search(
        rf'<h3>[^<]*{re.escape(location_name)}[^<]*\(discovery[^<]+</h3>'
        rf'.*?name="toggle_destruction"\s+value="(\d+)"',
        html, re.DOTALL,
    )
    if not m:
        raise AssertionError(f"location_id for {location_name!r} not found")
    return m.group(1)


def _seed_ckl_admin(page, recipient_id, location_id):
    """Admin path (controllers/management.php) writes CKL row without
    creating an information-gift-log entry. Used to set up known-location
    state for the giver before the player-path gift under test."""
    safe_goto(
        page,
        f"{PHP_BASE_URL}/controllers/management.php"
        f"?giftInformationLocation=1&target_controller_id={recipient_id}&location_id={location_id}"
    )
    page.wait_for_load_state("load")


def _faction_content(page):
    safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
    page.wait_for_load_state("load")
    return page.content()


class TestRessourcesPanelPresent:
    """Faction page renders the 'Informations reçues' panel, empty by default."""

    @pytest.fixture(scope="class", autouse=True)
    def panel_state(self, browser):
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)
        _set_active_via_ui(page, "Alpha")
        content = _faction_content(page)
        assert_no_collected_php_errors(page)
        ctx.close()
        type(self)._content = content
        yield

    def test_panel_present(self):
        assert "Informations reçues" in self._content


class TestGiftLocationLogged:
    """Player-path giftInformationLocation must write to information_gift_logs.
    The recipient's Faction page should then show an entry in the panel."""

    @pytest.fixture(scope="class", autouse=True)
    def gift_state(self, browser):
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        alpha_id = _resolve_controller_id_via_ui(page, "Alpha")
        beta_id = _resolve_controller_id_via_ui(page, "Beta")
        echo_base_id = _resolve_location_id_via_ui(page, "Echo-Base")

        _seed_ckl_admin(page, alpha_id, echo_base_id)

        _set_active_via_ui(page, "Alpha")
        page.request.get(
            f"{PHP_BASE_URL}/controllers/action.php"
            f"?giftInformationLocation=1"
            f"&controller_id={alpha_id}"
            f"&target_controller_id={beta_id}"
            f"&location_id={echo_base_id}"
        )

        _set_active_via_ui(page, "Beta")
        beta_content = _faction_content(page)
        assert_no_collected_php_errors(page)
        ctx.close()
        type(self)._beta_content = beta_content
        yield

    def test_recipient_sees_giver_lastname(self):
        assert "Alpha" in self._beta_content

    def test_recipient_sees_target_location_name(self):
        assert "Echo-Base" in self._beta_content

    def test_recipient_sees_transmis_phrasing(self):
        assert "vous a transmis" in self._beta_content


class TestAdminTransactionsTablePresent:
    """controllers/management.php gains an 'Information Transactions' admin section."""

    @pytest.fixture(scope="class", autouse=True)
    def admin_state(self, browser):
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        safe_goto(page, f"{PHP_BASE_URL}/controllers/management.php")
        page.wait_for_load_state("load")
        admin_content = page.content()

        assert_no_collected_php_errors(page)
        ctx.close()
        type(self)._admin_content = admin_content
        yield

    def test_admin_section_present(self):
        assert "Information Transactions" in self._admin_content
