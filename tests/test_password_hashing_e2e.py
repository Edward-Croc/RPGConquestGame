"""Playwright end-to-end tests for password hashing and password changes.

players.passwd holds a hash, the login compares it through
password_verify, and every seeding path -- the CSV importer, minimalData
and the SQL scenario files -- stores a hash, so no reload reintroduces
clear text. The player-facing page is connection/account.php.

Run:
    python3 -m pytest tests/test_password_hashing_e2e.py -v
"""
import pytest
from playwright.sync_api import Page

from conftest import GAME_PREFIX, PHP_BASE_URL, ensure_gm_login

import csv
import re
from pathlib import Path

from helpers import (
    DB_AVAILABLE, get_db_connection, load_scenario_via_admin, login_as, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def _scenario(browser):
    if not DB_AVAILABLE:
        pytest.skip("No local MySQL available")
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
    yield
    # Restore the seeded password : the change test leaves a different one.
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")


def _stored_password(username):
    conn = get_db_connection()
    cur = conn.cursor()
    cur.execute(
        f"SELECT passwd FROM `{GAME_PREFIX}players` WHERE username = %s", (username,)
    )
    row = cur.fetchone()
    cur.close()
    conn.close()
    return row["passwd"] if row else None


@pytest.mark.db
def test_no_account_stores_a_clear_password(browser, base_url):
    """Every seeded account, whether it comes from minimalData or from the
    scenario CSV, must be stored as a hash. Without the importer change a
    scenario reload silently reintroduces clear text."""
    conn = get_db_connection()
    cur = conn.cursor()
    cur.execute(f"SELECT username, passwd FROM `{GAME_PREFIX}players`")
    rows = cur.fetchall()
    cur.close()
    conn.close()

    assert rows, "no player seeded : the scenario did not load"
    clear = [r["username"] for r in rows if not str(r["passwd"]).startswith("$2y$")]
    assert clear == [], f"these accounts still hold a clear password : {clear}"


def test_login_still_works_for_gm_and_for_a_player(browser, base_url):
    """Positive anchor : hashing must not lock anyone out. gm comes from
    minimalData, single_player from the scenario CSV — the two write paths."""
    for user, password in (("gm", "orga"), ("single_player", "test")):
        ctx = browser.new_context()
        page = ctx.new_page()
        login_as(page, base_url, user, password)
        assert page.locator("a.logout-btn").count() >= 1, (
            f"{user} could not log in after hashing"
        )
        ctx.close()


def test_a_wrong_password_is_refused(browser, base_url):
    ctx = browser.new_context()
    page = ctx.new_page()
    safe_goto(page, f"{base_url}/connection/loginForm.php")
    page.fill("input[name='username']", "single_player")
    page.fill("input[name='passwd']", "not-the-password")
    page.click("input[type='submit'], button[type='submit']")
    page.wait_for_load_state("load")
    assert page.locator("a.logout-btn").count() == 0, (
        "a wrong password must not open a session"
    )
    ctx.close()


def test_the_password_case_is_preserved(browser, base_url):
    """loginForm lowercased the typed password while the stored value kept its
    case, so any capital made an account unreachable. The username stays
    normalised, the password no longer is."""
    ctx = browser.new_context()
    page = ctx.new_page()
    register_php_error_listener(page)
    login_as(page, base_url, "single_player", "test")

    safe_goto(page, f"{base_url}/connection/account.php")
    page.fill("input[name='current_password']", "test")
    page.fill("input[name='new_password']", "MiXeD")
    page.fill("input[name='confirm_password']", "MiXeD")
    submit = page.locator("form button.is-link")
    submit.scroll_into_view_if_needed()
    submit.click()
    page.wait_for_load_state("load")
    assert "Mot de passe changé" in page.content(), (
        f"the change was refused : {page.content()[:400]}"
    )
    assert_no_collected_php_errors(page)
    ctx.close()

    # The capitals must be honoured on the way back in.
    ctx = browser.new_context()
    page = ctx.new_page()
    login_as(page, base_url, "single_player", "MiXeD")
    logged = page.locator("a.logout-btn").count()
    ctx.close()
    assert logged >= 1, "a password with capitals must be usable"

    # And the lowercase form must not open the session.
    ctx = browser.new_context()
    page = ctx.new_page()
    safe_goto(page, f"{base_url}/connection/loginForm.php")
    page.fill("input[name='username']", "single_player")
    page.fill("input[name='passwd']", "mixed")
    page.click("input[type='submit'], button[type='submit']")
    page.wait_for_load_state("load")
    assert page.locator("a.logout-btn").count() == 0, (
        "the password must not be case-folded on the way in"
    )
    ctx.close()


@pytest.mark.db
def test_changing_the_password_stores_a_hash(browser, base_url):
    """The change must not write what the player typed."""
    stored = _stored_password("single_player")
    assert stored is not None and stored.startswith("$2y$"), (
        f"the changed password must be stored as a hash; got {stored!r}"
    )
    assert "MiXeD" not in str(stored), "the clear password leaked into the column"


def test_a_wrong_current_password_is_refused(browser, base_url):
    ctx = browser.new_context()
    page = ctx.new_page()
    login_as(page, base_url, "gm", "orga")
    safe_goto(page, f"{base_url}/connection/account.php")
    page.fill("input[name='current_password']", "wrong")
    page.fill("input[name='new_password']", "abcd")
    page.fill("input[name='confirm_password']", "abcd")
    submit = page.locator("form button.is-link")
    submit.scroll_into_view_if_needed()
    submit.click()
    page.wait_for_load_state("load")
    html = page.content()
    ctx.close()
    assert "Mot de passe actuel incorrect" in html, (
        "changing without the current password must be refused"
    )


def test_the_page_is_closed_to_anonymous_visitors(browser, base_url):
    ctx = browser.new_context()
    page = ctx.new_page()
    safe_goto(page, f"{base_url}/connection/account.php")
    url = page.url
    ctx.close()
    assert "loginForm" in url, f"an anonymous visitor must land on the login form; got {url}"


@pytest.mark.db
def test_the_admin_can_reset_a_player_password(browser, base_url):
    """The game master needs a way back in for a player who forgot theirs."""
    ctx = browser.new_context()
    page = ctx.new_page()
    ensure_gm_login(page, base_url)
    safe_goto(page, f"{base_url}/controllers/management.php")
    page.select_option("select[name='reset_player_id']", label="delta_player")
    page.fill("input[name='new_password']", "ResetMe")
    page.click("button[name='reset_password']")
    page.wait_for_load_state("load")
    assert "Mot de passe réinitialisé" in page.content()
    ctx.close()

    stored = _stored_password("delta_player")
    assert stored.startswith("$2y$"), "the reset must store a hash"

    ctx = browser.new_context()
    page = ctx.new_page()
    login_as(page, base_url, "delta_player", "ResetMe")
    logged = page.locator("a.logout-btn").count()
    ctx.close()
    assert logged >= 1, "the player must be able to log in with the reset password"


def test_the_account_page_lists_the_factions_of_the_player(browser, base_url):
    """The account page is not only a password form : a player must see which
    factions are theirs. multi_player holds Alpha and Beta, so a page that
    listed every controller, or only the session one, would fail here."""
    ctx = browser.new_context()
    page = ctx.new_page()
    register_php_error_listener(page)
    login_as(page, base_url, "multi_player", "test")
    safe_goto(page, f"{base_url}/connection/account.php")
    listed = page.locator("#playerFactions li").all_inner_texts()
    assert_no_collected_php_errors(page)
    ctx.close()

    joined = " ".join(listed)
    assert "Alpha" in joined and "Beta" in joined, (
        f"the player's two factions must be listed; got {listed}"
    )
    assert "Charlie" not in joined, (
        f"a faction the player does not hold must not be listed; got {listed}"
    )


@pytest.mark.db
def test_the_admin_can_restore_the_scenario_password(browser, base_url):
    """A player who lost their password gets the scenario default back. The
    previous test left delta_player on 'ResetMe', so this one proves the
    restore by logging in with the CSV value again."""
    ctx = browser.new_context()
    page = ctx.new_page()
    ensure_gm_login(page, base_url)
    safe_goto(page, f"{base_url}/controllers/management.php")
    page.select_option("select[name='scenario_player_id']", label="delta_player")
    page.select_option("select[name='scenario_name']", "TestConfig")
    page.click("button[name='reset_scenario_password']")
    page.wait_for_load_state("load")
    assert "Mot de passe remis à la valeur du scénario" in page.content()
    ctx.close()

    stored = _stored_password("delta_player")
    assert stored.startswith("$2y$"), "the restore must store a hash"

    ctx = browser.new_context()
    page = ctx.new_page()
    login_as(page, base_url, "delta_player", "test")
    logged = page.locator("a.logout-btn").count()
    ctx.close()
    assert logged >= 1, "the scenario password must open the session again"


def test_no_seed_file_carries_a_clear_password():
    """The CSV path hashes on import, but the SQL scenario files are piped to
    the database as they stand : a clear value there is stored as-is and locks
    every seeded account out. This reads the files, so it needs no database."""
    repo = Path(__file__).resolve().parent.parent
    offenders = []
    for sql_file in sorted(repo.glob("var/*/*.sql")):
        text = sql_file.read_text(encoding="utf-8", errors="replace")
        for block in re.findall(
            r"INSERT INTO \{prefix\}players[^;]+;", text, re.S
        ):
            for username, passwd in re.findall(r"\('([^']+)',\s*'([^']*)'", block):
                if not passwd.startswith("$2y$") and not passwd.startswith("$argon2"):
                    offenders.append(f"{sql_file.name}:{username}")
    assert offenders == [], f"these seeds hold a clear password : {offenders}"


def test_every_scenario_csv_seeds_a_password():
    """The restore-to-scenario button reads these files : a scenario whose CSV
    has no passwd column would silently offer a restore that does nothing."""
    repo = Path(__file__).resolve().parent.parent
    csv_files = sorted(repo.glob("var/csv/setup*_players.csv"))
    assert csv_files, "no scenario players CSV found"
    for csv_file in csv_files:
        with csv_file.open(encoding="utf-8") as handle:
            rows = list(csv.DictReader(handle))
        assert rows, f"{csv_file.name} seeds no player"
        missing = [r["username"] for r in rows if not (r.get("passwd") or "").strip()]
        assert missing == [], f"{csv_file.name} seeds no password for {missing}"
