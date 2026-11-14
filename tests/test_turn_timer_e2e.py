"""Playwright end-to-end tests for the turn timer (sidebar counter and MJ commands).

The timer has its own state (mechanics.turn_deadline / turn_timer_remaining),
driven by the turnTimer* config keys and by the MJ commands posted to
mechanics/turnTimer.php. This slice needs no end of turn.

Run:
    python3 -m pytest tests/test_turn_timer_e2e.py -v
"""
import time
from datetime import datetime
from zoneinfo import ZoneInfo

import pytest

from conftest import PHP_BASE_URL, GAME_PREFIX, ensure_gm_login

from helpers import (
    DB_AVAILABLE, get_db_connection, load_scenario_via_admin, login_as,
    safe_goto, set_config_via_ui, end_turn, ui_turn_counter,
    register_php_error_listener, assert_no_collected_php_errors,
)

_TIMER_URL = f"{PHP_BASE_URL}/mechanics/turnTimer.php"
_ACCUEIL = f"{PHP_BASE_URL}/base/accueil.php"
_IMMINENT = "Fin de tour imminente."


@pytest.fixture(scope="module", autouse=True)
def _scenario(browser):
    if not DB_AVAILABLE:
        pytest.skip("No local MySQL available")
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
    yield


def _gm_command(page, label):
    """Click a timer command in the sidebar (width 0 until the burger opens it)."""
    page.locator("span.openbtn").click()
    with page.expect_navigation():
        page.get_by_role("button", name=label, exact=True).click()
    page.wait_for_selector("a.logout-btn", state="attached")


def _timer_text(page):
    locator = page.locator("#turnTimer")
    return locator.text_content() if locator.count() else None


class TestTurnTimer:
    """Mode off, then durations 2h started, refused, stopped, then 0h."""

    @pytest.fixture(scope="class", autouse=True)
    def timer_state(self, browser):
        cls = type(self)
        gm_ctx = browser.new_context()
        player_ctx = browser.new_context()
        gm = gm_ctx.new_page()
        player = player_ctx.new_page()
        register_php_error_listener(gm)
        register_php_error_listener(player)
        try:
            ensure_gm_login(gm, PHP_BASE_URL)
            login_as(player, PHP_BASE_URL, "single_player", "test")

            # Mode off (CSV default).
            safe_goto(gm, _ACCUEIL)
            cls.off_gm_timer = gm.locator("#turnTimer").count()
            cls.off_gm_start = gm.get_by_role("button", name="Lancer", exact=True).count()
            safe_goto(player, _ACCUEIL)
            cls.off_player_timer = player.locator("#turnTimer").count()

            # Mode durations, 2h, stopped.
            set_config_via_ui(gm, "turnTimerMode", "durations")
            set_config_via_ui(gm, "turnTimerDurations", "2h")
            safe_goto(gm, _ACCUEIL)
            cls.stopped_gm_timer = gm.locator("#turnTimer").count()
            cls.stopped_gm_start = gm.get_by_role("button", name="Lancer", exact=True).count()
            safe_goto(player, _ACCUEIL)
            cls.stopped_player_buttons = player.get_by_role("button", name="Lancer", exact=True).count()

            # A POST without token is refused and starts nothing.
            gm.request.post(_TIMER_URL, form={"turn_timer_action": "start"})
            gm.request.post(
                _TIMER_URL,
                form={"turn_timer_action": "start", "end_turn_token": "0" * 32},
            )
            safe_goto(gm, _ACCUEIL)
            cls.refused_timer = gm.locator("#turnTimer").count()

            # Lancer.
            safe_goto(gm, _ACCUEIL)
            _gm_command(gm, "Lancer")
            cls.run_gm_deadline = int(gm.locator("#turnTimer").get_attribute("data-deadline"))
            cls.run_gm_now = int(gm.locator("#turnTimer").get_attribute("data-now"))
            cls.run_gm_text = _timer_text(gm)
            cls.run_gm_buttons = {
                label: gm.get_by_role("button", name=label, exact=True).count()
                for label in ("Lancer", "Pause", "Arrêter", "Reprendre")
            }
            safe_goto(player, _ACCUEIL)
            cls.run_player_text = _timer_text(player)
            cls.run_player_pause = player.get_by_role("button", name="Pause", exact=True).count()

            # A valid-token start while running changes nothing.
            safe_goto(gm, _ACCUEIL)
            token = gm.locator("input[name='end_turn_token']").first.get_attribute("value")
            deadline_before = gm.locator("#turnTimer").get_attribute("data-deadline")
            gm.wait_for_timeout(1100)
            gm.request.post(
                _TIMER_URL,
                form={"turn_timer_action": "start", "end_turn_token": token},
            )
            safe_goto(gm, _ACCUEIL)
            cls.restart_deadline_before = deadline_before
            cls.restart_deadline_after = gm.locator("#turnTimer").get_attribute("data-deadline")

            # A refused command prints a short French text.
            refused = gm.request.post(
                _TIMER_URL,
                form={"turn_timer_action": "start", "end_turn_token": "0" * 32},
            )
            cls.refused_status = refused.status
            cls.refused_body = refused.text()

            # Pause then Reprendre.
            safe_goto(gm, _ACCUEIL)
            _gm_command(gm, "Pause")
            cls.paused_text = _timer_text(gm)
            cls.paused_has_deadline = gm.locator("#turnTimer[data-deadline]").count()
            cls.paused_buttons = {
                label: gm.get_by_role("button", name=label, exact=True).count()
                for label in ("Pause", "Reprendre", "Arrêter")
            }
            _gm_command(gm, "Reprendre")
            cls.resumed_deadline = gm.locator("#turnTimer[data-deadline]").count()

            # Arrêter.
            _gm_command(gm, "Arrêter")
            cls.stopped_after_stop = gm.locator("#turnTimer").count()
            cls.stop_start_button = gm.get_by_role("button", name="Lancer", exact=True).count()

            # 0h: the deadline is already passed.
            set_config_via_ui(gm, "turnTimerDurations", "0h")
            safe_goto(gm, _ACCUEIL)
            _gm_command(gm, "Lancer")
            cls.late_gm_text = _timer_text(gm)
            safe_goto(player, _ACCUEIL)
            cls.late_player_text = _timer_text(player)

            assert_no_collected_php_errors(gm)
            assert_no_collected_php_errors(player)
            yield
        finally:
            try:
                _stop_if_running(gm)
                set_config_via_ui(gm, "turnTimerMode", "off")
            finally:
                gm_ctx.close()
                player_ctx.close()

    def test_mode_off_shows_no_counter_and_no_command(self):
        assert self.off_gm_timer == 0
        assert self.off_gm_start == 0
        assert self.off_player_timer == 0

    def test_stopped_timer_offers_start_to_gm_only(self):
        assert self.stopped_gm_timer == 0
        assert self.stopped_gm_start == 1
        assert self.stopped_player_buttons == 0

    def test_command_without_token_is_refused(self):
        """Paired with test_start_runs_the_timer, which proves the nominal path."""
        assert self.refused_timer == 0

    def test_start_runs_the_timer(self):
        assert 7190 <= self.run_gm_deadline - self.run_gm_now <= 7210
        assert " dans " in self.run_gm_text
        assert self.run_gm_buttons == {"Lancer": 0, "Pause": 1, "Arrêter": 1, "Reprendre": 0}

    def test_start_while_running_is_a_noop(self):
        assert self.restart_deadline_before is not None
        assert self.restart_deadline_after == self.restart_deadline_before

    def test_refusal_prints_a_short_text(self):
        assert self.refused_status == 403
        assert "Commande refusée, rechargez la page." in self.refused_body

    def test_player_sees_counter_but_no_command(self):
        assert self.run_player_text is not None
        assert " dans " in self.run_player_text
        assert self.run_player_pause == 0

    def test_pause_and_resume(self):
        assert self.paused_text is not None
        assert "Jeu en pause, il reste" in self.paused_text
        assert self.paused_has_deadline == 0
        assert self.paused_buttons == {"Pause": 0, "Reprendre": 1, "Arrêter": 1}
        assert self.resumed_deadline == 1

    def test_stop_removes_the_counter(self):
        assert self.stopped_after_stop == 0
        assert self.stop_start_button == 1

    def test_passed_deadline_shows_imminent_and_delay_to_gm_only(self):
        assert _IMMINENT in self.late_player_text
        assert "Retard" not in self.late_player_text
        assert _IMMINENT in self.late_gm_text
        assert "Retard :" in self.late_gm_text


def _remaining_from(page, reference):
    """Seconds between a reference epoch and the data-deadline of the sidebar counter."""
    locator = page.locator("#turnTimer[data-deadline]")
    return int(locator.get_attribute("data-deadline")) - reference if locator.count() else None


def _durations(turn, *entries):
    """Durations list with 1h below `turn`, then the given entries."""
    return ",".join(["1h"] * turn + list(entries))


class TestTurnTimerEndTurn:
    """The end of turn moves the deadline with the turn, parsing checks, exhaustion."""

    @pytest.fixture(scope="class", autouse=True)
    def eot_state(self, browser):
        cls = type(self)
        ctx = browser.new_context()
        gm = ctx.new_page()
        register_php_error_listener(gm)
        try:
            ensure_gm_login(gm, PHP_BASE_URL)
            turn = ui_turn_counter(gm)
            set_config_via_ui(gm, "turnTimerMode", "durations")
            set_config_via_ui(gm, "turnTimerDurations", _durations(turn, "2h", "3h", "1h30"))
            safe_goto(gm, _ACCUEIL)

            # Lancer, Pause, Reprendre, Pause.
            before = int(time.time())
            _gm_command(gm, "Lancer")
            cls.start_remaining = _remaining_from(gm, before)
            _gm_command(gm, "Pause")
            cls.pause_text = _timer_text(gm)
            cls.pause_has_deadline = gm.locator("#turnTimer[data-deadline]").count()
            before = int(time.time())
            _gm_command(gm, "Reprendre")
            cls.resume_remaining = _remaining_from(gm, before)
            _gm_command(gm, "Pause")

            # EOT while paused: the next turn starts paused with its full duration.
            end_turn(gm)
            safe_goto(gm, _ACCUEIL)
            cls.eot_paused_text = _timer_text(gm)
            cls.eot_paused_has_deadline = gm.locator("#turnTimer[data-deadline]").count()

            # EOT while running: deadline from durations[T+2] counted from the EOT.
            _gm_command(gm, "Reprendre")
            cls.eot_resume_remaining = _remaining_from(gm, int(time.time()))
            t_before = int(time.time())
            end_turn(gm)
            t_after = int(time.time())
            safe_goto(gm, _ACCUEIL)
            cls.eot_running_deadline = int(gm.locator("#turnTimer").get_attribute("data-deadline"))
            cls.eot_running_bounds = (t_before + 5400, t_after + 5400)

            # EOT with the list exhausted: the timer stops.
            end_turn(gm)
            safe_goto(gm, _ACCUEIL)
            cls.exhausted_timer = gm.locator("#turnTimer").count()
            cls.exhausted_start = gm.get_by_role("button", name="Lancer", exact=True).count()

            # Parsing checks, one Lancer each.
            cls.parsing = {}
            for value in ("2h", "2h30", "2h,", "abc"):
                set_config_via_ui(gm, "turnTimerDurations", value)
                safe_goto(gm, _ACCUEIL)
                before = int(time.time())
                _gm_command(gm, "Lancer")
                cls.parsing[value] = _remaining_from(gm, before)
                cls.parsing[value + "|count"] = gm.locator("#turnTimer").count()
                if gm.locator("#turnTimer").count():
                    _gm_command(gm, "Arrêter")

            set_config_via_ui(gm, "turnTimerMode", "off")
            assert_no_collected_php_errors(gm)
            yield
        finally:
            try:
                set_config_via_ui(gm, "turnTimerMode", "off")
            finally:
                ctx.close()

    def test_start_uses_the_duration_of_the_current_turn(self):
        assert 7195 <= self.start_remaining <= 7210

    def test_pause_shows_remaining_without_deadline(self):
        assert self.pause_text in ("Jeu en pause, il reste 2h00.", "Jeu en pause, il reste 1h59.")
        assert self.pause_has_deadline == 0

    def test_resume_keeps_the_remaining_time(self):
        assert 7180 <= self.resume_remaining <= 7210

    def test_end_turn_while_paused_starts_next_turn_paused_with_full_duration(self):
        assert self.eot_paused_text == "Jeu en pause, il reste 3h00."
        assert self.eot_paused_has_deadline == 0
        assert 10780 <= self.eot_resume_remaining <= 10810

    def test_end_turn_while_running_sets_the_next_deadline(self):
        low, high = self.eot_running_bounds
        assert low - 5 <= self.eot_running_deadline <= high + 5

    def test_exhausted_list_stops_the_timer(self):
        assert self.exhausted_timer == 0
        assert self.exhausted_start == 1

    def test_duration_parsing(self):
        assert 7195 <= self.parsing["2h"] <= 7210
        assert 8995 <= self.parsing["2h30"] <= 9010
        assert 7195 <= self.parsing["2h,"] <= 7210
        assert self.parsing["abc|count"] == 0


def _stamp(epoch, tz_name="UTC", offset_s=0):
    """Config text YYYY-MM-DD HH:MM of an epoch (plus an offset) in a timezone."""
    return datetime.fromtimestamp(epoch + offset_s, ZoneInfo(tz_name)).strftime("%Y-%m-%d %H:%M")


def _epoch(text, tz_name="UTC"):
    """UTC epoch of a YYYY-MM-DD HH:MM text written in a timezone."""
    return int(datetime.strptime(text, "%Y-%m-%d %H:%M").replace(tzinfo=ZoneInfo(tz_name)).timestamp())


def _deadline(page):
    locator = page.locator("#turnTimer[data-deadline]")
    return int(locator.get_attribute("data-deadline")) if locator.count() else None


def _stop_if_running(page):
    safe_goto(page, _ACCUEIL)
    if page.get_by_role("button", name="Arrêter", exact=True).count():
        _gm_command(page, "Arrêter")


class TestTurnTimerDates:
    """Mode dates: next future date, missed dates skipped, resume from the list, timezones."""

    @pytest.fixture(scope="class", autouse=True)
    def dates_state(self, browser):
        cls = type(self)
        ctx = browser.new_context()
        gm = ctx.new_page()
        register_php_error_listener(gm)
        try:
            ensure_gm_login(gm, PHP_BASE_URL)
            now = int(time.time())
            past = _stamp(now, "UTC", -7200)
            d3 = _stamp(now, "UTC", 3 * 3600)
            d6 = _stamp(now, "UTC", 6 * 3600)
            d4 = _stamp(now, "UTC", 4 * 3600)
            d5 = _stamp(now, "UTC", 5 * 3600)
            cls.d3, cls.d4, cls.d5 = _epoch(d3), _epoch(d4), _epoch(d5)
            set_config_via_ui(gm, "turnTimerMode", "dates")
            set_config_via_ui(gm, "turnTimerTimezone", "UTC")
            _stop_if_running(gm)

            # Past date skipped, earliest future date kept (list given out of order).
            set_config_via_ui(gm, "turnTimerDates", f"{d6},{past},{d3}")
            safe_goto(gm, _ACCUEIL)
            _gm_command(gm, "Lancer")
            cls.start_deadline = _deadline(gm)

            # Pause, change the list, Reprendre: the deadline comes from the list, not now + remaining.
            _gm_command(gm, "Pause")
            cls.paused_has_deadline = gm.locator("#turnTimer[data-deadline]").count()
            set_config_via_ui(gm, "turnTimerDates", f"{past},{d5}")
            safe_goto(gm, _ACCUEIL)
            _gm_command(gm, "Reprendre")
            cls.resume_deadline = _deadline(gm)

            # End of turn while running, no date crossed: still the next future date.
            end_turn(gm)
            safe_goto(gm, _ACCUEIL)
            cls.eot_deadline = _deadline(gm)
            _stop_if_running(gm)

            # No future date: Lancer leaves the timer stopped.
            set_config_via_ui(gm, "turnTimerDates", past)
            safe_goto(gm, _ACCUEIL)
            _gm_command(gm, "Lancer")
            cls.none_timer = gm.locator("#turnTimer").count()
            cls.none_start = gm.get_by_role("button", name="Lancer", exact=True).count()
            _stop_if_running(gm)

            # Invalid entries ignored, valid neighbours still count.
            set_config_via_ui(gm, "turnTimerDates", f"2026-13-01 10:00,2026-02-31 10:00,garbage,{d4}")
            safe_goto(gm, _ACCUEIL)
            _gm_command(gm, "Lancer")
            cls.invalid_deadline = _deadline(gm)
            _stop_if_running(gm)

            # Timezones: the same text, Tokyo then UTC then an unknown name.
            tokyo_text = _stamp(now, "Asia/Tokyo", 3 * 3600)
            cls.tokyo_text = tokyo_text
            set_config_via_ui(gm, "turnTimerDates", tokyo_text)
            cls.zone_deadline = {}
            for tz_name in ("Asia/Tokyo", "UTC", "", "Mars/Phobos"):
                set_config_via_ui(gm, "turnTimerTimezone", tz_name)
                safe_goto(gm, _ACCUEIL)
                _gm_command(gm, "Lancer")
                cls.zone_deadline[tz_name] = _deadline(gm)
                _stop_if_running(gm)

            assert_no_collected_php_errors(gm)
            yield
        finally:
            try:
                _stop_if_running(gm)
                set_config_via_ui(gm, "turnTimerMode", "off")
                set_config_via_ui(gm, "turnTimerDates", "")
                set_config_via_ui(gm, "turnTimerTimezone", "")
            finally:
                ctx.close()

    def test_start_skips_past_dates_and_keeps_the_earliest_future_one(self):
        assert self.start_deadline == self.d3

    def test_pause_has_no_deadline_then_resume_reads_the_list(self):
        assert self.paused_has_deadline == 0
        assert self.resume_deadline == self.d5

    def test_end_turn_keeps_the_next_future_date(self):
        assert self.eot_deadline == self.d5

    def test_no_future_date_leaves_the_timer_stopped(self):
        assert self.none_timer == 0
        assert self.none_start == 1

    def test_invalid_entries_are_ignored(self):
        assert self.invalid_deadline == self.d4

    def test_timezone_changes_the_epoch(self):
        tokyo = _epoch(self.tokyo_text, "Asia/Tokyo")
        assert self.zone_deadline["Asia/Tokyo"] == tokyo
        assert self.zone_deadline["UTC"] == tokyo + 9 * 3600

    def test_empty_or_unknown_timezone_falls_back_to_the_server_one(self):
        assert self.zone_deadline[""] is not None
        assert self.zone_deadline["Mars/Phobos"] == self.zone_deadline[""]


@pytest.mark.db
class TestTurnTimerColumnsAbsent:
    """Without the two mechanics columns the sidebar renders no counter."""

    @pytest.fixture(scope="class", autouse=True)
    def absent_state(self, browser):
        cls = type(self)
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        try:
            ensure_gm_login(page, PHP_BASE_URL)
            set_config_via_ui(page, "turnTimerMode", "durations")
            conn = get_db_connection()
            try:
                with conn.cursor() as cur:
                    cur.execute(f"ALTER TABLE {GAME_PREFIX}mechanics DROP COLUMN turn_deadline")
                    cur.execute(f"ALTER TABLE {GAME_PREFIX}mechanics DROP COLUMN turn_timer_remaining")
            finally:
                conn.close()
            safe_goto(page, _ACCUEIL)
            cls.timer_count = page.locator("#turnTimer").count()
            cls.start_count = page.get_by_role("button", name="Lancer", exact=True).count()
            cls.logout_count = page.locator("a.logout-btn").count()
            assert_no_collected_php_errors(page)
            yield
        finally:
            ctx.close()
            load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")

    def test_no_counter_and_no_command_when_columns_are_absent(self):
        assert self.logout_count == 1, "The page did not render"
        assert self.timer_count == 0
        assert self.start_count == 0


_EOT_URL = f"{PHP_BASE_URL}/mechanics/endTurn.php"
_REFUSAL = "Fin de tour non déclenchée"
_SUBMIT_EOT = "setTimeout(() => document.getElementById('endTurnForm').submit(), 0)"


def _await_eot_outcome(page):
    """Wait for endTurn.php to finish, return 'refused' or 'resolved'."""
    page.wait_for_url(_EOT_URL, timeout=180000)
    page.wait_for_function(
        "() => Array.from(document.querySelectorAll('h2'))"
        ".some(h => /\\w+\\s*:\\s*\\d+/.test(h.textContent)"
        " || h.textContent.includes('Fin de tour non déclenchée'))",
        timeout=180000,
    )
    return "refused" if _REFUSAL in page.content() else "resolved"


def _eot_refusal_text(page):
    return page.locator("h2", has_text=_REFUSAL).count()


class TestEndTurnAtomicClaim:
    """Two triggers on the same turn resolve it once, a form rendered on an older turn is refused."""

    @pytest.fixture(scope="class", autouse=True)
    def claim_state(self, browser):
        cls = type(self)
        ctx_a = browser.new_context()
        ctx_b = browser.new_context()
        page_a = ctx_a.new_page()
        page_b = ctx_b.new_page()
        register_php_error_listener(page_a)
        register_php_error_listener(page_b)
        try:
            ensure_gm_login(page_a, PHP_BASE_URL)
            ensure_gm_login(page_b, PHP_BASE_URL)

            # Concurrent double trigger on turn T.
            cls.turn_before = ui_turn_counter(page_a)
            safe_goto(page_b, _ACCUEIL)
            page_a.evaluate(_SUBMIT_EOT)
            page_b.evaluate(_SUBMIT_EOT)
            cls.outcomes = sorted([_await_eot_outcome(page_a), _await_eot_outcome(page_b)])
            cls.refusal_h2 = _eot_refusal_text(page_a) + _eot_refusal_text(page_b)
            cls.turn_after_double = ui_turn_counter(page_a)

            # Stale form: B renders at T+1, A ends T+1, B submits its old form.
            safe_goto(page_b, _ACCUEIL)
            end_turn(page_a)
            cls.turn_after_a = ui_turn_counter(page_a)
            page_b.evaluate(_SUBMIT_EOT)
            cls.stale_outcome = _await_eot_outcome(page_b)
            cls.stale_refusal_p = page_b.content()
            cls.turn_after_stale = ui_turn_counter(page_b)

            assert_no_collected_php_errors(page_a)
            assert_no_collected_php_errors(page_b)
            yield
        finally:
            ctx_a.close()
            ctx_b.close()

    def test_concurrent_double_trigger_resolves_once(self):
        assert self.outcomes == ["refused", "resolved"]
        assert self.refusal_h2 == 1
        assert self.turn_after_double == self.turn_before + 1

    def test_stale_form_is_refused(self):
        assert self.turn_after_a == self.turn_before + 2
        assert self.stale_outcome == "refused"
        assert "Fin de tour déjà en cours ou déjà effectuée, rechargez la page." in self.stale_refusal_p
        assert self.turn_after_stale == self.turn_before + 2


class TestTurnTimerAutoFire:
    """OnExpiry endTurn: the MJ page ends the turn at expiry, notice mode only warns."""

    @pytest.fixture(scope="class", autouse=True)
    def autofire_state(self, browser):
        cls = type(self)
        ctx = browser.new_context()
        gm = ctx.new_page()
        register_php_error_listener(gm)
        try:
            ensure_gm_login(gm, PHP_BASE_URL)
            turn = ui_turn_counter(gm)
            cls.turn_start = turn
            set_config_via_ui(gm, "turnTimerMode", "durations")
            set_config_via_ui(gm, "turnTimerOnExpiry", "endTurn")
            set_config_via_ui(gm, "turnTimerDurations", _durations(turn, "0h", "2h"))
            safe_goto(gm, _ACCUEIL)

            # Lancer: the deadline is already past, the page fires the end of turn alone.
            gm.locator("span.openbtn").click()
            gm.get_by_role("button", name="Lancer", exact=True).click()
            cls.fired_outcome = _await_eot_outcome(gm)
            cls.turn_after_fire = ui_turn_counter(gm)
            now = int(time.time())
            cls.next_deadline_left = _remaining_from(gm, now)
            _stop_if_running(gm)

            # Notice mode: the same expired deadline fires nothing.
            set_config_via_ui(gm, "turnTimerOnExpiry", "notice")
            set_config_via_ui(gm, "turnTimerDurations", _durations(cls.turn_after_fire, "0h"))
            safe_goto(gm, _ACCUEIL)
            _gm_command(gm, "Lancer")
            cls.notice_url = gm.url
            gm.wait_for_timeout(4000)
            cls.notice_url_later = gm.url
            cls.notice_text = _timer_text(gm)
            cls.notice_turn = ui_turn_counter(gm)

            assert_no_collected_php_errors(gm)
            yield
        finally:
            try:
                set_config_via_ui(gm, "turnTimerOnExpiry", "notice")
                _stop_if_running(gm)
                set_config_via_ui(gm, "turnTimerMode", "off")
            finally:
                ctx.close()

    def test_expired_deadline_fires_the_end_of_turn(self):
        assert self.fired_outcome == "resolved"
        assert self.turn_after_fire == self.turn_start + 1

    def test_next_deadline_comes_from_the_next_duration(self):
        assert 7000 <= self.next_deadline_left <= 7210

    def test_notice_mode_does_not_fire(self):
        assert self.notice_text is not None and _IMMINENT in self.notice_text
        assert self.notice_url == self.notice_url_later
        assert "endTurn.php" not in self.notice_url_later
        assert self.notice_turn == self.turn_after_fire


@pytest.mark.db
class TestTurnTimerClaimedResume:
    """A crash right after the claim leaves 'claimed': no auto-fire, the resume restarts the turn."""

    @pytest.fixture(scope="class", autouse=True)
    def claimed_state(self, browser):
        cls = type(self)
        ctx = browser.new_context()
        gm = ctx.new_page()
        register_php_error_listener(gm)
        try:
            ensure_gm_login(gm, PHP_BASE_URL)
            turn = ui_turn_counter(gm)
            cls.turn_start = turn
            set_config_via_ui(gm, "turnTimerMode", "durations")
            set_config_via_ui(gm, "turnTimerOnExpiry", "notice")
            set_config_via_ui(gm, "turnTimerDurations", _durations(turn, "0h"))
            safe_goto(gm, _ACCUEIL)
            _gm_command(gm, "Lancer")

            conn = get_db_connection()
            try:
                with conn.cursor() as cur:
                    cur.execute(f"UPDATE {GAME_PREFIX}mechanics SET end_step = 'claimed'")
            finally:
                conn.close()
            set_config_via_ui(gm, "turnTimerOnExpiry", "endTurn")
            safe_goto(gm, _ACCUEIL)
            cls.resume_buttons = gm.get_by_role("button", name="Resume End Turn", exact=True).count()
            cls.claimed_url = gm.url
            gm.wait_for_timeout(4000)
            cls.claimed_url_later = gm.url
            cls.claimed_turn = ui_turn_counter(gm)

            end_turn(gm)
            cls.turn_after_resume = ui_turn_counter(gm)

            assert_no_collected_php_errors(gm)
            yield
        finally:
            try:
                conn = get_db_connection()
                try:
                    with conn.cursor() as cur:
                        cur.execute(f"UPDATE {GAME_PREFIX}mechanics SET end_step = '' WHERE end_step = 'claimed'")
                finally:
                    conn.close()
                set_config_via_ui(gm, "turnTimerOnExpiry", "notice")
                _stop_if_running(gm)
                set_config_via_ui(gm, "turnTimerMode", "off")
            finally:
                ctx.close()

    def test_claimed_state_offers_resume_and_does_not_auto_fire(self):
        assert self.resume_buttons == 1
        assert self.claimed_url == self.claimed_url_later
        assert "endTurn.php" not in self.claimed_url_later
        assert self.claimed_turn == self.turn_start

    def test_resume_from_claimed_advances_exactly_one_turn(self):
        assert self.turn_after_resume == self.turn_start + 1


@pytest.mark.db
class TestEndTurnLateSecondTrigger:
    """A form rendered before another session claimed the turn is refused, whatever the step it then finds."""

    @pytest.fixture(scope="class", autouse=True)
    def late_state(self, browser):
        cls = type(self)
        ctx = browser.new_context()
        gm = ctx.new_page()
        register_php_error_listener(gm)
        try:
            ensure_gm_login(gm, PHP_BASE_URL)
            cls.turn_before = ui_turn_counter(gm)
            safe_goto(gm, _ACCUEIL)

            # Another session claims the turn between the render and the submit.
            conn = get_db_connection()
            try:
                with conn.cursor() as cur:
                    cur.execute(f"UPDATE {GAME_PREFIX}mechanics SET end_step = 'claimed'")
            finally:
                conn.close()
            gm.evaluate(_SUBMIT_EOT)
            cls.late_outcome = _await_eot_outcome(gm)
            cls.late_refusal_h2 = _eot_refusal_text(gm)
            cls.turn_after = ui_turn_counter(gm)

            assert_no_collected_php_errors(gm)
            yield
        finally:
            try:
                conn = get_db_connection()
                try:
                    with conn.cursor() as cur:
                        cur.execute(f"UPDATE {GAME_PREFIX}mechanics SET end_step = '' WHERE end_step = 'claimed'")
                finally:
                    conn.close()
            finally:
                ctx.close()

    def test_late_second_trigger_is_refused(self):
        assert self.late_outcome == "refused"
        assert self.late_refusal_h2 == 1

    def test_turn_is_unchanged(self):
        assert self.turn_after == self.turn_before


class TestAutoFireStaleDeadline:
    """An auto-fire carrying a deadline the timer no longer has is refused."""

    @pytest.fixture(scope="class", autouse=True)
    def stale_state(self, browser):
        cls = type(self)
        ctx = browser.new_context()
        gm = ctx.new_page()
        register_php_error_listener(gm)
        try:
            ensure_gm_login(gm, PHP_BASE_URL)
            set_config_via_ui(gm, "turnTimerMode", "durations")
            set_config_via_ui(gm, "turnTimerOnExpiry", "notice")
            set_config_via_ui(gm, "turnTimerDurations", "2h")
            safe_goto(gm, _ACCUEIL)
            _gm_command(gm, "Lancer")
            old_deadline = gm.locator("#turnTimer").get_attribute("data-deadline")
            token = gm.locator("input[name='end_turn_token']").first.get_attribute("value")
            turn_seen = gm.locator("input[name='turn_seen']").get_attribute("value")
            step_field = gm.locator("input[name='end_step_seen']")
            end_step_seen = step_field.get_attribute("value") if step_field.count() else ""
            cls.turn_before = ui_turn_counter(gm)

            safe_goto(gm, _ACCUEIL)
            _gm_command(gm, "Pause")
            response = gm.request.post(_EOT_URL, form={
                "end_turn_token": token,
                "turn_seen": turn_seen,
                "end_step_seen": end_step_seen,
                "auto_deadline": old_deadline,
            }, timeout=180000)
            cls.stale_body = response.text()
            cls.turn_after = ui_turn_counter(gm)
            cls.paused_text = _timer_text(gm)

            assert_no_collected_php_errors(gm)
            yield
        finally:
            try:
                set_config_via_ui(gm, "turnTimerOnExpiry", "notice")
                _stop_if_running(gm)
                set_config_via_ui(gm, "turnTimerMode", "off")
            finally:
                ctx.close()

    def test_stale_deadline_is_refused(self):
        assert _REFUSAL in self.stale_body
        assert self.turn_after == self.turn_before

    def test_timer_stays_paused(self):
        assert self.paused_text is not None and "pause" in self.paused_text.lower()
