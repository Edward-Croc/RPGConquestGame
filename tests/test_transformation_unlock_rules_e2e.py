"""E2E tests for issue #67 — controller_has_ressource gate + opt-out consume
flag + commit-time re-validation + atomic cost deduction + direct+OR cost
composition.

CSV pre-conditions (TestConfig):
  ressources_config:        Gold, Silver
  controller_ressources:    Alpha Gold=100 Silver=5, Beta Gold=80 Silver=5,
                            Echo Gold=2 Silver=5
  zones:                    Gamma-Claims claimer+holder=Alpha
  transformations:          Test Gold Cost Explicit / Default / Optout,
                            Test OR Zone Or Gold,
                            Test Direct And OR,
                            Test Cross Resource,
                            Test Malformed Amount

Test users:
  gm / orga              — privileged (bypasses re-validation)
  single_player / test   — Alpha only (drives non-privileged path)

Subjects:
  Transform_Subject      — Alpha worker (used for happy paths)
  Bystander_1            — Beta worker (used for cross-controller checks)

Run:
    python3 -m pytest tests/test_transformation_unlock_rules_e2e.py -v
"""
import pytest
import pymysql
from playwright.sync_api import Page

from conftest import (
    GAME_PREFIX, MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB,
    PHP_BASE_URL, ensure_gm_login,
)
from helpers import (
    DB_AVAILABLE, end_turn, load_minimal_data, load_scenario_via_admin,
    safe_goto, register_php_error_listener, assert_no_collected_php_errors,
    login_as, ui_transform_options, ui_transform_click,
    ui_teach_discipline_options, ui_worker_id,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def setup_testconfig(browser):
    if DB_AVAILABLE:
        load_minimal_data()
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
    yield


def _db():
    return pymysql.connect(
        host=MYSQL_HOST, port=MYSQL_PORT, user=MYSQL_USER,
        password=MYSQL_PASSWORD, database=MYSQL_DB,
        charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor,
    )


def _ressource_id(name):
    conn = _db(); cur = conn.cursor()
    cur.execute(f"SELECT id FROM `{GAME_PREFIX}ressources_config` WHERE ressource_name=%s LIMIT 1", (name,))
    row = cur.fetchone()
    cur.close(); conn.close()
    return row['id'] if row else None


def _controller_id(lastname):
    conn = _db(); cur = conn.cursor()
    cur.execute(f"SELECT id FROM `{GAME_PREFIX}controllers` WHERE lastname=%s LIMIT 1", (lastname,))
    row = cur.fetchone()
    cur.close(); conn.close()
    return row['id'] if row else None


def _zone_id(name):
    conn = _db(); cur = conn.cursor()
    cur.execute(f"SELECT id FROM `{GAME_PREFIX}zones` WHERE name=%s LIMIT 1", (name,))
    row = cur.fetchone()
    cur.close(); conn.close()
    return row['id'] if row else None


def _set_amount(controller_id, ressource_id, amount):
    conn = _db(); cur = conn.cursor()
    cur.execute(
        f"UPDATE `{GAME_PREFIX}controller_ressources` SET amount=%s "
        f"WHERE controller_id=%s AND ressource_id=%s",
        (amount, controller_id, ressource_id),
    )
    conn.commit()
    cur.close(); conn.close()


def _read_amount(controller_id, ressource_id):
    conn = _db(); cur = conn.cursor()
    cur.execute(
        f"SELECT amount FROM `{GAME_PREFIX}controller_ressources` "
        f"WHERE controller_id=%s AND ressource_id=%s LIMIT 1",
        (controller_id, ressource_id),
    )
    row = cur.fetchone()
    cur.close(); conn.close()
    return row['amount'] if row else None


def _set_zone_holder(zone_name, controller_lastname):
    """Set both claimer and holder on a zone, or NULL them if controller_lastname is None."""
    conn = _db(); cur = conn.cursor()
    if controller_lastname is None:
        cur.execute(
            f"UPDATE `{GAME_PREFIX}zones` SET holder_controller_id=NULL, claimer_controller_id=NULL WHERE name=%s",
            (zone_name,),
        )
    else:
        cid = _controller_id(controller_lastname)
        cur.execute(
            f"UPDATE `{GAME_PREFIX}zones` SET holder_controller_id=%s, claimer_controller_id=%s WHERE name=%s",
            (cid, cid, zone_name),
        )
    conn.commit()
    cur.close(); conn.close()


def _remove_worker_power(worker_lastname, power_name):
    """Remove a power that may already be on a worker (so the dropdown still offers it)."""
    conn = _db(); cur = conn.cursor()
    cur.execute(
        f"DELETE wp FROM `{GAME_PREFIX}worker_powers` wp "
        f"JOIN `{GAME_PREFIX}workers` w ON w.id = wp.worker_id "
        f"JOIN `{GAME_PREFIX}link_power_type` lpt ON lpt.id = wp.link_power_type_id "
        f"JOIN `{GAME_PREFIX}powers` p ON p.id = lpt.power_id "
        f"WHERE w.lastname=%s AND p.name=%s",
        (worker_lastname, power_name),
    )
    conn.commit()
    cur.close(); conn.close()


def _link_power_type_id(power_name):
    """Return the link_power_type_id for a Transformation by power name."""
    conn = _db(); cur = conn.cursor()
    cur.execute(
        f"SELECT lpt.id FROM `{GAME_PREFIX}link_power_type` lpt "
        f"JOIN `{GAME_PREFIX}powers` p ON p.id = lpt.power_id "
        f"WHERE p.name=%s LIMIT 1",
        (power_name,),
    )
    row = cur.fetchone()
    cur.close(); conn.close()
    return row['id'] if row else None


def _worker_id_db(lastname):
    """Resolve worker_id by lastname without needing admin-page access."""
    conn = _db(); cur = conn.cursor()
    cur.execute(
        f"SELECT id FROM `{GAME_PREFIX}workers` WHERE lastname=%s LIMIT 1",
        (lastname,),
    )
    row = cur.fetchone()
    cur.close(); conn.close()
    return row['id'] if row else None


def _click_transform_via_get(browser, base_url, worker_lastname, power_name,
                              username="single_player", password="test"):
    """Submit a transformation as a non-privileged user via direct GET (the
    same shape the form would produce). Returns the rendered page body."""
    wid = _worker_id_db(worker_lastname)
    lpt = _link_power_type_id(power_name)
    ctx = browser.new_context(); page = ctx.new_page()
    register_php_error_listener(page)
    login_as(page, base_url, username, password)
    page.goto(
        f"{base_url}/workers/action.php?worker_id={wid}"
        f"&transformation={lpt}&transform=1"
    )
    page.wait_for_load_state("load")
    body = page.content()
    assert_no_collected_php_errors(page)
    ctx.close()
    return body


# ---------------------------------------------------------------------------
# Direct controller_has_ressource gate
# ---------------------------------------------------------------------------

class TestRessourceGate:
    """Direct check controller_has_ressource: power visible iff controller has
    at least the required amount."""

    def test_power_visible_when_amount_sufficient(self, browser, base_url):
        alpha = _controller_id("Alpha"); gold = _ressource_id("Gold")
        _set_amount(alpha, gold, 5)
        _remove_worker_power("Transform_Subject", "Test Gold Cost Explicit")

        ctx = browser.new_context(); page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, base_url)
        options = ui_transform_options(page, "Transform_Subject", base_url=base_url)
        assert_no_collected_php_errors(page)
        ctx.close()
        assert "Test Gold Cost Explicit" in options, (
            f"With Gold=5 ≥ required=3, the power must appear. Got: {options}"
        )

    def test_power_hidden_when_amount_insufficient(self, browser, base_url):
        alpha = _controller_id("Alpha"); gold = _ressource_id("Gold")
        _set_amount(alpha, gold, 2)  # < required 3
        _remove_worker_power("Transform_Subject", "Test Gold Cost Explicit")

        ctx = browser.new_context(); page = ctx.new_page()
        ensure_gm_login(page, base_url)
        options = ui_transform_options(page, "Transform_Subject", base_url=base_url)
        ctx.close()
        assert "Test Gold Cost Explicit" not in options, (
            f"With Gold=2 < required=3, the power must NOT appear. Got: {options}"
        )

        # Restore
        _set_amount(alpha, gold, 100)


# ---------------------------------------------------------------------------
# consume opt-out semantics (default-deduct)
# ---------------------------------------------------------------------------

class TestConsumeBehavior:
    """Default behaviour: rule deducts unless explicit consume:false."""

    def test_explicit_consume_true_deducts(self, browser, base_url):
        alpha = _controller_id("Alpha"); gold = _ressource_id("Gold")
        _set_amount(alpha, gold, 10)
        _remove_worker_power("Transform_Subject", "Test Gold Cost Explicit")

        _click_transform_via_get(browser, base_url, "Transform_Subject", "Test Gold Cost Explicit")

        assert _read_amount(alpha, gold) == 7, (
            f"explicit consume:true must deduct 3 from 10; got {_read_amount(alpha, gold)}"
        )
        _set_amount(alpha, gold, 100)

    def test_default_consume_deducts(self, browser, base_url):
        """consume OMITTED defaults to true → deduct."""
        alpha = _controller_id("Alpha"); gold = _ressource_id("Gold")
        _set_amount(alpha, gold, 10)
        _remove_worker_power("Transform_Subject", "Test Gold Cost Default")

        _click_transform_via_get(browser, base_url, "Transform_Subject", "Test Gold Cost Default")

        assert _read_amount(alpha, gold) == 7, (
            f"absent consume defaults to true; must deduct 3 from 10; "
            f"got {_read_amount(alpha, gold)}"
        )
        _set_amount(alpha, gold, 100)

    def test_explicit_consume_false_does_not_deduct(self, browser, base_url):
        """explicit consume:false opts out → gate only, no deduction."""
        alpha = _controller_id("Alpha"); gold = _ressource_id("Gold")
        _set_amount(alpha, gold, 10)
        _remove_worker_power("Transform_Subject", "Test Gold Gate Optout")

        _click_transform_via_get(browser, base_url, "Transform_Subject", "Test Gold Gate Optout")

        assert _read_amount(alpha, gold) == 10, (
            f"explicit consume:false must NOT deduct; got {_read_amount(alpha, gold)}"
        )
        _set_amount(alpha, gold, 100)


# ---------------------------------------------------------------------------
# OR composition: zone-or-pay
# ---------------------------------------------------------------------------

class TestORComposition:
    """First-match-wins on OR with cheaper-branch-first ordering: the zone
    branch is consulted first; if it matches, no deduction. Otherwise the
    ressource branch matches and the cost is deducted."""

    def test_zone_branch_free_when_held(self, browser, base_url):
        """Alpha holds Gamma-Claims → zone branch matches first → no deduct."""
        alpha = _controller_id("Alpha"); gold = _ressource_id("Gold")
        _set_zone_holder("Gamma-Claims", "Alpha")
        _set_amount(alpha, gold, 10)
        _remove_worker_power("Transform_Subject", "Test OR Zone Or Gold")

        _click_transform_via_get(browser, base_url, "Transform_Subject", "Test OR Zone Or Gold")

        assert _read_amount(alpha, gold) == 10, (
            f"Zone branch first must match for Alpha (holds Gamma-Claims); "
            f"Gold must be unchanged. Got {_read_amount(alpha, gold)}"
        )
        _set_amount(alpha, gold, 100)

    def test_ressource_branch_deducts_when_no_zone(self, browser, base_url):
        """Strip the zone holder: Alpha now satisfies only the ressource
        branch → deduct 1."""
        alpha = _controller_id("Alpha"); gold = _ressource_id("Gold")
        _set_zone_holder("Gamma-Claims", None)
        _set_amount(alpha, gold, 10)
        _remove_worker_power("Transform_Subject", "Test OR Zone Or Gold")

        _click_transform_via_get(browser, base_url, "Transform_Subject", "Test OR Zone Or Gold")

        assert _read_amount(alpha, gold) == 9, (
            f"Resource branch must match (no zone) and deduct 1; "
            f"got {_read_amount(alpha, gold)}"
        )
        _set_zone_holder("Gamma-Claims", "Alpha")
        _set_amount(alpha, gold, 100)

    def test_or_fails_when_neither(self, browser, base_url):
        """No zone + no Gold → power vanishes from select."""
        alpha = _controller_id("Alpha"); gold = _ressource_id("Gold")
        _set_zone_holder("Gamma-Claims", None)
        _set_amount(alpha, gold, 0)
        _remove_worker_power("Transform_Subject", "Test OR Zone Or Gold")

        ctx = browser.new_context(); page = ctx.new_page()
        ensure_gm_login(page, base_url)
        options = ui_transform_options(page, "Transform_Subject", base_url=base_url)
        ctx.close()

        assert "Test OR Zone Or Gold" not in options, (
            f"With no zone and no Gold, the OR fails entirely → power must "
            f"be hidden. Got: {options}"
        )
        _set_zone_holder("Gamma-Claims", "Alpha")
        _set_amount(alpha, gold, 100)


# ---------------------------------------------------------------------------
# direct + OR composition + cross-resource warning
# ---------------------------------------------------------------------------

class TestDirectAndORComposition:
    """A rule can have both direct controller_has_ressource AND an OR
    branch carrying its own cost. Direct takes precedence; cross-resource
    fires error_log + only the direct cost deducts."""

    def test_direct_cost_fires_when_or_is_non_resource(self, browser, base_url):
        """Direct Gold=3 + OR [zone, alive]. Alpha holds Gamma-Claims OR is
        active → OR satisfies via a non-resource branch. Direct Gold cost
        still applies."""
        alpha = _controller_id("Alpha"); gold = _ressource_id("Gold")
        _set_zone_holder("Gamma-Claims", "Alpha")
        _set_amount(alpha, gold, 10)
        _remove_worker_power("Transform_Subject", "Test Direct And OR")

        _click_transform_via_get(browser, base_url, "Transform_Subject", "Test Direct And OR")

        assert _read_amount(alpha, gold) == 7, (
            f"Direct cost Gold=3 must deduct even when OR matches via "
            f"non-resource branch; got {_read_amount(alpha, gold)}"
        )
        _set_amount(alpha, gold, 100)

    def test_cross_resource_uses_direct_only(self, browser, base_url):
        """Direct Gold=1 + OR [Silver=1]. Both costs satisfiable → direct
        precedence: only Gold deducted, Silver untouched."""
        alpha = _controller_id("Alpha")
        gold = _ressource_id("Gold"); silver = _ressource_id("Silver")
        _set_amount(alpha, gold, 5)
        _set_amount(alpha, silver, 5)
        _remove_worker_power("Transform_Subject", "Test Cross Resource")

        _click_transform_via_get(browser, base_url, "Transform_Subject", "Test Cross Resource")

        assert _read_amount(alpha, gold) == 4, (
            f"Direct Gold cost must deduct; got Gold={_read_amount(alpha, gold)}"
        )
        assert _read_amount(alpha, silver) == 5, (
            f"OR Silver cost must NOT deduct under cross-resource policy; "
            f"got Silver={_read_amount(alpha, silver)}"
        )
        _set_amount(alpha, gold, 100); _set_amount(alpha, silver, 5)


# ---------------------------------------------------------------------------
# Malformed amount
# ---------------------------------------------------------------------------

class TestMalformedAmount:
    """Malformed amount=0 makes findMatchingBranch reject the power."""

    def test_amount_zero_drops_power(self, browser, base_url):
        ctx = browser.new_context(); page = ctx.new_page()
        ensure_gm_login(page, base_url)
        options = ui_transform_options(page, "Transform_Subject", base_url=base_url)
        ctx.close()

        assert "Test Malformed Amount" not in options, (
            f"Amount=0 must drop the power; got: {options}"
        )

    def test_unknown_key_drops_power(self, browser, base_url):
        """Fail-closed on unknown rule keys: a typo like
        'controller_has_resource' (English single-`s`) used as the only
        key in a state rule must hide the power. Otherwise the power
        would silently unlock — a footgun on a security/gating surface."""
        ctx = browser.new_context(); page = ctx.new_page()
        ensure_gm_login(page, base_url)
        options = ui_transform_options(page, "Transform_Subject", base_url=base_url)
        ctx.close()

        assert "Test Unknown Rule Key" not in options, (
            f"Unknown rule key must fail closed (drop the power); got: {options}"
        )


# ---------------------------------------------------------------------------
# Commit-time re-validation closes the display-vs-commit security gap
# ---------------------------------------------------------------------------

class TestCommitRevalidation:
    """A crafted GET that bypasses the form must be rejected at commit if
    the chosen power's rule no longer passes (or the worker is not in the
    surviving list). Closes the display-to-commit security gap on BOTH
    transform AND teach_discipline branches."""

    def test_transform_crafted_get_rejected_when_rule_fails(self, browser, base_url):
        """Alpha tries to GET-attack a transformation whose rule it cannot
        currently satisfy. The server-side re-validation must reject."""
        alpha = _controller_id("Alpha"); gold = _ressource_id("Gold")
        _set_amount(alpha, gold, 0)  # < required for any Gold-gated power
        # Clear any prior insert so the count check is unambiguous.
        _remove_worker_power("Transform_Subject", "Test Gold Cost Explicit")
        lpt = _link_power_type_id("Test Gold Cost Explicit")
        wid = _worker_id_db("Transform_Subject")

        ctx = browser.new_context(); page = ctx.new_page()
        login_as(page, base_url, "single_player", "test")
        page.goto(
            f"{base_url}/workers/action.php?worker_id={wid}"
            f"&transformation={lpt}&transform=1"
        )
        page.wait_for_load_state("load")
        body = page.content()
        ctx.close()

        # Re-validation must produce a 'plus disponible' notification AND
        # not actually grant the power (worker_powers row absent).
        conn = _db(); cur = conn.cursor()
        cur.execute(
            f"SELECT COUNT(*) AS c FROM `{GAME_PREFIX}worker_powers` wp "
            f"JOIN `{GAME_PREFIX}workers` w ON w.id = wp.worker_id "
            f"WHERE w.lastname='Transform_Subject' AND wp.link_power_type_id=%s",
            (lpt,),
        )
        row = cur.fetchone()
        cur.close(); conn.close()

        assert row['c'] == 0, "Re-validation must prevent the worker_powers INSERT"
        assert "plus disponible" in body, (
            f"Expected French notification 'plus disponible'; "
            f"body did not contain it"
        )
        _set_amount(alpha, gold, 100)

    def test_discipline_crafted_get_rejected_when_rule_fails(self, browser, base_url):
        """Same shape on teach_discipline. 'Test Discipline Zone Gated'
        carries on_age.controller_has_zone='Epsilon-Controlled' which
        Alpha never holds → re-validation must drop the crafted GET."""
        lpt = _link_power_type_id("Test Discipline Zone Gated")
        if lpt is None:
            pytest.skip("Test Discipline Zone Gated not in scenario")
        _remove_worker_power("Transform_Subject", "Test Discipline Zone Gated")
        wid = _worker_id_db("Transform_Subject")
        # Belt-and-buckle: ensure Epsilon-Controlled has no holder.
        _set_zone_holder("Epsilon-Controlled", None)

        ctx = browser.new_context(); page = ctx.new_page()
        login_as(page, base_url, "single_player", "test")
        page.goto(
            f"{base_url}/workers/action.php?worker_id={wid}"
            f"&discipline={lpt}&teach_discipline=1"
        )
        page.wait_for_load_state("load")
        ctx.close()

        conn = _db(); cur = conn.cursor()
        cur.execute(
            f"SELECT COUNT(*) AS c FROM `{GAME_PREFIX}worker_powers` wp "
            f"JOIN `{GAME_PREFIX}workers` w ON w.id = wp.worker_id "
            f"WHERE w.lastname='Transform_Subject' AND wp.link_power_type_id=%s",
            (lpt,),
        )
        row = cur.fetchone()
        cur.close(); conn.close()
        assert row['c'] == 0, (
            "Crafted teach_discipline GET must NOT insert worker_powers "
            "when re-validation fails"
        )

    def test_discipline_crafted_get_rejected_when_enemy_faction(self, browser, base_url):
        """Closing the same family of bug as #67 on the faction surface:
        a non-privileged player must not be able to teach an enemy-faction
        discipline by GET-crafting the link_power_type_id, even when the
        discipline carries no failing JSON gate. 'Defensive Posture' is
        FactionBeta-only via faction_powers; Alpha is in FactionAlpha so
        the display dropdown filters it out (getPowersByType joined on
        faction_powers). The commit branch must mirror that filtering."""
        lpt = _link_power_type_id("Defensive Posture")
        if lpt is None:
            pytest.skip("Defensive Posture not in scenario")
        _remove_worker_power("Transform_Subject", "Defensive Posture")
        wid = _worker_id_db("Transform_Subject")

        ctx = browser.new_context(); page = ctx.new_page()
        login_as(page, base_url, "single_player", "test")
        page.goto(
            f"{base_url}/workers/action.php?worker_id={wid}"
            f"&discipline={lpt}&teach_discipline=1"
        )
        page.wait_for_load_state("load")
        ctx.close()

        conn = _db(); cur = conn.cursor()
        cur.execute(
            f"SELECT COUNT(*) AS c FROM `{GAME_PREFIX}worker_powers` wp "
            f"JOIN `{GAME_PREFIX}workers` w ON w.id = wp.worker_id "
            f"WHERE w.lastname='Transform_Subject' AND wp.link_power_type_id=%s",
            (lpt,),
        )
        row = cur.fetchone()
        cur.close(); conn.close()
        assert row['c'] == 0, (
            "Crafted teach_discipline targeting an enemy-faction discipline "
            "must NOT insert worker_powers — the commit-side candidate "
            "fetch must use the controller's faction_powers join"
        )


# ---------------------------------------------------------------------------
# Privileged admin path bypasses re-validation AND cost
# ---------------------------------------------------------------------------

class TestAdminBypass:
    """gm has is_privileged → re-validation block short-circuited.
    Crafted GET that would 'plus disponible' for a player succeeds for gm,
    and no Gold is deducted (admin is an intentional escape hatch)."""

    def test_gm_bypasses_revalidation_and_cost(self, browser, base_url):
        alpha = _controller_id("Alpha"); gold = _ressource_id("Gold")
        _set_amount(alpha, gold, 0)  # would fail for a player
        _remove_worker_power("Transform_Subject", "Test Gold Cost Explicit")
        lpt = _link_power_type_id("Test Gold Cost Explicit")
        wid = _worker_id_db("Transform_Subject")
        gold_before = _read_amount(alpha, gold)

        ctx = browser.new_context(); page = ctx.new_page()
        ensure_gm_login(page, base_url)
        page.goto(
            f"{base_url}/workers/action.php?worker_id={wid}"
            f"&transformation={lpt}&transform=1"
        )
        page.wait_for_load_state("load")
        ctx.close()

        conn = _db(); cur = conn.cursor()
        cur.execute(
            f"SELECT COUNT(*) AS c FROM `{GAME_PREFIX}worker_powers` wp "
            f"JOIN `{GAME_PREFIX}workers` w ON w.id = wp.worker_id "
            f"WHERE w.lastname='Transform_Subject' AND wp.link_power_type_id=%s",
            (lpt,),
        )
        row = cur.fetchone()
        cur.close(); conn.close()

        assert row['c'] == 1, (
            "Admin path must grant the power even when the rule would "
            "fail re-validation for a normal player"
        )
        assert _read_amount(alpha, gold) == gold_before, (
            f"Admin path must NOT deduct ressources; "
            f"gold before={gold_before} after={_read_amount(alpha, gold)}"
        )
        _set_amount(alpha, gold, 100)
