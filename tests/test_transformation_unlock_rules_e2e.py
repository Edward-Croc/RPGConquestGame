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
                            Test Malformed Amount,
                            Test_Not_Beta (controller_not_faction
                            « FactionGolf, FactionBeta »)
                            Test_Gold_Recruit (on_recrutment Gold=3)

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
from playwright.sync_api import Page

from conftest import (
    GAME_PREFIX, MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB,
    PHP_BASE_URL, ensure_gm_login,
)
from helpers import (
    DB_AVAILABLE, end_turn, load_minimal_data, load_scenario_via_admin,
    safe_goto, register_php_error_listener, assert_no_collected_php_errors,
    login_as, ui_transform_options, ui_transform_click,
    ui_teach_discipline_options, ui_worker_id, get_db_connection,
    ui_controller_id, ui_workers_by_lastname, ui_select_option_id, ui_read_ressources_page, ui_zone_id,)


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
    return get_db_connection()


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

@pytest.mark.db
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

@pytest.mark.db
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

@pytest.mark.db
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

@pytest.mark.db
class TestDirectAndORComposition:
    """A rule can have both direct controller_has_ressource AND an OR
    branch carrying its own cost. Direct takes precedence; cross-resource
    fires error_log + only the direct cost deducts."""

    def test_direct_cost_fires_when_or_is_non_resource(self, browser, base_url):
        """Direct Gold=3 + OR [zone, age 0]. Alpha holds Gamma-Claims, and
        any agent is of age 0 or more → OR satisfies via a non-resource
        branch. Direct Gold cost still applies."""
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
# controller_not_faction — a refusal list, mirror of controller_faction
# ---------------------------------------------------------------------------

def _recruitment_page(browser, base_url, controller_lastname):
    """Render the first-come recruitment page of a controller as the gm.
    Returns (recruitment form rendered, transformation names offered).

    Rendering spends that controller's first-come allowance for the turn, so
    each controller is visited once in this module."""
    ctx = browser.new_context(); page = ctx.new_page()
    ensure_gm_login(page, base_url)
    controller_id = ui_controller_id(page, controller_lastname, base_url=base_url)
    safe_goto(page, f"{base_url}/workers/new.php?controller_id={controller_id}")
    page.wait_for_load_state("load")
    form_rendered = page.locator("input[name='chosir']").count() > 0
    offered = [
        (o.inner_text() or "").split(" (")[0].strip()
        for o in page.locator("select#transformationSelect option").all()
        if o.get_attribute("value")
    ]
    ctx.close()
    return form_rendered, offered


class TestControllerNotFaction:
    """Test_Not_Beta refuses FactionGolf and FactionBeta : as the comma string
    "FactionGolf, FactionBeta" in on_recrutment, and as the JSON list
    ["FactionGolf", "FactionBeta"] in on_transformation. Every other faction
    is offered it. FactionBeta sits second, so the comma split and the trim
    are exercised on the recruitment page, the list form on the agent sheet.

    Without the key in evaluateRuleKeysAllMatch's whitelist the power fails
    closed and nobody is offered it, so the positive assertions go red ; with
    the key whitelisted but no refusal branch, Beta is offered it and the
    negative assertions go red."""

    def test_agent_sheet_offers_it_to_another_faction(self, browser, base_url):
        ctx = browser.new_context(); page = ctx.new_page()
        ensure_gm_login(page, base_url)
        options = ui_transform_options(page, "Transform_Subject", base_url=base_url)
        ctx.close()

        assert "Test_Not_Beta" in options, (
            f"FactionAlpha is not in the refusal list : Transform_Subject must be offered Test_Not_Beta; got: {options}"
        )

    def test_agent_sheet_refuses_it_to_a_listed_faction(self, browser, base_url):
        ctx = browser.new_context(); page = ctx.new_page()
        ensure_gm_login(page, base_url)
        options = ui_transform_options(page, "Bystander_1", base_url=base_url)
        ctx.close()

        assert "War Gear" in options, (
            f"Bystander_1 (Beta) must still be offered its ordinary transformations; got: {options}"
        )
        assert "Test_Not_Beta" not in options, (
            f"FactionBeta is in the refusal list : Bystander_1 must not be offered Test_Not_Beta; got: {options}"
        )

    def test_recruitment_offers_it_to_another_faction(self, browser, base_url):
        form_rendered, offered = _recruitment_page(browser, base_url, "Alpha")
        assert form_rendered, "Alpha's recruitment page must render its recruitment form"
        assert "Test_Not_Beta" in offered, (
            f"FactionAlpha is not in the refusal list : its recruitment must offer Test_Not_Beta; got: {offered}"
        )

    def test_recruitment_refuses_it_to_a_listed_faction(self, browser, base_url):
        form_rendered, offered = _recruitment_page(browser, base_url, "Beta")
        assert form_rendered, "Beta's recruitment page must render its recruitment form"
        assert "Test_Not_Beta" not in offered, (
            f"FactionBeta is in the refusal list : its recruitment must not offer Test_Not_Beta; got: {offered}"
        )


# ---------------------------------------------------------------------------
# Recruitment re-checks the chosen transformation and charges its cost
# ---------------------------------------------------------------------------

def _gold(page):
    """Gold amount of the session's controller, read off the Ressources page."""
    rows, _ = ui_read_ressources_page(page)
    cell = rows.get("Gold", {}).get("Montant utilisable", "")
    return int(cell) if cell.isdigit() else None


def _offered_on_recruitment(page):
    """Transformation name -> option value in the first recruitment form, placeholder excluded."""
    return {
        (o.inner_text() or "").split(" (")[0].strip(): o.get_attribute("value")
        for o in page.locator("div.workers form").first.locator("select#transformationSelect option").all()
        if o.get_attribute("value")
    }


def _recruit_url(page, **fields):
    """The creation URL the first recruitment form submits, without its
    transformation, with `fields` set over the form's own values."""
    return page.locator("div.workers form").first.evaluate(
        """(form, fields) => {
            const data = new FormData(form);
            data.delete('transformation');
            for (const [name, value] of Object.entries(fields)) { data.set(name, value); }
            data.set('chosir', 'Recruter et Affecter');
            return form.action + '?' + new URLSearchParams(data).toString();
        }""",
        fields,
    )


def _rows(gm, lastname, controller_id, firstname=None):
    """Agents named `lastname` (and `firstname` when given) serving `controller_id`, read off the gm's agent list."""
    return [row for row in ui_workers_by_lastname(gm, lastname, base_url=PHP_BASE_URL)
            if int(row["controller_id"]) == int(controller_id)
            and (firstname is None or row["firstname"] == firstname)]


def _form_lastname(page):
    return page.locator("div.workers form").first.locator("input[name='lastname']").get_attribute("value")


def _visit(page, url):
    """Open `url` and return (status, page content)."""
    response = page.goto(url)
    page.wait_for_load_state("load")
    return (response.status if response else None), page.content()


def _gift_gold(page, target_controller_id, amount):
    """Give `amount` Gold from the session's controller through the Ressources page form."""
    safe_goto(page, f"{PHP_BASE_URL}/ressources/view.php")
    page.wait_for_load_state("load")
    gold = page.locator("select[name='ressource_id'] option:has-text('Gold')").first.get_attribute("value")
    page.locator("select[name='ressource_id']").select_option(value=gold)
    page.fill("input[name='amount']", str(amount))
    page.locator("select[name='target_controller_id']").select_option(value=str(target_controller_id))
    page.locator("input[name='giftRessource']").click()
    page.wait_for_load_state("load")


def _perfect_form_recruit(page, controller_id, transformation_label):
    """Fill admin.php's « Create Perfect Agent » form for `controller_id` with
    the transformation `transformation_label`, submit it, return (firstname, lastname)."""
    safe_goto(page, f"{PHP_BASE_URL}/admin/admin.php")
    form = page.locator("form:has(input[name='perfect_worker'])")
    form.locator("select#controllerSelect").select_option(value=str(controller_id))
    origin = form.locator("select#origin_id option[value]:not([value=''])").first
    form.locator("select#origin_id").select_option(value=origin.get_attribute("value"))
    origin_name = origin.inner_text().strip()
    firstname = form.locator(f"select#firstname option:has-text('({origin_name})')").first.get_attribute("value")
    form.locator("select#firstname").select_option(value=firstname)
    lastname_option = form.locator(f"select#lastname option:has-text('({origin_name})')").first
    lastname = lastname_option.get_attribute("value")
    form.locator("select#lastname").select_option(value=lastname)
    form.locator("select#transformationSelect").select_option(
        value=str(ui_select_option_id(page, "form:has(input[name='perfect_worker']) select#transformationSelect", transformation_label)))
    form.locator("input[name='chosir']").click()
    page.wait_for_load_state("load")
    return firstname, lastname


@pytest.fixture(scope="class")
def recruitment_cost(browser):
    """Recruitment checks, played in one sequence on the module's TestConfig :

    - Alpha recruits with Test_Gold_Recruit (Gold=3), then replays the URL ;
    - Echo (Gold=2) forges that recruitment, is refused, receives 1 Gold from
      Alpha, buys it with the same URL, then replays it with an empty stock ;
    - Beta (gm with Beta selected) forges Test_Not_Beta, then a transformation
      id in the discipline and hobby fields, then recruits with a real discipline ;
    - Delta's player sends the perfect-agent marker, which it is not entitled to ;
    - the gm with Beta selected uses the perfect-agent form for Echo.

    Alpha and Beta build their base, then use the regular recruitment (start_workers=1 at turn 0),
    their first-come allowance being spent by TestControllerNotFaction ;
    Echo and Delta use their first-come allowance."""
    state = {}
    contexts = []
    try:
        gm_context = browser.new_context(); contexts.append(gm_context)
        gm = gm_context.new_page()
        ensure_gm_login(gm, PHP_BASE_URL)
        ids = {name: ui_controller_id(gm, name, base_url=PHP_BASE_URL) for name in ("Alpha", "Beta", "Delta", "Echo")}
        safe_goto(gm, f"{PHP_BASE_URL}/admin/admin.php")
        perfect = "form:has(input[name='perfect_worker'])"
        power_ids = {
            "War Gear": ui_select_option_id(gm, f"{perfect} select#transformationSelect", "War Gear"),
            "Focused Mind": ui_select_option_id(gm, f"{perfect} select#disciplineSelect", "Focused Mind"),
        }

        # Alpha, as its player, picks Test_Gold_Recruit in the form and submits it.
        alpha_context = browser.new_context(); contexts.append(alpha_context)
        alpha = alpha_context.new_page()
        login_as(alpha, PHP_BASE_URL, "single_player", "test")
        # Recruiting a servant needs a base : Alpha and Beta build theirs first.
        zones = {name: ui_zone_id(gm, name, base_url=PHP_BASE_URL) for name in ("Epsilon-Controlled", "Zeta-Unclaimed")}
        safe_goto(alpha, f"{PHP_BASE_URL}/controllers/action.php?createBase=1&controller_id={ids['Alpha']}&zone_id={zones['Epsilon-Controlled']}")
        state["alpha_gold_before"] = _gold(alpha)
        safe_goto(alpha, f"{PHP_BASE_URL}/workers/new.php?recrutement=true")
        offered = _offered_on_recruitment(alpha)
        state["alpha_offered"] = set(offered)
        form = alpha.locator("div.workers form").first
        lastname = _form_lastname(alpha)
        form.locator("select#transformationSelect").select_option(value=offered["Test_Gold_Recruit"])
        form.locator("input[name='chosir']").click()
        alpha.wait_for_load_state("load")
        creation_url = alpha.url
        state["creation_url"] = creation_url
        state["alpha_html"] = alpha.content()
        state["alpha_gold_after"] = _gold(alpha)
        state["alpha_rows"] = _rows(gm, lastname, ids["Alpha"])
        state["replay_status"], _ = _visit(alpha, creation_url)
        state["alpha_gold_after_replay"] = _gold(alpha)
        state["alpha_rows_after_replay"] = _rows(gm, lastname, ids["Alpha"])

        # Echo holds Gold=2 : the page does not offer it, the forged URL must not buy it.
        echo_context = browser.new_context(); contexts.append(echo_context)
        echo = echo_context.new_page()
        login_as(echo, PHP_BASE_URL, "echo_player", "test")
        state["echo_gold_before"] = _gold(echo)
        safe_goto(echo, f"{PHP_BASE_URL}/workers/new.php")
        state["echo_offered"] = set(_offered_on_recruitment(echo))
        echo_lastname = _form_lastname(echo)
        bought = _recruit_url(echo, transformation=offered["Test_Gold_Recruit"])
        state["echo_status"], state["echo_html"] = _visit(echo, bought)
        state["echo_gold_after"] = _gold(echo)
        state["echo_rows_after_forged"] = _rows(gm, echo_lastname, ids["Echo"])
        # With exactly the cost in stock, the same URL buys it ; replayed on an empty stock, it shows the agent.
        _gift_gold(alpha, ids["Echo"], 1)
        state["echo_gold_gifted"] = _gold(echo)
        state["echo_bought_status"], state["echo_bought_html"] = _visit(echo, bought)
        state["echo_gold_bought"] = _gold(echo)
        state["echo_rows_bought"] = _rows(gm, echo_lastname, ids["Echo"])
        state["echo_replay_status"], state["echo_replay_html"] = _visit(echo, bought)
        state["echo_gold_replayed"] = _gold(echo)
        state["echo_rows_replayed"] = _rows(gm, echo_lastname, ids["Echo"])

        # Beta, played by the gm with Beta selected, forges what its page does not offer.
        beta_context = browser.new_context(); contexts.append(beta_context)
        beta = beta_context.new_page()
        ensure_gm_login(beta, PHP_BASE_URL)
        safe_goto(beta, f"{PHP_BASE_URL}/base/accueil.php?controller_id={ids['Beta']}&chosir=Choisir")
        safe_goto(beta, f"{PHP_BASE_URL}/controllers/action.php?createBase=1&controller_id={ids['Beta']}&zone_id={zones['Zeta-Unclaimed']}")
        safe_goto(beta, f"{PHP_BASE_URL}/workers/new.php?recrutement=true&controller_id={ids['Beta']}")
        state["beta_offered"] = set(_offered_on_recruitment(beta))
        beta_lastname = _form_lastname(beta)
        forged = {
            "transformation": _recruit_url(beta, transformation=offered["Test_Not_Beta"]),
            "discipline": _recruit_url(beta, discipline=str(power_ids["War Gear"])),
            "hobby": _recruit_url(beta, power_hobby_id=str(power_ids["War Gear"])),
        }
        real_discipline = _recruit_url(beta, discipline=str(power_ids["Focused Mind"]))
        for field, url in forged.items():
            state[f"beta_{field}_status"], state[f"beta_{field}_html"] = _visit(beta, url)
            state[f"beta_rows_after_{field}"] = _rows(gm, beta_lastname, ids["Beta"])
        _, state["beta_real_html"] = _visit(beta, real_discipline)
        state["beta_rows_after_real"] = _rows(gm, beta_lastname, ids["Beta"])

        # Delta's player is not privileged : the perfect-agent marker gives it nothing.
        delta_context = browser.new_context(); contexts.append(delta_context)
        delta = delta_context.new_page()
        login_as(delta, PHP_BASE_URL, "delta_player", "test")
        safe_goto(delta, f"{PHP_BASE_URL}/workers/new.php")
        delta_lastname = _form_lastname(delta)
        marked = _recruit_url(delta, perfect_worker="1", transformation=str(power_ids["War Gear"]))
        listed = _recruit_url(delta, **{"transformation[]": str(power_ids["War Gear"])})
        plain = _recruit_url(delta, perfect_worker="1")
        state["delta_marked_status"], state["delta_marked_html"] = _visit(delta, marked)
        state["delta_rows_after_marked"] = _rows(gm, delta_lastname, ids["Delta"])
        state["delta_listed_status"], state["delta_listed_html"] = _visit(delta, listed)
        state["delta_rows_after_listed"] = _rows(gm, delta_lastname, ids["Delta"])
        state["delta_plain_status"], state["delta_plain_html"] = _visit(delta, plain)
        state["delta_rows_after_plain"] = _rows(gm, delta_lastname, ids["Delta"])

        # The gm, Beta selected, gives Echo through the perfect-agent form what Echo cannot afford.
        state["echo_gold_before_perfect"] = _gold(echo)
        # Echo's first-come agent may share the lastname : the firstname tells them apart.
        perfect_firstname, perfect_lastname = _perfect_form_recruit(beta, ids["Echo"], "Test_Gold_Recruit")
        state["perfect_html"] = beta.content()
        state["perfect_rows"] = _rows(gm, perfect_lastname, ids["Echo"], firstname=perfect_firstname)
        state["echo_gold_after_perfect"] = _gold(echo)
        yield state
    finally:
        for context in contexts:
            context.close()


class TestRecruitmentCost:
    """workers/action.php's creation branch rebuilds the transformation list
    workers/new.php offers, refuses a posted transformation that is not on
    it, and charges its cost in the same transaction as the new agent."""

    def test_recruitment_charges_the_transformation_cost(self, recruitment_cost):
        assert "Test_Gold_Recruit" in recruitment_cost["alpha_offered"], \
            f"Alpha (Gold>=3) must be offered Test_Gold_Recruit; got {recruitment_cost['alpha_offered']}"
        assert len(recruitment_cost["alpha_rows"]) == 1, "the recruited agent must exist"
        assert "Test_Gold_Recruit" in recruitment_cost["alpha_html"], "the recruited agent must carry its transformation"
        assert recruitment_cost["alpha_gold_before"] is not None, "Alpha's Gold must be read"
        assert recruitment_cost["alpha_gold_after"] == recruitment_cost["alpha_gold_before"] - 3, (
            f"recruiting with Test_Gold_Recruit must cost 3 Gold: "
            f"{recruitment_cost['alpha_gold_before']} -> {recruitment_cost['alpha_gold_after']}"
        )

    def test_replayed_recruitment_charges_nothing(self, recruitment_cost):
        assert "creation=true" in recruitment_cost["creation_url"], "the replayed URL must be the recruitment itself"
        assert recruitment_cost["replay_status"] == 200, "a replay must show the agent already recruited"
        assert len(recruitment_cost["alpha_rows_after_replay"]) == 1, "a replay must not create a second agent"
        assert recruitment_cost["alpha_gold_after"] is not None, "Alpha's Gold must be read"
        assert recruitment_cost["alpha_gold_after_replay"] == recruitment_cost["alpha_gold_after"], (
            f"a replay must not charge again: {recruitment_cost['alpha_gold_after']} -> "
            f"{recruitment_cost['alpha_gold_after_replay']}"
        )

    def test_short_stock_buys_nothing(self, recruitment_cost):
        assert "Test_Gold_Recruit" not in recruitment_cost["echo_offered"], \
            f"Echo (Gold=2) must not be offered Test_Gold_Recruit; got {recruitment_cost['echo_offered']}"
        assert recruitment_cost["echo_rows_after_forged"] == [], "the refused recruitment must create no agent"
        assert "Cette transformation n'est plus disponible" in recruitment_cost["echo_html"]
        assert recruitment_cost["echo_status"] == 403, f"no agent to show: expected 403, got {recruitment_cost['echo_status']}"
        assert recruitment_cost["echo_gold_before"] == 2, f"Echo starts with 2 Gold, read {recruitment_cost['echo_gold_before']}"
        assert recruitment_cost["echo_gold_after"] == 2, "the refused recruitment must cost nothing"

    def test_exact_stock_buys_it(self, recruitment_cost):
        assert recruitment_cost["echo_gold_gifted"] == 3, f"Echo should hold 3 Gold after the gift, read {recruitment_cost['echo_gold_gifted']}"
        assert recruitment_cost["echo_bought_status"] == 200
        assert len(recruitment_cost["echo_rows_bought"]) == 1, "the same URL must buy the agent once the stock suffices"
        assert "Test_Gold_Recruit" in recruitment_cost["echo_bought_html"]
        assert recruitment_cost["echo_gold_bought"] == 0, f"3 Gold - 3, read {recruitment_cost['echo_gold_bought']}"

    def test_replay_after_the_stock_dropped_shows_the_agent(self, recruitment_cost):
        assert recruitment_cost["echo_replay_status"] == 200, \
            f"a replay on an empty stock must show the agent, got {recruitment_cost['echo_replay_status']}"
        assert "Cette transformation n'est plus disponible" not in recruitment_cost["echo_replay_html"]
        assert "Test_Gold_Recruit" in recruitment_cost["echo_replay_html"], "the page shows the agent bought before"
        assert len(recruitment_cost["echo_rows_replayed"]) == 1, "a replay must not create a second agent"
        assert recruitment_cost["echo_gold_replayed"] == 0, "a replay must not charge"

    def test_forged_transformation_is_refused(self, recruitment_cost):
        assert "Test_Not_Beta" not in recruitment_cost["beta_offered"], \
            f"Beta must not be offered Test_Not_Beta; got {recruitment_cost['beta_offered']}"
        assert recruitment_cost["beta_rows_after_transformation"] == [], "a forged transformation must create no agent"
        assert "Cette transformation n'est plus disponible" in recruitment_cost["beta_transformation_html"]
        assert recruitment_cost["beta_transformation_status"] == 403, \
            f"no agent to show: expected 403, got {recruitment_cost['beta_transformation_status']}"

    @staticmethod
    def _assert_smuggling_refused(recruitment_cost, field):
        assert recruitment_cost[f"beta_rows_after_{field}"] == [], \
            f"a transformation id posted as {field} must create no agent"
        assert "Ce pouvoir n'est pas disponible" in recruitment_cost[f"beta_{field}_html"]
        assert recruitment_cost[f"beta_{field}_status"] == 403

    # Two plain tests rather than a parametrized one : pytest would rebuild the class fixture for the second value.
    def test_transformation_smuggled_as_discipline_is_refused(self, recruitment_cost):
        self._assert_smuggling_refused(recruitment_cost, "discipline")

    def test_transformation_smuggled_as_hobby_is_refused(self, recruitment_cost):
        self._assert_smuggling_refused(recruitment_cost, "hobby")

    def test_real_discipline_still_recruits(self, recruitment_cost):
        assert len(recruitment_cost["beta_rows_after_real"]) == 1, \
            "the same recruitment with a real discipline must go through"
        assert "Focused Mind" in recruitment_cost["beta_real_html"], "the agent carries the discipline posted"

    def test_perfect_worker_marker_is_ignored_for_a_player(self, recruitment_cost):
        assert recruitment_cost["delta_rows_after_marked"] == [], "the marker must not let a player take War Gear"
        assert "Cette transformation n'est plus disponible" in recruitment_cost["delta_marked_html"]
        assert recruitment_cost["delta_marked_status"] == 403
        assert len(recruitment_cost["delta_rows_after_plain"]) == 1, \
            "the same recruitment without a transformation must go through"

    def test_transformation_posted_as_a_list_is_refused_cleanly(self, recruitment_cost):
        assert recruitment_cost["delta_listed_status"] == 403, \
            f"transformation[] must be refused, not crash: got {recruitment_cost['delta_listed_status']}"
        assert "Cette transformation n'est plus disponible" in recruitment_cost["delta_listed_html"]
        assert recruitment_cost["delta_rows_after_listed"] == []

    def test_perfect_agent_form_recruits_free_and_unchecked(self, recruitment_cost):
        assert len(recruitment_cost["perfect_rows"]) == 1, "the perfect-agent form must create the agent"
        assert "Test_Gold_Recruit" in recruitment_cost["perfect_html"], \
            "the gm gets a transformation Echo could not afford"
        assert recruitment_cost["echo_gold_before_perfect"] is not None, "Echo's Gold must be read"
        assert recruitment_cost["echo_gold_after_perfect"] == recruitment_cost["echo_gold_before_perfect"], \
            "the perfect-agent form charges nothing"


# ---------------------------------------------------------------------------
# Commit-time re-validation closes the display-vs-commit security gap
# ---------------------------------------------------------------------------

@pytest.mark.db
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

@pytest.mark.db
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


# ---------------------------------------------------------------------------
# Issue #94 regression : gm with a faction selected in session must NOT
# take the is_privileged escape hatch — the transform/teach_discipline
# path must re-validate and deduct cost, exactly like a plain player.
# The escape hatch is intended for pure admin (no faction in session).
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestGmAsFactionEntersNormalPath:
    """Discovered on Japon1555 playtest : gm acting as Chōsokabe triggered
    a `consume: true` transform without paying the cost. Root cause : both
    workers/action.php:170 (teach_discipline) and :196 (transform) bypassed
    on is_privileged alone. The fix gates the bypass on
    empty($_SESSION['controller']['id']) so a gm-in-faction session behaves
    like a normal player."""

    def test_gm_as_faction_transform_deducts_cost(self, browser, base_url):
        """gm logs in, selects Alpha via accueil.php, triggers an OR-ressource
        transform (mirrors the Cheval de Takamatsu case). Cost must be
        deducted — before #94 the bypass swallowed the consume."""
        alpha = _controller_id("Alpha"); gold = _ressource_id("Gold")
        _set_zone_holder("Gamma-Claims", None)  # force OR to ressource branch
        _set_amount(alpha, gold, 10)
        _remove_worker_power("Transform_Subject", "Test OR Zone Or Gold")
        lpt = _link_power_type_id("Test OR Zone Or Gold")
        wid = _worker_id_db("Transform_Subject")

        ctx = browser.new_context(); page = ctx.new_page()
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/accueil.php?controller_id={alpha}&chosir=Choisir")
        page.goto(
            f"{base_url}/workers/action.php?worker_id={wid}"
            f"&transformation={lpt}&transform=1"
        )
        page.wait_for_load_state("load")
        ctx.close()

        assert _read_amount(alpha, gold) == 9, (
            f"gm-as-Alpha must go through consume path: expected 9, "
            f"got {_read_amount(alpha, gold)} (issue #94)"
        )
        _set_amount(alpha, gold, 100)

    def test_gm_as_faction_transform_rejected_when_rule_fails(self, browser, base_url):
        """gm as Alpha with Gold=0 tries the Gold-3 transform. Re-validation
        must reject — pre-#94 the bypass would have inserted worker_powers."""
        alpha = _controller_id("Alpha"); gold = _ressource_id("Gold")
        _set_amount(alpha, gold, 0)
        _remove_worker_power("Transform_Subject", "Test Gold Cost Explicit")
        lpt = _link_power_type_id("Test Gold Cost Explicit")
        wid = _worker_id_db("Transform_Subject")

        ctx = browser.new_context(); page = ctx.new_page()
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/accueil.php?controller_id={alpha}&chosir=Choisir")
        page.goto(
            f"{base_url}/workers/action.php?worker_id={wid}"
            f"&transformation={lpt}&transform=1"
        )
        page.wait_for_load_state("load")
        body = page.content()
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
            "gm-as-faction must be rejected when rule fails (was bypassed pre-#94)"
        )
        assert "plus disponible" in body, "Expected re-validation notification"
        _set_amount(alpha, gold, 100)

    def test_gm_as_faction_discipline_rejected_when_rule_fails(self, browser, base_url):
        """Symmetric guard on teach_discipline (workers/action.php:170)."""
        lpt = _link_power_type_id("Test Discipline Zone Gated")
        if lpt is None:
            pytest.skip("Test Discipline Zone Gated not in scenario")
        alpha = _controller_id("Alpha")
        _remove_worker_power("Transform_Subject", "Test Discipline Zone Gated")
        _set_zone_holder("Epsilon-Controlled", None)
        wid = _worker_id_db("Transform_Subject")

        ctx = browser.new_context(); page = ctx.new_page()
        ensure_gm_login(page, base_url)
        safe_goto(page, f"{base_url}/base/accueil.php?controller_id={alpha}&chosir=Choisir")
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
            "gm-as-faction crafted teach_discipline GET must NOT insert "
            "worker_powers when re-validation fails (issue #94)"
        )


# ---------------------------------------------------------------------------
# cost shown in the dropdown
# ---------------------------------------------------------------------------

@pytest.mark.db
class TestRessourceCostShownInDropdown:
    """The transformation dropdown must state what a transformation will cost,
    stay silent when the rule deducts nothing (`consume: false`), and drop the
    ressource name when the power is already named after it — Japon1555 names
    each transformation after the ressource it consumes, so repeating it read
    as "Cheval de Takamatsu — Coût: 1 Cheval de Takamatsu".

    The label is fed by the same resolver the commit path uses, so a shown
    cost is by construction the one that will be charged."""

    def _labels(self, browser, base_url, power_name):
        alpha = _controller_id("Alpha"); gold = _ressource_id("Gold")
        _set_amount(alpha, gold, 100)
        _remove_worker_power("Transform_Subject", power_name)

        ctx = browser.new_context(); page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, base_url)
        labels = ui_transform_options(page, "Transform_Subject",
                                      base_url=base_url, raw=True)
        assert_no_collected_php_errors(page)
        ctx.close()

        matching = [l for l in labels if l.startswith(power_name)]
        assert matching, f"{power_name!r} must be offered at Gold=100. Got: {labels}"
        return matching[0]

    def test_deducting_rule_states_amount_and_ressource(self, browser, base_url):
        """`Test Direct And OR` costs 3 Gold and is not named after Gold, so
        the ressource name must appear."""
        label = self._labels(browser, base_url, "Test Direct And OR")
        assert "Coût: 3 Gold" in label, (
            f"a deducting rule must state amount and ressource; got {label!r}"
        )

    def test_ressource_name_dropped_when_already_in_the_power_name(self, browser, base_url):
        """`Test Gold Cost Explicit` carries 'Gold' in its own name, so the
        cost must read `Coût: 3` without repeating it."""
        label = self._labels(browser, base_url, "Test Gold Cost Explicit")
        assert "Coût: 3" in label, f"the amount must still show; got {label!r}"
        assert "Coût: 3 Gold" not in label, (
            f"the ressource name must not be repeated when the power name "
            f"already contains it; got {label!r}"
        )

    def test_non_deducting_rule_shows_no_cost(self, browser, base_url):
        """`consume: false` deducts nothing, so no cost may be shown."""
        label = self._labels(browser, base_url, "Test Gold Gate Optout")
        assert "Coût" not in label, (
            f"consume=false deducts nothing, so no cost must be shown; "
            f"got {label!r}"
        )
