"""Shared helpers for RPGConquestGame test suite.

Extracts exact-copy duplicated functions from individual test modules:
  - DB connection + worker/controller ID lookups
  - Login / logout page navigation
  - End-turn trigger (caller handles login)
  - Scenario load via admin UI (uses a fresh browser context)
  - Minimal-data replay (seeds gm, config rows, power_types, mechanics)

The DB_AVAILABLE flag is computed once at import time so test modules
can use it for `pytest.skip` guards without repeating the probe.
"""
import os
import re
import pymysql
from playwright.sync_api import Page

from conftest import (
    GAME_PREFIX, MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB,
    PHP_BASE_URL, SQL_DIR,
)


# ---------------------------------------------------------------------------
# DB availability probe (runs once at module import)
# ---------------------------------------------------------------------------

DB_AVAILABLE = False
try:
    _probe = pymysql.connect(
        host=MYSQL_HOST, port=MYSQL_PORT, user=MYSQL_USER,
        password=MYSQL_PASSWORD, database=MYSQL_DB, connect_timeout=3,
    )
    _probe.close()
    DB_AVAILABLE = True
except Exception:
    pass


# ---------------------------------------------------------------------------
# DB connection + lookup helpers
# ---------------------------------------------------------------------------

def get_db_connection():
    """Open a new pymysql connection with DictCursor + utf8mb4."""
    return pymysql.connect(
        host=MYSQL_HOST, port=MYSQL_PORT, user=MYSQL_USER,
        password=MYSQL_PASSWORD, database=MYSQL_DB,
        charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor,
    )


def load_minimal_data(prefix=None):
    """Replay var/mysql/minimalData.sql against the DB to reinstate the
    minimal invariants (gm user, default config rows, starting mechanics
    row, fixed power types). Safe to call anytime — all statements are
    idempotent via INSERT IGNORE / WHERE NOT EXISTS.

    Returns True on success, False if the DB is unreachable.
    """
    prefix = prefix or GAME_PREFIX
    minimal_path = os.path.join(SQL_DIR, "minimalData.sql")
    if not os.path.exists(minimal_path):
        return False
    try:
        sql = open(minimal_path, encoding="utf-8").read().replace("{prefix}", prefix)
        # Strip line comments (-- ...) so they don't confuse statement splitting
        sql = re.sub(r"--[^\n]*\n", "\n", sql)
        conn = pymysql.connect(
            host=MYSQL_HOST, port=MYSQL_PORT, user=MYSQL_USER,
            password=MYSQL_PASSWORD, database=MYSQL_DB,
            charset="utf8mb4", autocommit=True,
        )
        cursor = conn.cursor()
        for stmt in sql.split(";"):
            stmt = stmt.strip()
            if stmt:
                cursor.execute(stmt)
        conn.close()
        return True
    except pymysql.err.OperationalError:
        return False


# ---------------------------------------------------------------------------
# Page navigation helpers
# ---------------------------------------------------------------------------

def login_as(page: Page, base_url: str, username: str, password: str):
    """Navigate to the login form and submit the given credentials."""
    page.goto(f"{base_url}/connection/loginForm.php")
    page.wait_for_load_state("networkidle")
    page.locator("input[name='username']").fill(username)
    page.locator("input[name='passwd']").fill(password)
    page.locator("input[type='submit']").first.click()
    page.wait_for_load_state("networkidle")


def logout(page: Page, base_url: str):
    """Navigate to the logout endpoint."""
    page.goto(f"{base_url}/connection/logout.php")
    page.wait_for_load_state("networkidle")


def end_turn(page: Page, base_url: str = None):
    """Trigger end-of-turn. Page must already be logged in.

    Asserts no PHP warning or fatal error in the rendered response.
    """
    url = base_url or PHP_BASE_URL
    page.goto(f"{url}/mechanics/endTurn.php")
    page.wait_for_load_state("load", timeout=120000)
    html = page.content()
    assert "<b>Warning</b>" not in html, "PHP warning during end turn"
    assert "<b>Fatal error</b>" not in html, "PHP fatal error during end turn"


def load_scenario_via_admin(browser, base_url: str, scenario_name: str):
    """Login as gm in a fresh context and load the named scenario via admin.php.

    Uses its own browser context so it does not disturb the caller's page.
    """
    context = browser.new_context()
    page = context.new_page()
    login_as(page, base_url, "gm", "orga")
    page.goto(f"{base_url}/base/admin.php")
    page.wait_for_load_state("networkidle")
    page.locator("select[name='config_name']").select_option(scenario_name)
    page.locator("input[name='submit'][value='Submit']").click()
    page.wait_for_timeout(5000)
    page.wait_for_load_state("load", timeout=120000)
    context.close()


# ---------------------------------------------------------------------------
# UI-only ID-lookup helpers
#
# These scrape the rendered pages rather than querying the DB, so tests using
# them can run under UI_ONLY=1 against a remote deployment. All assume the
# caller is logged in as gm (they do NOT re-login).
# ---------------------------------------------------------------------------

def ui_controller_id(page: Page, lastname: str, base_url: str = None):
    """Return the controller id matching `lastname` by scraping the
    controllerSelect dropdown on accueil.php (gm-only view).

    Raises AssertionError if not found."""
    url = base_url or PHP_BASE_URL
    page.goto(f"{url}/base/accueil.php")
    page.wait_for_load_state("networkidle")
    options = page.locator("select#controllerSelect option").all()
    for opt in options:
        if lastname in (opt.inner_text() or ""):
            value = opt.get_attribute("value")
            if value:
                return int(value)
    raise AssertionError(
        f"Controller with lastname '{lastname}' not found in controllerSelect"
    )


def ui_all_workers(page: Page, base_url: str = None):
    """Scrape /workers/management_workers.php and return a list of dicts:
      [{id, controller_id, firstname, lastname, zone_name, action_choice}, ...]

    This is the single authoritative UI source for worker state — it lists
    EVERY worker (across all controllers) along with their current
    controller_id and action_choice, which is what we need to disambiguate
    captured/original vs trace rows."""
    url = base_url or PHP_BASE_URL
    page.goto(f"{url}/workers/management_workers.php")
    page.wait_for_load_state("load")
    rows = page.locator("div.management table tr").all()
    workers = []
    for row in rows[1:]:  # skip the header row
        cells = row.locator("td").all_inner_texts()
        if len(cells) < 4:
            continue
        # ID / CID cell looks like "17 / 3"
        id_cid = cells[0].strip().split("/")
        if len(id_cid) != 2:
            continue
        try:
            worker_id = int(id_cid[0].strip())
            controller_id = int(id_cid[1].strip())
        except ValueError:
            continue
        # Name cell: "firstname lastname (stats)" — split on "(" first
        name_stats = cells[1]
        name_part = name_stats.split("(")[0].strip()
        parts = name_part.split()
        firstname = parts[0] if parts else ""
        lastname = " ".join(parts[1:]) if len(parts) > 1 else ""
        workers.append({
            "id": worker_id,
            "controller_id": controller_id,
            "firstname": firstname,
            "lastname": lastname,
            "zone_name": cells[2].strip(),
            "action_choice": cells[3].strip(),
        })
    return workers


def ui_worker_id(page: Page, lastname: str, base_url: str = None,
                  prefer_non_trace: bool = True):
    """Return the worker id matching `lastname` via management_workers.php.

    When multiple workers share a lastname (post-capture: original +
    trace), prefer the one whose action_choice is NOT 'trace' unless
    prefer_non_trace is False. Raises AssertionError if none found."""
    matches = [w for w in ui_all_workers(page, base_url) if w["lastname"] == lastname]
    if not matches:
        raise AssertionError(f"No worker with lastname '{lastname}' found in management_workers view")
    if prefer_non_trace:
        non_trace = [w for w in matches if w["action_choice"] != "trace"]
        if non_trace:
            return non_trace[0]["id"]
    return matches[0]["id"]


def ui_worker_controller_id(page: Page, lastname: str, base_url: str = None,
                             prefer_non_trace: bool = True):
    """Return the current controller id of the worker matching `lastname`
    (non-trace preferred)."""
    matches = [w for w in ui_all_workers(page, base_url) if w["lastname"] == lastname]
    if not matches:
        raise AssertionError(f"No worker with lastname '{lastname}' found in management_workers view")
    if prefer_non_trace:
        non_trace = [w for w in matches if w["action_choice"] != "trace"]
        if non_trace:
            return non_trace[0]["controller_id"]
    return matches[0]["controller_id"]


def ui_all_zones(page: Page, base_url: str = None):
    """Scrape /zones/management_zones.php and return a list of dicts:
      [{id, name, claimer_name, holder_name}, ...]

    More stable than the zoneSelect dropdown on admin.php because this
    admin table renders id + name as plain <td> columns, and the
    claimer/holder as <select> with a `selected` option — so we can
    also extract the current controller names.
    """
    url = base_url or PHP_BASE_URL
    page.goto(f"{url}/zones/management_zones.php")
    page.wait_for_load_state("load")
    rows = page.locator("div.management table tr").all()
    zones = []
    for row in rows[1:]:  # skip header
        cells = row.locator("td").all()
        if len(cells) < 2:
            continue
        id_text = cells[0].inner_text().strip()
        try:
            zone_id = int(id_text)
        except ValueError:
            continue
        name = cells[1].inner_text().strip()
        # Pull the SELECTED option text from claimer and holder selects
        def _selected(cell):
            opts = cell.locator("option[selected]").all()
            if opts:
                return (opts[0].inner_text() or "").strip()
            return ""
        claimer = _selected(cells[2]) if len(cells) > 2 else ""
        holder = _selected(cells[3]) if len(cells) > 3 else ""
        zones.append({
            "id": zone_id,
            "name": name,
            "claimer_name": claimer,
            "holder_name": holder,
        })
    return zones


def ui_zone_id(page: Page, zone_name: str, base_url: str = None):
    """Return the zone id matching `zone_name` by scraping the admin
    zones management table at /zones/management_zones.php.

    Raises AssertionError if not found."""
    for zone in ui_all_zones(page, base_url):
        if zone["name"] == zone_name:
            return zone["id"]
    raise AssertionError(
        f"Zone with name '{zone_name}' not found in management_zones view"
    )


def _scrape_location_discovery_flags(page: Page, base_url: str = None):
    """Scrape /zones/management_locations.php into a nested dict:
      {location_name: {controller_lastname: {known: bool, secret: bool}}}

    The admin page renders one block per location with
    <li class="controller-discovery-flag"
        data-controller-name="X" data-known="true|false" data-secret="true|false">
    per controller. Stable attributes — no emoji/text matching needed.
    """
    url = base_url or PHP_BASE_URL
    page.goto(f"{url}/zones/management_locations.php")
    page.wait_for_load_state("load")
    result = {}
    blocks = page.locator("div.management div[style*='border']").all()
    if not blocks:
        raise AssertionError("No location blocks rendered on management_locations.php")
    for block in blocks:
        h3s = block.locator("h3").all()
        if not h3s:
            continue
        name = (h3s[0].inner_text() or "").split(" (discovery")[0].strip()
        flags = block.locator("li.controller-discovery-flag").all()
        for li in flags:
            ctrl = li.get_attribute("data-controller-name") or ""
            if not ctrl:
                continue
            result.setdefault(name, {})[ctrl] = {
                "known": li.get_attribute("data-known") == "true",
                "secret": li.get_attribute("data-secret") == "true",
            }
    return result


def ui_known_locations_for_controller(page: Page, controller_lastname: str, base_url: str = None):
    """Return the set of location names known by a controller.

    Reads data-known="true" from the structured li.controller-discovery-flag
    elements on /zones/management_locations.php."""
    data = _scrape_location_discovery_flags(page, base_url)
    return {
        loc for loc, ctrls in data.items()
        if ctrls.get(controller_lastname, {}).get("known")
    }


def ui_known_secret_locations_for_controller(page: Page, controller_lastname: str, base_url: str = None):
    """Return the set of location names for which this controller has
    discovered the SECRET (found_secret=1 in controller_known_locations).

    Reads data-secret="true" from li.controller-discovery-flag."""
    data = _scrape_location_discovery_flags(page, base_url)
    return {
        loc for loc, ctrls in data.items()
        if ctrls.get(controller_lastname, {}).get("secret")
    }


_STATS_RE = re.compile(
    r"j'ai\s*<strong>\s*(-?\d+)\s*</strong>\s*en\s*investigation\s*"
    r"et\s*<strong>\s*(-?\d+)/(-?\d+)\s*</strong>\s*en\s*attaque",
    re.IGNORECASE,
)


def ui_worker_stats(page: Page, lastname: str, base_url: str = None):
    """Scrape the worker's action.php page and return calculated combat stats.

    Reads the `life_report` string that mechanics/endTurn.php writes onto
    each worker_actions.report:
        "Ce tour j'ai <strong>3</strong> en investigation
         et <strong>3/3</strong> en attaque/défense."
    workers/view.php renders it under "Changements :" heading. We regex-
    extract (enquete_val, attack_val, defence_val).

    Raises AssertionError if the pattern isn't found (worker might be
    dead/captured — life_report only written for active agents).
    """
    url = base_url or PHP_BASE_URL
    # Switch session to the worker's controller so the page shows the report
    ctrl_id = ui_worker_controller_id(page, lastname, base_url=url)
    page.goto(f"{url}/base/accueil.php?controller_id={ctrl_id}&chosir=Choisir")
    page.wait_for_load_state("networkidle")
    wid = ui_worker_id(page, lastname, base_url=url)
    page.goto(f"{url}/workers/action.php?worker_id={wid}")
    page.wait_for_load_state("load")
    html = page.content()
    m = _STATS_RE.search(html)
    if not m:
        raise AssertionError(
            f"Worker '{lastname}' life_report stats not rendered on "
            f"workers/action.php (pattern not found)"
        )
    return {
        "enquete_val": int(m.group(1)),
        "attack_val": int(m.group(2)),
        "defence_val": int(m.group(3)),
    }


def ui_worker_count(page: Page, base_url: str = None):
    """Return total worker count via /workers/management_workers.php row count."""
    return len(ui_all_workers(page, base_url))


_TURN_RE = re.compile(r'(\d+)')


def ui_turn_counter(page: Page, base_url: str = None):
    """Return the current turncounter by scraping the page header.

    baseHTML renders `<div class="header">{gameTitle} : {timeValue} {turncounter} <br>...`.
    The first digit group in the header text is the turncounter (any
    subsequent digits belong to the optional controller info suffix).
    """
    url = base_url or PHP_BASE_URL
    page.goto(f"{url}/base/accueil.php")
    page.wait_for_load_state("networkidle")
    text = (page.locator("div.header").first.inner_text() or "").strip()
    m = _TURN_RE.search(text)
    if not m:
        raise AssertionError(f"No digits in header — could not parse turncounter from: {text!r}")
    return int(m.group(1))


def ui_zone_names(page: Page, base_url: str = None):
    """Return the set of all zone names via /zones/management_zones.php."""
    return {z["name"] for z in ui_all_zones(page, base_url)}


def ui_workers_by_lastname(page: Page, lastname: str, base_url: str = None):
    """Return ALL management_workers rows matching `lastname`.

    Useful for verifying that a captured worker appears twice (the
    original row moved to the captor's controller with action='captured'
    or 'prisoner', and the auto-created trace row on the original
    controller with action='trace')."""
    return [w for w in ui_all_workers(page, base_url) if w["lastname"] == lastname]


def ui_detected_enemies_of(page: Page, searcher_lastname: str, base_url: str = None):
    """Return the set of enemy worker lastnames that `searcher_lastname` has
    detected in its zone (from this controller's perspective).

    Scrapes `select#enemyWorkersSelect` on /workers/action.php?worker_id=N
    after switching the gm session to the searcher's current controller.
    The select is the UI exposure of controllers_known_enemies filtered by
    the worker's zone and the viewing controller.

    Option text from PHP is `'- %s '` where %s is the enemy's firstname+lastname.
    We take the last whitespace-separated token as lastname (works because
    TestConfig lastnames are single-word tokens like Finder_1, Bystander_1).

    Returns an empty set if the worker is not active (select not rendered)
    or has detected no enemies.
    """
    url = base_url or PHP_BASE_URL
    ctrl_id = ui_worker_controller_id(page, searcher_lastname, base_url=url)
    page.goto(f"{url}/base/accueil.php?controller_id={ctrl_id}&chosir=Choisir")
    page.wait_for_load_state("networkidle")
    wid = ui_worker_id(page, searcher_lastname, base_url=url)
    page.goto(f"{url}/workers/action.php?worker_id={wid}")
    page.wait_for_load_state("load")
    detected = set()
    options = page.locator("select#enemyWorkersSelect option").all()
    for opt in options:
        value = opt.get_attribute("value") or ""
        if not value.startswith("worker_"):
            continue  # skip network_N group labels
        text = (opt.inner_text() or "").strip().lstrip("-").strip()
        if not text:
            continue
        lastname = text.split()[-1]  # "firstname lastname" → lastname
        detected.add(lastname)
    return detected


def ui_power_options_by_type(page: Page, base_url: str = None):
    """Scrape admin.php's Create Perfect Agent form dropdowns and return
    a dict mapping power type to list of option labels (placeholder
    excluded).

    Keys match the power_types.name values: 'Hobby', 'Metier',
    'Discipline', 'Transformation'. The four <select> elements on the
    admin page are the authoritative UI enumeration of all powers
    linked to each type (via the link_power_type junction)."""
    url = base_url or PHP_BASE_URL
    page.goto(f"{url}/base/admin.php")
    page.wait_for_load_state("networkidle")
    type_map = {
        "Hobby": "select#power_hobby_id",
        "Metier": "select#power_metier_id",
        "Discipline": "select#disciplineSelect",
        "Transformation": "select#transformationSelect",
    }
    result = {}
    for type_name, selector in type_map.items():
        options = page.locator(f"{selector} option").all()
        labels = []
        for opt in options:
            value = opt.get_attribute("value") or ""
            if not value or not value.strip():
                continue  # skip placeholder (empty value)
            text = (opt.inner_text() or "").strip()
            # option text is like "Keen Eye (1, 0/0)" — strip the " (...)" suffix
            name = text.split(" (")[0].strip()
            labels.append(name)
        result[type_name] = labels
    return result


def ui_all_controllers(page: Page, base_url: str = None):
    """Return the set of all controller lastnames via
    /controllers/management.php.

    Reads tr.controller-row elements from the Controller Details admin
    table; each row carries data-controller-name=LastName.
    """
    url = base_url or PHP_BASE_URL
    page.goto(f"{url}/controllers/management.php")
    page.wait_for_load_state("load")
    rows = page.locator("tr.controller-row").all()
    return {
        (row.get_attribute("data-controller-name") or "").strip()
        for row in rows
        if (row.get_attribute("data-controller-name") or "").strip()
    }


def ui_controller_counters(page: Page, lastname: str, base_url: str = None):
    """Scrape /controllers/management.php for one controller's counters.

    Returns a dict matching the DB helper's keys:
      {turn_recruited_workers: int, turn_firstcome_workers: int,
       recruited_workers: int, can_build_base: bool, secret_controller: bool}

    Locator uses tr.controller-row[data-controller-name=X] and per-cell
    td[data-field=Y] — stable against text/emoji changes.
    Raises AssertionError if lastname not found."""
    url = base_url or PHP_BASE_URL
    page.goto(f"{url}/controllers/management.php")
    page.wait_for_load_state("load")
    row = page.locator(f'tr.controller-row[data-controller-name="{lastname}"]')
    if row.count() == 0:
        raise AssertionError(
            f"Controller '{lastname}' not found in controllers/management.php"
        )
    def _cell(field):
        return (row.locator(f'td[data-field="{field}"]').inner_text() or "").strip()
    def _as_int(s):
        try:
            return int(s)
        except (ValueError, TypeError):
            return 0
    return {
        "lastname": lastname,
        "secret_controller": "✔" in _cell("secret_controller"),
        "can_build_base": "✔" in _cell("can_build_base"),
        "recruited_workers": _as_int(_cell("recruited_workers")),
        "turn_recruited_workers": _as_int(_cell("turn_recruited_workers")),
        "turn_firstcome_workers": _as_int(_cell("turn_firstcome_workers")),
    }


def ui_faction_sections(page: Page, controller_lastname: str, base_url: str = None):
    """Return the 4 workers/viewAll.php sections for a given controller.

    Switches session to the controller via
    base/accueil.php?controller_id=X&chosir=Choisir, then loads
    workers/viewAll.php and classifies each rendered worker-short card
    by the h3 heading of its parent box:

      'live'      — "Nos Agents :"          (active, we primarily control)
      'doubles'   — "Nos Agents doubles :"  (active, we don't primarily control)
      'prisoners' — "Nos Prisonniers :"     (captured by us)
      'ancients'  — "Nos Anciens agents :"  (dead / our captured-by-others / traces)

    Returns {section_key: set(lastname, ...)} for all 4 keys (empty sets
    if a section has no workers or is not rendered).

    Lastname is extracted from the anchor text inside div.worker-short,
    which renders as "{firstname} {lastname}" (see showWorkerShort in
    workers/functions.php).
    """
    url = base_url or PHP_BASE_URL
    ctrl_id = ui_controller_id(page, controller_lastname, base_url=url)
    page.goto(f"{url}/base/accueil.php?controller_id={ctrl_id}&chosir=Choisir")
    page.wait_for_load_state("networkidle")
    page.goto(f"{url}/workers/viewAll.php")
    page.wait_for_load_state("load")

    heading_to_key = {
        'Nos Agents :': 'live',
        'Nos Agents doubles :': 'doubles',
        'Nos Prisonniers :': 'prisoners',
        'Nos Anciens agents :': 'ancients',
    }
    result = {'live': set(), 'doubles': set(), 'prisoners': set(), 'ancients': set()}
    for box in page.locator('div.box').all():
        h3_texts = box.locator('h3').all_inner_texts()
        key = None
        for ht in h3_texts:
            if ht.strip() in heading_to_key:
                key = heading_to_key[ht.strip()]
                break
        if key is None:
            continue
        for a in box.locator('div.worker-short a.has-text-weight-semibold').all():
            parts = (a.inner_text() or '').strip().split()
            if parts:
                result[key].add(parts[-1])
    return result
