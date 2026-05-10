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
    DB_AVAILABLE, load_minimal_data, safe_goto,
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
