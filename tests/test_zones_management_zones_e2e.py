"""E2E tests for the admin zone_rules + adjacent_zones editors on
`zones/management_zones.php`.

RED phase: covers not-yet-implemented per-row textareas
    <textarea name="zone_rules">
    <textarea name="adjacent_zones">
inside the existing claimer/holder update form. The handler at
zones/management_zones.php lines 16-24 will be extended so that:

  zone_rules_json:
    - empty        -> UPDATE zone_rules = NULL
    - non-empty    -> json_decode; parse fail (or non-array result) ->
                      reject the whole UPDATE and render a
                      `<p style='color: red;'>` error via $update_msg;
                      parse ok -> json_encode + UPDATE zone_rules.

  adjacent_zones:
    - TEXT column, comma-separated integer zone ids.
    - Trim leading/trailing whitespace, store raw (no content
      validation — trust admin, like claimer/holder).
    - Empty input -> store "" (empty string, NOT NULL).

Existing claimer/holder writes must still work alongside the new
fields (regression test 5).

Zone locked: `Zeta-Unclaimed` (TestConfig CSV row 8), an untouched
reserve zone with no claimer, holder, or adjacent zones — used
exclusively by this file so state does not race other test modules.

Run:
    python3 -m pytest tests/test_zones_management_zones_e2e.py -v
"""
import json

import pytest
from playwright.sync_api import Page

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, load_minimal_data, load_scenario_via_admin, safe_goto,
)


_TARGET_ZONE = "Zeta-Unclaimed"
_VALID_JSON_STR = '{"Claim":{"zone_name":"Foo","value_delta":1}}'
_INVALID_JSON_STR = "not json {"


def _row_locator_by_name(page, zone_name):
    """UI-only row lookup by the visible zone name column."""
    return page.locator(
        f"tr:has(td:text-is('{zone_name}'))"
    )


def _ui_reset_zone_row(page, base_url, zone_name):
    """UI-only reset: navigate to management_zones.php and submit the row
    for `zone_name` with claimer / holder / adjacent_zones / zone_rules
    cleared. Leaves the browser on the reloaded page. Preserves is_hidden."""
    ensure_gm_login(page, base_url)
    safe_goto(page, f"{base_url}/zones/management_zones.php")
    page.wait_for_load_state("load")
    form = _row_locator_by_name(page, zone_name)
    form.locator("textarea[name='zone_rules']").fill("")
    form.locator("input[name='adjacent_zones']").fill("")
    form.locator("select[name='claimer_id']").select_option("")
    form.locator("select[name='holder_id']").select_option("")
    form.locator("button[type='submit']").click()
    page.wait_for_load_state("load")


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(autouse=True)
def _require_db():
    if not DB_AVAILABLE:
        pytest.skip("No local MySQL available")


@pytest.fixture(scope="module", autouse=True)
def load_test_config(browser):
    """Load TestConfig fresh for this module so Zeta-Unclaimed exists."""
    if not DB_AVAILABLE:
        yield
        return
    load_minimal_data()
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
    yield


class TestZoneRulesAdminEdit:
    """Verifies the yet-to-be-built `zone_rules` editor on
    zones/management_zones.php: presence, valid persistence, invalid
    rejection with page error, empty-string NULL semantics, and the
    claimer/holder regression guard."""

    def test_textarea_present_on_management_zones_page(self, page: Page, base_url):
        """A textarea[name='zone_rules'] must exist for at least
        one row on management_zones.php."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/zones/management_zones.php")
        page.wait_for_load_state("load")
        textareaCount = page.locator("textarea[name='zone_rules']").count()
        assert textareaCount >= 1, (
            "Expected at least one textarea[name='zone_rules'] on "
            f"management_zones.php; found {textareaCount}"
        )

    def test_valid_json_persists_to_db(self, page: Page, base_url):
        """Filling the row's textarea with valid JSON and submitting
        must write the JSON to `zones.zone_rules` — verified via the
        form's own re-rendered textarea value."""
        _ui_reset_zone_row(page, base_url, _TARGET_ZONE)
        form = _row_locator_by_name(page, _TARGET_ZONE)
        form.locator("textarea[name='zone_rules']").fill(_VALID_JSON_STR)
        form.locator("button[type='submit']").click()
        page.wait_for_load_state("load")

        form = _row_locator_by_name(page, _TARGET_ZONE)
        stored = form.locator("textarea[name='zone_rules']").input_value()
        assert stored != "", (
            f"After valid-JSON submit, zone_rules textarea for "
            f"{_TARGET_ZONE} is still empty"
        )
        # Accept either the exact string or a json.loads-equal value —
        # the handler re-encodes via json_encode which can reorder keys
        # or drop whitespace.
        try:
            assert json.loads(stored) == json.loads(_VALID_JSON_STR), (
                f"Stored zone_rules parses but does not equal the "
                f"submitted JSON. Stored={stored!r}"
            )
        except (TypeError, ValueError) as exc:
            raise AssertionError(
                f"Stored zone_rules is not parseable JSON: {stored!r} "
                f"({exc})"
            )

    def test_invalid_json_rejected_no_db_write(self, page: Page, base_url):
        """Submitting a broken JSON string must NOT write to the DB and
        must render a red error message reusing `$update_msg`."""
        _ui_reset_zone_row(page, base_url, _TARGET_ZONE)
        form = _row_locator_by_name(page, _TARGET_ZONE)
        pre = form.locator("textarea[name='zone_rules']").input_value()
        assert pre == "", (
            f"Pre-condition failed: zone_rules textarea should be empty "
            f"after reset, got {pre!r}"
        )
        form.locator("textarea[name='zone_rules']").fill(_INVALID_JSON_STR)
        form.locator("button[type='submit']").click()
        page.wait_for_load_state("load")

        html = page.content()
        assert "color: red" in html, (
            "Expected an error message with `color: red` after invalid "
            "JSON submit — none rendered"
        )
        form = _row_locator_by_name(page, _TARGET_ZONE)
        stored = form.locator("textarea[name='zone_rules']").input_value()
        assert stored == "", (
            f"Invalid-JSON submit must NOT have written zone_rules; "
            f"textarea shows {stored!r}"
        )

    def test_empty_textarea_sets_null(self, page: Page, base_url):
        """Pre-seeding zone_rules to a valid JSON and then submitting
        the row's form with the textarea cleared must NULL the column —
        verified via the textarea's `data-value-is-null` attribute set
        by management_zones.php from the PHP `=== null` check."""
        _ui_reset_zone_row(page, base_url, _TARGET_ZONE)
        # Seed via UI submit
        form = _row_locator_by_name(page, _TARGET_ZONE)
        form.locator("textarea[name='zone_rules']").fill(_VALID_JSON_STR)
        form.locator("button[type='submit']").click()
        page.wait_for_load_state("load")

        form = _row_locator_by_name(page, _TARGET_ZONE)
        seed_null_attr = form.locator(
            "textarea[name='zone_rules']"
        ).get_attribute("data-value-is-null")
        assert seed_null_attr == "false", (
            "Pre-condition failed: zone_rules should be non-null after "
            f"seed; data-value-is-null={seed_null_attr!r}"
        )

        # `fill('')` empties the textarea to simulate the admin clearing it.
        form.locator("textarea[name='zone_rules']").fill("")
        form.locator("button[type='submit']").click()
        page.wait_for_load_state("load")

        form = _row_locator_by_name(page, _TARGET_ZONE)
        stored_null_attr = form.locator(
            "textarea[name='zone_rules']"
        ).get_attribute("data-value-is-null")
        assert stored_null_attr == "true", (
            "Empty textarea submit must NULL zone_rules; "
            f"data-value-is-null={stored_null_attr!r}"
        )

    def test_claimer_holder_update_still_works_alongside_zone_rules(
        self, page: Page, base_url
    ):
        """Regression guard: with the new textarea in place, a POST
        that changes claimer_id while leaving the textarea at its
        prefilled value must still update the claimer AND must NOT
        disturb the existing zone_rules payload."""
        # UI reset then seed zone_rules via a submit (the same UI path
        # a normal admin would use).
        _ui_reset_zone_row(page, base_url, _TARGET_ZONE)
        form = _row_locator_by_name(page, _TARGET_ZONE)
        form.locator("textarea[name='zone_rules']").fill(_VALID_JSON_STR)
        form.locator("button[type='submit']").click()
        page.wait_for_load_state("load")

        # Pick an Alpha-lastname claimer option by its visible label
        # (the select's option text is `firstname lastname`).
        form = _row_locator_by_name(page, _TARGET_ZONE)
        option_texts = form.locator(
            "select[name='claimer_id'] option"
        ).all_text_contents()
        alpha_label = next(
            (t.strip() for t in option_texts if t.strip().endswith("Alpha")),
            None,
        )
        assert alpha_label, (
            f"No Alpha-lastname option in claimer select; "
            f"options seen: {option_texts!r}"
        )
        # Read the textarea's prefill so the POST re-submits it unchanged
        # (mirrors how the admin would leave it alone in the UI).
        prefill = form.locator("textarea[name='zone_rules']").input_value()
        form.locator("select[name='claimer_id']").select_option(label=alpha_label)
        form.locator("button[type='submit']").click()
        page.wait_for_load_state("load")

        form = _row_locator_by_name(page, _TARGET_ZONE)
        selected_claimer = form.locator(
            "select[name='claimer_id'] option:checked"
        ).text_content().strip()
        assert selected_claimer == alpha_label, (
            f"Claimer update did not persist alongside the zone_rules field."
            f" Expected {alpha_label!r}, got {selected_claimer!r}"
        )
        stored = form.locator("textarea[name='zone_rules']").input_value()
        assert stored != "", (
            "Regression: zone_rules was cleared by an unrelated claimer update"
        )
        assert json.loads(stored) == json.loads(_VALID_JSON_STR), (
            "Regression: zone_rules value changed during a claimer update. "
            f"Prefill={prefill!r}, stored={stored!r}"
        )


class TestAdjacentZonesAdminEdit:
    """Verifies the yet-to-be-built `adjacent_zones` textarea editor
    on zones/management_zones.php. Adjacent_zones is a TEXT column
    holding comma-separated integer zone ids. On submit the handler
    trims whitespace and stores the raw string (no content
    validation). Empty input stores `""` — NOT NULL."""

    def test_adjacent_zones_textarea_present(self, page: Page, base_url):
        """A input[name='adjacent_zones'] must exist for at least
        one row on management_zones.php."""
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/zones/management_zones.php")
        page.wait_for_load_state("load")
        textareaCount = page.locator("input[name='adjacent_zones']").count()
        assert textareaCount >= 1, (
            "Expected at least one input[name='adjacent_zones'] on "
            f"management_zones.php; found {textareaCount}"
        )

    def test_adjacent_zones_persists_to_db(self, page: Page, base_url):
        """Filling the row's adjacent_zones input with `2,4` and
        submitting must persist exactly `2,4` — verified via the form's
        re-rendered input value."""
        _ui_reset_zone_row(page, base_url, _TARGET_ZONE)
        form = _row_locator_by_name(page, _TARGET_ZONE)
        form.locator("input[name='adjacent_zones']").fill("2,4")
        form.locator("button[type='submit']").click()
        page.wait_for_load_state("load")

        form = _row_locator_by_name(page, _TARGET_ZONE)
        stored = form.locator("input[name='adjacent_zones']").input_value()
        assert stored == "2,4", (
            f"Expected adjacent_zones='2,4' after submit; got {stored!r}"
        )

    def test_adjacent_zones_trimmed_on_submit(self, page: Page, base_url):
        """Leading/trailing whitespace around the input must be trimmed
        before storing. `  2,4  ` -> `2,4` (verified via form re-render)."""
        _ui_reset_zone_row(page, base_url, _TARGET_ZONE)
        form = _row_locator_by_name(page, _TARGET_ZONE)
        form.locator("input[name='adjacent_zones']").fill("  2,4  ")
        form.locator("button[type='submit']").click()
        page.wait_for_load_state("load")

        form = _row_locator_by_name(page, _TARGET_ZONE)
        stored = form.locator("input[name='adjacent_zones']").input_value()
        assert stored == "2,4", (
            f"Expected trimmed adjacent_zones='2,4' after submit; "
            f"got {stored!r}"
        )

    def test_adjacent_zones_empty_stores_empty_string_not_null(
        self, page: Page, base_url
    ):
        """Pre-seed adjacent_zones to `1,2,3`, then submit with the
        input cleared. Result must be an empty string, NOT NULL — the
        column is TEXT with an empty-string default. Verified via the
        input's `data-value-is-null='false'` attribute (PHP `=== null`
        check exposes the DB-level distinction between `""` and NULL)."""
        _ui_reset_zone_row(page, base_url, _TARGET_ZONE)
        # Seed via UI submit
        form = _row_locator_by_name(page, _TARGET_ZONE)
        form.locator("input[name='adjacent_zones']").fill("1,2,3")
        form.locator("button[type='submit']").click()
        page.wait_for_load_state("load")

        form = _row_locator_by_name(page, _TARGET_ZONE)
        seeded = form.locator("input[name='adjacent_zones']").input_value()
        assert seeded == "1,2,3", (
            f"Pre-condition failed: adjacent_zones should be '1,2,3' "
            f"after seed; got {seeded!r}"
        )

        form.locator("input[name='adjacent_zones']").fill("")
        form.locator("button[type='submit']").click()
        page.wait_for_load_state("load")

        form = _row_locator_by_name(page, _TARGET_ZONE)
        input_locator = form.locator("input[name='adjacent_zones']")
        stored = input_locator.input_value()
        is_null_attr = input_locator.get_attribute("data-value-is-null")
        assert stored == "", (
            "Empty input submit must store '' (empty string), NOT the "
            f"pre-seed. Got {stored!r}"
        )
        assert is_null_attr == "false", (
            "adjacent_zones must NOT be NULL — it is a TEXT column with "
            f"empty-string semantics on clear. data-value-is-null="
            f"{is_null_attr!r}"
        )
