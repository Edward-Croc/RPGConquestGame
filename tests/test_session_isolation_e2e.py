"""Playwright E2E test for session isolation between installations (issue #170).

The bug, reported from production: log into one game, change the path of the URL
to another game served from the same domain, and you are authenticated there too.

A session cookie is bound to the **domain**, not to the path. Under the default
cookie name, two installations on one host therefore share an identifier, and
`logged_in`, `user_id` and `is_privileged` carry straight over. Worse,
`$_SESSION['DBNAME']`, `FOLDER` and `GAME_PREFIX` are rewritten on every request
by getDBConnection (BDD/db_connector.php), so the surviving `user_id` is then
read against the OTHER database — the identity is not merely leaked, it is
rewritten into whoever holds that id in the other game.

`base/session.php` fixes it twice over: the cookie is named after the
installation's own directory, and the session records which installation opened
it, so a cookie arriving from elsewhere is emptied rather than trusted.

Why the suite could not see this before: everything ran against a single
installation. The Docker stack already serves a second one
(docker-compose.yml:33-35), which is what this file uses.

Run:
    python3 -m pytest tests/test_session_isolation_e2e.py -v
"""
import pytest
import requests

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import safe_goto


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module")
def sibling_url():
    """URL of a second installation on the same host, or skip.

    The local stack serves RPGConquestGameTest2 beside RPGConquestGameTest. A
    deployment with only one installation has nothing to isolate, so there is
    nothing to assert there.
    """
    candidate = PHP_BASE_URL.rstrip("/") + "2"
    try:
        response = requests.get(candidate + "/connection/loginForm.php", timeout=10)
    except requests.RequestException:
        pytest.skip(f"no sibling installation answering at {candidate}")
    if response.status_code != 200:
        pytest.skip(f"sibling installation at {candidate} answered {response.status_code}")
    return candidate


class TestSessionIsolation:
    """One installation's session must not authenticate another."""

    def test_the_two_installations_use_different_session_cookies(self, sibling_url):
        """The first guard, read straight off the wire : sharing a cookie name is
        what let the session cross over in the first place."""
        names = []
        for url in (PHP_BASE_URL, sibling_url):
            response = requests.get(url + "/connection/loginForm.php", timeout=10)
            names.append({c.name for c in response.cookies})
        assert names[0], "the first installation set no session cookie at all"
        assert names[1], "the sibling installation set no session cookie at all"
        assert not (names[0] & names[1]), (
            f"both installations share a session cookie name: {names[0] & names[1]}"
        )

    def test_a_session_does_not_cross_to_the_sibling(self, page, base_url, sibling_url):
        """The heart of the issue : being logged in here must not log us in there.

        Paired with the positive control below, without which landing on a login
        form would prove nothing — a broken deployment would do the same.
        """
        ensure_gm_login(page, base_url)
        safe_goto(page, sibling_url + "/")
        page.wait_for_load_state("load")
        assert "loginForm.php" in page.url, (
            f"the sibling installation accepted this session; landed on {page.url}"
        )

    def test_the_session_still_works_where_it_was_opened(self, page, base_url,
                                                         sibling_url):
        """Positive control for the test above : the isolation must not have cost
        us the session on the installation that issued it."""
        ensure_gm_login(page, base_url)
        safe_goto(page, sibling_url + "/")
        page.wait_for_load_state("load")
        safe_goto(page, base_url + "/")
        page.wait_for_load_state("load")
        assert "loginForm.php" not in page.url, (
            f"the session was lost on its own installation; landed on {page.url}"
        )
