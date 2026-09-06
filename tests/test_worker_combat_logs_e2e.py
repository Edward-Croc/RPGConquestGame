"""End-to-end coverage of the `worker_combat_logs` table — log-refactor.

`resolveWorkerCombat()` (mechanics/attackMechanic.php) opens one row per
attacker x defender pair on entry (outcome NULL) and closes it with the
resolved outcome on exit — see mechanics/logs.php:logWorkerCombat /
logWorkerCombatUpdate. These tests verify that two-phase contract purely
through the read-only admin page (workers/management_combat.php) : no
pymysql, no direct SQL.

Data: built by helpers.seed_worker_combat_scenario(), the same shared
builder test_agent_combat_e2e.py's `combat_scenario` fixture uses, so the
same 16 attacker-defender pairs enter resolveWorkerCombat() and this file's
expected row count / per-pair outcomes stay in lockstep with that file's
documented combat math.

Of the 19 queued attacker->defender pairs, 3 never reach
resolveWorkerCombat() (attacker-guard `continue` at
mechanics/attackMechanic.php:498 when the attacker itself went inactive
before its turn: Chain_B captured by Chain_A, Chain_D killed by Chain_C,
Mover_Test's action was reset to 'passive' by its own move before the EOT
even queried attacksArray) -- leaving exactly 16 resolved rows, all with
attempt=1.

Run:
    python3 -m pytest tests/test_worker_combat_logs_e2e.py -v
"""
import pytest
from playwright.sync_api import Page

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, load_minimal_data, load_scenario_via_admin,
    safe_goto,
    ui_worker_id, ui_combat_logs, ui_combat_unresolved_count,
    seed_worker_combat_scenario,
)


@pytest.fixture(scope="module", autouse=True)
def combat_logs_scenario(browser):
    """Reproduce test_agent_combat_e2e.py's `combat_scenario` turn-1 setup
    so the same 16 attacker-defender pairs enter resolveWorkerCombat() and
    populate worker_combat_logs identically."""
    context = None
    try:
        context = seed_worker_combat_scenario(browser, base_url=PHP_BASE_URL)
        yield
    finally:
        if context is not None:
            context.close()
        # Unconditional : ensure_scenario_loaded() would skip a reload here.
        if DB_AVAILABLE:
            load_minimal_data()
        load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")


class TestWorkerCombatLogsStructure:
    """The three highest-value structural assertions on the two-phase
    log-then-resolve contract."""

    def test_no_unresolved_rows_after_clean_eot(self, page: Page):
        """Every row opened by logWorkerCombat() must have been closed by
        logWorkerCombatUpdate() before the EOT response completes — proves
        the two-phase loop closes."""
        ensure_gm_login(page, PHP_BASE_URL)
        count = ui_combat_unresolved_count(page, base_url=PHP_BASE_URL)
        assert count == 0, (
            f"expected 0 unresolved worker_combat_logs rows after a clean "
            f"EOT, got {count}"
        )
        logs = ui_combat_logs(page, base_url=PHP_BASE_URL)
        unresolved_rows = [r for r in logs if not r['resolved']]
        assert unresolved_rows == [], (
            f"banner reports 0 unresolved but found unresolved rows: {unresolved_rows}"
        )

    def test_all_rows_have_attempt_one(self, page: Page):
        """attempt=1 on every row -- catches a duplicate-INSERT regression
        (logWorkerCombat reuses/bumps an existing unresolved row for the
        same (turn, attacker, defender) instead of inserting a fresh one)."""
        ensure_gm_login(page, PHP_BASE_URL)
        logs = ui_combat_logs(page, base_url=PHP_BASE_URL)
        assert len(logs) == 16, (
            f"expected exactly 16 worker_combat_logs rows for this "
            f"scenario (19 queued pairs - 3 attacker-guard skips), got {len(logs)}"
        )
        bad = [r for r in logs if r['attempt'] != 1]
        assert bad == [], f"every row should have attempt=1; offenders: {bad}"

    def test_no_row_for_chain_b_attacking_chain_c(self, page: Page):
        """Chain_B is captured by Chain_A (enquete 8 > 7) before its own
        attack-phase turn, so mechanics/attackMechanic.php:498's attacker
        guard `continue`s the outer loop and resolveWorkerCombat is never
        entered for this pair. Zero rows proves the INSERT lives inside
        the function, not at the callsite.
        test_agent_combat_e2e.py:475-495 (test_chain_b_did_not_attack)
        already establishes the capture fact via management_workers row
        counts."""
        ensure_gm_login(page, PHP_BASE_URL)
        chain_b_id = ui_worker_id(page, 'Chain_B', base_url=PHP_BASE_URL)
        chain_c_id = ui_worker_id(page, 'Chain_C', base_url=PHP_BASE_URL)
        logs = ui_combat_logs(page, base_url=PHP_BASE_URL, worker_id=chain_b_id)
        pair = [
            r for r in logs
            if r['attacker_worker_id'] == chain_b_id and r['defender_worker_id'] == chain_c_id
        ]
        assert pair == [], (
            f"Chain_B->Chain_C should never appear in worker_combat_logs; found {pair}"
        )


class TestWorkerCombatLogsOutcomes:
    """Per-pair outcome assertions, cross-checked against
    test_agent_combat_e2e.py's documented combat math."""

    @pytest.mark.parametrize("attacker,defender,outcome", [
        ("Chain_A", "Chain_B", "capture"),
        ("Chain_C", "Chain_D", "kill"),
        ("Chain_F", "Chain_G", "kill"),
        ("Chain_E", "Chain_F", "kill"),
        ("Even_Atk", "Even_Def", "miss"),
        ("Counter_Atk", "Counter_Def", "riposte_kill"),
        ("Inv_Atk_1", "Inv_Def_1", "capture"),
        ("Inv_Atk_2", "Inv_Def_2", "kill"),
        ("Riposte_R2_A", "Riposte_R2_B", "riposte_kill"),
        ("Riposte_R3_A", "Riposte_R3_B", "miss"),
    ])
    def test_pair_outcome(self, page: Page, attacker, defender, outcome):
        ensure_gm_login(page, PHP_BASE_URL)
        atk_id = ui_worker_id(page, attacker, base_url=PHP_BASE_URL)
        def_id = ui_worker_id(page, defender, base_url=PHP_BASE_URL)
        logs = ui_combat_logs(page, base_url=PHP_BASE_URL, worker_id=atk_id)
        pair = [
            r for r in logs
            if r['attacker_worker_id'] == atk_id and r['defender_worker_id'] == def_id
        ]
        assert len(pair) == 1, (
            f"expected exactly one worker_combat_logs row for "
            f"{attacker}->{defender}; got {pair}"
        )
        assert pair[0]['outcome'] == outcome, (
            f"{attacker}->{defender} expected outcome={outcome!r}, "
            f"got {pair[0]['outcome']!r}"
        )

    def test_all_four_reachable_outcomes_present(self, page: Page):
        """miss | kill | capture | riposte_kill are all reachable in the
        current engine (mutual_kill deliberately is not -- see
        mechanics/logs.php:resolveWorkerCombatOutcome). At least one row
        of each must appear across the scenario."""
        ensure_gm_login(page, PHP_BASE_URL)
        logs = ui_combat_logs(page, base_url=PHP_BASE_URL)
        outcomes = {r['outcome'] for r in logs}
        for expected in ('miss', 'kill', 'capture', 'riposte_kill'):
            assert expected in outcomes, (
                f"expected outcome {expected!r} to appear at least once "
                f"across the scenario; got {outcomes}"
            )
        assert 'mutual_kill' not in outcomes, (
            "mutual_kill is deliberately unreachable in the current engine"
        )
