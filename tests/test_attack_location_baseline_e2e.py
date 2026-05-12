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


def _set_location_attack_mode(mode):
    conn = _db_conn()
    cur = conn.cursor()
    cur.execute(
        f"UPDATE `{GAME_PREFIX}config` SET value = %s "
        f"WHERE name = 'locationAttackMode'",
        (mode,),
    )
    conn.commit()
    cur.close()
    conn.close()


def _read_queue_rows_for_attacker(attacker_id):
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


def _reset_echo_base_pre_swap_flags(location_id):
    """Reset Echo-Base to its TestConfig pre-swap state: flags (1, 0)
    AND the original activate_json swap definition. Necessary because
    save_to_json=TRUE in the swap alternates direction on each call, so
    prior class attacks may have left the swap pointing at (1, 0)."""
    conn = _db_conn()
    cur = conn.cursor()
    original_activate_json = (
        '{"update_location":{"can_be_destroyed":0,'
        '"can_be_repaired":1,"save_to_json":"TRUE"}}'
    )
    cur.execute(
        f"UPDATE `{GAME_PREFIX}locations` "
        f"SET can_be_destroyed = 1, can_be_repaired = 0, "
        f"activate_json = %s WHERE id = %s",
        (original_activate_json, location_id),
    )
    conn.commit()
    cur.close()
    conn.close()


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
class TestAttackLocationEndTurnMode:
    """End-of-turn dispatch mode (`locationAttackMode='endTurn'`).

    Contract: click queues a row into controller_location_attacks with a
    defence_val_snapshot and no immediate side-effects; the Vos-attaques
    panel renders the queued entry in italic with the live-recomputed
    attack force; end-of-turn resolution populates resolved columns and
    fires the same effects path as immediate mode.
    """

    @pytest.fixture(scope="class", autouse=True)
    def attack_state(self, browser):
        _set_location_attack_mode('endTurn')

        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        echo_base_id = _location_id_via_management(page, "Echo-Base")
        foxtrot_id = _controller_id_via_management(page, "Foxtrot")

        # Earlier classes (Baseline, TabsRendering) trip Echo-Base's
        # activate_json swap so can_be_destroyed lands at 0, which
        # filters it out of Foxtrot's attackable dropdown. Reset to the
        # pre-swap (1, 0) before clicking so the form has a target.
        _reset_echo_base_pre_swap_flags(echo_base_id)

        pre_queue = _read_queue_rows_for_attacker(foxtrot_id)
        pre_logs = _read_attack_logs_for("Echo-Base")

        _seed_ckl_admin(page, "Foxtrot", "Echo-Base")
        _switch_controller(page, "Foxtrot")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")

        attack_button_count_at_click = page.locator("input[name='attackLocation']").count()
        if attack_button_count_at_click > 0:
            attack_form = page.locator(
                "form:has(input[name='attackLocation'])"
            ).first
            attack_form.locator("select[name='target_location_id']").select_option(
                value=str(echo_base_id)
            )
            attack_form.locator("input[name='attackLocation']").click()
            page.wait_for_load_state("load")

        pre_click_flags = _read_location_flags(echo_base_id)
        post_click_queue = _read_queue_rows_for_attacker(foxtrot_id)
        post_click_logs = _read_attack_logs_for("Echo-Base")

        _switch_controller(page, "Foxtrot")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        foxtrot_html_queued = page.content()

        _reset_echo_base_pre_swap_flags(echo_base_id)
        pre_eot_flags = _read_location_flags(echo_base_id)
        end_turn(page, PHP_BASE_URL)

        post_eot_flags = _read_location_flags(echo_base_id)
        post_eot_queue = _read_queue_rows_for_attacker(foxtrot_id)
        post_eot_logs = _read_attack_logs_for("Echo-Base")

        assert_no_collected_php_errors(page)
        context.close()

        type(self)._echo_base_id = echo_base_id
        type(self)._foxtrot_id = foxtrot_id
        type(self)._pre_queue = pre_queue
        type(self)._post_click_queue = post_click_queue
        type(self)._post_eot_queue = post_eot_queue
        type(self)._pre_logs = pre_logs
        type(self)._post_click_logs = post_click_logs
        type(self)._post_eot_logs = post_eot_logs
        type(self)._pre_click_flags = pre_click_flags
        type(self)._pre_eot_flags = pre_eot_flags
        type(self)._post_eot_flags = post_eot_flags
        type(self)._foxtrot_html_queued = foxtrot_html_queued
        type(self)._attack_button_count_at_click = attack_button_count_at_click
        yield

        _set_location_attack_mode('immediate')

    def test_attack_form_was_rendered_in_endturn_mode(self):
        """endTurn mode: the attack-selector form still renders for the
        controller (only Worker mode hides it)."""
        assert self._attack_button_count_at_click > 0, (
            "Attack form should render in endTurn mode; got "
            f"{self._attack_button_count_at_click} attackLocation buttons"
        )

    def test_click_inserts_queue_row_with_snapshot(self):
        """endTurn mode: click on Attaquer inserts exactly one row in
        controller_location_attacks with defence_val_snapshot populated
        and success NULL (queued, not yet resolved)."""
        new = [r for r in self._post_click_queue
               if r['id'] not in {p['id'] for p in self._pre_queue}]
        assert len(new) == 1, (
            f"Expected exactly one new controller_location_attacks row; "
            f"got {len(new)}"
        )
        row = new[0]
        assert row['attacker_controller_id'] == self._foxtrot_id
        assert row['location_id'] == self._echo_base_id
        assert row['defence_val_snapshot'] is not None, (
            "defence_val_snapshot should be populated at click time"
        )
        assert row['success'] is None, (
            "success should be NULL (queued, not yet resolved at click)"
        )

    def test_click_does_not_fire_side_effects(self):
        """endTurn mode: at click time, attackLocation returns after the
        queue insert WITHOUT running the effects path. Echo-Base's flags
        stay at whatever they were and location_attack_logs gains no row.
        """
        post_click_logs_ids = {r['id'] for r in self._post_click_logs}
        pre_logs_ids = {r['id'] for r in self._pre_logs}
        new_logs_at_click = post_click_logs_ids - pre_logs_ids
        assert len(new_logs_at_click) == 0, (
            f"location_attack_logs should NOT gain a row at click time "
            f"in endTurn mode; got {len(new_logs_at_click)} new rows"
        )

    def test_panel_renders_italic_queued_preview(self):
        """While the queue row is unresolved (success IS NULL), the
        Vos-attaques panel renders the queued entry wrapped in `<em>`
        with the textLocationAttackQueued template ('Attaque planifi...')."""
        assert "Attaque planifi" in self._foxtrot_html_queued, (
            "Queued preview text 'Attaque planifi...' should render in "
            "Foxtrot's outgoing-attacks panel before EOT"
        )
        assert "<em>" in self._foxtrot_html_queued, (
            "Queued entries should be wrapped in <em> tags (italic) to "
            "distinguish from resolved entries"
        )

    def test_eot_populates_resolved_columns(self):
        """End-of-turn: the queue row gets attack_val_resolved +
        defence_val_resolved + success + resolved_turn populated, marking
        it as fully resolved."""
        resolved = [r for r in self._post_eot_queue
                    if r['attacker_controller_id'] == self._foxtrot_id
                    and r['location_id'] == self._echo_base_id]
        assert len(resolved) >= 1, (
            "Should have at least one resolved queue row for Foxtrot vs "
            "Echo-Base after EOT"
        )
        row = resolved[-1]
        assert row['success'] is not None, (
            "success should be populated after end-of-turn resolution"
        )
        assert row['attack_val_resolved'] is not None
        assert row['defence_val_resolved'] is not None
        assert row['resolved_turn'] is not None

    def test_eot_fires_side_effects(self):
        """End-of-turn: the shared resolveLocationAttackEffects helper
        runs. Echo-Base's activate_json swap flips flags from the reset
        (1, 0) to the post-swap (0, 1), and a location_attack_logs row
        gets inserted as the audit trail."""
        resolved = [r for r in self._post_eot_queue
                    if r['attacker_controller_id'] == self._foxtrot_id
                    and r['location_id'] == self._echo_base_id]
        row = resolved[-1] if resolved else {}
        assert row.get('success') == 1, (
            f"EOT resolution: Foxtrot vs Echo-Base should succeed "
            f"(deterministic math from TestConfig); got success="
            f"{row.get('success')}, attack_resolved="
            f"{row.get('attack_val_resolved')}, defence_resolved="
            f"{row.get('defence_val_resolved')}"
        )
        assert self._pre_eot_flags['can_be_destroyed'] == 1, (
            "Sanity: pre-EOT reset should leave can_be_destroyed=1"
        )
        assert self._pre_eot_flags['can_be_repaired'] == 0, (
            "Sanity: pre-EOT reset should leave can_be_repaired=0"
        )
        assert self._post_eot_flags['can_be_destroyed'] == 0, (
            f"Post-EOT: can_be_destroyed should flip to 0; "
            f"got {self._post_eot_flags['can_be_destroyed']}"
        )
        assert self._post_eot_flags['can_be_repaired'] == 1, (
            f"Post-EOT: can_be_repaired should flip to 1; "
            f"got {self._post_eot_flags['can_be_repaired']}"
        )
        new_logs = [r for r in self._post_eot_logs
                    if r['id'] not in {p['id'] for p in self._pre_logs}]
        assert len(new_logs) >= 1, (
            f"location_attack_logs should gain at least one row after "
            f"EOT in endTurn mode; got {len(new_logs)} new rows"
        )


@pytest.mark.db
class TestAttackLocationWorkerMode:
    """Worker mode (`locationAttackMode='worker'`).

    Contract: controller-page attack-selector form is HIDDEN. Backend
    rejects any direct GET to action.php?attackLocation= (defence in
    depth) — no queue row inserted, no side-effects fire.
    """

    @pytest.fixture(scope="class", autouse=True)
    def worker_state(self, browser):
        _set_location_attack_mode('worker')

        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)

        echo_base_id = _location_id_via_management(page, "Echo-Base")
        foxtrot_id = _controller_id_via_management(page, "Foxtrot")

        # Reset Echo-Base flags so a viable attackable target exists —
        # rules out a false-pass where the form is empty because no
        # destroyable locations are known (we want the test to prove the
        # MODE hides the form, not the lack of targets).
        _reset_echo_base_pre_swap_flags(echo_base_id)

        pre_queue = _read_queue_rows_for_attacker(foxtrot_id)
        pre_logs = _read_attack_logs_for("Echo-Base")

        _seed_ckl_admin(page, "Foxtrot", "Echo-Base")
        _switch_controller(page, "Foxtrot")
        safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
        page.wait_for_load_state("load")
        attack_button_count = page.locator("input[name='attackLocation']").count()

        safe_goto(
            page,
            f"{PHP_BASE_URL}/controllers/action.php"
            f"?attackLocation=1&target_location_id={echo_base_id}"
        )
        page.wait_for_load_state("load")

        post_get_queue = _read_queue_rows_for_attacker(foxtrot_id)
        post_get_logs = _read_attack_logs_for("Echo-Base")

        assert_no_collected_php_errors(page)
        context.close()

        type(self)._echo_base_id = echo_base_id
        type(self)._foxtrot_id = foxtrot_id
        type(self)._attack_button_count = attack_button_count
        type(self)._pre_queue = pre_queue
        type(self)._post_get_queue = post_get_queue
        type(self)._pre_logs = pre_logs
        type(self)._post_get_logs = post_get_logs
        yield

        _set_location_attack_mode('immediate')

    def test_attack_selector_hidden(self):
        """Controller view should NOT render the attack-selector form
        when locationAttackMode='worker' — UI gate at view.php."""
        assert self._attack_button_count == 0, (
            "Attack form should be hidden in worker mode; got "
            f"{self._attack_button_count} attackLocation buttons"
        )

    def test_crafted_get_does_not_queue(self):
        """Crafted GET to action.php?attackLocation= must be rejected by
        attackLocation()'s mode-check (defence in depth). No new queue
        row should appear in controller_location_attacks."""
        pre_ids = {r['id'] for r in self._pre_queue}
        new = [r for r in self._post_get_queue if r['id'] not in pre_ids]
        assert len(new) == 0, (
            f"Crafted GET in worker mode should NOT insert a queue row; "
            f"got {len(new)} new rows"
        )

    def test_crafted_get_does_not_log(self):
        """Crafted GET must not produce a location_attack_logs row
        either (no effects path runs)."""
        pre_ids = {r['id'] for r in self._pre_logs}
        new = [r for r in self._post_get_logs if r['id'] not in pre_ids]
        assert len(new) == 0, (
            f"Crafted GET in worker mode should NOT insert an attack "
            f"log row; got {len(new)} new rows"
        )
