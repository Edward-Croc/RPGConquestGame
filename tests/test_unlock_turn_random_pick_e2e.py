"""E2E tests for issue #86 — block Metier / Hobby random rolls before `unlock_turn`.

Lock surface (see tests/AUDIT_issue_86.md for the full lock list):

- `unlock_turn` is a single integer rule key inside `powers.other.on_random_pick`.
  Semantic: value N means power is LOCKED at turns 0..N-1, UNLOCKED at N+.
- Two evaluators (must agree):
  - SQL filter in `powers/functions.php::randomPowersByType` —
    `unlock_turn IS NULL OR unlock_turn <= current_turn` keeps the row.
  - PHP walker case in `evaluateRuleKeysAllMatch` —
    `value > turn_number → return false` (same predicate, opposite direction).
- Inclusive threshold (D5): `unlock_turn: 2` → eligible at turn 2 and above.
- TestConfig seeds two test powers with `unlock_turn: 2`:
  - `Test_Hobby_UnlockTurn_2` (hobby_id type=1)
  - `Test_Job_UnlockTurn_2` (metier_id type=2)

Test driver: render `/workers/new.php?recrutement=true&controller_id=<alpha>` N
times and scrape the resulting proposal HTML for the test power name. Each
page renders `recrutement_nb_choices` proposals (=3 in minimalData) → 3 rolls
per page for each of hobby + metier. With N_VISITS=50 page-visits this gives
~150 rolls per power type.

Alpha is used (start_workers=1, can_build_base=1). A base is created in the
module fixture so canStartRecrutement passes at turn 0.

Classes run in declaration order; each advances one end_turn relative to the
previous so turn state stays in lockstep with class semantics.

Run:
    python3 -m pytest tests/test_unlock_turn_random_pick_e2e.py -v
"""
import pathlib

import pymysql
import pytest

from conftest import (
    GAME_PREFIX, MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB,
    PHP_BASE_URL, ensure_gm_login,
)
from helpers import (
    DB_AVAILABLE, load_minimal_data, load_scenario_via_admin, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
    end_turn, ui_controller_ids_map, ui_zone_id,
)


N_VISITS = 50  # × nb_choices proposals/visit = sample size per power type


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


_controller_ids_cache = {}


@pytest.fixture(scope="module", autouse=True)
def setup_testconfig_with_alpha_base(browser):
    """Load TestConfig + create Alpha's base via URL-driver so she can
    recruit at turn 0 (start_workers=1 satisfied by default; hasBase
    needs the base we create here)."""
    if DB_AVAILABLE:
        load_minimal_data()
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")

    ctx = browser.new_context()
    page = ctx.new_page()
    register_php_error_listener(page)
    ensure_gm_login(page, PHP_BASE_URL)

    _controller_ids_cache.update(ui_controller_ids_map(page, PHP_BASE_URL))
    alpha_cid = _controller_ids_cache["Alpha"]
    # Pick a zone Alpha doesn't already hold a base in. Epsilon-Controlled
    # is unowned in TestConfig.
    zid = ui_zone_id(page, "Epsilon-Controlled", base_url=PHP_BASE_URL)
    safe_goto(
        page,
        f"{PHP_BASE_URL}/controllers/action.php"
        f"?createBase=1&controller_id={alpha_cid}&zone_id={zid}"
    )
    page.wait_for_load_state("load")
    assert_no_collected_php_errors(page)
    ctx.close()
    yield


def _alpha_cid():
    return _controller_ids_cache["Alpha"]


def _db():
    return pymysql.connect(
        host=MYSQL_HOST, port=MYSQL_PORT, user=MYSQL_USER,
        password=MYSQL_PASSWORD, database=MYSQL_DB,
        charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor,
    )


def _reset_recruit_counter(cid):
    """workers/new.php increments turn_recruited_workers on every render.
    Reset to 0 between visits so subsequent renders stay within
    start_workers / turn_recrutable_workers limits."""
    conn = _db(); cur = conn.cursor()
    cur.execute(
        f"UPDATE `{GAME_PREFIX}controllers` SET turn_recruited_workers = 0 "
        f"WHERE id = %s",
        (cid,),
    )
    conn.commit()
    cur.close(); conn.close()


def _count_rolls_containing(browser, needle, visits=N_VISITS):
    """Open workers/new.php as Alpha `visits` times. Return the number of
    times `needle` appears in the rendered HTML across all visits.

    The recruit counter is reset before each visit so the form actually
    renders proposals every time. Each render rolls randomPowersByType
    independently."""
    cid = _alpha_cid()
    ctx = browser.new_context()
    page = ctx.new_page()
    register_php_error_listener(page)
    ensure_gm_login(page, PHP_BASE_URL)
    safe_goto(page, f"{PHP_BASE_URL}/base/accueil.php?controller_id={cid}&chosir=Choisir")
    page.wait_for_load_state("networkidle")
    hits = 0
    for _ in range(visits):
        _reset_recruit_counter(cid)
        safe_goto(
            page,
            f"{PHP_BASE_URL}/workers/new.php?recrutement=true&controller_id={cid}"
        )
        page.wait_for_load_state("load")
        if needle in page.content():
            hits += 1
    assert_no_collected_php_errors(page)
    ctx.close()
    return hits


def _advance_one_turn(browser):
    ctx = browser.new_context()
    page = ctx.new_page()
    register_php_error_listener(page)
    ensure_gm_login(page, PHP_BASE_URL)
    end_turn(page)
    ctx.close()


# ---------------------------------------------------------------------------
# Turn 0 — gated power excluded, baseline appears
# ---------------------------------------------------------------------------

class Test01ExcludedAtTurnZero:
    """`unlock_turn: 2` means locked at turns 0..1. At turn 0 the gated
    power must never appear; a baseline ungated power must appear."""

    def test_hobby_excluded_at_turn_zero(self, browser):
        hits = _count_rolls_containing(browser, "Test_Hobby_UnlockTurn_2")
        assert hits == 0, (
            f"Test_Hobby_UnlockTurn_2 must NOT appear at turn 0 (unlock_turn=2); "
            f"got {hits} hits in {N_VISITS} visits"
        )

    def test_metier_excluded_at_turn_zero(self, browser):
        hits = _count_rolls_containing(browser, "Test_Job_UnlockTurn_2")
        assert hits == 0, (
            f"Test_Job_UnlockTurn_2 must NOT appear at turn 0 (unlock_turn=2); "
            f"got {hits} hits in {N_VISITS} visits"
        )

    def test_baseline_hobby_appears_at_turn_zero(self, browser):
        # Blank Slate is a TestConfig hobby with no rules — must always roll.
        hits = _count_rolls_containing(browser, "Blank Slate")
        assert hits > 0, (
            f"Blank Slate (no rules) should appear at least once at turn 0; "
            f"got 0 hits in {N_VISITS} visits — recruit form likely not "
            f"rendering, check Alpha's base setup"
        )


# ---------------------------------------------------------------------------
# Turn 1 — gated power still excluded
# ---------------------------------------------------------------------------

class Test02ExcludedAtTurnOne:
    """Boundary case: turn 1 is one below the unlock_turn=2 threshold,
    so exclusion still holds."""

    @pytest.fixture(scope="class", autouse=True)
    def _advance(self, browser):
        _advance_one_turn(browser)
        yield

    def test_hobby_excluded_at_turn_one(self, browser):
        hits = _count_rolls_containing(browser, "Test_Hobby_UnlockTurn_2")
        assert hits == 0, (
            f"Test_Hobby_UnlockTurn_2 must NOT appear at turn 1 (unlock_turn=2); "
            f"got {hits} hits in {N_VISITS} visits"
        )

    def test_metier_excluded_at_turn_one(self, browser):
        hits = _count_rolls_containing(browser, "Test_Job_UnlockTurn_2")
        assert hits == 0, (
            f"Test_Job_UnlockTurn_2 must NOT appear at turn 1 (unlock_turn=2); "
            f"got {hits} hits in {N_VISITS} visits"
        )


# ---------------------------------------------------------------------------
# Turn 2 — gated power eligible (inclusive threshold)
# ---------------------------------------------------------------------------

class Test03EligibleAtThreshold:
    """At turn 2 (== unlock_turn value), the gated power becomes eligible."""

    @pytest.fixture(scope="class", autouse=True)
    def _advance(self, browser):
        _advance_one_turn(browser)
        yield

    def test_hobby_eligible_at_turn_two(self, browser):
        hits = _count_rolls_containing(browser, "Test_Hobby_UnlockTurn_2")
        assert hits > 0, (
            f"Test_Hobby_UnlockTurn_2 should appear at least once at turn 2; "
            f"got 0 hits in {N_VISITS} visits — statistically near-impossible "
            f"if the SQL filter passes the row, check filter / pool size"
        )

    def test_metier_eligible_at_turn_two(self, browser):
        hits = _count_rolls_containing(browser, "Test_Job_UnlockTurn_2")
        assert hits > 0, (
            f"Test_Job_UnlockTurn_2 should appear at least once at turn 2"
        )


# ---------------------------------------------------------------------------
# Turn 3 — eligibility holds above threshold
# ---------------------------------------------------------------------------

class Test04EligibleAboveThreshold:
    """Above unlock_turn, eligibility holds — no regression at turn 3."""

    @pytest.fixture(scope="class", autouse=True)
    def _advance(self, browser):
        _advance_one_turn(browser)
        yield

    def test_hobby_eligible_at_turn_three(self, browser):
        hits = _count_rolls_containing(browser, "Test_Hobby_UnlockTurn_2")
        assert hits > 0, (
            f"Test_Hobby_UnlockTurn_2 should appear at least once at turn 3"
        )


# ---------------------------------------------------------------------------
# D6 — admin perfect-form bypasses the gate
# ---------------------------------------------------------------------------

class Test05AdminPerfectFormStillSees:
    """The admin perfect-worker form on base/admin.php sources its dropdowns
    via getSQLPowerText + a direct JOIN, NOT via randomPowersByType. So the
    gated power must still appear in the admin dropdown regardless of turn."""

    def test_admin_dropdown_includes_gated_hobby(self, browser):
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)
        safe_goto(page, f"{PHP_BASE_URL}/base/admin.php")
        page.wait_for_load_state("networkidle")
        labels = [
            (opt.inner_text() or "").strip()
            for opt in page.locator("select#power_hobby_id option").all()
        ]
        ctx.close()
        assert any("Test_Hobby_UnlockTurn_2" in lbl for lbl in labels), (
            f"Admin perfect-form Hobby dropdown must include "
            f"Test_Hobby_UnlockTurn_2 (D6); got: {labels}"
        )


# ---------------------------------------------------------------------------
# D11 — walker wiring smoke check
# ---------------------------------------------------------------------------

class Test06WalkerUnlockTurnWiring:
    """`unlock_turn` was renamed from PR #82's existing `turn` key and
    added to the evaluateRuleKeysAllMatch case-list. This smoke check
    asserts both the whitelist entry and the case handler are present
    — runtime behaviour is already covered by the PR #82 walker tests
    plus the SQL exclusion classes above."""

    def test_whitelist_and_case_present(self):
        php_src = pathlib.Path(__file__).parent.parent.joinpath(
            "powers/functions.php"
        ).read_text()
        assert "'unlock_turn'" in php_src, (
            "unlock_turn must appear in ALLOWED_KEYS in "
            "evaluateRuleKeysAllMatch"
        )
        assert "elseif ($key === 'unlock_turn')" in php_src, (
            "evaluateRuleKeysAllMatch must implement the unlock_turn case"
        )
