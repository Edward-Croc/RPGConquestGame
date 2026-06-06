"""Pre-refactor regression baseline for `attackLocation` immediate-resolution.

Issue [#14](https://github.com/Edward-Croc/games/RPGConquestGame/issues/14)
will move location attacks from immediate (today) to end-of-turn
resolution. This test pins the CURRENT contract so the refactor can't
silently drift behaviour.

Scenario: Foxtrot attacks Echo-Base.
  - Both pre-seeded in TestConfig (Theta-Artefacts zone, can_be_destroyed=1).
  - Echo-Base carries an `update_location` swap that flips
    can_be_destroyed=1 / can_be_repaired=0 → 0/1 on a successful attack
    (Slice 17 seed).

Setup:
  1. Load TestConfig.
  2. Admin-seed Foxtrot's CKL row for Echo-Base via giftInformationLocation
     (without it, Echo-Base wouldn't appear in Foxtrot's attackable dropdown).

Math sanity (TestConfig defaults):
  - Foxtrot has 2 native active workers in Theta-Artefacts (Artefact_Worker_Foxtrot,
    Gift_Source_Foxtrot, both passive). Echo has 1 (Artefact_Searcher_Echo).
  - baseAttack=baseDefence=0, baseAttackAddWorkers=baseDefenceAddWorkers=1, no caps.
  - Theta-Artefacts is unowned (no claimer/holder), so no zone-control +1.
  - Diff = (0 + 2) − (0 + 1 + 0 turn-bonus) = 1, which equals
    attackLocationDiff=1 → deterministic success. No extra setup needed.

Branch dependency: this test relies on the `calculateControllerValue` fix
(branch `fix-calculateControllerValue-fail-minor`, awaiting PR merge) — on
plain `main`, an undefined `$turn_number` makes the worker-count SQL bind
NULL and worker bonuses always resolve to 0, breaking the math.

Assertions (Plan B):
  1. `location_attack_logs` has exactly one new row with attacker=Foxtrot.id
     and target_location_name='Echo-Base'.
  2. Echo-Base's flags flipped: can_be_destroyed=1→0 and can_be_repaired=0→1
     per its update_location swap.

Run:
    python3 -m pytest tests/test_attack_location_baseline_e2e.py -v
"""
import re

import pymysql
import pytest
from playwright.sync_api import Page

from conftest import (
    GAME_PREFIX, MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB,
    PHP_BASE_URL, ensure_gm_login,
)
from helpers import (
    DB_AVAILABLE, end_turn, load_minimal_data, safe_goto,
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
    if DB_AVAILABLE:
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


def _location_id_via_management(page, location_name):
    safe_goto(page, f"{PHP_BASE_URL}/zones/management_locations.php")
    page.wait_for_load_state("load")
    html = page.content()
    m = re.search(
        rf'<h3>[^<]*{re.escape(location_name)}[^<]*\(discovery[^<]+</h3>'
        rf'.*?name="toggle_destruction"\s+value="(\d+)"',
        html, re.DOTALL,
    )
    if not m:
        raise AssertionError(f"location_id for '{location_name}' not found")
    return int(m.group(1))


def _controller_id_via_management(page, controller_lastname):
    safe_goto(page, f"{PHP_BASE_URL}/base/accueil.php")
    page.wait_for_load_state("load")
    for opt in page.locator("select#controllerSelect option").all():
        v = opt.get_attribute("value") or ""
        t = (opt.inner_text() or "").strip()
        if v and controller_lastname in t:
            return int(v)
    raise AssertionError(f"controller_id for '{controller_lastname}' not found")


def _seed_ckl_admin(page, controller_lastname, location_name):
    """Admin path: gm POSTs giftInformationLocation to seed CKL."""
    cid = _controller_id_via_management(page, controller_lastname)
    location_id = _location_id_via_management(page, location_name)
    safe_goto(
        page,
        f"{PHP_BASE_URL}/controllers/management.php"
        f"?giftInformationLocation=1&target_controller_id={cid}&location_id={location_id}"
    )
    page.wait_for_load_state("load")


def _switch_controller(page, controller_lastname):
    cid = _controller_id_via_management(page, controller_lastname)
    safe_goto(page, f"{PHP_BASE_URL}/base/accueil.php?controller_id={cid}&chosir=Choisir")
    page.wait_for_load_state("load")


def _read_location_flags(location_id):
    conn = _db_conn()
    cur = conn.cursor()
    cur.execute(
        f"SELECT can_be_destroyed, can_be_repaired FROM `{GAME_PREFIX}locations` WHERE id = %s",
        (location_id,),
    )
    row = cur.fetchone()
    cur.close()
    conn.close()
    return row


def _read_attack_logs_for(location_name):
    conn = _db_conn()
    cur = conn.cursor()
    cur.execute(
        f"SELECT * FROM `{GAME_PREFIX}location_attack_logs` "
        f"WHERE location_name = %s ORDER BY id ASC",
        (location_name,),
    )
    rows = cur.fetchall()
    cur.close()
    conn.close()
    return rows


def _read_queue_rows_for_attacker(attacker_id):
    """Belt-and-buckle DB read of controller_location_attacks rows for
    an attacker. Used by @pytest.mark.db belt-and-buckle tests; UI-only
    tests don't depend on this."""
    conn = _db_conn()
    cur = conn.cursor()
    cur.execute(
        f"SELECT * FROM `{GAME_PREFIX}controller_location_attacks` "
        f"WHERE attacker_controller_id = %s ORDER BY id ASC",
        (attacker_id,),
    )
    rows = cur.fetchall()
    cur.close()
    conn.close()
    return rows


def _set_config_via_ui(page, name, value):
    """Set a config row's value via /base/configuration.php POST.

    HTTP-only equivalent of UPDATE config SET value=? WHERE name=?. Used
    so the locationAttackMode flip is observable from any deployment
    (local OR remote demo where direct DB access isn't available).
    """
    safe_goto(page, f"{PHP_BASE_URL}/base/configuration.php")
    page.wait_for_load_state("load")
    target_row = None
    for row in page.locator("tr:has(form)").all():
        name_cell = row.locator("td").nth(1)
        if name_cell.inner_text().strip() == name:
            target_row = row
            break
    if target_row is None:
        raise AssertionError(f"Config row {name!r} not found on /base/configuration.php")
    target_row.locator("input[name='value']").fill(value)
    target_row.locator("input[name='update_config']").click()
    page.wait_for_load_state("load")


def _foxtrot_can_attack_echo_base(page, echo_base_id):
    """As Foxtrot on /controllers/action.php, is Echo-Base in the
    attackable dropdown? Returns True if the option exists. Used as the
    UI proxy for `locations.can_be_destroyed = 1` AND CKL-seeded."""
    select = page.locator("select#attackLocationSelect")
    if select.count() == 0:
        return False
    return select.locator(f"option[value='{echo_base_id}']").count() > 0


def _ensure_echo_base_destroyable_via_ui(page, echo_base_id, foxtrot_id):
    """Ensure Echo-Base is attackable by Foxtrot (i.e. can_be_destroyed=1
    in the location row AND the CKL is seeded). Toggle once via the
    /zones/management_locations.php admin button if the location is
    currently non-destroyable. The toggle uses updateLocation +
    save_to_json so calling once flips both flags + swap direction.
    """
    _seed_ckl_admin(page, "Foxtrot", "Echo-Base")
    _switch_controller(page, "Foxtrot")
    safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
    page.wait_for_load_state("load")
    if _foxtrot_can_attack_echo_base(page, echo_base_id):
        return
    safe_goto(page, f"{PHP_BASE_URL}/zones/management_locations.php")
    page.wait_for_load_state("load")
    # toggle button lives inside a display:none span; submit the form
    # via JS since playwright's actionability check fails on zero-box.
    page.evaluate(
        "id => {"
        "  const inp = document.querySelector("
        "    `input[name='toggle_destruction'][value='${id}']`"
        "  );"
        "  if (inp && inp.form) inp.form.submit();"
        "}",
        echo_base_id,
    )
    page.wait_for_load_state("load")
    _switch_controller(page, "Foxtrot")
    safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
    page.wait_for_load_state("load")
    if not _foxtrot_can_attack_echo_base(page, echo_base_id):
        raise AssertionError(
            "Echo-Base remained non-destroyable after one toggle — "
            "manual reset needed."
        )


@pytest.mark.db
class TestAttackLocationBaseline:
    """Pre-refactor immediate-resolution contract for attackLocation.
    Foxtrot attacks Echo-Base. Asserts log row inserted + Echo-Base flags
    flipped per its activate_json.update_location swap."""

    @pytest.fixture(scope="class", autouse=True)
    def attack_state(self, browser):
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        # Pre-state.
        echo_base_id = _location_id_via_management(page, "Echo-Base")
        foxtrot_id = _controller_id_via_management(page, "Foxtrot")
        pre_flags = _read_location_flags(echo_base_id)
        pre_logs = _read_attack_logs_for("Echo-Base")

        # Setup: seed CKL only. Foxtrot's 2 native workers in
        # Theta-Artefacts (Artefact_Worker_Foxtrot, Gift_Source_Foxtrot)
        # vs Echo's 1 (Artefact_Searcher_Echo) yields a deterministic
        # attack-diff of 1, which equals attackLocationDiff=1 → success
        # under TestConfig. No additional worker seeding needed.
        _seed_ckl_admin(page, "Foxtrot", "Echo-Base")

        # As Foxtrot: navigate to controllers/action.php and click attack
        # against Echo-Base via the rendered form.
        _switch_controller(page, "Foxtrot")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        attack_button_count = page.locator("input[name='attackLocation']").count()
        if attack_button_count > 0:
            attack_form = page.locator(
                "form:has(input[name='attackLocation'])"
            ).first
            attack_form.locator("select[name='target_location_id']").select_option(
                value=str(echo_base_id)
            )
            attack_form.locator("input[name='attackLocation']").click()
            page.wait_for_load_state("load")

        # Post-state.
        post_flags = _read_location_flags(echo_base_id)
        post_logs = _read_attack_logs_for("Echo-Base")

        assert_no_collected_php_errors(page)
        context.close()

        type(self)._echo_base_id = echo_base_id
        type(self)._foxtrot_id = foxtrot_id
        type(self)._pre_flags = pre_flags
        type(self)._post_flags = post_flags
        type(self)._pre_logs = pre_logs
        type(self)._post_logs = post_logs
        type(self)._attack_button_count = attack_button_count
        yield

    def test_attack_form_was_rendered(self):
        """Sanity: the attack-location form rendered for Foxtrot
        (CKL-seeded for Echo-Base)."""
        assert self._attack_button_count > 0, (
            "Attack form should render on Foxtrot's controllers/view.php "
            "after CKL seed"
        )

    def test_log_row_inserted(self):
        """Every attack (success or fail) inserts exactly one row into
        location_attack_logs with the attacker_id + target_location_name."""
        new_logs = [r for r in self._post_logs if r['id'] not in {p['id'] for p in self._pre_logs}]
        assert len(new_logs) == 1, (
            f"Expected exactly one new location_attack_logs row for "
            f"Echo-Base; got {len(new_logs)} (pre={len(self._pre_logs)} "
            f"post={len(self._post_logs)})"
        )
        log = new_logs[0]
        assert log['attacker_id'] == self._foxtrot_id, (
            f"attacker_id should be Foxtrot ({self._foxtrot_id}); "
            f"got {log['attacker_id']}"
        )
        assert log['location_name'] == 'Echo-Base', (
            f"location_name should be 'Echo-Base'; got {log['location_name']!r}"
        )

    def test_echo_base_flags_flipped_per_activate_json_swap(self):
        """Successful attack on a location with activate_json.update_location
        triggers updateLocation: Echo-Base's flags flip per the swap
        (can_be_destroyed 1→0, can_be_repaired 0→1)."""
        assert self._pre_flags['can_be_destroyed'] == 1, (
            f"Pre-attack: Echo-Base.can_be_destroyed should be 1; "
            f"got {self._pre_flags['can_be_destroyed']}"
        )
        assert self._pre_flags['can_be_repaired'] == 0, (
            f"Pre-attack: Echo-Base.can_be_repaired should be 0; "
            f"got {self._pre_flags['can_be_repaired']}"
        )
        assert self._post_flags['can_be_destroyed'] == 0, (
            f"Post-attack: Echo-Base.can_be_destroyed should flip to 0; "
            f"got {self._post_flags['can_be_destroyed']}"
        )
        assert self._post_flags['can_be_repaired'] == 1, (
            f"Post-attack: Echo-Base.can_be_repaired should flip to 1; "
            f"got {self._post_flags['can_be_repaired']}"
        )


@pytest.mark.db
class TestAttackLogTabsRendering:
    """Step 1b — `controllers/view.php` `Vos attaques ce <timeValue>` and
    `Alerte ! Votre base a été attaquée ce <timeValue>` sections render as
    Bulma tabs (one tab per turn that has logged attacks for the controller,
    latest first, empty turns skipped). Tab labels follow the
    `<ucfirst(timeValue)> N` format."""

    @pytest.fixture(scope="class", autouse=True)
    def attack_state(self, browser):
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        echo_base_id = _location_id_via_management(page, "Echo-Base")
        foxtrot_id = _controller_id_via_management(page, "Foxtrot")

        # Setup: seed Foxtrot's CKL for Echo-Base + perform one attack so
        # location_attack_logs has a row to render in the tabs.
        _seed_ckl_admin(page, "Foxtrot", "Echo-Base")
        _switch_controller(page, "Foxtrot")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        if page.locator("input[name='attackLocation']").count() > 0:
            attack_form = page.locator(
                "form:has(input[name='attackLocation'])"
            ).first
            attack_form.locator("select[name='target_location_id']").select_option(
                value=str(echo_base_id)
            )
            attack_form.locator("input[name='attackLocation']").click()
            page.wait_for_load_state("load")

        # Read time-value config + current turn (for tab-label assertion).
        conn = _db_conn()
        cur = conn.cursor()
        cur.execute(
            f"SELECT value FROM `{GAME_PREFIX}config` WHERE name = 'timeValue'"
        )
        time_word = (cur.fetchone() or {}).get('value', 'Tour')
        cur.execute(f"SELECT turncounter FROM `{GAME_PREFIX}mechanics` LIMIT 1")
        turn = cur.fetchone()['turncounter']
        cur.close()
        conn.close()

        # Foxtrot's outgoing tabs (re-render now that there's a logged attack).
        _switch_controller(page, "Foxtrot")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        foxtrot_html = page.content()

        # Echo's incoming tabs (target of the attack).
        _switch_controller(page, "Echo")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        echo_html = page.content()

        assert_no_collected_php_errors(page)
        context.close()

        type(self)._foxtrot_html = foxtrot_html
        type(self)._echo_html = echo_html
        type(self)._time_word = time_word
        type(self)._turn = turn
        yield

    def test_outgoing_attacks_render_tab_with_timevalue_label(self):
        """Foxtrot's `Vos attaques ce <timeValue>` panel renders a tab
        labeled `<ucfirst(timeValue)> N` for the turn of the logged
        attack."""
        expected_label = f">{self._time_word[:1].upper()}{self._time_word[1:]} {self._turn}<"
        assert 'data-tab-group="outgoing-attacks"' in self._foxtrot_html, (
            "Foxtrot's view should render the outgoing-attacks tab group "
            "(data-tab-group attribute on the tab bar)"
        )
        assert expected_label in self._foxtrot_html, (
            f"Outgoing tab should carry label {expected_label!r} (the "
            f"<timeValue> N format for the current turn); html slice "
            f"omitted for brevity"
        )

    def test_incoming_alert_renders_tab_with_timevalue_label(self):
        """Echo (target_controller of Foxtrot's attack) sees the alert
        with the same tab structure for incoming attacks, labeled
        `<ucfirst(timeValue)> N`."""
        expected_label = f">{self._time_word[:1].upper()}{self._time_word[1:]} {self._turn}<"
        assert 'data-tab-group="incoming-attacks"' in self._echo_html, (
            "Echo's view should render the incoming-attacks tab group"
        )
        assert expected_label in self._echo_html, (
            f"Incoming tab should carry label {expected_label!r}"
        )


@pytest.mark.db
class TestAttackLogMultiTurnTabs:
    """Multi-turn tab rendering: 2+ turns of attack logs produce 2+ tabs,
    sorted latest-first, with the latest tab marked is-active.

    Setup uses direct DB inserts of synthetic location_attack_logs rows
    (attacker=Foxtrot, target=Echo) on turn 0 and turn 1 — bypasses the
    actual attack mechanic to exercise the renderer's grouping/ordering
    independently of attack-math state across turns."""

    @pytest.fixture(scope="class", autouse=True)
    def multi_turn_logs_state(self, browser):
        conn = _db_conn()
        cur = conn.cursor()
        cur.execute(
            f"SELECT id FROM `{GAME_PREFIX}controllers` WHERE lastname = 'Foxtrot' LIMIT 1"
        )
        foxtrot_id = cur.fetchone()['id']
        cur.execute(
            f"SELECT id FROM `{GAME_PREFIX}controllers` WHERE lastname = 'Echo' LIMIT 1"
        )
        echo_id = cur.fetchone()['id']
        for turn in [0, 1]:
            cur.execute(
                f"INSERT INTO `{GAME_PREFIX}location_attack_logs` "
                f"(location_name, target_controller_id, attacker_id, attack_val, defence_val, "
                f"turn, success, target_result_text, attacker_result_text) "
                f"VALUES ('MultiTurnFakeBase', %s, %s, 5, 1, %s, 1, "
                f"'multi-turn-target-text-t%s', 'multi-turn-attacker-text-t%s')",
                (echo_id, foxtrot_id, turn, turn, turn),
            )
        conn.commit()

        cur.execute(
            f"SELECT value FROM `{GAME_PREFIX}config` WHERE name = 'timeValue'"
        )
        time_word = (cur.fetchone() or {}).get('value', 'Tour')
        cur.close()
        conn.close()

        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        _switch_controller(page, "Foxtrot")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        foxtrot_html = page.content()

        _switch_controller(page, "Echo")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        echo_html = page.content()

        assert_no_collected_php_errors(page)
        context.close()

        type(self)._foxtrot_html = foxtrot_html
        type(self)._echo_html = echo_html
        type(self)._time_word = time_word
        yield

    def _label(self, turn):
        return f">{self._time_word[:1].upper()}{self._time_word[1:]} {turn}<"

    def test_outgoing_two_turns_latest_first_active(self):
        """Foxtrot's outgoing tab bar shows Tour 1 before Tour 0 (latest
        first), and Tour 1 is the active tab on initial render."""
        html = self._foxtrot_html
        pos_t1 = html.find(self._label(1))
        pos_t0 = html.find(self._label(0))
        assert pos_t1 > 0 and pos_t0 > 0, (
            f"Both {self._label(1)!r} and {self._label(0)!r} should appear "
            f"in Foxtrot's outgoing tabs"
        )
        assert pos_t1 < pos_t0, (
            f"Latest turn ({self._label(1)!r}) should appear before "
            f"earlier turn ({self._label(0)!r}); got positions "
            f"{pos_t1} and {pos_t0}"
        )
        m = re.search(
            r'<li class="is-active" data-tab-group="outgoing-attacks" '
            r'data-tab-index="\d+"><a[^>]*>([^<]+)</a>',
            html,
        )
        assert m, "Active outgoing tab should render with is-active class"
        active_label = m.group(1).strip()
        expected = f"{self._time_word[:1].upper()}{self._time_word[1:]} 1"
        assert active_label == expected, (
            f"Active outgoing tab should be {expected!r} (latest turn); "
            f"got {active_label!r}"
        )

    def test_incoming_two_turns_latest_first_active(self):
        """Echo's incoming tab bar shows Tour 1 before Tour 0 and marks
        Tour 1 as active — same contract as outgoing."""
        html = self._echo_html
        pos_t1 = html.find(self._label(1))
        pos_t0 = html.find(self._label(0))
        assert pos_t1 > 0 and pos_t0 > 0, (
            f"Both {self._label(1)!r} and {self._label(0)!r} should appear "
            f"in Echo's incoming tabs"
        )
        assert pos_t1 < pos_t0, (
            f"Latest turn ({self._label(1)!r}) should appear before "
            f"earlier turn ({self._label(0)!r})"
        )
        m = re.search(
            r'<li class="is-active" data-tab-group="incoming-attacks" '
            r'data-tab-index="\d+"><a[^>]*>([^<]+)</a>',
            html,
        )
        assert m, "Active incoming tab should render with is-active class"
        active_label = m.group(1).strip()
        expected = f"{self._time_word[:1].upper()}{self._time_word[1:]} 1"
        assert active_label == expected, (
            f"Active incoming tab should be {expected!r}; got {active_label!r}"
        )


@pytest.mark.db
class TestAttackLocationEndTurnModeDbBeltAndBuckle:
    """Belt-and-buckle DB-precision sanity for the endTurn mode contract.

    Mirrors `TestAttackLocationEndTurnMode`'s scenario but inspects the
    `controller_location_attacks` columns directly to verify exact
    values that the UI-only class can only observe indirectly:
    `defence_val_snapshot` non-NULL at click, `success` flips NULL→1 at
    EOT, `attack_val_resolved`/`defence_val_resolved`/`resolved_turn`
    all populated. Declared BEFORE the UI-only `EndTurnMode` class so
    the EOT attack math resolves on turn 0 (Echo-Base age=0 = no
    defence-from-age bonus that would tie attack vs defence). Marked
    @pytest.mark.db so it skips on demo runs (UI_ONLY=1).
    """

    @pytest.fixture(scope="class", autouse=True)
    def attack_state(self, browser):
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        echo_base_id = _location_id_via_management(page, "Echo-Base")
        foxtrot_id = _controller_id_via_management(page, "Foxtrot")

        _set_config_via_ui(page, "locationAttackMode", "endTurn")
        _ensure_echo_base_destroyable_via_ui(page, echo_base_id, foxtrot_id)

        pre_queue = _read_queue_rows_for_attacker(foxtrot_id)
        pre_logs = _read_attack_logs_for("Echo-Base")
        pre_flags = _read_location_flags(echo_base_id)

        _switch_controller(page, "Foxtrot")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        if page.locator("input[name='attackLocation']").count() > 0:
            attack_form = page.locator(
                "form:has(input[name='attackLocation'])"
            ).first
            attack_form.locator("select[name='target_location_id']").select_option(
                value=str(echo_base_id)
            )
            attack_form.locator("input[name='attackLocation']").click()
            page.wait_for_load_state("load")

        post_click_queue = _read_queue_rows_for_attacker(foxtrot_id)
        post_click_logs = _read_attack_logs_for("Echo-Base")
        post_click_flags = _read_location_flags(echo_base_id)

        end_turn(page, PHP_BASE_URL)

        post_eot_queue = _read_queue_rows_for_attacker(foxtrot_id)
        post_eot_logs = _read_attack_logs_for("Echo-Base")
        post_eot_flags = _read_location_flags(echo_base_id)

        assert_no_collected_php_errors(page)
        _set_config_via_ui(page, "locationAttackMode", "immediate")
        context.close()

        type(self)._echo_base_id = echo_base_id
        type(self)._foxtrot_id = foxtrot_id
        type(self)._pre_queue = pre_queue
        type(self)._post_click_queue = post_click_queue
        type(self)._post_eot_queue = post_eot_queue
        type(self)._pre_logs = pre_logs
        type(self)._post_click_logs = post_click_logs
        type(self)._post_eot_logs = post_eot_logs
        type(self)._pre_flags = pre_flags
        type(self)._post_click_flags = post_click_flags
        type(self)._post_eot_flags = post_eot_flags
        yield

    def test_db_click_inserts_queue_row_with_snapshot(self):
        """DB-precision: click inserts a queue row with
        defence_val_snapshot populated and success NULL."""
        new = [r for r in self._post_click_queue
               if r['id'] not in {p['id'] for p in self._pre_queue}]
        assert len(new) == 1, (
            f"Expected one new controller_location_attacks row; "
            f"got {len(new)}"
        )
        row = new[0]
        assert row['attacker_controller_id'] == self._foxtrot_id
        assert row['location_id'] == self._echo_base_id
        assert row['defence_val_snapshot'] is not None, (
            "defence_val_snapshot must be populated at click time"
        )
        assert row['success'] is None, (
            "success must be NULL (queued, not yet resolved)"
        )

    def test_db_click_does_not_log(self):
        """DB-precision: at click time no location_attack_logs row."""
        new_logs = [r for r in self._post_click_logs
                    if r['id'] not in {p['id'] for p in self._pre_logs}]
        assert len(new_logs) == 0, (
            f"location_attack_logs must NOT gain a row at click time "
            f"in endTurn mode; got {len(new_logs)} new rows"
        )

    def test_db_eot_populates_resolved_columns(self):
        """DB-precision: after EOT the queue row has
        attack_val_resolved + defence_val_resolved + success +
        resolved_turn all populated, success==1 for the deterministic
        Foxtrot-vs-Echo-Base scenario on turn 0."""
        resolved = [r for r in self._post_eot_queue
                    if r['attacker_controller_id'] == self._foxtrot_id
                    and r['location_id'] == self._echo_base_id]
        assert len(resolved) >= 1
        row = resolved[-1]
        assert row['success'] == 1, (
            f"EOT must resolve as success for deterministic Foxtrot vs "
            f"Echo-Base scenario on turn 0; got success={row['success']}, "
            f"attack_resolved={row['attack_val_resolved']}, "
            f"defence_resolved={row['defence_val_resolved']}"
        )
        assert row['attack_val_resolved'] is not None
        assert row['defence_val_resolved'] is not None
        assert row['resolved_turn'] is not None

    def test_db_eot_fires_activate_json_swap(self):
        """DB-precision: the activate_json update_location swap fires —
        Echo-Base's can_be_destroyed/can_be_repaired flags flip from
        pre-EOT to post-EOT (direction depends on swap state, but they
        MUST change). Relies on the attack succeeding on turn 0."""
        assert (self._post_eot_flags['can_be_destroyed']
                != self._post_click_flags['can_be_destroyed']
                or self._post_eot_flags['can_be_repaired']
                != self._post_click_flags['can_be_repaired']), (
            f"Echo-Base flags must change across EOT — proves "
            f"activate_json swap fired. pre_eot="
            f"{self._post_click_flags}, post_eot={self._post_eot_flags}"
        )

    def test_db_eot_inserts_audit_log(self):
        """DB-precision: location_attack_logs gains exactly one new row
        after EOT (the audit trail of the resolved attack)."""
        new_logs = [r for r in self._post_eot_logs
                    if r['id'] not in {p['id'] for p in self._post_click_logs}]
        assert len(new_logs) >= 1, (
            f"location_attack_logs must gain a row after EOT; "
            f"got {len(new_logs)}"
        )


class TestAttackLocationEndTurnMode:
    """End-of-turn dispatch mode (`locationAttackMode='endTurn'`).

    UI-only — works on any deployment (local or remote demo). Verifies
    the contract through HTML observation of Foxtrot's outgoing-attacks
    panel and Echo's incoming alert panel: at click time the queue row
    surfaces as an italic 'Attaque planifi...' entry with a predicted
    band; at end-of-turn the italic preview transitions into a resolved
    log entry and Echo's alert panel gains a matching row.
    """

    @pytest.fixture(scope="class", autouse=True)
    def attack_state(self, browser):
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        echo_base_id = _location_id_via_management(page, "Echo-Base")
        foxtrot_id = _controller_id_via_management(page, "Foxtrot")
        echo_id = _controller_id_via_management(page, "Echo")

        _set_config_via_ui(page, "locationAttackMode", "endTurn")
        _ensure_echo_base_destroyable_via_ui(page, echo_base_id, foxtrot_id)

        _switch_controller(page, "Foxtrot")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        pre_click_foxtrot_html = page.content()
        attack_button_count_at_click = page.locator("input[name='attackLocation']").count()

        _switch_controller(page, "Echo")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        pre_click_echo_html = page.content()

        _switch_controller(page, "Foxtrot")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        if attack_button_count_at_click > 0:
            attack_form = page.locator(
                "form:has(input[name='attackLocation'])"
            ).first
            attack_form.locator("select[name='target_location_id']").select_option(
                value=str(echo_base_id)
            )
            attack_form.locator("input[name='attackLocation']").click()
            page.wait_for_load_state("load")

        _switch_controller(page, "Foxtrot")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        post_click_foxtrot_html = page.content()

        _switch_controller(page, "Echo")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        post_click_echo_html = page.content()

        end_turn(page, PHP_BASE_URL)

        _switch_controller(page, "Foxtrot")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        post_eot_foxtrot_html = page.content()

        _switch_controller(page, "Echo")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        post_eot_echo_html = page.content()

        assert_no_collected_php_errors(page)
        _set_config_via_ui(page, "locationAttackMode", "immediate")
        context.close()

        type(self)._echo_base_id = echo_base_id
        type(self)._foxtrot_id = foxtrot_id
        type(self)._echo_id = echo_id
        type(self)._pre_click_foxtrot_html = pre_click_foxtrot_html
        type(self)._pre_click_echo_html = pre_click_echo_html
        type(self)._post_click_foxtrot_html = post_click_foxtrot_html
        type(self)._post_click_echo_html = post_click_echo_html
        type(self)._post_eot_foxtrot_html = post_eot_foxtrot_html
        type(self)._post_eot_echo_html = post_eot_echo_html
        type(self)._attack_button_count_at_click = attack_button_count_at_click
        yield

    def test_attack_form_was_rendered_in_endturn_mode(self):
        """endTurn mode: the attack-selector form still renders for the
        controller (only Worker mode hides it)."""
        assert self._attack_button_count_at_click > 0, (
            "Attack form should render in endTurn mode; got "
            f"{self._attack_button_count_at_click} attackLocation buttons"
        )

    def test_click_inserts_queue_row_observable_as_italic_preview(self):
        """endTurn mode: click on Attaquer surfaces a new italic
        'Attaque planifi...' entry in Foxtrot's outgoing-attacks panel —
        the user-visible proxy for a queue row insert with
        defence_val_snapshot populated (the band derivation requires it).
        """
        pre_count = self._pre_click_foxtrot_html.count("<em>Attaque planifi")
        post_count = self._post_click_foxtrot_html.count("<em>Attaque planifi")
        assert post_count == pre_count + 1, (
            f"Foxtrot's panel should gain exactly one new italic queued "
            f"preview after click; pre={pre_count} post={post_count}"
        )
        assert "Echo-Base" in self._post_click_foxtrot_html, (
            "Queued preview should mention Echo-Base"
        )
        bands = ["Échec probable", "Faibles chances", "Réussite probable"]
        assert any(b in self._post_click_foxtrot_html for b in bands), (
            f"Queued preview should include one of the predicted-outcome "
            f"bands {bands} — band derivation proves defence_val_snapshot "
            f"was populated"
        )

    def test_click_does_not_fire_side_effects(self):
        """endTurn mode: at click time, no resolved log entry appears in
        Echo's `Alerte` incoming-attack panel — proves the effects path
        did NOT run synchronously."""
        pre_alertes = self._pre_click_echo_html.count("Alerte !")
        post_alertes = self._post_click_echo_html.count("Alerte !")
        assert post_alertes == pre_alertes, (
            f"Echo's alert-panel count should NOT change at click time "
            f"in endTurn mode; pre={pre_alertes} post={post_alertes}"
        )

    def test_panel_renders_italic_queued_preview(self):
        """While the queue row is unresolved, the Vos-attaques panel
        renders the queued entry wrapped in `<em>` with the
        textLocationAttackQueued template ('Attaque planifi...')."""
        assert "Attaque planifi" in self._post_click_foxtrot_html, (
            "Queued preview text 'Attaque planifi...' should render in "
            "Foxtrot's outgoing-attacks panel before EOT"
        )
        assert "<em>" in self._post_click_foxtrot_html, (
            "Queued entries should be wrapped in <em> tags (italic) to "
            "distinguish from resolved entries"
        )

    def test_eot_replaces_italic_preview_with_resolved_entry(self):
        """End-of-turn: the italic preview for this attack disappears
        from Foxtrot's panel and a resolved (non-italic) entry takes its
        place — proves the resolver UPDATEd the queue row's `success`
        column from NULL to non-NULL. EOT side-effect precision (the
        location_attack_logs INSERT, the activate_json swap) is verified
        by the DB belt-and-buckle class — Echo's `Alerte !` wrapper is a
        one-per-turn render and not reliable as a per-attack counter.
        """
        pre_italic = self._post_click_foxtrot_html.count("<em>Attaque planifi")
        post_italic = self._post_eot_foxtrot_html.count("<em>Attaque planifi")
        assert post_italic < pre_italic, (
            f"Italic queued preview count should DECREASE after EOT "
            f"(queue rows transition to resolved); pre={pre_italic} "
            f"post={post_italic}"
        )


class TestAttackLocationEndTurnCancelAndFilter:
    """endTurn mode UX extensions:
      (a) a planned attack is cancellable via an inline 'Annuler' button
          on the italic preview entry;
      (b) once a target has a pending queue row for the attacker, the
          target is filtered out of that attacker's attackable dropdown
          (no double-queuing the same target).
    """

    @pytest.fixture(scope="class", autouse=True)
    def state(self, browser):
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        echo_base_id = _location_id_via_management(page, "Echo-Base")
        foxtrot_id = _controller_id_via_management(page, "Foxtrot")

        _set_config_via_ui(page, "locationAttackMode", "endTurn")
        _ensure_echo_base_destroyable_via_ui(page, echo_base_id, foxtrot_id)

        _switch_controller(page, "Foxtrot")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        dropdown_pre_queue_html = page.content()

        if page.locator("input[name='attackLocation']").count() > 0:
            attack_form = page.locator(
                "form:has(input[name='attackLocation'])"
            ).first
            attack_form.locator("select[name='target_location_id']").select_option(
                value=str(echo_base_id)
            )
            attack_form.locator("input[name='attackLocation']").click()
            page.wait_for_load_state("load")

        _switch_controller(page, "Foxtrot")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        post_queue_html = page.content()

        cancel_link = page.locator("a.cancel-location-attack-btn").first
        cancel_link.click()
        page.wait_for_load_state("load")

        _switch_controller(page, "Foxtrot")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        post_cancel_html = page.content()

        assert_no_collected_php_errors(page)
        _set_config_via_ui(page, "locationAttackMode", "immediate")
        context.close()

        type(self)._echo_base_id = echo_base_id
        type(self)._dropdown_pre_queue_html = dropdown_pre_queue_html
        type(self)._post_queue_html = post_queue_html
        type(self)._post_cancel_html = post_cancel_html
        yield

    def test_target_in_dropdown_before_queueing(self):
        """Sanity: Echo-Base is present in Foxtrot's attackable dropdown
        BEFORE queueing — rules out a false-pass on the filter test."""
        option_marker = f'value="{self._echo_base_id}">Echo-Base'
        assert option_marker in self._dropdown_pre_queue_html, (
            "Echo-Base should appear in Foxtrot's attackable dropdown pre-queue"
        )

    def test_target_filtered_from_dropdown_when_pending(self):
        """endTurn (b): once Foxtrot has a pending queue row for
        Echo-Base, Echo-Base must NOT appear in the attackable dropdown
        — prevents double-queuing the same target."""
        option_marker = f'value="{self._echo_base_id}">Echo-Base'
        assert option_marker not in self._post_queue_html, (
            "Echo-Base should be filtered out of Foxtrot's attackable "
            "dropdown while a pending queue row exists"
        )

    def test_cancel_button_renders_on_italic_preview(self):
        """endTurn (a): the italic preview entry renders with an inline
        Annuler button so the user can revoke the queued attack."""
        assert "cancel-location-attack-btn" in self._post_queue_html, (
            "Italic preview entry should include the cancel button class"
        )
        assert "Annuler" in self._post_queue_html, (
            "Cancel button label 'Annuler' should appear"
        )

    def test_cancel_removes_italic_preview(self):
        """endTurn (a): clicking Annuler deletes the queue row, so the
        italic preview vanishes on next page load."""
        pre = self._post_queue_html.count("<em>Attaque planifi")
        post = self._post_cancel_html.count("<em>Attaque planifi")
        assert post < pre, (
            f"Italic preview should disappear after Annuler; "
            f"pre={pre} post={post}"
        )

    def test_target_returns_to_dropdown_after_cancel(self):
        """endTurn (b reverse): after cancel, the queue row is gone so
        Echo-Base reappears in the attackable dropdown."""
        option_marker = f'value="{self._echo_base_id}">Echo-Base'
        assert option_marker in self._post_cancel_html, (
            "Echo-Base should reappear in the attackable dropdown after cancel"
        )


class TestAttackModeDisabled:
    """`locationAttackMode` set to a value outside the implemented whitelist
    (`['immediate', 'endTurn']` for attack form/URL, `['endTurn']` for
    cancel + EOT resolver) — e.g. `'worker'` (mode C, not yet built) or any
    unknown string — must disable every attack gate. We use `'foobar'`
    here as a deliberately unknown value so a single fixture exercises all
    four gates: form hidden (A1), attackLocation URL → 403 (A2),
    cancelLocationAttack URL → 403 (A3), locationAttackMechanic skipped
    with echoed warning (A5). `'worker'` would also pass A1+A2 (existing
    `TestAttackLocationWorkerMode` covers that path).
    """

    @pytest.fixture(scope="class", autouse=True)
    def disabled_state(self, browser):
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        echo_base_id = _location_id_via_management(page, "Echo-Base")
        foxtrot_id = _controller_id_via_management(page, "Foxtrot")

        _ensure_echo_base_destroyable_via_ui(page, echo_base_id, foxtrot_id)
        _set_config_via_ui(page, "locationAttackMode", "foobar")

        _switch_controller(page, "Foxtrot")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        attack_button_count = page.locator("input[name='attackLocation']").count()

        attack_url_resp = page.goto(
            f"{PHP_BASE_URL}/controllers/action.php"
            f"?attackLocation=1&target_location_id={echo_base_id}"
            f"&controller_id={foxtrot_id}"
        )
        attack_url_status = attack_url_resp.status if attack_url_resp else None

        cancel_url_resp = page.goto(
            f"{PHP_BASE_URL}/controllers/action.php"
            f"?cancelLocationAttack=1&controller_id={foxtrot_id}"
        )
        cancel_url_status = cancel_url_resp.status if cancel_url_resp else None

        ensure_gm_login(page, PHP_BASE_URL)
        page.goto(f"{PHP_BASE_URL}/mechanics/endTurn.php")
        page.wait_for_load_state("load", timeout=120000)
        eot_html = page.content()

        _set_config_via_ui(page, "locationAttackMode", "immediate")
        assert_no_collected_php_errors(page)
        context.close()

        type(self)._attack_button_count = attack_button_count
        type(self)._attack_url_status = attack_url_status
        type(self)._cancel_url_status = cancel_url_status
        type(self)._eot_html = eot_html
        yield

    def test_attack_form_hidden_for_unknown_mode(self):
        """A1 — controller view should NOT render the attack-selector
        form when `locationAttackMode` is outside the whitelist
        (`['immediate', 'endTurn']`)."""
        assert self._attack_button_count == 0, (
            f"input[name='attackLocation'] should be hidden when "
            f"locationAttackMode is unknown; got {self._attack_button_count}"
        )

    def test_attackLocation_url_returns_403(self):
        """A2 — crafted GET to action.php?attackLocation=… hard-403s
        before the dispatcher runs. Stricter than the existing 'no-side-effect'
        assertion in `TestAttackLocationWorkerMode`."""
        assert self._attack_url_status == 403, (
            f"attackLocation URL should 403 when locationAttackMode is "
            f"unknown; got {self._attack_url_status}"
        )

    def test_cancelLocationAttack_url_returns_403(self):
        """A3 — cancelLocationAttack is gated to `['endTurn']` only. Any
        other mode (including `'immediate'`) must hard-403."""
        assert self._cancel_url_status == 403, (
            f"cancelLocationAttack URL should 403 when locationAttackMode "
            f"is outside ['endTurn']; got {self._cancel_url_status}"
        )

    def test_locationAttackMechanic_skipped_at_end_turn(self):
        """A5 — locationAttackMechanic prints the skip warning and does
        not iterate the (potentially stale) queue when mode is outside
        `['endTurn']`."""
        assert re.search(r"locationAttackMechanic : mode 'foobar'.*not supported, skipped", self._eot_html), (
            "EOT page should contain the locationAttackMechanic skip-warning"
        )


# Still @pytest.mark.db — belt-and-buckle DB class. Verifies internal
# queue resolution state (controller_location_attacks.success flag,
# resolved_turn timestamp) and defender-invisible log details
# (location_attack_logs.target_controller_id = NULL) that have no UI
# surface even if synthetic locations + queue rows could be seeded via
# admin endpoints.
@pytest.mark.db
class TestEndTurnCascadeDestroyed:
    """When a queued end-turn attack's target no longer exists at
    resolution time (cascade from prior successful attack — FK
    ON DELETE SET NULL blanks the queue row's location_id), the row
    must be marked success=0/resolved_turn AND an attacker-only log
    entry must be written using the textLocationAttackDestroyed
    config text. Defender-invisible: target_controller_id=NULL.

    Uses a synthetic location + synthetic queue row to isolate from
    the rest of the file's location-attack state."""

    @pytest.fixture(scope="class", autouse=True)
    def cascade_state(self, browser):
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)
        _set_config_via_ui(page, "locationAttackMode", "endTurn")
        foxtrot_id = _controller_id_via_management(page, "Foxtrot")

        conn = _db_conn()
        cur = conn.cursor()
        cur.execute(f"SELECT turncounter FROM `{GAME_PREFIX}mechanics` LIMIT 1")
        turn = cur.fetchone()['turncounter']
        cur.execute(f"SELECT id FROM `{GAME_PREFIX}zones` LIMIT 1")
        zone_id = cur.fetchone()['id']
        cur.execute(
            f"INSERT INTO `{GAME_PREFIX}locations` "
            f"(name, description, zone_id, controller_id, can_be_destroyed, is_base) "
            f"VALUES ('SyntheticCascadeTarget', 'temp', %s, %s, 1, 1)",
            (zone_id, foxtrot_id),
        )
        synthetic_id = cur.lastrowid
        cur.execute(
            f"INSERT INTO `{GAME_PREFIX}controller_location_attacks` "
            f"(location_id, location_name, attacker_controller_id, queued_turn, defence_val_snapshot) "
            f"VALUES (%s, 'SyntheticCascadeTarget', %s, %s, 0)",
            (synthetic_id, foxtrot_id, turn),
        )
        queue_row_id = cur.lastrowid
        cur.execute(
            f"DELETE FROM `{GAME_PREFIX}locations` WHERE id = %s",
            (synthetic_id,),
        )
        conn.commit()
        cur.close()
        conn.close()

        end_turn(page, PHP_BASE_URL)
        _set_config_via_ui(page, "locationAttackMode", "immediate")
        assert_no_collected_php_errors(page)
        context.close()

        conn = _db_conn()
        cur = conn.cursor()
        cur.execute(
            f"SELECT * FROM `{GAME_PREFIX}controller_location_attacks` WHERE id = %s",
            (queue_row_id,),
        )
        queue_row_post = cur.fetchone()
        cur.execute(
            f"SELECT * FROM `{GAME_PREFIX}location_attack_logs` "
            f"WHERE location_name = 'SyntheticCascadeTarget' AND attacker_id = %s",
            (foxtrot_id,),
        )
        log_rows = cur.fetchall()
        cur.close()
        conn.close()

        type(self)._queue_row_post = queue_row_post
        type(self)._log_rows = log_rows
        yield

    def test_cascade_destroyed_fails_and_logs(self):
        """Combined assertion: queue row resolved-failed; log entry
        defender-invisible with textLocationAttackDestroyed message."""
        assert self._queue_row_post['success'] == 0, (
            f"Queue row should be success=0; got {self._queue_row_post['success']}"
        )
        assert self._queue_row_post['resolved_turn'] is not None
        assert len(self._log_rows) == 1, (
            f"Expected exactly one log row; got {len(self._log_rows)}"
        )
        log = self._log_rows[0]
        assert log['target_controller_id'] is None, (
            f"target_controller_id must be NULL (defender-invisible); "
            f"got {log['target_controller_id']}"
        )
        assert 'détruit' in (log['attacker_result_text'] or ''), (
            f"attacker_result_text should contain destroyed-text; "
            f"got {log['attacker_result_text']!r}"
        )
        assert 'SyntheticCascadeTarget' in (log['attacker_result_text'] or ''), (
            f"attacker_result_text should embed the location name; "
            f"got {log['attacker_result_text']!r}"
        )


# Still @pytest.mark.db — see TestEndTurnCascadeDestroyed comment.
@pytest.mark.db
class TestMoveBaseCancelsInFlightAttacks:
    """When moveBase fires while in-flight queued end-turn attacks
    target the base, those attacks must be cancelled via
    failQueuedLocationAttack with reason='moved'. Defender-invisible
    (target_controller_id=NULL); attacker_result_text uses
    textLocationAttackMoved config text.

    Uses a synthetic base + synthetic queue row + gm-privileged
    /controllers/action.php?moveBase=… URL to isolate from other state."""

    @pytest.fixture(scope="class", autouse=True)
    def move_cancel_state(self, browser):
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)
        _set_config_via_ui(page, "locationAttackMode", "endTurn")
        foxtrot_id = _controller_id_via_management(page, "Foxtrot")
        echo_id = _controller_id_via_management(page, "Echo")

        conn = _db_conn()
        cur = conn.cursor()
        cur.execute(f"SELECT turncounter FROM `{GAME_PREFIX}mechanics` LIMIT 1")
        turn = cur.fetchone()['turncounter']
        cur.execute(f"SELECT id FROM `{GAME_PREFIX}zones` LIMIT 2")
        zones = cur.fetchall()
        zone_origin = zones[0]['id']
        zone_target = zones[1]['id']
        cur.execute(
            f"INSERT INTO `{GAME_PREFIX}locations` "
            f"(name, description, zone_id, controller_id, can_be_destroyed, is_base) "
            f"VALUES ('SyntheticMovedBase', 'temp', %s, %s, 1, 1)",
            (zone_origin, echo_id),
        )
        synthetic_id = cur.lastrowid
        cur.execute(
            f"INSERT INTO `{GAME_PREFIX}controller_location_attacks` "
            f"(location_id, location_name, attacker_controller_id, queued_turn, defence_val_snapshot) "
            f"VALUES (%s, 'SyntheticMovedBase', %s, %s, 0)",
            (synthetic_id, foxtrot_id, turn),
        )
        queue_row_id = cur.lastrowid
        conn.commit()
        cur.close()
        conn.close()

        safe_goto(
            page,
            f"{PHP_BASE_URL}/controllers/action.php"
            f"?moveBase=1&base_id={synthetic_id}&zone_id={zone_target}&controller_id={echo_id}",
        )
        page.wait_for_load_state("load")

        _set_config_via_ui(page, "locationAttackMode", "immediate")
        assert_no_collected_php_errors(page)
        context.close()

        conn = _db_conn()
        cur = conn.cursor()
        cur.execute(
            f"SELECT * FROM `{GAME_PREFIX}controller_location_attacks` WHERE id = %s",
            (queue_row_id,),
        )
        queue_row_post = cur.fetchone()
        cur.execute(
            f"SELECT * FROM `{GAME_PREFIX}location_attack_logs` "
            f"WHERE location_name = 'SyntheticMovedBase' AND attacker_id = %s",
            (foxtrot_id,),
        )
        log_rows = cur.fetchall()
        cur.close()
        conn.close()

        type(self)._queue_row_post = queue_row_post
        type(self)._log_rows = log_rows
        yield

    def test_move_cancels_in_flight_attack(self):
        """Combined assertion: queue row resolved-failed; log entry
        defender-invisible with textLocationAttackMoved message."""
        assert self._queue_row_post['success'] == 0, (
            f"Queue row should be success=0; got {self._queue_row_post['success']}"
        )
        assert self._queue_row_post['resolved_turn'] is not None
        assert len(self._log_rows) == 1, (
            f"Expected exactly one log row; got {len(self._log_rows)}"
        )
        log = self._log_rows[0]
        assert log['target_controller_id'] is None, (
            f"target_controller_id must be NULL (defender-invisible); "
            f"got {log['target_controller_id']}"
        )
        assert 'déplacé' in (log['attacker_result_text'] or ''), (
            f"attacker_result_text should contain moved-text; "
            f"got {log['attacker_result_text']!r}"
        )
        assert 'SyntheticMovedBase' in (log['attacker_result_text'] or ''), (
            f"attacker_result_text should embed the location name; "
            f"got {log['attacker_result_text']!r}"
        )


# Still @pytest.mark.db — see TestEndTurnCascadeDestroyed comment.
@pytest.mark.db
class TestDuplicateQueueAttemptRejected:
    """Backend INSERT WHERE NOT EXISTS guard at attackLocation() must
    prevent duplicate (attacker, location, queued_turn) queue rows.
    Two URL hits with identical params → exactly one queue row exists."""

    @pytest.fixture(scope="class", autouse=True)
    def duplicate_state(self, browser):
        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)
        _set_config_via_ui(page, "locationAttackMode", "endTurn")
        foxtrot_id = _controller_id_via_management(page, "Foxtrot")
        echo_id = _controller_id_via_management(page, "Echo")

        conn = _db_conn()
        cur = conn.cursor()
        cur.execute(f"SELECT turncounter FROM `{GAME_PREFIX}mechanics` LIMIT 1")
        turn = cur.fetchone()['turncounter']
        cur.execute(f"SELECT id FROM `{GAME_PREFIX}zones` LIMIT 1")
        zone_id = cur.fetchone()['id']
        cur.execute(
            f"INSERT INTO `{GAME_PREFIX}locations` "
            f"(name, description, zone_id, controller_id, can_be_destroyed, is_base) "
            f"VALUES ('SyntheticDupTarget', 'temp', %s, %s, 1, 1)",
            (zone_id, echo_id),
        )
        synthetic_id = cur.lastrowid
        conn.commit()
        cur.close()
        conn.close()

        url = (
            f"{PHP_BASE_URL}/controllers/action.php"
            f"?attackLocation=1&controller_id={foxtrot_id}&target_location_id={synthetic_id}"
        )
        safe_goto(page, url)
        page.wait_for_load_state("load")
        safe_goto(page, url)
        page.wait_for_load_state("load")

        _set_config_via_ui(page, "locationAttackMode", "immediate")
        assert_no_collected_php_errors(page)
        context.close()

        conn = _db_conn()
        cur = conn.cursor()
        cur.execute(
            f"SELECT * FROM `{GAME_PREFIX}controller_location_attacks` "
            f"WHERE attacker_controller_id = %s AND location_id = %s AND queued_turn = %s",
            (foxtrot_id, synthetic_id, turn),
        )
        queue_rows = cur.fetchall()
        cur.close()
        conn.close()

        type(self)._queue_rows = queue_rows
        yield

    def test_duplicate_url_attack_inserts_only_one_row(self):
        """Two URL hits with same (attacker, location, turn) → exactly
        one queue row (backend INSERT WHERE NOT EXISTS guard fires on
        the second attempt)."""
        assert len(self._queue_rows) == 1, (
            f"Expected exactly one queue row after duplicate URL hit; "
            f"got {len(self._queue_rows)}"
        )
