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
