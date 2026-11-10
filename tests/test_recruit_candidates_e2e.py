"""Playwright E2E tests for stored recruitment candidates (issue #219).

The recruitment page (/workers/new.php) offers candidates that are stored per
controller and per recruit type ('first_come' / 'recrutement') instead of being
drawn afresh on every render. What these tests lock:

- the set offered is stable: a reload (F5) shows the same candidates, each
  carrying a hidden `candidate_id` input with its stored row id;
- the set is shared: two players of the same controller see the same set;
- displaying the page spends nothing: a third visit is still allowed, where the
  old page incremented the controller's recruitment counter at display and
  answered « Le recrutement n'est pas permis ! » on the next visit;
- `recruitMissingCandidates` = `generate` tops a short stored set up to
  `recrutement_nb_choices`, `do_nothing` leaves it as it is;
- the organiser (gm) with no faction selected is refused (403), and follows the
  player path once a faction is selected;
- recruiting goes through a stored candidate of the URL's controller: a forged,
  invented or missing candidate_id is refused (403), the names come from the
  stored row, the allowance is checked and spent at creation, the recruited
  candidate leaves the set, a replay shows the agent already recruited;
- « Create Perfect Agent » consumes no candidate and no allowance;
- at the end of turn the first-come set is redrawn, and the recruitment set
  keeps one candidate chosen by `recruitCarryMode` (newest_agent, chosen,
  random) and is completed with new candidates; under `chosen` the player
  marks the candidate to keep with « Garder ce candidat ».

Data (TestConfig CSVs + minimalData config):
  Controllers: Lord Alpha, Lady Beta (can_build_base=1, start_workers=1)
               Lady Delta (no base, first-come only)
  Players:     single_player/test -> Alpha (auto-selected on login)
               multi_player/test  -> Alpha + Beta (selects Alpha via accueil)
               delta_player/test  -> Delta (auto-selected on login)
  Zones:       Epsilon-Controlled (Alpha's base), Zeta-Unclaimed (Beta's base)
  Config:      recrutement_nb_choices=3, first_come_nb_choices=1

UI-only: no SQL, ids are read from the rendered pages.

The classes are order-dependent: they share state across the file (the turns
ended, Beta's 4 candidates, Echo's spent first-come allowance), so run the file
whole and in order.

Run:
    python3 -m pytest tests/test_recruit_candidates_e2e.py -v
"""
from urllib.parse import parse_qsl, urlencode, urlsplit, urlunsplit

import pytest

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, end_turn, load_minimal_data, load_scenario_via_admin, login_as,
    safe_goto, set_config_via_ui, ui_all_workers, ui_controller_ids_map,
    ui_recruit_perfect_worker, ui_zone_id,
    register_php_error_listener, assert_no_collected_php_errors,
)


REFUSED_TEXT = "Le recrutement n'est pas permis"


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module")
def ids(browser):
    """Load TestConfig once, give Alpha and Beta a base, return controller ids."""
    if DB_AVAILABLE:
        load_minimal_data()
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
    ctx = browser.new_context()
    page = ctx.new_page()
    register_php_error_listener(page)
    try:
        ensure_gm_login(page, PHP_BASE_URL)
        controllers = ui_controller_ids_map(page)
        for lastname, zone_name in (("Alpha", "Epsilon-Controlled"), ("Beta", "Zeta-Unclaimed")):
            cid = controllers[lastname]
            zid = ui_zone_id(page, zone_name)
            _select_controller(page, cid)
            safe_goto(page,
                f"{PHP_BASE_URL}/controllers/action.php"
                f"?createBase=1&controller_id={cid}&zone_id={zid}"
            )
        assert_no_collected_php_errors(page)
    finally:
        ctx.close()
    return controllers


def _select_controller(page, controller_id):
    """Select a faction through the accueil flow (sets the session controller)."""
    safe_goto(page,
        f"{PHP_BASE_URL}/base/accueil.php?controller_id={controller_id}&chosir=Choisir"
    )


def _player_page(browser, username, password="test"):
    """Open a fresh context logged in as `username`; returns (context, page)."""
    ctx = browser.new_context()
    page = ctx.new_page()
    register_php_error_listener(page)
    login_as(page, PHP_BASE_URL, username, password)
    return ctx, page


def _recruit_url(controller_id, recruit_type):
    """URL of the recruitment page as the agents page buttons build it."""
    url = f"{PHP_BASE_URL}/workers/new.php?controller_id={controller_id}"
    if recruit_type == "recrutement":
        url += "&recrutement=true"
    return url


def _read_candidates(page, controller_id, recruit_type):
    """Open the recruitment page and return its candidates sorted by id.

    Each candidate is {candidate_id, firstname, lastname, origin_id, name}.
    Fails explicitly when the page refuses the recruitment or when a form
    lacks its hidden `candidate_id` input."""
    safe_goto(page, _recruit_url(controller_id, recruit_type))
    content = page.content()
    assert REFUSED_TEXT not in content, (
        f"The {recruit_type} page must not be refused on display: showing the "
        f"candidates spends nothing (url {page.url})"
    )
    forms = page.locator("div.workers form")
    assert forms.count() > 0, (
        f"The {recruit_type} page must render at least one candidate form"
    )
    candidates = []
    for form in forms.all():
        cid_input = form.locator("input[name='candidate_id']")
        assert cid_input.count() == 1, (
            "Each candidate form must carry exactly one hidden candidate_id input "
            f"(found {cid_input.count()})"
        )
        candidate_id = cid_input.get_attribute("value")
        assert candidate_id and candidate_id.isdigit(), (
            f"candidate_id must be a stored row id, got {candidate_id!r}"
        )
        candidates.append({
            "candidate_id": int(candidate_id),
            "firstname": form.locator("input[name='firstname']").get_attribute("value"),
            "lastname": form.locator("input[name='lastname']").get_attribute("value"),
            "origin_id": form.locator("input[name='origin_id']").get_attribute("value"),
            "name": form.locator("strong").first.inner_text().strip(),
        })
    return sorted(candidates, key=lambda c: c["candidate_id"])


def _ids_of(candidates):
    return [c["candidate_id"] for c in candidates]


class TestRecrutementCandidatesStable:
    """Alpha's recruitment set: stable across reloads, display spends nothing."""

    def test_reload_shows_same_candidates_and_spends_nothing(self, browser, ids):
        """single_player (Alpha) opens the recruitment page three times.

        Locks: the set holds recrutement_nb_choices (3) candidates, each with a
        stored candidate_id; a reload shows the identical set; the third visit
        is still allowed. Goes red when the page redraws on render (ids/names
        change), when the hidden candidate_id is missing, or when the display
        increments turn_recruited_workers (Alpha has start_workers=1 at turn 0,
        so the second visit would answer « pas permis »)."""
        ctx, page = _player_page(browser, "single_player")
        try:
            first = _read_candidates(page, ids["Alpha"], "recrutement")
            assert len(first) == 3, (
                f"recrutement_nb_choices is 3, the page must offer 3 candidates; got {len(first)}"
            )
            assert len(set(_ids_of(first))) == 3, "candidate ids must be distinct"

            second = _read_candidates(page, ids["Alpha"], "recrutement")
            assert second == first, (
                "A reload must offer the same stored candidates (ids and names);\n"
                f"first={first}\nsecond={second}"
            )

            third = _read_candidates(page, ids["Alpha"], "recrutement")
            assert third == first, "A third visit must still offer the same candidates"
            assert_no_collected_php_errors(page)
        finally:
            ctx.close()


class TestRecrutementCandidatesShared:
    """Two players of the same controller share the stored set."""

    def test_two_players_of_alpha_see_the_same_set(self, browser, ids):
        """single_player and multi_player (Alpha selected) open Alpha's
        recruitment page and must see the same candidate ids and names.

        Goes red when each render draws its own candidates or when the
        candidate_id hidden input is missing."""
        single_ctx, single_page = _player_page(browser, "single_player")
        multi_ctx, multi_page = _player_page(browser, "multi_player")
        try:
            single_set = _read_candidates(single_page, ids["Alpha"], "recrutement")
            assert len(single_set) == 3, "Alpha's player must be offered 3 candidates"

            _select_controller(multi_page, ids["Alpha"])
            multi_set = _read_candidates(multi_page, ids["Alpha"], "recrutement")
            assert multi_set == single_set, (
                "Both players of Alpha must see the same stored candidates;\n"
                f"single_player={single_set}\nmulti_player={multi_set}"
            )
            assert_no_collected_php_errors(single_page)
            assert_no_collected_php_errors(multi_page)
        finally:
            single_ctx.close()
            multi_ctx.close()


class TestFirstComeCandidates:
    """First-come page: one stored candidate, stable, no base needed."""

    def test_first_come_candidate_is_stored_and_stable(self, browser, ids):
        """delta_player (Delta, no base) opens the first-come page twice.

        Locks: first_come_nb_choices (1) stored candidate with a candidate_id,
        identical on reload. Goes red when the candidate is redrawn, when the
        candidate_id input is missing, or when the display increments
        turn_firstcome_workers (the reload would then answer « pas permis »)."""
        ctx, page = _player_page(browser, "delta_player")
        try:
            first = _read_candidates(page, ids["Delta"], "first_come")
            assert len(first) == 1, (
                f"first_come_nb_choices is 1, the page must offer 1 candidate; got {len(first)}"
            )
            second = _read_candidates(page, ids["Delta"], "first_come")
            assert second == first, (
                "A reload of the first-come page must offer the same stored candidate;\n"
                f"first={first}\nsecond={second}"
            )
            assert_no_collected_php_errors(page)
        finally:
            ctx.close()


class TestRecruitMissingCandidates:
    """recruitMissingCandidates: generate tops up, do_nothing leaves the set."""

    def test_generate_tops_up_and_do_nothing_does_not(self, browser, ids):
        """gm with Beta selected; Beta has a base and no recruitment this turn.

        1. default (generate): 3 stored, raise recrutement_nb_choices to 4 ->
           the 3 same ids plus exactly one new one;
        2. do_nothing + recrutement_nb_choices=5 -> still the same 4.
        Config is restored at the end. Goes red when the set is redrawn (ids
        change), not topped up under generate, or topped up under do_nothing."""
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        try:
            ensure_gm_login(page, PHP_BASE_URL)
            _select_controller(page, ids["Beta"])
            initial = _read_candidates(page, ids["Beta"], "recrutement")
            assert len(initial) == 3, f"Beta must start with 3 candidates; got {len(initial)}"

            set_config_via_ui(page, "recrutement_nb_choices", "4")
            topped = _read_candidates(page, ids["Beta"], "recrutement")
            assert len(topped) == 4, (
                f"generate must draw the missing candidate up to 4; got {len(topped)}"
            )
            assert set(_ids_of(initial)) < set(_ids_of(topped)), (
                "The 3 stored candidates must still be offered after the top-up;\n"
                f"initial={_ids_of(initial)}\ntopped={_ids_of(topped)}"
            )
            kept = [c for c in topped if c["candidate_id"] in _ids_of(initial)]
            assert kept == initial, "The stored candidates must keep their names"

            set_config_via_ui(page, "recruitMissingCandidates", "do_nothing")
            set_config_via_ui(page, "recrutement_nb_choices", "5")
            untouched = _read_candidates(page, ids["Beta"], "recrutement")
            assert untouched == topped, (
                "do_nothing must not draw the missing candidate: the 4 stored stay as they are;\n"
                f"before={_ids_of(topped)}\nafter={_ids_of(untouched)}"
            )
            assert_no_collected_php_errors(page)
        finally:
            try:
                set_config_via_ui(page, "recrutement_nb_choices", "3")
            finally:
                try:
                    set_config_via_ui(page, "recruitMissingCandidates", "generate")
                finally:
                    ctx.close()


class TestOrganiserNeedsAFaction:
    """The gm must select a faction before opening the recruitment page."""

    def test_gm_without_faction_is_refused_and_with_faction_follows_player_path(self, browser, ids):
        """A fresh gm session (7 controllers, none auto-selected) gets 403 on
        Alpha's recruitment page; after selecting Alpha it gets 200 and the
        same stored candidates as Alpha's player.

        Goes red when the privileged bypass lets the gm in with no faction
        selected, or when the gm path draws its own candidates."""
        gm_ctx = browser.new_context()
        gm_page = gm_ctx.new_page()
        register_php_error_listener(gm_page)
        player_ctx, player_page = _player_page(browser, "single_player")
        try:
            login_as(gm_page, PHP_BASE_URL, "gm", "orga")
            response = gm_page.goto(_recruit_url(ids["Alpha"], "recrutement"))
            status = response.status if response is not None else None
            assert status == 403, (
                f"The gm with no faction selected must be refused on workers/new.php; got {status}"
            )

            _select_controller(gm_page, ids["Alpha"])
            response = gm_page.goto(_recruit_url(ids["Alpha"], "recrutement"))
            status = response.status if response is not None else None
            assert status == 200, (
                f"The gm with Alpha selected must reach the recruitment page; got {status}"
            )
            gm_set = _read_candidates(gm_page, ids["Alpha"], "recrutement")
            player_set = _read_candidates(player_page, ids["Alpha"], "recrutement")
            assert gm_set == player_set, (
                "The gm with Alpha selected must see Alpha's stored candidates;\n"
                f"gm={gm_set}\nplayer={player_set}"
            )
            assert_no_collected_php_errors(gm_page)
            assert_no_collected_php_errors(player_page)
        finally:
            gm_ctx.close()
            player_ctx.close()


# ---------------------------------------------------------------------------
# Recruitment through a stored candidate (workers/action.php creation branch)
# ---------------------------------------------------------------------------

ECHO_FIRSTCOME_ALLOWANCE = "2"
# Larger than 3 : a first-come origin may hold a single name, three distinct names need a wider draw.
ECHO_FIRSTCOME_CHOICES = "10"


def _offer(page, controller_id, recruit_type):
    """Open the recruitment page without asserting on it.

    Returns {refused, candidates, urls}: candidates as in `_read_candidates`
    (sorted by id), urls maps each candidate_id to the creation URL its form
    submits (FormData of the form, first zone, no transformation)."""
    safe_goto(page, _recruit_url(controller_id, recruit_type))
    offer = {"refused": REFUSED_TEXT in page.content(), "candidates": [], "urls": {}}
    for form in page.locator("div.workers form").all():
        cid_input = form.locator("input[name='candidate_id']")
        if cid_input.count() != 1:
            continue
        candidate_id = int(cid_input.get_attribute("value"))
        offer["candidates"].append({
            "candidate_id": candidate_id,
            "firstname": form.locator("input[name='firstname']").get_attribute("value"),
            "lastname": form.locator("input[name='lastname']").get_attribute("value"),
            "origin_id": form.locator("input[name='origin_id']").get_attribute("value"),
            "name": form.locator("strong").first.inner_text().strip(),
        })
        offer["urls"][candidate_id] = form.evaluate(
            """(form) => {
                const data = new FormData(form);
                data.delete('transformation');
                data.set('chosir', 'Recruter et Affecter');
                return form.action + '?' + new URLSearchParams(data).toString();
            }"""
        )
    offer["candidates"].sort(key=lambda c: c["candidate_id"])
    return offer


def _with_fields(url, drop=(), **fields):
    """`url` with `fields` set over its query and the `drop` names removed."""
    parts = urlsplit(url)
    query = [(k, v) for k, v in parse_qsl(parts.query, keep_blank_values=True)
             if k not in drop and k not in fields]
    query += list(fields.items())
    return urlunsplit(parts._replace(query=urlencode(query)))


def _visit(page, url):
    """Open `url` and return (status, content)."""
    response = page.goto(url)
    page.wait_for_load_state("load")
    return (response.status if response is not None else None), page.content()


def _agents(gm, controller_id):
    """Agents serving `controller_id`, read off the gm's agent list (one row per id)."""
    rows = {}
    for row in ui_all_workers(gm, PHP_BASE_URL):
        if int(row["controller_id"]) == int(controller_id):
            rows.setdefault(row["id"], row)
    return list(rows.values())


def _named(agents, candidate, firstname=None):
    """Agents among `agents` carrying the candidate's lastname and `firstname` (default: the candidate's)."""
    firstname = candidate["firstname"] if firstname is None else firstname
    return [a for a in agents if a["lastname"] == candidate["lastname"] and a["firstname"] == firstname]


def _three_distinct(candidates):
    """The first three candidates by id whose (firstname, lastname, origin_id) differ."""
    picked = []
    for candidate in candidates:
        key = (candidate["firstname"], candidate["lastname"], candidate["origin_id"])
        if all(key != (p["firstname"], p["lastname"], p["origin_id"]) for p in picked):
            picked.append(candidate)
        if len(picked) == 3:
            break
    return picked


@pytest.fixture(scope="class")
def candidate_recruitment(browser, ids):
    """Echo (first-come only, no recruitment before) recruits through its stored candidates.

    turn_firstcome_workers is raised to 2 and first_come_nb_choices to 10 before
    Echo first opens its page, both restored to 1 at the end. c1 < c2 < c3 are
    the first three candidates by id with distinct names (a replay is found by
    name). Sequence: forged URLs (Delta's candidate id, an invented id, no id),
    c1 with a stale firstname (refused), c1 with a hobby that is not c1's, reopen, c2,
    reopen, c3, c1's original URL, then turn_firstcome_workers raised to 3 and
    Echo's page reopened (is c3 still offered after its refused attempt?)."""
    state = {}
    contexts = []
    gm = None
    try:
        gm_ctx = browser.new_context(); contexts.append(gm_ctx)
        gm = gm_ctx.new_page()
        register_php_error_listener(gm)
        ensure_gm_login(gm, PHP_BASE_URL)

        delta_ctx, delta = _player_page(browser, "delta_player"); contexts.append(delta_ctx)
        delta_offer = _offer(delta, ids["Delta"], "first_come")
        state["delta_offer"] = delta_offer

        set_config_via_ui(gm, "turn_firstcome_workers", ECHO_FIRSTCOME_ALLOWANCE)
        set_config_via_ui(gm, "first_come_nb_choices", ECHO_FIRSTCOME_CHOICES)

        echo_ctx, echo = _player_page(browser, "echo_player"); contexts.append(echo_ctx)
        first = _offer(echo, ids["Echo"], "first_come")
        state["first"] = first
        picked = _three_distinct(first["candidates"])
        state["picked"] = picked
        if len(picked) < 3 or not delta_offer["candidates"]:
            yield state
            return
        c1, c2, c3 = picked
        c1_url = first["urls"][c1["candidate_id"]]
        # c1's block names its hobby and job as "Name (enquete, attack/defence)", like the hobby options.
        c1_text = echo.locator(
            "div.workers", has=echo.locator(f"input[name='candidate_id'][value='{c1['candidate_id']}']")
        ).inner_text()
        safe_goto(gm, f"{PHP_BASE_URL}/admin/admin.php")
        gm.wait_for_load_state("load")
        hobby_options = [
            ((opt.inner_text() or "").strip(), opt.get_attribute("value") or "")
            for opt in gm.locator("select#power_hobby_id option").all()
        ]
        hobby_options = [(label, value) for label, value in hobby_options if value and label]
        c1_hobbies = sorted((label for label, _ in hobby_options if label in c1_text), key=len)
        state["c1_hobby_label"] = c1_hobbies[-1] if c1_hobbies else None
        forged_hobby = next(((label, value) for label, value in hobby_options if label not in c1_text), None)
        state["forged_hobby_label"] = forged_hobby[0] if forged_hobby else None
        state["agents_start"] = _agents(gm, ids["Echo"])

        forged = {
            "other_controller": _with_fields(c1_url, candidate_id=str(delta_offer["candidates"][0]["candidate_id"])),
            "invented": _with_fields(c1_url, candidate_id="999999"),
            "missing": _with_fields(c1_url, drop=("candidate_id",)),
        }
        for label, url in forged.items():
            state[f"forged_{label}_status"], _ = _visit(echo, url)
            state[f"agents_after_{label}"] = _agents(gm, ids["Echo"])
        state["after_forged"] = _offer(echo, ids["Echo"], "first_come")

        # A page opened before « Régénérer » posts an identity the stored row no longer has.
        state["stale_status"], _ = _visit(echo, _with_fields(c1_url, firstname="Forgedname"))
        state["agents_after_stale"] = _agents(gm, ids["Echo"])
        state["after_stale"] = _offer(echo, ids["Echo"], "first_come")

        state["agents_before_tampered"] = _agents(gm, ids["Echo"])
        tampered = {}
        if forged_hobby is not None:
            tampered["power_hobby_id"] = forged_hobby[1]
        state["tampered_status"], _ = _visit(echo, _with_fields(c1_url, **tampered))
        state["agents_after_tampered"] = _agents(gm, ids["Echo"])
        # The gm list (still open) holds each agent's job and hobby in its folded « Disciplines » div.
        new_ids = {a["id"] for a in state["agents_after_tampered"]} - {a["id"] for a in state["agents_before_tampered"]}
        state["tampered_agent_text"] = None
        if len(new_ids) == 1:
            powers_div = gm.locator(f"#disciplines-{new_ids.pop()}")
            if powers_div.count() == 1:
                state["tampered_agent_text"] = " ".join((powers_div.text_content() or "").split())
        state["after_c1"] = _offer(echo, ids["Echo"], "first_come")

        state["agents_before_c2"] = _agents(gm, ids["Echo"])
        state["c2_status"], _ = _visit(echo, first["urls"][c2["candidate_id"]])
        state["agents_after_c2"] = _agents(gm, ids["Echo"])
        state["after_c2"] = _offer(echo, ids["Echo"], "first_come")

        state["agents_before_c3"] = _agents(gm, ids["Echo"])
        state["c3_status"], state["c3_html"] = _visit(echo, first["urls"][c3["candidate_id"]])
        state["agents_after_c3"] = _agents(gm, ids["Echo"])

        state["agents_before_replay"] = _agents(gm, ids["Echo"])
        state["replay_status"], state["replay_html"] = _visit(echo, c1_url)
        state["agents_after_replay"] = _agents(gm, ids["Echo"])

        # A third slot reopens the page : c3 is offered again only if its refused attempt was rolled back.
        set_config_via_ui(gm, "turn_firstcome_workers", "3")
        state["after_raise"] = _offer(echo, ids["Echo"], "first_come")
        assert_no_collected_php_errors(echo)
        yield state
    finally:
        try:
            if gm is not None:
                set_config_via_ui(gm, "turn_firstcome_workers", "1")
                set_config_via_ui(gm, "first_come_nb_choices", "1")
        finally:
            for context in contexts:
                context.close()


class TestRecruitThroughStoredCandidate:
    """A player recruits only through a candidate stored for its own
    controller; the names come from the stored row, the allowance is checked
    and spent at creation, and the recruited candidate leaves the set."""

    def test_echo_is_offered_three_distinct_candidates(self, candidate_recruitment):
        """Precondition of the sequence: Echo's first-come page is allowed and
        offers at least three candidates with distinct names, and Delta's
        page offers a stored candidate to forge with."""
        state = candidate_recruitment
        assert not state["first"]["refused"], "Echo's first-come page must be allowed before any recruitment"
        assert len(state["picked"]) == 3, (
            "Echo's first-come page must offer three candidates with distinct names; "
            f"got {state['first']['candidates']}"
        )
        assert state["delta_offer"]["candidates"], "Delta's first-come page must offer its stored candidate"

    # Three plain tests rather than a parametrized one : pytest would rebuild the class fixture for each value.
    def test_forged_candidate_id_is_refused_other_controller(self, candidate_recruitment):
        self._assert_forged_refused(candidate_recruitment, "other_controller")

    def test_forged_candidate_id_is_refused_invented(self, candidate_recruitment):
        self._assert_forged_refused(candidate_recruitment, "invented")

    def test_forged_candidate_id_is_refused_missing(self, candidate_recruitment):
        self._assert_forged_refused(candidate_recruitment, "missing")

    @staticmethod
    def _assert_forged_refused(state, label):
        """c1's URL with candidate_id set to Delta's stored candidate, to an
        invented id, or removed: refused with 403 and no Echo agent created.
        Positive anchor: Echo's page still offers c1, c2 and c3 afterwards.
        Goes red while action.php creates the agent from the URL's fields."""
        if len(state.get("picked", [])) < 3:
            pytest.fail("Echo's sequence did not start (see test_echo_is_offered_three_distinct_candidates)")
        assert state[f"forged_{label}_status"] == 403, (
            f"A creation URL with a {label} candidate_id must be refused with 403; "
            f"got {state[f'forged_{label}_status']}"
        )
        assert len(state[f"agents_after_{label}"]) == len(state["agents_start"]), (
            f"A {label} candidate_id must not create an agent for Echo; "
            f"{len(state['agents_start'])} -> {len(state[f'agents_after_{label}'])}"
        )
        offered_ids = set(_ids_of(state["after_forged"]["candidates"]))
        assert set(_ids_of(state["picked"])) <= offered_ids, (
            "After the refused URLs, Echo's page must still offer c1, c2 and c3; "
            f"picked={_ids_of(state['picked'])} offered={sorted(offered_ids)}"
        )

    def test_stale_identity_is_refused(self, candidate_recruitment):
        """c1's URL with a firstname that is not c1's stored one (a page opened
        before « Régénérer »): refused with 403, no agent created, and Echo's
        page still offers c1. Goes red while action.php trusts the id alone."""
        state = candidate_recruitment
        if len(state.get("picked", [])) < 3:
            pytest.fail("Echo's sequence did not start (see test_echo_is_offered_three_distinct_candidates)")
        assert state["stale_status"] == 403, f"A stale identity must be refused with 403; got {state['stale_status']}"
        assert len(state["agents_after_stale"]) == len(state["agents_start"]), (
            f"A stale identity must not create an agent; {len(state['agents_start'])} -> {len(state['agents_after_stale'])}"
        )
        assert state["picked"][0]["candidate_id"] in _ids_of(state["after_stale"]["candidates"]), (
            "After the refused URL, Echo's page must still offer c1"
        )

    def test_tampered_hobby_is_ignored(self, candidate_recruitment):
        """c1's URL with power_hobby_id set to a hobby that is not c1's: the
        agent is created (count +1) under c1's stored names and holds c1's
        stored hobby, not the forged one.
        Goes red while action.php takes the powers from the URL."""
        state = candidate_recruitment
        if len(state.get("picked", [])) < 3:
            pytest.fail("Echo's sequence did not start (see test_echo_is_offered_three_distinct_candidates)")
        c1 = state["picked"][0]
        before, after = state["agents_before_tampered"], state["agents_after_tampered"]
        assert state["tampered_status"] == 200, f"c1's recruitment must succeed; got {state['tampered_status']}"
        assert len(after) == len(before) + 1, f"c1's recruitment must create one agent; {len(before)} -> {len(after)}"
        assert _named(after, c1), f"Echo must hold an agent named {c1['firstname']} {c1['lastname']}; got {after}"
        assert state["c1_hobby_label"], "c1's proposal must name a hobby among the admin hobby options"
        assert state["forged_hobby_label"], "The admin hobby options must offer a hobby that is not c1's"
        agent_text = state["tampered_agent_text"]
        assert agent_text, "The gm list must show the agent c1's recruitment created"
        assert state["c1_hobby_label"] in agent_text, (
            f"The new agent must hold c1's stored hobby {state['c1_hobby_label']!r}; got {agent_text!r}"
        )
        assert state["forged_hobby_label"] not in agent_text, (
            f"The new agent must not hold the forged hobby {state['forged_hobby_label']!r}; got {agent_text!r}"
        )

    def test_recruited_candidate_leaves_the_set(self, candidate_recruitment):
        """After c1's recruitment Echo's page offers every other stored
        candidate, same ids and names, and neither c1 nor a replacement.
        Goes red while the candidate stays in the table."""
        state = candidate_recruitment
        if len(state.get("picked", [])) < 3:
            pytest.fail("Echo's sequence did not start (see test_echo_is_offered_three_distinct_candidates)")
        c1 = state["picked"][0]
        expected = [c for c in state["first"]["candidates"] if c["candidate_id"] != c1["candidate_id"]]
        assert not state["after_c1"]["refused"], "One recruitment of an allowance of 2 must leave the page allowed"
        assert state["after_c1"]["candidates"] == expected, (
            "After c1's recruitment the page must offer exactly the other stored candidates;\n"
            f"expected={_ids_of(expected)}\noffered={_ids_of(state['after_c1']['candidates'])}"
        )

    def test_second_recruitment_spends_the_allowance(self, candidate_recruitment):
        """c2's URL creates an agent (count +1); reopening the page then
        answers « Le recrutement n'est pas permis » (allowance 2 spent at
        creation). Goes red while creation does not increment turn_firstcome_workers."""
        state = candidate_recruitment
        if len(state.get("picked", [])) < 3:
            pytest.fail("Echo's sequence did not start (see test_echo_is_offered_three_distinct_candidates)")
        c2 = state["picked"][1]
        before, after = state["agents_before_c2"], state["agents_after_c2"]
        assert state["c2_status"] == 200, f"c2's recruitment must succeed; got {state['c2_status']}"
        assert len(after) == len(before) + 1, f"c2's recruitment must create one agent; {len(before)} -> {len(after)}"
        assert _named(after, c2), f"Echo must hold an agent named {c2['firstname']} {c2['lastname']}"
        assert state["after_c2"]["refused"], (
            "Two first-come recruitments spend the allowance of 2: the page must answer « pas permis »; "
            f"offered={_ids_of(state['after_c2']['candidates'])}"
        )

    def test_recruitment_beyond_the_allowance_is_refused(self, candidate_recruitment):
        """c3's stored URL, once the allowance is spent: refused with 403 and
        « Le recrutement n'est pas permis », no agent created, none under c3's
        name. Positive anchor: c2's recruitment just before succeeded.
        The refusal rolls the candidate claim back: once the allowance is
        raised to 3, Echo's page offers c3 again (positive) and neither c1
        nor c2, both recruited (negative). Goes red while creation does not
        check the allowance, or deletes the candidate of a refused attempt."""
        state = candidate_recruitment
        if len(state.get("picked", [])) < 3:
            pytest.fail("Echo's sequence did not start (see test_echo_is_offered_three_distinct_candidates)")
        c3 = state["picked"][2]
        assert state["c2_status"] == 200, "c2's recruitment, just before, must have succeeded"
        before, after = state["agents_before_c3"], state["agents_after_c3"]
        assert state["c3_status"] == 403, f"A recruitment beyond the allowance must be refused with 403; got {state['c3_status']}"
        assert REFUSED_TEXT in state["c3_html"], (
            f"A recruitment beyond the allowance must answer « {REFUSED_TEXT} »"
        )
        assert len(after) == len(before), f"No agent may be created beyond the allowance; {len(before)} -> {len(after)}"
        assert len(_named(after, c3)) == len(_named(before, c3)), (
            f"No agent named {c3['firstname']} {c3['lastname']} may be created beyond the allowance"
        )
        raised = state["after_raise"]
        offered_ids = set(_ids_of(raised["candidates"]))
        assert not raised["refused"], "With turn_firstcome_workers raised to 3, Echo's page must be allowed again"
        assert c3["candidate_id"] in offered_ids, (
            "The refused attempt must leave c3 stored: Echo's page must offer it again; "
            f"c3={c3['candidate_id']} offered={sorted(offered_ids)}"
        )
        recruited = {state["picked"][0]["candidate_id"], state["picked"][1]["candidate_id"]}
        assert not recruited & offered_ids, (
            f"c1 and c2, both recruited, must not be offered again; recruited={sorted(recruited)} offered={sorted(offered_ids)}"
        )

    def test_replay_shows_the_recruited_agent(self, candidate_recruitment):
        """c1's original URL, reopened after c1 left the set: 200, the page
        shows c1's agent, the agent count is unchanged. Locks the replay path."""
        state = candidate_recruitment
        if len(state.get("picked", [])) < 3:
            pytest.fail("Echo's sequence did not start (see test_echo_is_offered_three_distinct_candidates)")
        c1 = state["picked"][0]
        assert _named(state["agents_before_replay"], c1), "c1's agent must exist before the replay"
        assert state["replay_status"] == 200, f"A replay must show the agent already recruited; got {state['replay_status']}"
        assert c1["firstname"] in state["replay_html"] and c1["lastname"] in state["replay_html"], (
            f"The replay must show {c1['firstname']} {c1['lastname']}"
        )
        assert len(state["agents_after_replay"]) == len(state["agents_before_replay"]), (
            f"A replay must not create an agent; {len(state['agents_before_replay'])} -> {len(state['agents_after_replay'])}"
        )


class TestPerfectAgentOutsideCandidates:
    """« Create Perfect Agent » consumes no candidate and no allowance."""

    def test_perfect_agent_leaves_candidates_and_allowance(self, browser, ids):
        """gm with Beta selected recruits a perfect agent for Beta: the agent
        exists, and Beta's recruitment page still offers the same candidate ids
        and is still allowed. Goes red if the perfect path consumes a
        candidate or increments turn_recruited_workers."""
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        try:
            ensure_gm_login(page, PHP_BASE_URL)
            _select_controller(page, ids["Beta"])
            before = _read_candidates(page, ids["Beta"], "recrutement")
            ui_recruit_perfect_worker(page, ids["Beta"], "Zeta-Unclaimed", "PerfectBeta",
                                      "Blank Slate", "Common Folk", base_url=PHP_BASE_URL)
            created = [a for a in _agents(page, ids["Beta"]) if a["lastname"] == "PerfectBeta"]
            assert len(created) == 1, f"The perfect agent must be created for Beta; got {created}"
            after = _read_candidates(page, ids["Beta"], "recrutement")
            assert _ids_of(after) == _ids_of(before), (
                "A perfect agent must not consume a stored candidate;\n"
                f"before={_ids_of(before)}\nafter={_ids_of(after)}"
            )
            assert_no_collected_php_errors(page)
        finally:
            ctx.close()


# ---------------------------------------------------------------------------
# End-of-turn carry-over of the stored candidates (recruitCarryMode)
# ---------------------------------------------------------------------------

KEEP_BUTTON = "Garder ce candidat"
KEPT_TEXT = "Gardé pour le prochain tour"


def _parse_blocks(page):
    """Candidate blocks of the page currently open, sorted by candidate_id.

    One entry per `div.workers` holding a creation form: {candidate_id,
    keep_button, kept}. keep_button: the block shows « Garder ce candidat »;
    kept: the block shows « Gardé pour le prochain tour »."""
    blocks = []
    for block in page.locator("div.workers").all():
        cid_input = block.locator("form:has(input[name='creation']) input[name='candidate_id']")
        if cid_input.count() != 1:
            continue
        blocks.append({
            "candidate_id": int(cid_input.get_attribute("value")),
            "keep_button": block.get_by_role("button", name=KEEP_BUTTON, exact=True).count() > 0,
            "kept": KEPT_TEXT in block.inner_text(),
        })
    return sorted(blocks, key=lambda b: b["candidate_id"])


def _carry_offer(page, controller_id, recruit_type):
    """Open the recruitment page and return {refused, blocks} (see `_parse_blocks`)."""
    safe_goto(page, _recruit_url(controller_id, recruit_type))
    return {"refused": REFUSED_TEXT in page.content(), "blocks": _parse_blocks(page)}


def _block_ids(offer):
    return [b["candidate_id"] for b in offer["blocks"]]


def _mark(page, controller_id, candidate_id):
    """Click « Garder ce candidat » in `candidate_id`'s block of the recrutement page.

    Returns {clicked, url, blocks}: url and blocks are read off the page the
    POST lands on, without reopening the recruitment page."""
    safe_goto(page, _recruit_url(controller_id, "recrutement"))
    for block in page.locator("div.workers").all():
        cid_input = block.locator("form:has(input[name='creation']) input[name='candidate_id']")
        if cid_input.count() != 1 or int(cid_input.get_attribute("value")) != candidate_id:
            continue
        button = block.get_by_role("button", name=KEEP_BUTTON, exact=True)
        if button.count() == 0:
            break
        with page.expect_navigation():
            button.first.click()
        page.wait_for_load_state("load")
        return {"clicked": True, "url": page.url, "blocks": _parse_blocks(page)}
    return {"clicked": False, "url": page.url, "blocks": []}


def _forge_mark(page, controller_id, candidate_id):
    """POST the real « Garder ce candidat » form of `controller_id`'s recrutement
    page through `page`'s session, with keep_candidate_id set to `candidate_id`.

    The field names and values are read off the first keep form of the page;
    only keep_candidate_id is replaced. Returns {fields, status} or None when
    the page offers no keep form."""
    url = _recruit_url(controller_id, "recrutement")
    safe_goto(page, url)
    keep_form = page.locator("form:has(input[name='keep_candidate_id'])")
    if keep_form.count() == 0:
        return None
    fields = keep_form.first.evaluate(
        """form => Object.fromEntries(Array.from(form.querySelectorAll('input[name]'))
            .map(input => [input.name, input.value]))"""
    )
    fields["keep_candidate_id"] = str(candidate_id)
    response = page.request.post(url, form=fields)
    return {"fields": fields, "status": response.status}


def _assert_keeps(before, after, label, expected_kept=None):
    """`after` holds 3 candidates: exactly one id of `before` (`expected_kept`
    when given), the others new ids above every id of `before`."""
    old_ids = _block_ids(before)
    new_ids = _block_ids(after)
    assert not before["refused"] and old_ids, f"{label}: the page must offer candidates before the end of turn"
    assert not after["refused"], f"{label}: the allowance is reset at the end of turn, the page must be allowed"
    assert len(new_ids) == 3, f"{label}: recrutement_nb_choices is 3, the page must offer 3 candidates; got {new_ids}"
    survivors = [i for i in new_ids if i in old_ids]
    assert len(survivors) == 1, (
        f"{label}: exactly one stored candidate must stay over the end of turn;\n"
        f"before={old_ids}\nafter={new_ids}"
    )
    if expected_kept is not None:
        assert survivors == [expected_kept], (
            f"{label}: the candidate kept must be {expected_kept}; kept {survivors}"
        )
    fresh = [i for i in new_ids if i not in old_ids]
    assert all(i > max(old_ids) for i in fresh), (
        f"{label}: the other candidates must be new draws (ids above {max(old_ids)}); got {fresh}"
    )


def _assert_none_kept(before, after, label):
    """`after` holds 3 new candidates, none of `before`'s ids."""
    old_ids = _block_ids(before)
    new_ids = _block_ids(after)
    assert not before["refused"] and old_ids, f"{label}: the page must offer candidates before the end of turn"
    assert not after["refused"], f"{label}: the allowance is reset at the end of turn, the page must be allowed"
    assert len(new_ids) == 3, f"{label}: recrutement_nb_choices is 3, the page must offer 3 candidates; got {new_ids}"
    assert not set(new_ids) & set(old_ids), (
        f"{label}: with nothing marked no stored candidate may stay;\nbefore={old_ids}\nafter={new_ids}"
    )
    assert all(i > max(old_ids) for i in new_ids), (
        f"{label}: every candidate must be a new draw (ids above {max(old_ids)}); got {new_ids}"
    )


def _observe_all(state, key, gm, single, delta, echo, ids):
    """Record Alpha's and Beta's recrutement offers, Delta's and Echo's first-come offers under `key`."""
    state[key] = {"alpha": _carry_offer(single, ids["Alpha"], "recrutement")}
    _select_controller(gm, ids["Beta"])
    state[key]["beta"] = _carry_offer(gm, ids["Beta"], "recrutement")
    if delta is not None:
        state[key]["delta"] = _carry_offer(delta, ids["Delta"], "first_come")
    if echo is not None:
        state[key]["echo"] = _carry_offer(echo, ids["Echo"], "first_come")


@pytest.fixture(scope="class")
def carry_newest(browser, ids):
    """Default recruitCarryMode (newest_agent): one end of turn (0 -> 1).

    Records Alpha's (single_player) and Beta's (gm, Beta selected) recrutement
    offers, Delta's and Echo's first-come offers, before and after. Echo's
    leftovers are read with turn_firstcome_workers raised to 3, restored to 1
    before the end of turn.

    The end of turn runs under recruitMissingCandidates=do_nothing (restored to
    generate afterwards), so the 'after' reads show what the end of turn stored
    instead of the page's own top-up. A page still draws when nothing is stored
    (stored == 0 always draws), so the first-come reads do not prove the end of
    turn drew them; only the keep-cases prove its top-up to recrutement_nb_choices."""
    state = {}
    contexts = []
    gm = None
    try:
        gm_ctx = browser.new_context(); contexts.append(gm_ctx)
        gm = gm_ctx.new_page()
        register_php_error_listener(gm)
        ensure_gm_login(gm, PHP_BASE_URL)
        single_ctx, single = _player_page(browser, "single_player"); contexts.append(single_ctx)
        delta_ctx, delta = _player_page(browser, "delta_player"); contexts.append(delta_ctx)
        echo_ctx, echo = _player_page(browser, "echo_player"); contexts.append(echo_ctx)

        # Echo spent its first-come allowance this turn : raised for one read of its leftovers.
        set_config_via_ui(gm, "turn_firstcome_workers", "3")
        try:
            _observe_all(state, "before", gm, single, delta, echo, ids)
        finally:
            set_config_via_ui(gm, "turn_firstcome_workers", "1")
        set_config_via_ui(gm, "recruitMissingCandidates", "do_nothing")
        end_turn(gm, PHP_BASE_URL)
        _observe_all(state, "after", gm, single, delta, echo, ids)
        yield state
    finally:
        try:
            if gm is not None:
                set_config_via_ui(gm, "recruitMissingCandidates", "generate")
        finally:
            for context in contexts:
                context.close()


class TestCarryOverNewestAgent:
    """newest_agent: the highest stored id stays, first-come sets are redrawn."""

    def test_alpha_keeps_its_newest_candidate(self, carry_newest):
        """Alpha's 3 stored candidates: after the end of turn only the highest
        id stays, completed by 2 new ids. Positive anchor: the page is allowed
        at turn 1 and offers 3 candidates. Goes red while no end-of-turn step
        touches the candidates (all 3 old ids survive)."""
        before, after = carry_newest["before"]["alpha"], carry_newest["after"]["alpha"]
        _assert_keeps(before, after, "Alpha", expected_kept=max(_block_ids(before)))

    def test_beta_keeps_its_newest_candidate(self, carry_newest):
        """Beta's 4 stored candidates (recrutement_nb_choices was 4 when drawn):
        only the highest id stays and the set goes back to 3. Goes red while
        the 4 old ids survive the end of turn."""
        before, after = carry_newest["before"]["beta"], carry_newest["after"]["beta"]
        assert len(_block_ids(before)) == 4, f"Beta must hold 4 stored candidates before; got {_block_ids(before)}"
        _assert_keeps(before, after, "Beta", expected_kept=max(_block_ids(before)))

    def test_delta_first_come_is_redrawn(self, carry_newest):
        """Delta's stored first-come candidate is discarded: the page offers
        first_come_nb_choices (1) new candidate. Positive anchor: the page is
        allowed and offers one candidate. Goes red while the old one survives."""
        self._assert_redrawn(carry_newest, "delta", "Delta")

    def test_echo_first_come_is_redrawn(self, carry_newest):
        """Echo's first-come leftovers (drawn under first_come_nb_choices=10)
        are all discarded: the page offers exactly 1 new candidate. Goes red
        while the leftovers survive (the page still offers them all)."""
        self._assert_redrawn(carry_newest, "echo", "Echo")

    @staticmethod
    def _assert_redrawn(state, key, label):
        before, after = state["before"][key], state["after"][key]
        old_ids, new_ids = _block_ids(before), _block_ids(after)
        assert old_ids, f"{label}: the first-come page must offer stored candidates before the end of turn"
        assert not after["refused"], f"{label}: the first-come allowance is reset, the page must be allowed"
        assert len(new_ids) == 1, (
            f"{label}: first_come_nb_choices is 1, the page must offer exactly 1 candidate; got {new_ids}"
        )
        assert not set(new_ids) & set(old_ids), (
            f"{label}: every stored first-come candidate must be discarded;\nbefore={old_ids}\nafter={new_ids}"
        )


@pytest.fixture(scope="class")
def carry_chosen(browser, ids):
    """recruitCarryMode=chosen: Alpha marks two non-newest candidates in turn,
    then Alpha's player forges a mark on one of Beta's candidates; Beta marks
    nothing; one end of turn (1 -> 2). Restores newest_agent.

    The end of turn runs under recruitMissingCandidates=do_nothing (restored to
    generate afterwards), so the 'after' reads show what the end of turn stored.
    Beta's « none kept » case would still draw on display if nothing were stored
    (stored == 0 always draws): only Alpha's keep-case proves the end-of-turn
    top-up to recrutement_nb_choices."""
    state = {}
    contexts = []
    gm = None
    try:
        gm_ctx = browser.new_context(); contexts.append(gm_ctx)
        gm = gm_ctx.new_page()
        register_php_error_listener(gm)
        ensure_gm_login(gm, PHP_BASE_URL)
        single_ctx, single = _player_page(browser, "single_player"); contexts.append(single_ctx)
        delta_ctx, delta = _player_page(browser, "delta_player"); contexts.append(delta_ctx)

        state["alpha_newest_mode"] = _carry_offer(single, ids["Alpha"], "recrutement")
        set_config_via_ui(gm, "recruitCarryMode", "chosen")
        state["alpha_chosen_mode"] = _carry_offer(single, ids["Alpha"], "recrutement")
        state["delta_chosen_mode"] = _carry_offer(delta, ids["Delta"], "first_come")

        alpha_ids = _block_ids(state["alpha_chosen_mode"])
        state["first_mark"], state["second_mark"] = (alpha_ids[0], alpha_ids[1]) if len(alpha_ids) >= 3 else (None, None)
        if state["first_mark"] is not None:
            state["after_first_mark"] = _mark(single, ids["Alpha"], state["first_mark"])
            state["after_second_mark"] = _mark(single, ids["Alpha"], state["second_mark"])

        _select_controller(gm, ids["Beta"])
        beta_ids = _block_ids(_carry_offer(gm, ids["Beta"], "recrutement"))
        state["forged_target"] = beta_ids[0] if beta_ids else None
        if state["forged_target"] is not None:
            state["forged_mark"] = _forge_mark(single, ids["Alpha"], state["forged_target"])
            state["after_forged_mark"] = {"alpha": _carry_offer(single, ids["Alpha"], "recrutement")}
            _select_controller(gm, ids["Beta"])
            state["after_forged_mark"]["beta"] = _carry_offer(gm, ids["Beta"], "recrutement")

        _observe_all(state, "before", gm, single, None, None, ids)
        set_config_via_ui(gm, "recruitMissingCandidates", "do_nothing")
        end_turn(gm, PHP_BASE_URL)
        _observe_all(state, "after", gm, single, None, None, ids)
        yield state
    finally:
        try:
            if gm is not None:
                set_config_via_ui(gm, "recruitMissingCandidates", "generate")
        finally:
            try:
                if gm is not None:
                    set_config_via_ui(gm, "recruitCarryMode", "newest_agent")
            finally:
                for context in contexts:
                    context.close()


class TestCarryOverChosen:
    """chosen: the player marks the candidate to keep; nothing marked keeps none."""

    def test_keep_button_only_in_chosen_mode_on_recrutement_page(self, carry_chosen):
        """Alpha's recrutement page shows no « Garder ce candidat » under
        newest_agent and one per candidate under chosen; Delta's first-come
        page shows none under chosen. Positive anchors: each page offers its
        candidates. Goes red while the button does not exist."""
        newest, chosen, delta = (carry_chosen["alpha_newest_mode"], carry_chosen["alpha_chosen_mode"],
                                 carry_chosen["delta_chosen_mode"])
        assert newest["blocks"] and not newest["refused"], "Alpha's page must offer candidates under newest_agent"
        assert not any(b["keep_button"] for b in newest["blocks"]), (
            f"Under newest_agent the page must not offer « {KEEP_BUTTON} »; got {newest['blocks']}"
        )
        assert delta["blocks"] and not delta["refused"], "Delta's first-come page must offer its candidate"
        assert not any(b["keep_button"] for b in delta["blocks"]), (
            f"The first-come page must not offer « {KEEP_BUTTON} »; got {delta['blocks']}"
        )
        assert len(chosen["blocks"]) == 3 and all(b["keep_button"] for b in chosen["blocks"]), (
            f"Under chosen each of Alpha's 3 candidates must offer « {KEEP_BUTTON} »; got {chosen['blocks']}"
        )

    def test_marking_a_candidate_unmarks_the_previous_one(self, carry_chosen):
        """Alpha's player marks the lowest id, then the middle one. After each
        click the POST lands back on the recruitment page (same 3 candidates);
        the marked one shows « Gardé pour le prochain tour » and no button, the
        others show the button. After the second mark the first one shows the
        button again. Goes red while the button does not exist."""
        first_id, second_id = carry_chosen["first_mark"], carry_chosen["second_mark"]
        assert first_id is not None, "Alpha must offer 3 candidates under chosen to mark two of them"
        chosen_ids = _block_ids(carry_chosen["alpha_chosen_mode"])
        for key, marked_id in (("after_first_mark", first_id), ("after_second_mark", second_id)):
            result = carry_chosen[key]
            assert result["clicked"], f"« {KEEP_BUTTON} » must be offered on candidate {marked_id}"
            assert [b["candidate_id"] for b in result["blocks"]] == chosen_ids, (
                f"Marking must land back on the recruitment page with the same candidates; "
                f"url={result['url']} blocks={result['blocks']}"
            )
            for block in result["blocks"]:
                if block["candidate_id"] == marked_id:
                    assert block["kept"] and not block["keep_button"], (
                        f"Candidate {marked_id} must show « {KEPT_TEXT} » instead of the button; got {block}"
                    )
                else:
                    assert not block["kept"] and block["keep_button"], (
                        f"Only candidate {marked_id} may be marked, {block['candidate_id']} must show the button; got {block}"
                    )

    def test_mark_on_another_controllers_candidate_is_refused(self, carry_chosen):
        """After Alpha's two marks, Alpha's player POSTs the real keep form to
        Alpha's recrutement page with keep_candidate_id set to one of Beta's
        recrutement candidates. The mark is scoped to the URL's controller and
        the recruitment type, and rolled back unless exactly one row matches:
        Alpha's page still shows the middle candidate as kept (positive anchor,
        the unmark-all of the refused POST is rolled back too) and Beta's page
        (gm, Beta selected) shows no « Gardé pour le prochain tour ». Goes red
        when the mark UPDATE drops its controller scope (Beta's candidate is
        marked) or commits without checking the matched row (Alpha's mark is
        cleared)."""
        target, forged = carry_chosen["forged_target"], carry_chosen.get("forged_mark")
        assert target is not None, "Beta must offer recrutement candidates to forge a mark on one of them"
        assert forged is not None, f"Alpha's recrutement page must offer a « {KEEP_BUTTON} » form to forge"
        assert set(forged["fields"]) >= {"keep_candidate_id"}, f"The keep form must post keep_candidate_id; got {forged['fields']}"
        assert forged["status"] == 200, (
            f"The refused mark must still land back on the recruitment page; got HTTP {forged['status']}"
        )
        alpha, beta = carry_chosen["after_forged_mark"]["alpha"], carry_chosen["after_forged_mark"]["beta"]
        second_id = carry_chosen["second_mark"]
        assert not alpha["refused"] and len(alpha["blocks"]) == 3, f"Alpha's page must still offer 3 candidates; got {alpha}"
        assert [b["candidate_id"] for b in alpha["blocks"] if b["kept"]] == [second_id], (
            f"Alpha's previous mark ({second_id}) must stay unchanged after the refused POST; got {alpha['blocks']}"
        )
        assert not beta["refused"] and target in _block_ids(beta), (
            f"Beta's page must still offer the targeted candidate {target}; got {beta}"
        )
        assert not any(b["kept"] for b in beta["blocks"]), (
            f"No candidate of Beta may show « {KEPT_TEXT} » after Alpha's forged POST; got {beta['blocks']}"
        )

    def test_alpha_keeps_the_marked_candidate(self, carry_chosen):
        """After the end of turn Alpha keeps exactly the marked (middle) id,
        not the newest, completed by 2 new ids; the mark is cleared (the kept
        candidate shows the button again, no « Gardé pour le prochain tour »).
        Goes red while every stored candidate survives the end of turn."""
        before, after = carry_chosen["before"]["alpha"], carry_chosen["after"]["alpha"]
        assert carry_chosen["second_mark"] is not None, "Alpha must offer 3 candidates under chosen"
        _assert_keeps(before, after, "Alpha", expected_kept=carry_chosen["second_mark"])
        kept = [b for b in after["blocks"] if b["candidate_id"] == carry_chosen["second_mark"]][0]
        assert not kept["kept"] and kept["keep_button"], (
            f"After the carry-over the mark is cleared: the kept candidate must show the button again; got {kept}"
        )

    def test_beta_with_nothing_marked_keeps_none(self, carry_chosen):
        """Beta marked nothing under chosen: after the end of turn none of its
        stored ids stays and 3 new ones are drawn. Positive anchor: the page is
        allowed at turn 2. Goes red while the stored candidates survive."""
        _assert_none_kept(carry_chosen["before"]["beta"], carry_chosen["after"]["beta"], "Beta")


@pytest.fixture(scope="class")
def carry_random(browser, ids):
    """recruitCarryMode=random: one end of turn (2 -> 3). Restores newest_agent.

    The end of turn runs under recruitMissingCandidates=do_nothing (restored to
    generate afterwards), so the 'after' reads prove the end-of-turn top-up to
    recrutement_nb_choices (both cases keep one candidate, stored is never 0)."""
    state = {}
    contexts = []
    gm = None
    try:
        gm_ctx = browser.new_context(); contexts.append(gm_ctx)
        gm = gm_ctx.new_page()
        register_php_error_listener(gm)
        ensure_gm_login(gm, PHP_BASE_URL)
        single_ctx, single = _player_page(browser, "single_player"); contexts.append(single_ctx)

        set_config_via_ui(gm, "recruitCarryMode", "random")
        _observe_all(state, "before", gm, single, None, None, ids)
        set_config_via_ui(gm, "recruitMissingCandidates", "do_nothing")
        end_turn(gm, PHP_BASE_URL)
        _observe_all(state, "after", gm, single, None, None, ids)
        yield state
    finally:
        try:
            if gm is not None:
                set_config_via_ui(gm, "recruitMissingCandidates", "generate")
        finally:
            try:
                if gm is not None:
                    set_config_via_ui(gm, "recruitCarryMode", "newest_agent")
            finally:
                for context in contexts:
                    context.close()


class TestCarryOverRandom:
    """random: one of the stored candidates stays, whichever it is."""

    def test_alpha_keeps_one_of_its_candidates(self, carry_random):
        """Alpha keeps exactly one of its 3 stored ids, completed by 2 new ids.
        Goes red while all 3 stored candidates survive the end of turn."""
        _assert_keeps(carry_random["before"]["alpha"], carry_random["after"]["alpha"], "Alpha")

    def test_beta_keeps_one_of_its_candidates(self, carry_random):
        """Beta keeps exactly one of its stored ids, completed by new ids up to
        3. Goes red while all its stored candidates survive the end of turn."""
        _assert_keeps(carry_random["before"]["beta"], carry_random["after"]["beta"], "Beta")
