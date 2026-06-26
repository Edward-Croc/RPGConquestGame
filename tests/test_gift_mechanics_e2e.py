"""Playwright E2E tests for the two gift mechanics — ressource transfer
(Issue #10) and information gifts with the per-gift log (Issue #61).

Both flows share the same TestConfig scenario and the same controller-id
lookup helpers, so they live in one module to share the scenario load.

Run:
    python3 -m pytest tests/test_gift_mechanics_e2e.py -v
"""
import re

import pytest

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, load_minimal_data, load_scenario_via_admin, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def load_test_config(browser):
    if DB_AVAILABLE:
        load_minimal_data()
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
    yield


# ---------------------------------------------------------------------------
# Shared lookup + view helpers
# ---------------------------------------------------------------------------


def _resolve_controller_id_via_ui(page, lastname):
    """Read a controller_id from accueil's universal select (shows all)."""
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


def _ressources_view(page):
    safe_goto(page, f"{PHP_BASE_URL}/ressources/view.php")
    page.wait_for_load_state("load")
    return page.content()


def _faction_view(page):
    safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
    page.wait_for_load_state("load")
    return page.content()


def _scrape_amount(content, ressource_name):
    """Pull the integer amount cell from the summary table for the named ressource."""
    pattern = rf'<td>{re.escape(ressource_name)}</td>\s*<td[^>]*>\s*(\d+)\s*</td>'
    m = re.search(pattern, content)
    return int(m.group(1)) if m else None


def _seed_ckl_admin(page, recipient_id, location_id):
    """Admin path writes CKL row without an information-gift-log entry —
    sets up known-location state for the player-path gift under test."""
    safe_goto(
        page,
        f"{PHP_BASE_URL}/controllers/management.php"
        f"?giftInformationLocation=1&target_controller_id={recipient_id}"
        f"&location_id={location_id}"
    )
    page.wait_for_load_state("load")


# ---------------------------------------------------------------------------
# Ressource gift (Issue #10)
# ---------------------------------------------------------------------------


class TestRessourcesPageRenders:
    """Page loads with TestConfig amounts and gift form visible."""

    @pytest.fixture(scope="class", autouse=True)
    def page_state(self, browser):
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)
        _set_active_via_ui(page, "Alpha")
        content = _ressources_view(page)
        assert_no_collected_php_errors(page)
        ctx.close()
        type(self)._content = content
        yield

    def test_title_present(self):
        assert "Ressources de la faction" in self._content

    def test_gift_form_present(self):
        assert "Faire un don" in self._content
        assert "giftRessource" in self._content

    def test_donations_section_present(self):
        assert "Donations reçues" in self._content

    def test_summary_shows_gold_row(self):
        assert _scrape_amount(self._content, "Gold") is not None


class TestRessourceGiftHappyPath:
    """Submitting the gift form via UI shifts the giver and recipient amounts."""

    _gift_amount = 10

    @pytest.fixture(scope="class", autouse=True)
    def gift_state(self, browser):
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        _set_active_via_ui(page, "Alpha")
        pre_alpha = _scrape_amount(_ressources_view(page), "Gold")
        _set_active_via_ui(page, "Beta")
        pre_beta = _scrape_amount(_ressources_view(page), "Gold")

        _set_active_via_ui(page, "Alpha")
        safe_goto(page, f"{PHP_BASE_URL}/ressources/view.php")
        page.wait_for_load_state("load")
        gold_value = page.locator(
            "select[name='ressource_id'] option:has-text('Gold')"
        ).first.get_attribute("value")
        beta_value = page.locator(
            "select[name='target_controller_id'] option:has-text('Beta')"
        ).first.get_attribute("value")
        page.locator("select[name='ressource_id']").select_option(value=gold_value)
        page.fill("input[name='amount']", str(self._gift_amount))
        page.locator("select[name='target_controller_id']").select_option(value=beta_value)
        page.locator("input[name='giftRessource']").click()
        page.wait_for_load_state("load")
        post_alpha_content = page.content()
        post_alpha = _scrape_amount(post_alpha_content, "Gold")

        _set_active_via_ui(page, "Beta")
        post_beta_content = _ressources_view(page)
        post_beta = _scrape_amount(post_beta_content, "Gold")

        assert_no_collected_php_errors(page)
        ctx.close()
        type(self)._pre_alpha = pre_alpha
        type(self)._post_alpha = post_alpha
        type(self)._pre_beta = pre_beta
        type(self)._post_beta = post_beta
        type(self)._post_alpha_content = post_alpha_content
        type(self)._post_beta_content = post_beta_content
        yield

    def test_giver_decremented(self):
        assert self._post_alpha == self._pre_alpha - self._gift_amount

    def test_recipient_incremented(self):
        assert self._post_beta == self._pre_beta + self._gift_amount

    def test_success_notification_renders(self):
        assert "is-success" in self._post_alpha_content
        assert f"Don de {self._gift_amount}" in self._post_alpha_content

    def test_donations_log_shows_entry(self):
        """Beta's view should mention Alpha + Gold in the Donations reçues panel."""
        assert "Alpha" in self._post_beta_content
        assert "Gold" in self._post_beta_content


class TestRessourceGiftValidationRejects:
    """Self-target, over-stock, and zero-amount POSTs leave both amounts unchanged."""

    @pytest.fixture(scope="class", autouse=True)
    def reject_state(self, browser):
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        alpha_value = _resolve_controller_id_via_ui(page, "Alpha")
        beta_value = _resolve_controller_id_via_ui(page, "Beta")

        _set_active_via_ui(page, "Alpha")
        pre_alpha_content = _ressources_view(page)
        pre_alpha = _scrape_amount(pre_alpha_content, "Gold")
        gold_value = page.locator(
            "select[name='ressource_id'] option:has-text('Gold')"
        ).first.get_attribute("value")

        _set_active_via_ui(page, "Beta")
        pre_beta = _scrape_amount(_ressources_view(page), "Gold")

        _set_active_via_ui(page, "Alpha")
        rejections = [
            {"ressource_id": gold_value, "target_controller_id": beta_value,  "amount": "999999", "giftRessource": "Donner"},
            {"ressource_id": gold_value, "target_controller_id": alpha_value, "amount": "5",      "giftRessource": "Donner"},
            {"ressource_id": gold_value, "target_controller_id": beta_value,  "amount": "0",      "giftRessource": "Donner"},
        ]
        for form in rejections:
            page.request.post(f"{PHP_BASE_URL}/ressources/action.php", form=form)

        post_alpha = _scrape_amount(_ressources_view(page), "Gold")
        _set_active_via_ui(page, "Beta")
        post_beta = _scrape_amount(_ressources_view(page), "Gold")

        assert_no_collected_php_errors(page)
        ctx.close()
        type(self)._pre_alpha = pre_alpha
        type(self)._post_alpha = post_alpha
        type(self)._pre_beta = pre_beta
        type(self)._post_beta = post_beta
        yield

    def test_giver_unchanged(self):
        assert self._post_alpha == self._pre_alpha

    def test_recipient_unchanged(self):
        assert self._post_beta == self._pre_beta


# ---------------------------------------------------------------------------
# Information gift log (Issue #61)
# ---------------------------------------------------------------------------


class TestInformationPanelPresent:
    """Faction page renders the 'Informations reçues' panel, empty by default."""

    @pytest.fixture(scope="class", autouse=True)
    def panel_state(self, browser):
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)
        _set_active_via_ui(page, "Alpha")
        content = _faction_view(page)
        assert_no_collected_php_errors(page)
        ctx.close()
        type(self)._content = content
        yield

    def test_panel_present(self):
        assert "Informations reçues" in self._content


class TestInformationGiftLocationLogged:
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
        beta_content = _faction_view(page)
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


class TestInformationAdminTransactionsTable:
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
