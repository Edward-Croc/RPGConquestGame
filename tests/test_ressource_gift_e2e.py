"""Playwright E2E tests for Issue #10 — ressource gift mechanic.

Tests rely on TestConfig CSV defaults (Alpha 100 Gold, Beta 80 Gold, etc.)
and verify behaviour by reading the rendered HTML of the Ressources page.

No direct DB writes. The IDs needed to build form payloads are scraped
from the page's own <option value="..."> attributes so the tests can
run on the demo environment with UI_ONLY=1.

Assertions are delta-based — they compare pre/post amounts read from
the UI, so they don't break if a prior test nudged the baseline.

Run:
    python3 -m pytest tests/test_ressource_gift_e2e.py -v
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
    """Read a controller_id from accueil's universal select (shows all controllers)."""
    safe_goto(page, f"{PHP_BASE_URL}/base/accueil.php")
    page.wait_for_load_state("load")
    return page.locator(
        f"select[name='controller_id'] option:has-text('{lastname}')"
    ).first.get_attribute("value")


def _set_active_via_ui(page, lastname):
    value = _resolve_controller_id_via_ui(page, lastname)
    safe_goto(page, f"{PHP_BASE_URL}/base/accueil.php?controller_id={value}")
    page.wait_for_load_state("load")


def _view(page):
    safe_goto(page, f"{PHP_BASE_URL}/ressources/view.php")
    page.wait_for_load_state("load")
    return page.content()


def _scrape_amount(content, ressource_name):
    """Pull the integer amount cell from the summary table for the named ressource."""
    pattern = rf'<td>{re.escape(ressource_name)}</td>\s*<td[^>]*>\s*(\d+)\s*</td>'
    m = re.search(pattern, content)
    return int(m.group(1)) if m else None


class TestRessourcesPageRenders:
    """Page loads with TestConfig amounts and gift form visible."""

    @pytest.fixture(scope="class", autouse=True)
    def page_state(self, browser):
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        _set_active_via_ui(page, "Alpha")
        content = _view(page)
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


class TestGiftHappyPath:
    """Submitting the gift form via UI shifts the giver and recipient amounts."""

    _gift_amount = 10

    @pytest.fixture(scope="class", autouse=True)
    def gift_state(self, browser):
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        _set_active_via_ui(page, "Alpha")
        pre_alpha = _scrape_amount(_view(page), "Gold")

        _set_active_via_ui(page, "Beta")
        pre_beta = _scrape_amount(_view(page), "Gold")

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
        post_beta_content = _view(page)
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


class TestGiftValidationRejects:
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
        pre_alpha_content = _view(page)
        pre_alpha = _scrape_amount(pre_alpha_content, "Gold")
        gold_value = page.locator(
            "select[name='ressource_id'] option:has-text('Gold')"
        ).first.get_attribute("value")

        _set_active_via_ui(page, "Beta")
        pre_beta = _scrape_amount(_view(page), "Gold")

        _set_active_via_ui(page, "Alpha")
        rejections = [
            {"ressource_id": gold_value, "target_controller_id": beta_value,  "amount": "999999", "giftRessource": "Donner"},
            {"ressource_id": gold_value, "target_controller_id": alpha_value, "amount": "5",     "giftRessource": "Donner"},
            {"ressource_id": gold_value, "target_controller_id": beta_value,  "amount": "0",      "giftRessource": "Donner"},
        ]
        for form in rejections:
            page.request.post(f"{PHP_BASE_URL}/ressources/action.php", form=form)

        post_alpha = _scrape_amount(_view(page), "Gold")
        _set_active_via_ui(page, "Beta")
        post_beta = _scrape_amount(_view(page), "Gold")

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
