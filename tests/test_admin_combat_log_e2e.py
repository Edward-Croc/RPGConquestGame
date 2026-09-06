"""End-to-end coverage of the read-only admin page
`workers/management_combat.php` -- log-refactor.

Structure, auth guard, ordering, filters (including the turn=0 sentinel
trap), the agent_attack_defence-only location filter, unresolved-row
invariants, empty-state rendering, and the base/admin.php hub links. All
assertions are UI-only (data-* attributes, no pymysql / direct SQL) so the
suite runs under UI_ONLY=1 against a remote deployment.

Data: built by helpers.seed_worker_combat_scenario(), the same shared
builder test_worker_combat_logs_e2e.py uses, so this file's
ordering/filter tests have real, deterministic worker_combat_logs rows
to work against (16 rows, single turn, single created_at second --
which is exactly the condition the id-DESC tie-break exists to cover).

Run:
    python3 -m pytest tests/test_admin_combat_log_e2e.py -v
"""
from itertools import groupby

import pytest
from playwright.sync_api import Page

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    login_as, safe_goto, set_config_via_ui,
    ui_combat_logs, ui_combat_unresolved_count, ui_combat_filter_options,
    seed_worker_combat_scenario,
)


@pytest.fixture(scope="module", autouse=True)
def admin_combat_scenario(browser):
    """Load TestConfig and produce the same 16-row combat dataset as
    test_worker_combat_logs_e2e.py (see that file for the full pair
    breakdown and per-pair outcomes)."""
    context = None
    try:
        context = seed_worker_combat_scenario(browser, base_url=PHP_BASE_URL)
        yield
    finally:
        if context is not None:
            context.close()


class TestAdminCombatLogRenders:
    """Basic render smoke -- gm session, marker present, correct wrapper."""

    def test_page_renders_for_gm(self, page: Page):
        ensure_gm_login(page, PHP_BASE_URL)
        safe_goto(page, f"{PHP_BASE_URL}/workers/management_combat.php")
        assert page.locator("div.management[data-combat-log='1']").count() == 1, (
            "expected the root <div class='management' data-combat-log=\"1\"> wrapper"
        )


class TestAdminGuard:
    """gm-only page: anonymous and non-privileged sessions must be
    redirected to the login form, never see the log."""

    def test_anonymous_redirects_to_login(self, browser):
        context = browser.new_context()
        page = context.new_page()
        page.goto(f"{PHP_BASE_URL}/workers/management_combat.php")
        assert "loginForm.php" in page.url, (
            f"anonymous GET on management_combat.php must redirect to the "
            f"login form; landed on {page.url}"
        )
        context.close()

    def test_non_privileged_login_redirects(self, browser):
        """single_player/test (TestConfig, non-gm) is not is_privileged.

        management_combat.php redirects to connection/loginForm.php, but
        loginForm.php itself immediately bounces an already-authenticated
        session on to base/accueil.php -- so the final landing page for a
        logged-in-but-non-privileged user is accueil.php, not
        loginForm.php. Either way the guard must keep them off the page."""
        context = browser.new_context()
        page = context.new_page()
        login_as(page, PHP_BASE_URL, "single_player", "test")
        page.goto(f"{PHP_BASE_URL}/workers/management_combat.php")
        assert "management_combat.php" not in page.url, (
            f"non-privileged session must not reach management_combat.php; "
            f"landed on {page.url}"
        )
        assert page.url.endswith("accueil.php") or "loginForm.php" in page.url, (
            f"expected the guard to bounce to accueil.php (via the "
            f"already-logged-in redirect in loginForm.php) or land on "
            f"loginForm.php directly; landed on {page.url}"
        )
        context.close()


class TestAdminCombatLogOrdering:
    """Default order: turn DESC, id DESC tie-break (management_combat.php
    calls getWorkerCombatLogs(..., 'turn', 'desc'))."""

    def test_default_order_turn_desc_id_desc_tiebreak(self, page: Page):
        ensure_gm_login(page, PHP_BASE_URL)
        logs = ui_combat_logs(page, base_url=PHP_BASE_URL)
        assert len(logs) > 0, "expected at least one combat log row"

        turns = [r['turn'] for r in logs]
        assert turns == sorted(turns, reverse=True), (
            f"data-turn should be non-increasing top-to-bottom; got {turns}"
        )

        for turn_value, group in groupby(logs, key=lambda r: r['turn']):
            ids = [r['id'] for r in group]
            assert ids == sorted(ids, reverse=True) and len(set(ids)) == len(ids), (
                f"within turn={turn_value}, data-combat-log-id should be "
                f"strictly decreasing (the id tie-break created_at "
                f"granularity exists for); got {ids}"
            )


class TestAdminCombatLogFilters:
    def test_worker_filter_matches_attacker_or_defender(self, page: Page):
        ensure_gm_login(page, PHP_BASE_URL)
        unfiltered = ui_combat_logs(page, base_url=PHP_BASE_URL)
        assert unfiltered, "need at least one row to test the worker filter"
        target_id = unfiltered[0]['defender_worker_id']
        filtered = ui_combat_logs(page, base_url=PHP_BASE_URL, worker_id=target_id)
        assert filtered, f"expected at least one row for worker_id={target_id}"
        assert len(filtered) <= len(unfiltered)
        for row in filtered:
            assert target_id in (row['attacker_worker_id'], row['defender_worker_id']), (
                f"row {row} does not carry worker_id={target_id} on "
                f"either the attacker or defender side"
            )

    def test_turn_filter(self, page: Page):
        ensure_gm_login(page, PHP_BASE_URL)
        unfiltered = ui_combat_logs(page, base_url=PHP_BASE_URL)
        assert unfiltered
        turn_value = unfiltered[0]['turn']
        filtered = ui_combat_logs(page, base_url=PHP_BASE_URL, turn=turn_value)
        assert filtered
        assert all(r['turn'] == turn_value for r in filtered)
        assert len(filtered) <= len(unfiltered)

    def test_turn_zero_filter_not_equivalent_to_unfiltered(self, page: Page):
        """Regression guard for the sentinel trap: a plain (int) cast on
        an empty ?turn= would make 'no filter' and 'turn zero'
        indistinguishable. This scenario's combat resolves on turn 1, so
        ?turn=0 must return strictly fewer rows than unfiltered."""
        ensure_gm_login(page, PHP_BASE_URL)
        unfiltered = ui_combat_logs(page, base_url=PHP_BASE_URL)
        zero_filtered = ui_combat_logs(page, base_url=PHP_BASE_URL, turn=0)
        assert len(zero_filtered) != len(unfiltered), (
            f"?turn=0 must be distinguishable from no-filter; got "
            f"{len(zero_filtered)} rows filtered vs {len(unfiltered)} unfiltered"
        )
        assert all(r['turn'] == 0 for r in zero_filtered)

    def test_worker_and_turn_combined_filter_intersection(self, page: Page):
        ensure_gm_login(page, PHP_BASE_URL)
        unfiltered = ui_combat_logs(page, base_url=PHP_BASE_URL)
        assert unfiltered
        row = unfiltered[0]
        combined = ui_combat_logs(
            page, base_url=PHP_BASE_URL,
            worker_id=row['defender_worker_id'], turn=row['turn'],
        )
        assert combined, "expected at least one row in the intersection"
        for r in combined:
            assert r['turn'] == row['turn']
            assert row['defender_worker_id'] in (
                r['attacker_worker_id'], r['defender_worker_id']
            )


class TestAdminCombatLogLocationGating:
    """select[name='location'] + the Lieu column only render in
    agent_attack_defence mode; outside it, a bookmarked ?location=N is
    ignored rather than emptying the table (defence in depth)."""

    @pytest.fixture(autouse=True)
    def _restore_mode(self, page: Page):
        try:
            yield
        finally:
            ensure_gm_login(page, PHP_BASE_URL)
            set_config_via_ui(page, "locationAttackMode", "immediate", base_url=PHP_BASE_URL)

    def test_location_filter_absent_in_immediate_mode(self, page: Page):
        ensure_gm_login(page, PHP_BASE_URL)
        set_config_via_ui(page, "locationAttackMode", "immediate", base_url=PHP_BASE_URL)
        options = ui_combat_filter_options(page, base_url=PHP_BASE_URL)
        assert options['has_location_filter'] is False
        assert options['locations'] == []
        safe_goto(page, f"{PHP_BASE_URL}/workers/management_combat.php")
        assert page.locator("th:text-is('Lieu')").count() == 0, (
            "Lieu header should be absent outside agent_attack_defence mode"
        )

    def test_location_filter_present_in_agent_attack_defence_mode(self, page: Page):
        ensure_gm_login(page, PHP_BASE_URL)
        set_config_via_ui(page, "locationAttackMode", "agent_attack_defence", base_url=PHP_BASE_URL)
        options = ui_combat_filter_options(page, base_url=PHP_BASE_URL)
        assert options['has_location_filter'] is True
        safe_goto(page, f"{PHP_BASE_URL}/workers/management_combat.php")
        assert page.locator("th:text-is('Lieu')").count() == 1, (
            "Lieu header should be present in agent_attack_defence mode"
        )

    def test_location_param_ignored_outside_agent_attack_defence_mode(self, page: Page):
        """A bookmarked ?location=N in immediate mode must not empty the
        table -- it is ignored entirely, not merely hidden."""
        ensure_gm_login(page, PHP_BASE_URL)
        set_config_via_ui(page, "locationAttackMode", "immediate", base_url=PHP_BASE_URL)
        unfiltered = ui_combat_logs(page, base_url=PHP_BASE_URL)
        filtered = ui_combat_logs(page, base_url=PHP_BASE_URL, location_id=999999)
        assert len(filtered) == len(unfiltered), (
            f"?location= must be ignored (not emptying the table) outside "
            f"agent_attack_defence mode; unfiltered={len(unfiltered)} "
            f"filtered={len(filtered)}"
        )


class TestAdminCombatLogUnresolvedInvariants:
    """Banner/table agreement + per-row invariants for unresolved combats."""

    def test_unresolved_banner_matches_table(self, page: Page):
        ensure_gm_login(page, PHP_BASE_URL)
        count = ui_combat_unresolved_count(page, base_url=PHP_BASE_URL)
        assert isinstance(count, int)
        logs = ui_combat_logs(page, base_url=PHP_BASE_URL)
        unresolved_rows = [r for r in logs if not r['resolved']]
        for r in unresolved_rows:
            assert r['outcome'] is None, (
                f"unresolved row must have empty data-outcome; got {r}"
            )
            assert 'combat-unresolved' in r['class_attr'], (
                f"unresolved row must carry class combat-unresolved; got {r}"
            )
        assert count == len(unresolved_rows), (
            f"[data-unresolved-count]={count} must equal the number of "
            f"data-resolved=\"0\" rows ({len(unresolved_rows)})"
        )
        if count == 0:
            # Unresolved rows need attackMechanic aborted mid-loop, unreachable from the UI.
            assert unresolved_rows == []


class TestAdminCombatLogEmptyState:
    def test_worker_and_turn_zero_combo_yields_empty_state(self, page: Page):
        """Single filters can't be empty by design (dropdowns are built
        from DISTINCT values already present in the log), so the
        guaranteed-empty case is a combination: any real worker id
        intersected with turn=0 (no combat happened on turn 0 in this
        scenario)."""
        ensure_gm_login(page, PHP_BASE_URL)
        unfiltered = ui_combat_logs(page, base_url=PHP_BASE_URL)
        assert unfiltered
        worker_id = unfiltered[0]['attacker_worker_id']
        empty_logs = ui_combat_logs(
            page, base_url=PHP_BASE_URL, worker_id=worker_id, turn=0
        )
        assert empty_logs == []
        assert page.locator("tr.combat-row").count() == 0
        assert page.locator('[data-combat-empty="filtered"]').count() == 1


class TestAdminCombatLogHubLinks:
    def test_hub_links_and_navigation(self, page: Page):
        ensure_gm_login(page, PHP_BASE_URL)
        safe_goto(page, f"{PHP_BASE_URL}/base/admin.php")
        html = page.content()
        assert "Attack on location log" in html, (
            "hub should carry the renamed 'Attack on location log' link"
        )
        assert "Attack on player base list" not in html, (
            "the old 'Attack on player base list' label must be gone"
        )
        link = page.locator("a[href$='workers/management_combat.php']")
        assert link.count() == 1, (
            "expected exactly one link ending in workers/management_combat.php"
        )
        assert link.first.inner_text().strip() == "Agent combat log"

        link.first.click()
        page.wait_for_load_state("load")
        assert page.locator("[data-combat-log]").count() == 1, (
            "clicking the hub link should land on management_combat.php"
        )
