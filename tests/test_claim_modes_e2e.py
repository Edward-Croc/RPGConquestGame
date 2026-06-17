"""Playwright E2E tests for the claim-mode dispatcher (issue #47 zone rework v1).

Mode A (`claimMode='worker'`) is exercised by the existing
`test_agent_combat_e2e.py` suite and stays untouched. This file covers
mode B (`claimMode='worker_leader'`) where the per-worker D6 is dropped
in favour of `calculateControllerValue('Claim', ...)` vs the zone's
`calculated_defence_val` recomputed by `recalculateZoneDefence`.

Math calibration (TestConfig defaults + per-test overrides):
  defence_val(zone) baseline = 6 (per setupBDD.sql DEFAULT).
  Set `zones.defence_val = 0` for the target zone so the threshold is
  reachable by the Beta-Combat worker pool. With baseClaimAddWorkers=1
  and ~7 Beta workers in Beta-Combat (all active including passive),
  claim_val ≈ 7 vs defence ≈ 3 (noControllerZoneDefenceBonus) → wins.
"""
import pymysql
import pytest

from conftest import (
    GAME_PREFIX, MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB,
    PHP_BASE_URL, ensure_gm_login,
)
from helpers import (
    end_turn, load_minimal_data, safe_goto, ui_claim, ui_claim_click,
    register_php_error_listener, assert_no_collected_php_errors,
)


def _db_conn():
    return pymysql.connect(
        host=MYSQL_HOST, port=MYSQL_PORT, user=MYSQL_USER,
        password=MYSQL_PASSWORD, database=MYSQL_DB,
        charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor,
    )


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def load_test_config(browser):
    """Load TestConfig once for this module — mirrors the pattern used by
    other claim/combat test modules."""
    load_minimal_data()
    context = browser.new_context()
    page = context.new_page()
    register_php_error_listener(page)
    safe_goto(page, f"{PHP_BASE_URL}/connection/loginForm.php")
    page.wait_for_load_state("load")
    page.locator("input[name='username']").fill("gm")
    page.locator("input[name='passwd']").fill("orga")
    page.locator("input[type='submit']").first.click()
    page.wait_for_load_state("load")
    safe_goto(page, f"{PHP_BASE_URL}/base/admin.php")
    page.wait_for_load_state("load")
    page.locator("select[name='config_name']").select_option("TestConfig")
    page.locator("input[type='submit'][value='Submit']").click()
    if page.locator("#confirmModalYes").is_visible():
        page.locator("#confirmModalYes").click()
    page.wait_for_timeout(5000)
    page.wait_for_load_state("load", timeout=90000)
    assert_no_collected_php_errors(page)
    context.close()
    yield


@pytest.mark.db
class TestClaimModeWorkerLeader:
    """Mode B (`claimMode='worker_leader'`) — deterministic claim by
    aggregate controller presence (no D6, no per-worker attack_val).

    Scenario: Beta has ~7 workers in Beta-Combat (default TestConfig).
    The zone is unowned. After one Beta worker submits `action_choice='claim'`
    for Beta and end-of-turn fires, the zone's holder + claimer become Beta.
    """

    @pytest.fixture(scope="class", autouse=True)
    def claim_state(self, browser):
        conn = _db_conn()
        cur = conn.cursor()
        cur.execute(
            f"UPDATE `{GAME_PREFIX}config` SET value = 'worker_leader' "
            f"WHERE name = 'claimMode'"
        )
        cur.execute(
            f"UPDATE `{GAME_PREFIX}zones` SET defence_val = 0 "
            f"WHERE name = 'Beta-Combat'"
        )
        conn.commit()

        cur.execute(
            f"SELECT claimer_controller_id, holder_controller_id "
            f"FROM `{GAME_PREFIX}zones` WHERE name = 'Beta-Combat'"
        )
        pre = cur.fetchone()

        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        ui_claim_click(page, 'Chain_B', 'Beta')
        end_turn(page, PHP_BASE_URL)

        conn.commit()  # refresh REPEATABLE READ snapshot so post-state SELECT sees PHP's commit
        cur.execute(
            f"SELECT claimer_controller_id, holder_controller_id, calculated_defence_val "
            f"FROM `{GAME_PREFIX}zones` WHERE name = 'Beta-Combat'"
        )
        post = cur.fetchone()

        cur.execute(
            f"SELECT id FROM `{GAME_PREFIX}controllers` WHERE lastname = 'Beta' LIMIT 1"
        )
        beta_id = cur.fetchone()['id']

        cur.execute(
            f"UPDATE `{GAME_PREFIX}config` SET value = 'worker' "
            f"WHERE name = 'claimMode'"
        )
        cur.execute(
            f"UPDATE `{GAME_PREFIX}zones` SET defence_val = 6 "
            f"WHERE name = 'Beta-Combat'"
        )
        conn.commit()
        cur.close()
        conn.close()

        assert_no_collected_php_errors(page)
        context.close()

        type(self)._pre = pre
        type(self)._post = post
        type(self)._beta_id = beta_id
        yield

    def test_pre_state_unowned(self):
        """Sanity: Beta-Combat starts unowned (TestConfig default)."""
        assert self._pre['claimer_controller_id'] is None
        assert self._pre['holder_controller_id'] is None

    def test_zone_holder_is_beta_after_end_turn(self):
        """Mode B claim by one Beta worker resolves at end-of-turn — Beta
        becomes the zone holder. Deterministic: per-worker attack_val is
        not consulted; the controller-aggregate formula wins."""
        assert self._post['holder_controller_id'] == self._beta_id, (
            f"holder_controller_id should be Beta (id={self._beta_id}); "
            f"got {self._post['holder_controller_id']}"
        )

    def test_zone_claimer_is_beta_after_end_turn(self):
        """No `action_params.claim_controller_id` override was given, so
        claimer mirrors holder (Beta)."""
        assert self._post['claimer_controller_id'] == self._beta_id, (
            f"claimer_controller_id should be Beta (id={self._beta_id}); "
            f"got {self._post['claimer_controller_id']}"
        )

    def test_zone_defence_was_recomputed(self):
        """`recalculateZoneDefence` (mode-B branch) populated
        `calculated_defence_val` via `calculateControllerValue('ZoneDefence', ...)`.
        With overridden `zones.defence_val=0` + no holder pre-EOT +
        `noControllerZoneDefenceBonus=3`, the recomputed defence is the
        noController bonus."""
        assert self._post['calculated_defence_val'] is not None, (
            "calculated_defence_val should be populated by recalculateZoneDefence"
        )


@pytest.mark.db
class TestClaimModeWorkerLeaderCrossController:
    """Mode B cross-controller claim — `action_params.claim_controller_id`
    override splits holder from claimer.

    Scenario: Keep_Def (Beta's worker) submits `claim` with
    `claim_controller_id=Alpha`. Beta's aggregate presence in Beta-Combat
    drives `claim_val`; the override only routes the claimer column.
    Expected post-EOT: holder = Beta (leader's controller), claimer = Alpha
    (override). Mirrors the existing `ui_claim('Keep_Def', 'Alpha')` line
    at `test_agent_combat_e2e.py:185` but under mode B's deterministic
    formula instead of mode A's per-worker D6.
    """

    @pytest.fixture(scope="class", autouse=True)
    def claim_state(self, browser):
        conn = _db_conn()
        cur = conn.cursor()
        cur.execute(
            f"UPDATE `{GAME_PREFIX}config` SET value = 'worker_leader' "
            f"WHERE name = 'claimMode'"
        )
        cur.execute(
            f"UPDATE `{GAME_PREFIX}zones` SET defence_val = 0, "
            f"claimer_controller_id = NULL, holder_controller_id = NULL "
            f"WHERE name = 'Beta-Combat'"
        )
        cur.execute(f"SELECT turncounter FROM `{GAME_PREFIX}mechanics` LIMIT 1")
        current_turn = cur.fetchone()['turncounter']
        cur.execute(
            f"UPDATE `{GAME_PREFIX}worker_actions` wa "
            f"JOIN `{GAME_PREFIX}workers` w ON w.id = wa.worker_id "
            f"JOIN `{GAME_PREFIX}controller_worker` cw ON cw.worker_id = w.id "
            f"SET wa.action_choice = 'passive', wa.action_params = '{{}}' "
            f"WHERE wa.turn_number = %s "
            f"  AND w.zone_id = (SELECT id FROM `{GAME_PREFIX}zones` WHERE name='Beta-Combat') "
            f"  AND cw.controller_id = (SELECT id FROM `{GAME_PREFIX}controllers` WHERE lastname='Beta') "
            f"  AND wa.action_choice = 'claim'",
            (current_turn,)
        )
        conn.commit()

        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        ui_claim_click(page, 'Keep_Def', 'Alpha')
        end_turn(page, PHP_BASE_URL)

        conn.commit()
        cur.execute(
            f"SELECT claimer_controller_id, holder_controller_id "
            f"FROM `{GAME_PREFIX}zones` WHERE name = 'Beta-Combat'"
        )
        post = cur.fetchone()

        cur.execute(
            f"SELECT id, lastname FROM `{GAME_PREFIX}controllers` "
            f"WHERE lastname IN ('Alpha', 'Beta')"
        )
        ids = {row['lastname']: row['id'] for row in cur.fetchall()}

        cur.execute(
            f"UPDATE `{GAME_PREFIX}config` SET value = 'worker' "
            f"WHERE name = 'claimMode'"
        )
        cur.execute(
            f"UPDATE `{GAME_PREFIX}zones` SET defence_val = 6 "
            f"WHERE name = 'Beta-Combat'"
        )
        conn.commit()
        cur.close()
        conn.close()

        assert_no_collected_php_errors(page)
        context.close()

        type(self)._post = post
        type(self)._alpha_id = ids['Alpha']
        type(self)._beta_id = ids['Beta']
        yield

    def test_holder_is_leaders_controller(self):
        """Holder = the LEADER's actual controller (Beta), regardless of
        the claim_controller_id override. The override only routes
        claimer; the holder column reflects who's physically running
        the zone."""
        assert self._post['holder_controller_id'] == self._beta_id, (
            f"holder_controller_id should be Beta (id={self._beta_id}); "
            f"got {self._post['holder_controller_id']}"
        )

    def test_claimer_uses_action_params_override(self):
        """Claimer = the override from `action_params.claim_controller_id`
        (Alpha), not the leader's controller. Parity with mode A's
        cross-controller claim semantics."""
        assert self._post['claimer_controller_id'] == self._alpha_id, (
            f"claimer_controller_id should be Alpha (id={self._alpha_id}); "
            f"got {self._post['claimer_controller_id']}"
        )


@pytest.mark.db
class TestClaimModeSupportingClaimersBonus:
    """Mode B `baseClaimAddSupporting` term — adds
    `max(0, COUNT(claim-action workers in zone for controller) - 1) × multiplier`
    to the controller's claim_val.

    Isolation: all other Claim terms are muted (baseClaim=0, AddWorkers=0,
    AddOwnedLocations=0). With two Beta workers submitting `claim` for
    Beta-Combat, supporters = max(0, 2-1) = 1, claim_val = 1 × 10 = 10.
    Defence = noControllerZoneDefenceBonus(=3) since the zone is unowned.
    10 - 3 = 7 >= claimDiff(=1) → WIN. The control class below uses one
    claimer and asserts the inverse → proves the supporters term is what
    flipped the outcome.
    """

    @pytest.fixture(scope="class", autouse=True)
    def claim_state(self, browser):
        conn = _db_conn()
        cur = conn.cursor()
        cur.execute(
            f"SELECT name, value FROM `{GAME_PREFIX}config` WHERE name IN ("
            f"'claimMode','baseClaim','baseClaimAddWorkers',"
            f"'baseClaimAddOwnedLocations','baseClaimAddSupporting')"
        )
        prev_config = {row['name']: row['value'] for row in cur.fetchall()}
        cur.execute(
            f"UPDATE `{GAME_PREFIX}config` SET value = 'worker_leader' "
            f"WHERE name = 'claimMode'"
        )
        cur.execute(
            f"UPDATE `{GAME_PREFIX}config` SET value = '0' WHERE name IN "
            f"('baseClaim','baseClaimAddWorkers','baseClaimAddOwnedLocations')"
        )
        cur.execute(
            f"UPDATE `{GAME_PREFIX}config` SET value = '10' "
            f"WHERE name = 'baseClaimAddSupporting'"
        )
        cur.execute(
            f"UPDATE `{GAME_PREFIX}zones` SET defence_val = 0, "
            f"claimer_controller_id = NULL, holder_controller_id = NULL "
            f"WHERE name = 'Beta-Combat'"
        )
        cur.execute(f"SELECT turncounter FROM `{GAME_PREFIX}mechanics` LIMIT 1")
        current_turn = cur.fetchone()['turncounter']
        cur.execute(
            f"UPDATE `{GAME_PREFIX}worker_actions` wa "
            f"JOIN `{GAME_PREFIX}workers` w ON w.id = wa.worker_id "
            f"SET wa.action_choice = 'passive', wa.action_params = '{{}}' "
            f"WHERE wa.turn_number = %s "
            f"  AND w.zone_id = (SELECT id FROM `{GAME_PREFIX}zones` WHERE name='Beta-Combat') "
            f"  AND wa.action_choice = 'claim'",
            (current_turn,)
        )
        conn.commit()

        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        ui_claim_click(page, 'Chain_B', 'Beta')
        ui_claim(page, 'Even_Def', 'Beta')
        end_turn(page, PHP_BASE_URL)

        conn.commit()
        cur.execute(
            f"SELECT claimer_controller_id, holder_controller_id "
            f"FROM `{GAME_PREFIX}zones` WHERE name = 'Beta-Combat'"
        )
        post = cur.fetchone()
        cur.execute(
            f"SELECT id FROM `{GAME_PREFIX}controllers` WHERE lastname = 'Beta' LIMIT 1"
        )
        beta_id = cur.fetchone()['id']

        for name, value in prev_config.items():
            cur.execute(
                f"UPDATE `{GAME_PREFIX}config` SET value = %s WHERE name = %s",
                (value, name),
            )
        cur.execute(
            f"UPDATE `{GAME_PREFIX}zones` SET defence_val = 6 "
            f"WHERE name = 'Beta-Combat'"
        )
        conn.commit()
        cur.close()
        conn.close()

        assert_no_collected_php_errors(page)
        context.close()

        type(self)._post = post
        type(self)._beta_id = beta_id
        yield

    def test_holder_is_beta_with_two_claimers(self):
        """Two Beta claim-actioners produce supporters=1 → claim_val=10 (all
        other Claim terms muted by zero multipliers). claim_val(10) -
        defence(3) = 7 >= claimDiff(1) → Beta becomes holder."""
        assert self._post['holder_controller_id'] == self._beta_id, (
            f"holder_controller_id should be Beta (id={self._beta_id}); "
            f"got {self._post['holder_controller_id']}. Without "
            f"baseClaimAddSupporting contributing, claim_val would be "
            f"0 and Beta would not have won."
        )

    def test_claimer_is_beta_with_two_claimers(self):
        """No action_params override given — claimer mirrors holder (Beta)."""
        assert self._post['claimer_controller_id'] == self._beta_id


@pytest.mark.db
class TestClaimModeSupportingClaimersControl:
    """Control case for `TestClaimModeSupportingClaimersBonus` — same muted
    Claim-term config, but a single Beta claim-actioner makes supporters =
    max(0, 1-1) = 0 → claim_val = 0. Defence = 3. 0 - 3 = -3 does not clear
    claimDiff(=1) → claim fails → zone stays unowned. Together with the
    bonus-active class, this isolates the supporting-claimers term as the
    sole reason Beta won in the multi-claimer case.
    """

    @pytest.fixture(scope="class", autouse=True)
    def claim_state(self, browser):
        conn = _db_conn()
        cur = conn.cursor()
        cur.execute(
            f"SELECT name, value FROM `{GAME_PREFIX}config` WHERE name IN ("
            f"'claimMode','baseClaim','baseClaimAddWorkers',"
            f"'baseClaimAddOwnedLocations','baseClaimAddSupporting')"
        )
        prev_config = {row['name']: row['value'] for row in cur.fetchall()}
        cur.execute(
            f"UPDATE `{GAME_PREFIX}config` SET value = 'worker_leader' "
            f"WHERE name = 'claimMode'"
        )
        cur.execute(
            f"UPDATE `{GAME_PREFIX}config` SET value = '0' WHERE name IN "
            f"('baseClaim','baseClaimAddWorkers','baseClaimAddOwnedLocations')"
        )
        cur.execute(
            f"UPDATE `{GAME_PREFIX}config` SET value = '10' "
            f"WHERE name = 'baseClaimAddSupporting'"
        )
        cur.execute(
            f"UPDATE `{GAME_PREFIX}zones` SET defence_val = 0, "
            f"claimer_controller_id = NULL, holder_controller_id = NULL "
            f"WHERE name = 'Beta-Combat'"
        )
        cur.execute(f"SELECT turncounter FROM `{GAME_PREFIX}mechanics` LIMIT 1")
        current_turn = cur.fetchone()['turncounter']
        cur.execute(
            f"UPDATE `{GAME_PREFIX}worker_actions` wa "
            f"JOIN `{GAME_PREFIX}workers` w ON w.id = wa.worker_id "
            f"SET wa.action_choice = 'passive', wa.action_params = '{{}}' "
            f"WHERE wa.turn_number = %s "
            f"  AND w.zone_id = (SELECT id FROM `{GAME_PREFIX}zones` WHERE name='Beta-Combat') "
            f"  AND wa.action_choice = 'claim'",
            (current_turn,)
        )
        conn.commit()

        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        ui_claim_click(page, 'Chain_B', 'Beta')
        end_turn(page, PHP_BASE_URL)

        conn.commit()
        cur.execute(
            f"SELECT claimer_controller_id, holder_controller_id "
            f"FROM `{GAME_PREFIX}zones` WHERE name = 'Beta-Combat'"
        )
        post = cur.fetchone()

        for name, value in prev_config.items():
            cur.execute(
                f"UPDATE `{GAME_PREFIX}config` SET value = %s WHERE name = %s",
                (value, name),
            )
        cur.execute(
            f"UPDATE `{GAME_PREFIX}zones` SET defence_val = 6 "
            f"WHERE name = 'Beta-Combat'"
        )
        conn.commit()
        cur.close()
        conn.close()

        assert_no_collected_php_errors(page)
        context.close()

        type(self)._post = post
        yield

    def test_zone_remains_unowned_with_single_claimer(self):
        """Single Beta claimer → supporters = 0 → claim_val = 0 (other terms
        muted). 0 - 3 = -3 fails claimDiff(=1) → zone stays unowned."""
        assert self._post['holder_controller_id'] is None, (
            f"holder should remain NULL with single claimer + muted Claim "
            f"terms; got {self._post['holder_controller_id']}"
        )
        assert self._post['claimer_controller_id'] is None, (
            f"claimer should remain NULL with single claimer + muted Claim "
            f"terms; got {self._post['claimer_controller_id']}"
        )


@pytest.mark.db
class TestClaimModeDisabled:
    """When `claimMode` is set to a value outside the implemented whitelist
    (`worker`, `worker_leader`) — e.g. `controller` (mode C, not yet built)
    or any unknown string — every claim gate turns off:
      - worker-page claim form not rendered (C1)
      - `workers/action.php?claim=1` URL responds 403 (C2)
      - `claimMechanic` EOT step echoes a skip warning + does nothing (C3)
      - `recalculateZoneDefence` EOT step echoes a skip warning +
        leaves `calculated_defence_val` untouched (C4)
    """

    @pytest.fixture(scope="class", autouse=True)
    def disabled_state(self, browser):
        conn = _db_conn()
        cur = conn.cursor()
        cur.execute(
            f"SELECT value FROM `{GAME_PREFIX}config` WHERE name = 'claimMode'"
        )
        prev_mode = cur.fetchone()['value']
        cur.execute(
            f"UPDATE `{GAME_PREFIX}config` SET value = 'controller' "
            f"WHERE name = 'claimMode'"
        )
        conn.commit()

        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        cur.execute(
            f"SELECT w.id FROM `{GAME_PREFIX}workers` w WHERE w.lastname = 'Chain_B' LIMIT 1"
        )
        worker_id = cur.fetchone()['id']

        action_page_resp_status = page.goto(
            f"{PHP_BASE_URL}/workers/action.php?worker_id={worker_id}"
        ).status
        action_page_html = page.content()
        claim_input_count = page.locator("input[name='claim']").count()

        claim_url_resp_status = page.goto(
            f"{PHP_BASE_URL}/workers/action.php?worker_id={worker_id}&claim=1"
        ).status

        ensure_gm_login(page, PHP_BASE_URL)
        page.goto(f"{PHP_BASE_URL}/mechanics/endTurn.php")
        page.wait_for_load_state("load", timeout=120000)
        eot_html = page.content()

        cur.execute(
            f"UPDATE `{GAME_PREFIX}config` SET value = %s WHERE name = 'claimMode'",
            (prev_mode,)
        )
        conn.commit()
        cur.close()
        conn.close()

        assert_no_collected_php_errors(page)
        context.close()

        type(self)._action_page_status = action_page_resp_status
        type(self)._claim_input_count = claim_input_count
        type(self)._claim_url_status = claim_url_resp_status
        type(self)._eot_html = eot_html
        yield

    def test_claim_form_not_rendered(self):
        """C1 — claim button absent when mode is outside whitelist. The page
        itself still loads (200) so the gate is targeted at the claim form
        only, not the whole worker page."""
        assert self._action_page_status == 200, (
            f"workers/action.php should still render (200); got "
            f"{self._action_page_status}"
        )
        assert self._claim_input_count == 0, (
            f"input[name='claim'] should be hidden when claimMode is "
            f"outside whitelist; found {self._claim_input_count}"
        )

    def test_claim_url_returns_403(self):
        """C2 — `?claim=1` URL handler hard-403s before activateWorker fires."""
        assert self._claim_url_status == 403, (
            f"claim URL should 403 when claimMode is outside whitelist; "
            f"got {self._claim_url_status}"
        )

    def test_claim_mechanic_skipped_at_end_turn(self):
        """C3 — claimMechanic prints the skip warning and does not fall
        through to the legacy mode-A SQL path."""
        assert "claimMechanic : mode 'controller' not supported, skipped" in self._eot_html, (
            "EOT page should contain the claimMechanic skip-warning heading"
        )

    def test_recalculate_zone_defence_skipped_at_end_turn(self):
        """C4 — recalculateZoneDefence prints the skip warning and does not
        update calculated_defence_val for any zone."""
        assert "Mode 'controller' not supported, skipped" in self._eot_html, (
            "EOT page should contain the recalculateZoneDefence skip-warning"
        )


@pytest.mark.db
class TestClaimModeHeldZoneSkip:
    """Mode B — when a worker's `action_choice='claim'` targets a zone the
    worker's own controller ALREADY holds, the claim path skips entirely
    in `claimByWorkerLeaderMath`. The worker still counts as a
    defence supporter via `recalculateZoneDefence`'s mode-B supporting
    term, but produces no `claim_report`, no CKE leak, no zone-row
    UPDATE.

    Scenario: Beta-Combat is pre-set so holder == Beta. Chain_B (Beta's
    worker) submits claim. EOT runs. Assertions:
      - Zone holder/claimer unchanged.
      - Chain_B has no `claim_report` for the post-EOT turn (its row
        carried forward without one being written for the resolved turn).
      - No CKE entries for Chain_B with `last_discovery_turn == claim_turn`
        (no enemy observer learned of the worker via the claim path).
    """

    @pytest.fixture(scope="class", autouse=True)
    def held_zone_state(self, browser):
        conn = _db_conn()
        cur = conn.cursor()
        cur.execute(
            f"UPDATE `{GAME_PREFIX}config` SET value = 'worker_leader' "
            f"WHERE name = 'claimMode'"
        )
        cur.execute(
            f"SELECT id FROM `{GAME_PREFIX}controllers` WHERE lastname = 'Beta' LIMIT 1"
        )
        beta_id = cur.fetchone()['id']
        cur.execute(
            f"UPDATE `{GAME_PREFIX}zones` SET defence_val = 0, "
            f"claimer_controller_id = %s, holder_controller_id = %s "
            f"WHERE name = 'Beta-Combat'",
            (beta_id, beta_id)
        )
        cur.execute(
            f"SELECT id FROM `{GAME_PREFIX}zones` WHERE name = 'Beta-Combat' LIMIT 1"
        )
        beta_combat_id = cur.fetchone()['id']
        cur.execute(
            f"SELECT id FROM `{GAME_PREFIX}workers` WHERE lastname = 'Chain_B' LIMIT 1"
        )
        chain_b_id = cur.fetchone()['id']
        cur.execute(f"SELECT turncounter FROM `{GAME_PREFIX}mechanics` LIMIT 1")
        claim_turn = cur.fetchone()['turncounter']
        cur.execute(
            f"UPDATE `{GAME_PREFIX}worker_actions` wa "
            f"JOIN `{GAME_PREFIX}workers` w ON w.id = wa.worker_id "
            f"SET wa.action_choice = 'passive', wa.action_params = '{{}}' "
            f"WHERE wa.turn_number = %s "
            f"  AND w.zone_id = %s "
            f"  AND wa.action_choice IN ('claim', 'investigate', 'attack')",
            (claim_turn, beta_combat_id)
        )
        conn.commit()

        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        ui_claim_click(page, 'Chain_B', 'Beta')
        end_turn(page, PHP_BASE_URL)

        conn.commit()
        cur.execute(
            f"SELECT claimer_controller_id, holder_controller_id "
            f"FROM `{GAME_PREFIX}zones` WHERE id = %s",
            (beta_combat_id,)
        )
        post_zone = cur.fetchone()
        cur.execute(
            f"SELECT report FROM `{GAME_PREFIX}worker_actions` "
            f"WHERE worker_id = %s AND turn_number = %s LIMIT 1",
            (chain_b_id, claim_turn)
        )
        chain_b_action = cur.fetchone()

        cur.execute(
            f"UPDATE `{GAME_PREFIX}config` SET value = 'worker' "
            f"WHERE name = 'claimMode'"
        )
        cur.execute(
            f"UPDATE `{GAME_PREFIX}zones` SET defence_val = 6, "
            f"claimer_controller_id = NULL, holder_controller_id = NULL "
            f"WHERE name = 'Beta-Combat'"
        )
        conn.commit()
        cur.close()
        conn.close()

        assert_no_collected_php_errors(page)
        context.close()

        type(self)._post_zone = post_zone
        type(self)._chain_b_action = chain_b_action
        type(self)._beta_id = beta_id
        yield

    def test_zone_holder_unchanged(self):
        """Holder remains Beta after EOT — held-zone skip means no UPDATE
        runs against the zones row."""
        assert self._post_zone['holder_controller_id'] == self._beta_id, (
            f"holder should remain Beta (id={self._beta_id}); got "
            f"{self._post_zone['holder_controller_id']}"
        )

    def test_zone_claimer_unchanged(self):
        """Claimer remains Beta as well — `action_params.claim_controller_id`
        is not applied since we never enter the resolution body."""
        assert self._post_zone['claimer_controller_id'] == self._beta_id, (
            f"claimer should remain Beta (id={self._beta_id}); got "
            f"{self._post_zone['claimer_controller_id']}"
        )

    def test_no_claim_report_written(self):
        """No `claim_report` key written into the worker's `report` JSON —
        the held-zone skip bypasses both the success and failure text paths."""
        import json
        report_raw = (self._chain_b_action or {}).get('report')
        report = json.loads(report_raw) if report_raw else {}
        assert not report.get('claim_report'), (
            f"claim_report should be empty for the held-zone claimer; "
            f"got {report.get('claim_report')!r}"
        )


@pytest.mark.db
class TestClaimModeMultiControllerOrdering:
    """Mode B — when two controllers both submit claims for the same
    unowned zone and both clear `claimDiff`, the resolver picks the
    leader with the highest `(attack_val, defence_val, enquete_val,
    worker_id)` tiebreak. The first group (in that sort order) to pass
    wins the zone; later passing groups are forced to lose. All losers
    still write their fail reports + leak CKE to observers (per user
    spec — observers saw every attempt).

    Scenario uses Chain_A (Alpha) vs Chain_D (Delta) in Beta-Combat:
      - Chain_A's powers Eagle Scout / Veteran Tactician / Focused
        Mind / War Gear sum attack=5. Chain_D's Blank Slate / Common
        Folk sum 0. With TestConfig dice fixed at 3 (MINROLL=MAXROLL=3)
        and `claim` ∈ `activeAttackActions`, Chain_A's attack_val=8 vs
        Chain_D's 3 → Alpha sorts first.
      - Both leaders' claim_val = baseClaim(5) + workers(1) = 6 >
        noControllerZoneDefenceBonus(3) + claimDiff(1) → both would pass.
        Alpha wins; Delta forced-loss.
    """

    @pytest.fixture(scope="class", autouse=True)
    def multi_state(self, browser):
        conn = _db_conn()
        cur = conn.cursor()
        cur.execute(
            f"SELECT name, value FROM `{GAME_PREFIX}config` WHERE name IN ("
            f"'claimMode','baseClaim')"
        )
        prev_config = {row['name']: row['value'] for row in cur.fetchall()}
        cur.execute(
            f"UPDATE `{GAME_PREFIX}config` SET value='worker_leader' "
            f"WHERE name='claimMode'"
        )
        cur.execute(
            f"UPDATE `{GAME_PREFIX}config` SET value='5' "
            f"WHERE name='baseClaim'"
        )
        cur.execute(
            f"UPDATE `{GAME_PREFIX}zones` SET defence_val=0, "
            f"claimer_controller_id=NULL, holder_controller_id=NULL "
            f"WHERE name='Beta-Combat'"
        )
        cur.execute(
            f"SELECT id, lastname FROM `{GAME_PREFIX}controllers` "
            f"WHERE lastname IN ('Alpha', 'Delta')"
        )
        ids = {row['lastname']: row['id'] for row in cur.fetchall()}
        cur.execute(
            f"SELECT id FROM `{GAME_PREFIX}workers` WHERE lastname='Chain_A' LIMIT 1"
        )
        chain_a_id = cur.fetchone()['id']
        cur.execute(
            f"SELECT id FROM `{GAME_PREFIX}workers` WHERE lastname='Chain_D' LIMIT 1"
        )
        chain_d_id = cur.fetchone()['id']
        cur.execute(
            f"SELECT id FROM `{GAME_PREFIX}zones` WHERE name='Beta-Combat' LIMIT 1"
        )
        beta_combat_id = cur.fetchone()['id']
        cur.execute(f"SELECT turncounter FROM `{GAME_PREFIX}mechanics` LIMIT 1")
        claim_turn = cur.fetchone()['turncounter']
        cur.execute(
            f"UPDATE `{GAME_PREFIX}worker_actions` wa "
            f"JOIN `{GAME_PREFIX}workers` w ON w.id = wa.worker_id "
            f"SET wa.action_choice='passive', wa.action_params='{{}}' "
            f"WHERE wa.turn_number=%s AND w.zone_id=%s "
            f"  AND wa.action_choice='claim'",
            (claim_turn, beta_combat_id)
        )
        conn.commit()

        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        ui_claim_click(page, 'Chain_A', 'Alpha')
        ui_claim(page, 'Chain_D', 'Delta')
        end_turn(page, PHP_BASE_URL)

        conn.commit()
        cur.execute(
            f"SELECT claimer_controller_id, holder_controller_id "
            f"FROM `{GAME_PREFIX}zones` WHERE id=%s",
            (beta_combat_id,)
        )
        post_zone = cur.fetchone()
        cur.execute(
            f"SELECT report FROM `{GAME_PREFIX}worker_actions` "
            f"WHERE worker_id=%s AND turn_number=%s LIMIT 1",
            (chain_a_id, claim_turn)
        )
        chain_a_action = cur.fetchone()
        cur.execute(
            f"SELECT report FROM `{GAME_PREFIX}worker_actions` "
            f"WHERE worker_id=%s AND turn_number=%s LIMIT 1",
            (chain_d_id, claim_turn)
        )
        chain_d_action = cur.fetchone()

        for name, value in prev_config.items():
            cur.execute(
                f"UPDATE `{GAME_PREFIX}config` SET value=%s WHERE name=%s",
                (value, name)
            )
        cur.execute(
            f"UPDATE `{GAME_PREFIX}zones` SET defence_val=6 "
            f"WHERE name='Beta-Combat'"
        )
        conn.commit()
        cur.close()
        conn.close()

        assert_no_collected_php_errors(page)
        context.close()

        type(self)._post_zone = post_zone
        type(self)._chain_a_action = chain_a_action
        type(self)._chain_d_action = chain_d_action
        type(self)._alpha_id = ids['Alpha']
        type(self)._delta_id = ids['Delta']
        yield

    def test_holder_is_alpha_highest_attack_val(self):
        """Alpha wins the ordering: Chain_A's attack_val (dice 3 + powers
        5 = 8) beats Chain_D's (dice 3 + powers 0 = 3). The UPDATE fires
        for Alpha; the zone's holder reflects that."""
        assert self._post_zone['holder_controller_id'] == self._alpha_id, (
            f"holder should be Alpha (id={self._alpha_id}, highest "
            f"attack_val leader); got {self._post_zone['holder_controller_id']}"
        )

    def test_claimer_mirrors_winner(self):
        """`action_params.claim_controller_id` from `ui_claim_click` is
        Alpha (the leader's own controller). Claimer column tracks that."""
        assert self._post_zone['claimer_controller_id'] == self._alpha_id

    def test_winner_chain_a_got_claim_report(self):
        """Chain_A's claim_report key is populated (from
        textesClaimSuccessArray) — proves the winning group still wrote
        its self-report."""
        import json
        report_raw = (self._chain_a_action or {}).get('report')
        report = json.loads(report_raw) if report_raw else {}
        assert report.get('claim_report'), (
            f"Chain_A (winner) should have a claim_report; got "
            f"{report.get('claim_report')!r}"
        )

    def test_loser_chain_d_still_got_claim_report(self):
        """Chain_D's claim cleared the threshold individually but was
        forced to lose because Alpha already won the zone. The fail
        report + CKE leak still fire — observers saw both attempts."""
        import json
        report_raw = (self._chain_d_action or {}).get('report')
        report = json.loads(report_raw) if report_raw else {}
        assert report.get('claim_report'), (
            f"Chain_D (post-winner loser) should still have a "
            f"claim_report (fail-text); got {report.get('claim_report')!r}"
        )


@pytest.mark.db
class TestClaimModeWorkerLeaderReportPlaceholders:
    """Mode B view templates resolve all 4 placeholders:
      - %1$s = leader's full name
      - %2$s = zone name
      - %3$s = co-claimer names (or `d'autres agents` fallback)
      - %4$s = `action_params.claim_controller_id` target controller's name
               (or `Personne (Sans bannière)` for the `'null'` sentinel /
                missing override)

    TestConfig's `textesClaim{Success,Fail}ViewArray` are only `%1$s`/`%2$s`
    in steady state (TestConfig runs Mode A). This class transiently
    overrides them to single-variant markers with all 4 placeholders, runs
    a mode-B claim, then reads an observing enemy worker's `claim_report`
    and asserts the substitutions actually landed.
    """

    @pytest.fixture(scope="class", autouse=True)
    def report_state(self, browser):
        conn = _db_conn()
        cur = conn.cursor()
        cur.execute(
            f"SELECT name, value FROM `{GAME_PREFIX}config` WHERE name IN ("
            f"'claimMode','baseClaim',"
            f"'textesClaimSuccessViewArray','textesClaimFailViewArray')"
        )
        prev_config = {row['name']: row['value'] for row in cur.fetchall()}
        cur.execute(
            f"UPDATE `{GAME_PREFIX}config` SET value='worker_leader' "
            f"WHERE name='claimMode'"
        )
        cur.execute(
            f"UPDATE `{GAME_PREFIX}config` SET value='10' WHERE name='baseClaim'"
        )
        cur.execute(
            f'UPDATE `{GAME_PREFIX}config` SET value=\'["SUCCESS_MARKER '
            f'leader=%1$s zone=%2$s co=%3$s onBehalf=%4$s"]\' '
            f"WHERE name='textesClaimSuccessViewArray'"
        )
        cur.execute(
            f'UPDATE `{GAME_PREFIX}config` SET value=\'["FAIL_MARKER '
            f'leader=%1$s zone=%2$s co=%3$s onBehalf=%4$s"]\' '
            f"WHERE name='textesClaimFailViewArray'"
        )
        cur.execute(
            f"UPDATE `{GAME_PREFIX}zones` SET defence_val=0, "
            f"claimer_controller_id=NULL, holder_controller_id=NULL "
            f"WHERE name='Beta-Combat'"
        )

        cur.execute(
            f"SELECT id, lastname, CONCAT(firstname, ' ', lastname) AS full_name "
            f"FROM `{GAME_PREFIX}controllers` "
            f"WHERE lastname IN ('Alpha', 'Beta')"
        )
        controller_rows = list(cur.fetchall())
        ids = {row['lastname']: row['id'] for row in controller_rows}
        controller_full_names = {row['lastname']: row['full_name'] for row in controller_rows}
        cur.execute(
            f"SELECT id, CONCAT(firstname, ' ', lastname) AS full_name "
            f"FROM `{GAME_PREFIX}workers` WHERE lastname='Chain_A' LIMIT 1"
        )
        chain_a_row = cur.fetchone()
        cur.execute(
            f"SELECT id, CONCAT(firstname, ' ', lastname) AS full_name "
            f"FROM `{GAME_PREFIX}workers` WHERE lastname='Even_Atk' LIMIT 1"
        )
        even_atk_row = cur.fetchone()
        cur.execute(
            f"SELECT id FROM `{GAME_PREFIX}workers` WHERE lastname='Chain_C' LIMIT 1"
        )
        chain_c_id = cur.fetchone()['id']
        cur.execute(
            f"SELECT id FROM `{GAME_PREFIX}zones` WHERE name='Beta-Combat' LIMIT 1"
        )
        beta_combat_id = cur.fetchone()['id']
        cur.execute(f"SELECT turncounter FROM `{GAME_PREFIX}mechanics` LIMIT 1")
        claim_turn = cur.fetchone()['turncounter']
        cur.execute(
            f"UPDATE `{GAME_PREFIX}worker_actions` wa "
            f"JOIN `{GAME_PREFIX}workers` w ON w.id=wa.worker_id "
            f"SET wa.action_choice='passive', wa.action_params='{{}}' "
            f"WHERE wa.turn_number=%s AND w.zone_id=%s "
            f"  AND wa.action_choice='claim'",
            (claim_turn, beta_combat_id)
        )
        conn.commit()

        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        # Chain_A submits claim with claim_controller_id=Beta (override).
        # Even_Atk submits claim with Alpha default. Both belong to Alpha.
        # Leader = Chain_A (powers-attack=5 vs Even_Atk's 0 → highest attack_val).
        ui_claim_click(page, 'Chain_A', 'Beta')
        ui_claim(page, 'Even_Atk', 'Alpha')
        end_turn(page, PHP_BASE_URL)

        conn.commit()
        cur.execute(
            f"SELECT report FROM `{GAME_PREFIX}worker_actions` "
            f"WHERE worker_id=%s AND turn_number=%s LIMIT 1",
            (chain_c_id, claim_turn)
        )
        chain_c_action = cur.fetchone()

        for name, value in prev_config.items():
            cur.execute(
                f"UPDATE `{GAME_PREFIX}config` SET value=%s WHERE name=%s",
                (value, name)
            )
        cur.execute(
            f"UPDATE `{GAME_PREFIX}zones` SET defence_val=6 WHERE name='Beta-Combat'"
        )
        conn.commit()
        cur.close()
        conn.close()

        assert_no_collected_php_errors(page)
        context.close()

        type(self)._chain_c_action = chain_c_action
        type(self)._chain_a_full_name = chain_a_row['full_name']
        type(self)._even_atk_full_name = even_atk_row['full_name']
        type(self)._beta_full_name = controller_full_names['Beta']
        yield

    def _claim_report(self):
        import json
        report_raw = (self._chain_c_action or {}).get('report')
        report = json.loads(report_raw) if report_raw else {}
        return report.get('claim_report') or ''

    def test_success_marker_present(self):
        """Observer's report came from the SUCCESS template (Alpha's group
        won the zone) — proves the template selection on `$success=true`."""
        assert 'SUCCESS_MARKER' in self._claim_report(), (
            f"Expected SUCCESS_MARKER in Chain_C's claim_report; got "
            f"{self._claim_report()!r}"
        )

    def test_leader_name_substituted(self):
        """%1$s rendered Chain_A's full name (the leader)."""
        assert f'leader={self._chain_a_full_name}' in self._claim_report(), (
            f"Expected `leader={self._chain_a_full_name}`; got "
            f"{self._claim_report()!r}"
        )

    def test_zone_name_substituted(self):
        """%2$s rendered the zone name."""
        assert 'zone=Beta-Combat' in self._claim_report()

    def test_co_claimer_names_substituted(self):
        """%3$s rendered Even_Atk's full name (the co-claimer; Chain_A
        is the leader and is excluded)."""
        assert f'co={self._even_atk_full_name}' in self._claim_report(), (
            f"Expected `co={self._even_atk_full_name}`; got "
            f"{self._claim_report()!r}"
        )

    def test_on_behalf_controller_name_substituted(self):
        """%4$s rendered Beta's full controller name (Chain_A's
        action_params.claim_controller_id override target). `getControllerName`
        returns `CONCAT(firstname, ' ', lastname)`."""
        assert f'onBehalf={self._beta_full_name}' in self._claim_report(), (
            f"Expected `onBehalf={self._beta_full_name}`; got "
            f"{self._claim_report()!r}"
        )

