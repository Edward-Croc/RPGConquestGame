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
    end_turn, load_minimal_data, safe_goto, ui_claim_click,
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
    page.locator("input[name='submit'][value='Submit']").click()
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
