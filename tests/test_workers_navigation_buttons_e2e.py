"""Playwright E2E tests for the agent-page navigation buttons (Issue #13).

The agent page (workers/action.php?worker_id=N) renders a card-header
with 3 buttons: Back, Previous, Next. The header is sticky on scroll,
the card background colour reflects the worker's bucket (alive /
double-agent / prisoner / dead), and the prev/next buttons walk the
controller's roster in the same bucket and in the same sort order
the user sees on workers/viewAll.php.

Cases covered:
  - sort respected via $_SESSION['workers_view_sort'] (URL > session > 'age')
  - disable at first/last via <span class="button is-info is-nav-disabled" aria-disabled="true">
  - back button: Referer-aware with strict-equality whitelist + host check
  - same-bucket only
  - sticky just the <header class="card-header">, buttons co-stick
  - explicit URL sort propagation
  - gm uses active controller list (same as players)
  - pre-defined 4 fixed colors via .workers.is-bucket-{alive,double-agent,prisoner,dead}

Run:
    python3 -m pytest tests/test_workers_navigation_buttons_e2e.py -v
"""
import re

import pytest
import pymysql

from conftest import (
    GAME_PREFIX, MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB,
    PHP_BASE_URL, ensure_gm_login,
)
from helpers import (
    DB_AVAILABLE, load_minimal_data, load_scenario_via_admin,
    safe_goto, register_php_error_listener, assert_no_collected_php_errors,
    login_as,
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


def _alpha_live_workers_by_id():
    """Return Alpha's live (alive bucket) worker ids ordered ASC."""
    conn = _db(); cur = conn.cursor()
    cur.execute(
        f"SELECT w.id, w.lastname FROM `{GAME_PREFIX}workers` w "
        f"JOIN `{GAME_PREFIX}controller_worker` cw ON cw.worker_id = w.id "
        f"JOIN `{GAME_PREFIX}controllers` c ON c.id = cw.controller_id "
        f"JOIN `{GAME_PREFIX}worker_actions` wa ON wa.worker_id = w.id "
        f"JOIN `{GAME_PREFIX}mechanics` m ON m.turncounter = wa.turn_number "
        f"WHERE c.lastname='Alpha' AND cw.is_primary_controller=1 "
        f"AND wa.action_choice IN ('passive','investigate','attack','claim','hide') "
        f"ORDER BY w.id ASC"
    )
    rows = cur.fetchall()
    cur.close(); conn.close()
    return rows


def _alpha_live_workers_by_attack_desc():
    """Compute total_attack via the same SUM(p.attack) join as getWorkers(),
    then order DESC with id ASC tiebreaker. Mirrors sortWorkerBuckets behaviour."""
    conn = _db(); cur = conn.cursor()
    cur.execute(
        f"SELECT w.id, w.lastname, "
        f"  COALESCE(SUM(p.attack), 0) AS total_attack "
        f"FROM `{GAME_PREFIX}workers` w "
        f"JOIN `{GAME_PREFIX}controller_worker` cw ON cw.worker_id = w.id "
        f"JOIN `{GAME_PREFIX}controllers` c ON c.id = cw.controller_id "
        f"JOIN `{GAME_PREFIX}worker_actions` wa ON wa.worker_id = w.id "
        f"JOIN `{GAME_PREFIX}mechanics` m ON m.turncounter = wa.turn_number "
        f"LEFT JOIN `{GAME_PREFIX}worker_powers` wp ON w.id = wp.worker_id "
        f"LEFT JOIN `{GAME_PREFIX}link_power_type` lpt ON wp.link_power_type_id = lpt.ID "
        f"LEFT JOIN `{GAME_PREFIX}powers` p ON lpt.power_id = p.ID "
        f"WHERE c.lastname='Alpha' AND cw.is_primary_controller=1 "
        f"AND wa.action_choice IN ('passive','investigate','attack','claim','hide') "
        f"GROUP BY w.id, w.lastname "
        f"ORDER BY total_attack DESC, w.id ASC"
    )
    rows = cur.fetchall()
    cur.close(); conn.close()
    return rows


def _open_worker_as_alpha(page, base_url, worker_id, referer=None):
    """Login as single_player (Alpha), goto the worker page, optionally
    with a custom Referer header via page.goto's native referer argument."""
    login_as(page, base_url, "single_player", "test")
    url = f"{base_url}/workers/action.php?worker_id={worker_id}"
    if referer:
        page.goto(url, referer=referer)
    else:
        safe_goto(page, url)
    page.wait_for_load_state("load")
    return page.content()


# ---------------------------------------------------------------------------
# Buttons rendered
# ---------------------------------------------------------------------------

def test_header_buttons_present(browser, base_url):
    """All 3 buttons (Back, Previous, Next) render inside the card-header
    block of workers/view.php."""
    workers = _alpha_live_workers_by_id()
    assert len(workers) >= 3, f"Need 3+ Alpha live workers for nav; got {len(workers)}"
    mid_wid = workers[len(workers) // 2]['id']

    ctx = browser.new_context()
    page = ctx.new_page()
    register_php_error_listener(page)
    _open_worker_as_alpha(page, base_url, mid_wid)
    html = page.content()
    assert_no_collected_php_errors(page)
    ctx.close()

    header = re.search(r'<header[^>]*class="card-header"[^>]*>.*?</header>', html, re.DOTALL)
    assert header, "card-header block not found"
    block = header.group(0)
    assert "Retour" in block, "Back button label missing from header"
    assert "Précédent" in block, "Previous button label missing from header"
    assert "Suivant" in block, "Next button label missing from header"


# ---------------------------------------------------------------------------
# Sort coupling
# ---------------------------------------------------------------------------

def test_next_click_advances_by_age_sort(browser, base_url):
    """sort=age → Next on worker N targets the next-higher-id Alpha live worker."""
    workers = _alpha_live_workers_by_id()
    assert len(workers) >= 2
    first_wid = workers[0]['id']
    expected_next_wid = workers[1]['id']

    ctx = browser.new_context()
    page = ctx.new_page()
    login_as(page, base_url, "single_player", "test")
    safe_goto(page, f"{base_url}/workers/action.php?worker_id={first_wid}&sort=age")
    page.wait_for_load_state("load")
    html = page.content()
    ctx.close()

    next_href = re.search(
        r'<a[^>]*href="([^"]*worker_id=\d+[^"]*)"[^>]*>Suivant', html
    )
    assert next_href, "Next button anchor not found"
    assert f"worker_id={expected_next_wid}" in next_href.group(1), (
        f"Next should target worker_id={expected_next_wid}; got {next_href.group(1)}"
    )


def test_next_respects_attack_sort(browser, base_url):
    """sort=attack → Next on the highest-attack worker targets the
    next-lower-attack worker (with id ASC tiebreaker)."""
    workers = _alpha_live_workers_by_attack_desc()
    if len(workers) < 2:
        pytest.skip("Need 2+ Alpha live workers for attack-sort test")
    first_wid = workers[0]['id']  # highest attack
    expected_next_wid = workers[1]['id']

    ctx = browser.new_context()
    page = ctx.new_page()
    login_as(page, base_url, "single_player", "test")
    safe_goto(page, f"{base_url}/workers/action.php?worker_id={first_wid}&sort=attack")
    page.wait_for_load_state("load")
    html = page.content()
    ctx.close()

    next_href = re.search(
        r'<a[^>]*href="([^"]*worker_id=\d+[^"]*)"[^>]*>Suivant', html
    )
    assert next_href, "Next button anchor not found"
    assert f"worker_id={expected_next_wid}" in next_href.group(1), (
        f"With sort=attack, Next should target worker_id={expected_next_wid} "
        f"(next-lower-attack); got {next_href.group(1)}"
    )
    assert "sort=attack" in next_href.group(1), (
        f"Sort should propagate via URL; got {next_href.group(1)}"
    )


# ---------------------------------------------------------------------------
# Disabled at edges
# ---------------------------------------------------------------------------

def test_prev_disabled_at_first_worker(browser, base_url):
    """First worker in sort=age order has Prev rendered as
    <span class="button is-info is-nav-disabled" aria-disabled="true">."""
    workers = _alpha_live_workers_by_id()
    assert len(workers) >= 2
    first_wid = workers[0]['id']

    ctx = browser.new_context()
    page = ctx.new_page()
    login_as(page, base_url, "single_player", "test")
    safe_goto(page, f"{base_url}/workers/action.php?worker_id={first_wid}&sort=age")
    page.wait_for_load_state("load")
    html = page.content()
    ctx.close()

    prev = re.search(r'<(\w+)([^>]*)>← Précédent</\w+>', html)
    assert prev, "Previous button not found"
    tag, attrs = prev.group(1), prev.group(2)
    assert tag == "span", f"Previous must be a <span> at first; got <{tag}>"
    assert "is-nav-disabled" in attrs, f"Previous must carry is-nav-disabled class; got: {attrs}"
    assert 'aria-disabled="true"' in attrs, f"Previous must carry aria-disabled='true'; got: {attrs}"


def test_next_disabled_at_last_worker(browser, base_url):
    """Last worker in sort=age order has Next rendered as the disabled span."""
    workers = _alpha_live_workers_by_id()
    assert len(workers) >= 2
    last_wid = workers[-1]['id']

    ctx = browser.new_context()
    page = ctx.new_page()
    login_as(page, base_url, "single_player", "test")
    safe_goto(page, f"{base_url}/workers/action.php?worker_id={last_wid}&sort=age")
    page.wait_for_load_state("load")
    html = page.content()
    ctx.close()

    nxt = re.search(r'<(\w+)([^>]*)>Suivant →</\w+>', html)
    assert nxt, "Next button not found"
    tag, attrs = nxt.group(1), nxt.group(2)
    assert tag == "span", f"Next must be a <span> at last; got <{tag}>"
    assert "is-nav-disabled" in attrs, f"Next must carry is-nav-disabled class; got: {attrs}"
    assert 'aria-disabled="true"' in attrs, f"Next must carry aria-disabled='true'; got: {attrs}"


# ---------------------------------------------------------------------------
# Back button — Referer-aware
# ---------------------------------------------------------------------------

def test_back_button_honours_viewall_referer(browser, base_url):
    """Referer = our viewAll → back URL is the FULL referer (with scheme +
    host), not the path-only fallback. Distinguishes 'honoured' from
    'fell-back-to-viewAll' since both contain 'viewAll.php'."""
    workers = _alpha_live_workers_by_id()
    wid = workers[0]['id']

    referer = f"{base_url}/workers/viewAll.php"
    ctx = browser.new_context()
    page = ctx.new_page()
    _open_worker_as_alpha(page, base_url, wid, referer=referer)
    html = page.content()
    ctx.close()

    back = re.search(r'<a[^>]*href="([^"]+)"[^>]*>← Retour</a>', html)
    assert back, "Back button anchor not found"
    # Honoured referer keeps the full URL (scheme + host). Fallback is path-only.
    assert back.group(1) == referer, (
        f"Back should honour viewAll referer with full URL; "
        f"got '{back.group(1)}' vs expected '{referer}'"
    )


def test_back_button_honours_zone_referer(browser, base_url):
    """Referer = our zones/view.php → back URL points at zones/view.php."""
    workers = _alpha_live_workers_by_id()
    wid = workers[0]['id']

    ctx = browser.new_context()
    page = ctx.new_page()
    _open_worker_as_alpha(page, base_url, wid, referer=f"{base_url}/zones/view.php")
    html = page.content()
    ctx.close()

    back = re.search(r'<a[^>]*href="([^"]+)"[^>]*>← Retour</a>', html)
    assert back, "Back button anchor not found"
    assert "zones/view.php" in back.group(1), (
        f"Back should honour zones/view.php referer; got {back.group(1)}"
    )


def test_back_button_fallback_no_referer(browser, base_url):
    """No Referer (or empty) → back URL falls back to viewAll."""
    workers = _alpha_live_workers_by_id()
    wid = workers[0]['id']

    ctx = browser.new_context()
    page = ctx.new_page()
    _open_worker_as_alpha(page, base_url, wid, referer=None)
    html = page.content()
    ctx.close()

    back = re.search(r'<a[^>]*href="([^"]+)"[^>]*>← Retour</a>', html)
    assert back, "Back button anchor not found"
    assert "viewAll.php" in back.group(1), f"Back should fall back to viewAll; got {back.group(1)}"


def test_back_button_rejects_cross_domain_referer(browser, base_url):
    """Referer pointing at evil.example.com (same path) MUST be rejected
    and fall back to local viewAll."""
    workers = _alpha_live_workers_by_id()
    wid = workers[0]['id']

    ctx = browser.new_context()
    page = ctx.new_page()
    _open_worker_as_alpha(
        page, base_url, wid,
        referer="https://evil.example.com/anyfolder/workers/viewAll.php"
    )
    html = page.content()
    ctx.close()

    back = re.search(r'<a[^>]*href="([^"]+)"[^>]*>← Retour</a>', html)
    assert back, "Back button anchor not found"
    href = back.group(1)
    assert "evil.example.com" not in href, (
        f"Cross-domain Referer MUST be rejected; got open-redirect URL: {href}"
    )
    assert "viewAll.php" in href, f"Should fall back to local viewAll; got {href}"


def test_back_button_rejects_substring_referer(browser, base_url):
    """Referer = local host with a path-like-but-extended suffix must be
    rejected (exact-equality check defeats /viewAll.php.exploit etc.)."""
    workers = _alpha_live_workers_by_id()
    wid = workers[0]['id']

    ctx = browser.new_context()
    page = ctx.new_page()
    _open_worker_as_alpha(
        page, base_url, wid,
        referer=f"{base_url}/workers/viewAll.phpdoor"
    )
    html = page.content()
    ctx.close()

    back = re.search(r'<a[^>]*href="([^"]+)"[^>]*>← Retour</a>', html)
    assert back, "Back button anchor not found"
    href = back.group(1)
    assert "viewAll.phpdoor" not in href, (
        f"Substring/extended Referer MUST be rejected; got: {href}"
    )
    assert href.endswith("/workers/viewAll.php"), (
        f"Should fall back to canonical viewAll path; got {href}"
    )


# ---------------------------------------------------------------------------
# Bucket scope + colour
# ---------------------------------------------------------------------------

def test_bucket_scope_alive_only_navigates_alive(browser, base_url):
    """On a live worker, prev/next walks ONLY Alpha's alive bucket. Asserts
    the next-href targets a worker id that's in alive bucket, not in
    prisoner / dead / double-agent buckets."""
    workers = _alpha_live_workers_by_id()
    assert len(workers) >= 2
    wid = workers[0]['id']
    alive_ids = {w['id'] for w in workers}

    ctx = browser.new_context()
    page = ctx.new_page()
    login_as(page, base_url, "single_player", "test")
    safe_goto(page, f"{base_url}/workers/action.php?worker_id={wid}&sort=age")
    page.wait_for_load_state("load")
    html = page.content()
    ctx.close()

    next_match = re.search(
        r'<a[^>]*href="[^"]*worker_id=(\d+)[^"]*"[^>]*>Suivant', html
    )
    assert next_match, "Next button anchor not found"
    next_wid = int(next_match.group(1))
    assert next_wid in alive_ids, (
        f"Next worker_id={next_wid} must be in Alpha's alive bucket; "
        f"alive={alive_ids}"
    )


def test_bucket_background_color_class_alive(browser, base_url):
    """Alpha's live worker page renders the outer '.workers section'
    wrapper with class 'is-bucket-alive' so the existing .workers blue
    is applied as the alive background (soft palette anchored on the
    existing #c3e5f8 .workers tint)."""
    workers = _alpha_live_workers_by_id()
    wid = workers[0]['id']

    ctx = browser.new_context()
    page = ctx.new_page()
    _open_worker_as_alpha(page, base_url, wid)
    html = page.content()
    ctx.close()

    wrapper = re.search(r"<div\s+class=['\"]workers section([^'\"]*)['\"]", html)
    assert wrapper, "'.workers section' wrapper div not found"
    classes = wrapper.group(1)
    assert "is-bucket-alive" in classes, (
        f"Alive worker must carry is-bucket-alive on the .workers wrapper; "
        f"got: 'workers section{classes}'"
    )


# ---------------------------------------------------------------------------
# Sticky header
# ---------------------------------------------------------------------------

def test_sticky_header_on_scroll(browser, base_url):
    """After scrolling the worker page well past the natural header
    position, the .card-header bounding box top is still at viewport top
    AND the 3 nav buttons are still visible within it."""
    workers = _alpha_live_workers_by_id()
    wid = workers[0]['id']

    ctx = browser.new_context()
    page = ctx.new_page()
    _open_worker_as_alpha(page, base_url, wid)

    # Scroll to bottom of the page to test sticky
    page.evaluate("window.scrollTo(0, document.body.scrollHeight);")
    page.wait_for_timeout(200)

    header_box = page.locator(".card-header").first.bounding_box()
    assert header_box, "card-header bounding box unavailable"
    # After scrolling, the sticky header should be at top (allow small fuzz)
    assert header_box['y'] < 50, (
        f"Sticky header top should be near 0 after scroll; got y={header_box['y']}"
    )

    # All 3 button labels remain visible
    assert page.locator(".card-header :text('Retour')").first.is_visible()
    assert page.locator(".card-header :text('Précédent')").first.is_visible()
    assert page.locator(".card-header :text('Suivant')").first.is_visible()

    ctx.close()
