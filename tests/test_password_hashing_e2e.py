"""Playwright end-to-end tests for password hashing and password changes.

players.passwd used to hold the password in clear and the login compared
it in SQL. It now holds a hash, the comparison happens through
password_verify, and the CSV importer hashes on the way in so a scenario
reload never reintroduces clear text.

Run:
    python3 -m pytest tests/test_password_hashing_e2e.py -v
"""
import pytest
from playwright.sync_api import Page

from conftest import GAME_PREFIX, PHP_BASE_URL, ensure_gm_login

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

    safe_goto(page, f"{base_url}/connection/changePassword.php")
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
    safe_goto(page, f"{base_url}/connection/changePassword.php")
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
    safe_goto(page, f"{base_url}/connection/changePassword.php")
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
