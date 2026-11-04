"""Playwright E2E tests for the organiser's recruitment candidates page (issue #219).

workers/management_candidates.php lists every stored recruitment candidate
(`{prefix}recruit_candidates`) to the organiser and lets him redraw one in
place. What these tests lock:

- admin/admin.php links the page from its « List » block as
  « Recruitment candidates »;
- the page is privileged only: a player gets neither the list nor the redraw;
- it lists the candidates of every controller, of both recruit types, the ids
  matching what the players see on workers/new.php;
- sorting by `?sort=controller` (default, also for a missing or unknown value)
  or `?sort=recruit_type`;
- « Régénérer » redraws one candidate IN PLACE: same id, same controller, same
  recruit type, no row added or removed, new data, answered by a redirect
  (POST then GET);
- a controller without base is refused a recrutement candidate (403,
  « Le recrutement n'est pas permis »), the candidate stays stored, while its
  first-come candidate is recruited.

Page contract the tests rely on:
  - h1 « Candidats au recrutement »;
  - one `<tr>` per candidate, holding a `<form method="POST">` that carries
    `input[type=hidden][name='candidate_id']` (the stored row id) and a submit
    button named « Régénérer »;
  - the controller's lastname and the recruit type (`first_come` /
    `recrutement`, exactly) are cell texts of that row; the controller cell
    comes before the candidate's name cell;
  - column-header links `?sort=controller` and `?sort=recruit_type`;
  - `<th>` headers « Tour », « Candidat », « Origine », « Métier / Loisir »,
    one per `<td>` of a candidate row (cells are read by header position).

Ordering checked: a controller is ranked by its lastname in lexical order
(in TestConfig that is also the controller id order, Alpha < Beta < ... <
Golf, so either implementation passes). Default order = rows grouped by
controller in that rank, each controller's rows grouped by recruit type,
ids increasing inside a (controller, type) group. `?sort=recruit_type` =
rows grouped by recruit type (either type first), controllers in rank inside
a type, ids increasing inside a (type, controller) group.

Data (TestConfig CSVs + minimalData config):
  Controllers: Lord Alpha, Lady Beta (can_build_base=1), Lord Charlie,
               Lady Delta, Lord Echo, Lady Foxtrot, Lord Golf (no base)
  Players:     single_player/test -> Alpha, delta_player/test -> Delta,
               gm/test -> every controller, none selected at login
  Setup:       Alpha builds its base on Epsilon-Controlled, single_player
               opens Alpha's recruitment page, delta_player Delta's first-come
               page, then one end of turn (0 -> 1) stores candidates of both
               types for every controller.

UI-only: no SQL, ids are read from the rendered pages.

Run:
    python3 -m pytest tests/test_recruit_candidates_organiser_e2e.py -v
"""
from urllib.parse import urlencode

import pytest

from conftest import GAME_PREFIX, PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, get_db_connection, end_turn, load_minimal_data, load_scenario_via_admin, login_as,
    safe_goto, ui_all_workers, ui_controller_ids_map, ui_turn_counter, ui_zone_id,
    register_php_error_listener, assert_no_collected_php_errors,
)


PAGE_URL = f"{PHP_BASE_URL}/workers/management_candidates.php"
PAGE_H1 = "Candidats au recrutement"
REGENERATE = "Régénérer"
REFUSED_TEXT = "Le recrutement n'est pas permis"
RECRUIT_TYPES = ("first_come", "recrutement")
REGENERATE_ATTEMPTS = 5
TURN_HEADER = "Tour"
CANDIDATE_DATA_HEADERS = ("Candidat", "Origine", "Métier / Loisir")


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


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


def _gm_page(browser):
    """Open a fresh context logged in as gm, no faction selected; returns (context, page)."""
    ctx = browser.new_context()
    page = ctx.new_page()
    register_php_error_listener(page)
    ensure_gm_login(page, PHP_BASE_URL)
    return ctx, page


def _recruit_url(controller_id, recruit_type):
    """URL of the recruitment page as the agents page buttons build it."""
    url = f"{PHP_BASE_URL}/workers/new.php?controller_id={controller_id}"
    if recruit_type == "recrutement":
        url += "&recrutement=true"
    return url


def _player_blocks(page, controller_id, recruit_type):
    """Open the recruitment page; return {candidate_id: {firstname, lastname, text}}."""
    safe_goto(page, _recruit_url(controller_id, recruit_type))
    blocks = {}
    for block in page.locator("div.workers").all():
        cid_input = block.locator("form:has(input[name='creation']) input[name='candidate_id']")
        if cid_input.count() != 1:
            continue
        form = cid_input.locator("xpath=ancestor::form[1]")
        blocks[int(cid_input.get_attribute("value"))] = {
            "firstname": form.locator("input[name='firstname']").get_attribute("value"),
            "lastname": form.locator("input[name='lastname']").get_attribute("value"),
            "text": " ".join(block.inner_text().split()),
        }
    return blocks


def _open_organiser(page, query=""):
    """Open the organiser page (optional query string); return the response status."""
    response = page.goto(PAGE_URL + query)
    page.wait_for_load_state("load")
    return response.status if response is not None else None


def _parse_rows(page, lastnames):
    """Candidate rows of the organiser page currently open, in page order.

    Each row is {candidate_id, controller, recruit_type, text, turn, data}:
    controller is the first cell holding a known controller lastname as a word,
    recruit_type the cell whose text is exactly a recruit type, text the row's
    text with whitespace collapsed, turn the cell under the « Tour » header and
    data the cells under the candidate headers (« Candidat », « Origine »,
    « Métier / Loisir »), None when a header is missing."""
    rows = []
    for row in page.locator("tr", has=page.locator("input[type='hidden'][name='candidate_id']")).all():
        cid_input = row.locator("input[type='hidden'][name='candidate_id']")
        if cid_input.count() != 1:
            continue
        cells = [" ".join(t.split()) for t in row.locator("td").all_inner_texts()]
        headers = [" ".join(t.split()) for t in row.locator("xpath=ancestor::table[1]//th").all_inner_texts()]
        by_header = dict(zip(headers, cells)) if len(headers) == len(cells) else {}
        controller = next((name for cell in cells for name in cell.split() if name in lastnames), None)
        recruit_type = next((cell for cell in cells if cell in RECRUIT_TYPES), None)
        rows.append({
            "candidate_id": int(cid_input.get_attribute("value")),
            "controller": controller,
            "recruit_type": recruit_type,
            "text": " ".join(row.inner_text().split()),
            "turn": by_header.get(TURN_HEADER),
            "data": (tuple(by_header[h] for h in CANDIDATE_DATA_HEADERS)
                     if all(h in by_header for h in CANDIDATE_DATA_HEADERS) else None),
        })
    return rows


def _read_organiser(page, lastnames, query=""):
    """Open the organiser page and return its rows; fails when the page is not served."""
    status = _open_organiser(page, query)
    assert status == 200, f"The organiser must reach {PAGE_URL}{query}; got HTTP {status}"
    assert page.locator("h1", has_text=PAGE_H1).count() == 1, (
        f"The organiser page must show the h1 « {PAGE_H1} » (url {page.url})"
    )
    return _parse_rows(page, lastnames)


def _row_locator(page, candidate_id):
    return page.locator(
        "tr", has=page.locator(f"input[type='hidden'][name='candidate_id'][value='{candidate_id}']")
    )


def _ids(rows, controller=None, recruit_type=None):
    return sorted(r["candidate_id"] for r in rows
                  if (controller is None or r["controller"] == controller)
                  and (recruit_type is None or r["recruit_type"] == recruit_type))


def _row_by_id(rows, candidate_id):
    return next((r for r in rows if r["candidate_id"] == candidate_id), None)


def _agents(gm, controller_id):
    """Agents serving `controller_id`, read off the gm's agent list (one row per id)."""
    rows = {}
    for row in ui_all_workers(gm, PHP_BASE_URL):
        if int(row["controller_id"]) == int(controller_id):
            rows.setdefault(row["id"], row)
    return list(rows.values())


def _is_contiguous(values):
    """Each value of `values` appears in one unbroken run."""
    seen = []
    for value in values:
        if seen and seen[-1] == value:
            continue
        if value in seen:
            return False
        seen.append(value)
    return True


def _assert_controller_order(rows, rank, label):
    """Default order: controller rank, then recruit type grouped, then id."""
    assert all(r["controller"] in rank for r in rows), (
        f"{label}: every row must name its controller; got {[r for r in rows if r['controller'] not in rank]}"
    )
    ranks = [rank[r["controller"]] for r in rows]
    assert ranks == sorted(ranks), (
        f"{label}: rows must be sorted by controller; got {[r['controller'] for r in rows]}"
    )
    for controller in rank:
        own = [r for r in rows if r["controller"] == controller]
        assert _is_contiguous([r["recruit_type"] for r in own]), (
            f"{label}: {controller}'s rows must be grouped by recruit type; "
            f"got {[(r['candidate_id'], r['recruit_type']) for r in own]}"
        )
        for recruit_type in RECRUIT_TYPES:
            group = [r["candidate_id"] for r in own if r["recruit_type"] == recruit_type]
            assert group == sorted(group), f"{label}: {controller}/{recruit_type} ids must increase; got {group}"


def _assert_recruit_type_order(rows, rank, label):
    """?sort=recruit_type: recruit type grouped, then controller rank, then id."""
    assert all(r["controller"] in rank and r["recruit_type"] in RECRUIT_TYPES for r in rows), (
        f"{label}: every row must name its controller and recruit type"
    )
    assert _is_contiguous([r["recruit_type"] for r in rows]), (
        f"{label}: rows must be grouped by recruit type; got {[r['recruit_type'] for r in rows]}"
    )
    for recruit_type in RECRUIT_TYPES:
        group = [r for r in rows if r["recruit_type"] == recruit_type]
        ranks = [rank[r["controller"]] for r in group]
        assert ranks == sorted(ranks), (
            f"{label}: inside {recruit_type}, rows must be sorted by controller; "
            f"got {[r['controller'] for r in group]}"
        )
        for controller in rank:
            ids = [r["candidate_id"] for r in group if r["controller"] == controller]
            assert ids == sorted(ids), f"{label}: {recruit_type}/{controller} ids must increase; got {ids}"


@pytest.fixture(scope="module")
def setup(browser):
    """Load TestConfig, build Alpha's base, open the players' pages, end the turn once.

    Returns {ids: {lastname: controller_id}, lastnames, rank, zone_id}."""
    if DB_AVAILABLE:
        load_minimal_data()
    load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")
    contexts = []
    try:
        gm_ctx, gm = _gm_page(browser); contexts.append(gm_ctx)
        controllers = ui_controller_ids_map(gm)
        zone_id = ui_zone_id(gm, "Epsilon-Controlled")
        _select_controller(gm, controllers["Alpha"])
        safe_goto(gm,
            f"{PHP_BASE_URL}/controllers/action.php"
            f"?createBase=1&controller_id={controllers['Alpha']}&zone_id={zone_id}"
        )
        single_ctx, single = _player_page(browser, "single_player"); contexts.append(single_ctx)
        safe_goto(single, _recruit_url(controllers["Alpha"], "recrutement"))
        delta_ctx, delta = _player_page(browser, "delta_player"); contexts.append(delta_ctx)
        safe_goto(delta, _recruit_url(controllers["Delta"], "first_come"))
        end_turn(gm, PHP_BASE_URL)
        assert_no_collected_php_errors(gm)
    finally:
        for context in contexts:
            context.close()
    lastnames = sorted(controllers)
    return {
        "ids": controllers,
        "lastnames": set(lastnames),
        "rank": {name: i for i, name in enumerate(lastnames)},
        "zone_id": zone_id,
    }


class TestAdminLink:
    """admin/admin.php links the organiser page."""

    def test_admin_links_recruitment_candidates(self, browser, setup):
        """The gm's admin page holds a « Recruitment candidates » link to
        workers/management_candidates.php, in the « List » block next to
        « Worker list » (positive anchor). Goes red when the link is missing."""
        ctx, gm = _gm_page(browser)
        try:
            safe_goto(gm, f"{PHP_BASE_URL}/admin/admin.php")
            assert gm.get_by_role("link", name="Worker list", exact=True).count() == 1, (
                "admin.php must still link « Worker list » in its List block"
            )
            link = gm.get_by_role("link", name="Recruitment candidates", exact=True)
            assert link.count() == 1, "admin.php must link « Recruitment candidates »"
            assert (link.get_attribute("href") or "").endswith("/workers/management_candidates.php"), (
                f"« Recruitment candidates » must point to workers/management_candidates.php; "
                f"got {link.get_attribute('href')!r}"
            )
        finally:
            ctx.close()


class TestOrganiserList:
    """The gm, no faction selected, lists every stored candidate."""

    def test_lists_every_controller_and_both_types(self, browser, setup):
        """Locks: h1, rows for Alpha, Beta, Charlie and Delta in both recruit
        types (the end of turn stores both for every controller, with or
        without base), a « Régénérer » button on each row, distinct ids, and
        Alpha's recrutement ids equal to those single_player sees on Alpha's
        recruitment page. Goes red when the page is missing, filters by the
        session faction or lists ids the players do not see."""
        gm_ctx, gm = _gm_page(browser)
        single_ctx, single = _player_page(browser, "single_player")
        try:
            rows = _read_organiser(gm, setup["lastnames"])
            assert rows, "The organiser page must list the stored candidates"
            ids = [r["candidate_id"] for r in rows]
            assert len(ids) == len(set(ids)), f"Each candidate must be listed once; got {ids}"
            for controller in ("Alpha", "Beta", "Charlie", "Delta"):
                for recruit_type in RECRUIT_TYPES:
                    assert _ids(rows, controller, recruit_type), (
                        f"{controller} must have {recruit_type} candidates listed; "
                        f"got {[(r['controller'], r['recruit_type']) for r in rows]}"
                    )
            for row in rows:
                buttons = _row_locator(gm, row["candidate_id"]).get_by_role("button", name=REGENERATE, exact=True)
                assert buttons.count() == 1, (
                    f"Candidate {row['candidate_id']}'s row must hold one « {REGENERATE} » button"
                )
            alpha_listed = _ids(rows, "Alpha", "recrutement")
            alpha_seen = sorted(_player_blocks(single, setup["ids"]["Alpha"], "recrutement"))
            assert alpha_seen, "single_player must be offered Alpha's recrutement candidates"
            assert alpha_listed == alpha_seen, (
                "The organiser must list the same Alpha recrutement candidates as Alpha's player sees;\n"
                f"organiser={alpha_listed}\nplayer={alpha_seen}"
            )
            assert_no_collected_php_errors(gm)
        finally:
            gm_ctx.close()
            single_ctx.close()


class TestOrganiserSort:
    """Sorting by controller (default, whitelisted) or by recruit type."""

    def test_sort_by_controller_and_by_recruit_type(self, browser, setup):
        """Locks: no param, ?sort=controller and a bogus ?sort=evil give the
        same controller-grouped order; ?sort=recruit_type groups by recruit
        type; the two orders differ. Both sort links are in the page. Goes red
        when the page is missing, a sort is ignored, or an unknown value is
        not brought back to the default."""
        ctx, gm = _gm_page(browser)
        try:
            rank = setup["rank"]
            default = _read_organiser(gm, setup["lastnames"])
            for query in ("?sort=controller", "?sort=recruit_type"):
                assert gm.locator(f"a[href*='{query}']").count() >= 1, f"The page must link {query}"
            _assert_controller_order(default, rank, "default")
            by_controller = _read_organiser(gm, setup["lastnames"], "?sort=controller")
            _assert_controller_order(by_controller, rank, "sort=controller")
            bogus = _read_organiser(gm, setup["lastnames"], "?sort=evil")
            _assert_controller_order(bogus, rank, "sort=evil")
            by_type = _read_organiser(gm, setup["lastnames"], "?sort=recruit_type")
            _assert_recruit_type_order(by_type, rank, "sort=recruit_type")

            def order(rows):
                return [r["candidate_id"] for r in rows]
            assert order(by_controller) == order(default) == order(bogus), (
                "No param, sort=controller and an unknown sort must give the same order"
            )
            assert sorted(order(by_type)) == sorted(order(default)), "Both orders must list the same candidates"
            assert order(by_type) != order(default), (
                "sort=recruit_type must order the rows differently from the default"
            )
            assert_no_collected_php_errors(gm)
        finally:
            ctx.close()


class TestOrganiserRegenerate:
    """« Régénérer » redraws one candidate in place."""

    def test_regenerate_redraws_alpha_candidate_in_place(self, browser, setup):
        """The gm clicks « Régénérer » on Alpha's first recrutement row (up to
        5 times, a draw may repeat on TestConfig's small pools).

        Locks: the POST answers with a redirect to a GET; the same id stays
        listed with the same controller and recruit type; no row is added or
        removed; the candidate's data (« Candidat », « Origine », « Métier /
        Loisir » cells, not the « Tour » cell, which moves when the row was
        carried over) changes; the « Tour » cell is the current turn after the
        redraw; single_player's recruitment page shows that same candidate_id
        under the new name. Goes red when the page is missing, the redraw
        inserts a new row, nothing is redrawn, or the turn is not restamped."""
        gm_ctx, gm = _gm_page(browser)
        single_ctx, single = _player_page(browser, "single_player")
        try:
            alpha = setup["ids"]["Alpha"]
            current_turn = ui_turn_counter(gm, PHP_BASE_URL)
            rows = _read_organiser(gm, setup["lastnames"])
            target = _ids(rows, "Alpha", "recrutement")
            assert target, "Alpha must have a recrutement candidate listed"
            target = target[0]
            before = _row_by_id(rows, target)
            assert before["data"] is not None, (
                f"The organiser table must hold the headers {CANDIDATE_DATA_HEADERS}; row {before}"
            )
            before_ids = sorted(r["candidate_id"] for r in rows)
            player_before = _player_blocks(single, alpha, "recrutement")
            assert target in player_before, f"single_player must be offered candidate {target}"

            after = None
            for _ in range(REGENERATE_ATTEMPTS):
                button = _row_locator(gm, target).get_by_role("button", name=REGENERATE, exact=True)
                assert button.count() == 1, f"Candidate {target}'s row must hold a « {REGENERATE} » button"
                with gm.expect_navigation() as navigation:
                    button.click()
                gm.wait_for_load_state("load")
                response = navigation.value
                assert response is not None and response.request.method == "GET", (
                    "« Régénérer » must answer with a redirect to a GET (POST then GET)"
                )
                assert response.request.redirected_from is not None, (
                    "« Régénérer » must redirect after the POST"
                )
                rows = _read_organiser(gm, setup["lastnames"])
                after = _row_by_id(rows, target)
                assert after is not None, f"Candidate {target} must still be listed after « {REGENERATE} »"
                assert (after["controller"], after["recruit_type"]) == ("Alpha", "recrutement"), (
                    f"The redrawn candidate must keep its controller and recruit type; got {after}"
                )
                assert sorted(r["candidate_id"] for r in rows) == before_ids, (
                    "« Régénérer » must update the row in place: no row added or removed"
                )
                if after["data"] != before["data"]:
                    break
            assert after["data"] != before["data"], (
                f"{REGENERATE_ATTEMPTS} redraws never changed candidate {target}: {before['data']!r}"
            )
            assert after["turn"] == str(current_turn), (
                f"The redrawn candidate must be stamped with the current turn {current_turn}; "
                f"« {TURN_HEADER} » cell is {after['turn']!r}"
            )

            player_after = _player_blocks(single, alpha, "recrutement")
            assert sorted(player_after) == sorted(player_before), (
                f"single_player must still be offered the same ids; before={sorted(player_before)} "
                f"after={sorted(player_after)}"
            )
            redrawn = player_after[target]
            assert redrawn["text"] != player_before[target]["text"], (
                "single_player's page must show the redrawn candidate, not the old one"
            )
            assert redrawn["firstname"] in after["text"] and redrawn["lastname"] in after["text"], (
                f"single_player's candidate {target} ({redrawn['firstname']} {redrawn['lastname']}) "
                f"must be the one the organiser lists: {after['text']!r}"
            )
            assert_no_collected_php_errors(gm)
        finally:
            gm_ctx.close()
            single_ctx.close()


class TestOrganiserRefusedToPlayer:
    """A player gets neither the list nor the redraw."""

    def test_player_gets_no_list_and_cannot_regenerate(self, browser, setup):
        """single_player opens the page (no h1, no candidate row) and posts the
        gm's « Régénérer » form for one of Alpha's candidates, several times;
        each POST is redirected to the login form and the gm reads that candidate
        unchanged. Positive anchor: the gm reads the list before and after.
        Goes red when the page is missing for the gm, or when a player session
        reaches the list or redraws a candidate."""
        gm_ctx, gm = _gm_page(browser)
        single_ctx, single = _player_page(browser, "single_player")
        try:
            rows = _read_organiser(gm, setup["lastnames"])
            target = _ids(rows, "Alpha", "recrutement")
            assert target, "Alpha must have a recrutement candidate listed"
            target = target[0]
            before = _row_by_id(rows, target)
            # The body the page itself would post, submit button included.
            form = _row_locator(gm, target).locator("form", has=gm.locator("input[name='candidate_id']"))
            post = form.evaluate(
                """(form) => {
                    const submitter = Array.from(form.querySelectorAll('input[type=submit], button'))
                        .find(b => (b.value || b.textContent || '').trim() === 'Régénérer');
                    const data = submitter ? new FormData(form, submitter) : new FormData(form);
                    return {action: form.action, body: new URLSearchParams(data).toString()};
                }"""
            )

            status = _open_organiser(single)
            assert single.locator("h1", has_text=PAGE_H1).count() == 0, (
                f"A player must not get the organiser page (status {status}, url {single.url})"
            )
            assert not _parse_rows(single, setup["lastnames"]), "A player must not see the candidate rows"

            # Posted several times: a lost guard redrawing an identical candidate once
            # would otherwise go unseen on TestConfig's small pools. The redirect is
            # read unfollowed, as the login form itself forwards a logged-in player
            # to accueil.php.
            for attempt in range(REGENERATE_ATTEMPTS):
                response = single.request.post(
                    post["action"], data=post["body"],
                    headers={"Content-Type": "application/x-www-form-urlencoded"},
                    max_redirects=0,
                )
                location = response.headers.get("location", "")
                assert response.status in (301, 302, 303) and location.endswith("/connection/loginForm.php"), (
                    f"A player's POST #{attempt + 1} must be redirected to the login form; "
                    f"got HTTP {response.status}, Location {location!r}"
                )

            after = _row_by_id(_read_organiser(gm, setup["lastnames"]), target)
            assert after is not None, f"Candidate {target} must still be listed"
            assert after["text"] == before["text"], (
                f"A player's POST must not redraw candidate {target};\nbefore={before['text']!r}\nafter={after['text']!r}"
            )
            assert_no_collected_php_errors(gm)
        finally:
            gm_ctx.close()
            single_ctx.close()


def _identity_query(candidate_id):
    """The identity fields the recruitment form posts for a stored candidate (read from the DB)."""
    conn = get_db_connection(); cur = conn.cursor()
    cur.execute(f"SELECT firstname, lastname, origin_id FROM `{GAME_PREFIX}recruit_candidates` WHERE id=%s", (candidate_id,))
    row = cur.fetchone()
    cur.close(); conn.close()
    return urlencode({"firstname": row["firstname"], "lastname": row["lastname"], "origin_id": row["origin_id"]})


class TestNoBaseNoRecrutement:
    """A controller without base is refused a recrutement candidate."""

    @pytest.mark.db
    def test_charlie_without_base_recruits_first_come_only(self, browser, setup):
        """The gm selects Charlie (no base) and sends the creation URL of a
        Charlie recrutement candidate read off the organiser page: 403,
        « Le recrutement n'est pas permis », no Charlie agent, the candidate
        still listed. Positive anchor: the same gm recruits Charlie's
        first-come candidate the same way (200, one more Charlie agent).
        Goes red when the page is missing or the base check is lost."""
        ctx, gm = _gm_page(browser)
        try:
            charlie = setup["ids"]["Charlie"]
            rows = _read_organiser(gm, setup["lastnames"])
            recrutement = _ids(rows, "Charlie", "recrutement")
            first_come = _ids(rows, "Charlie", "first_come")
            assert recrutement and first_come, (
                f"Charlie must have candidates of both types listed; recrutement={recrutement} first_come={first_come}"
            )
            agents_start = _agents(gm, charlie)
            _select_controller(gm, charlie)

            def creation(candidate_id):
                return (f"{PHP_BASE_URL}/workers/action.php?creation=true&candidate_id={candidate_id}"
                        f"&controller_id={charlie}&zone_id={setup['zone_id']}&chosir=Recruter+et+Affecter&{_identity_query(candidate_id)}")

            response = gm.goto(creation(recrutement[0]))
            gm.wait_for_load_state("load")
            status = response.status if response is not None else None
            assert status == 403, f"A recrutement without base must be refused with 403; got {status}"
            assert REFUSED_TEXT in gm.content(), f"The refusal must say « {REFUSED_TEXT} »"
            assert len(_agents(gm, charlie)) == len(agents_start), "The refused recruitment must create no agent"
            assert recrutement[0] in _ids(_read_organiser(gm, setup["lastnames"]), "Charlie", "recrutement"), (
                f"The refused candidate {recrutement[0]} must stay stored"
            )

            response = gm.goto(creation(first_come[0]))
            gm.wait_for_load_state("load")
            status = response.status if response is not None else None
            assert status == 200, f"Charlie's first-come recruitment needs no base and must succeed; got {status}"
            assert len(_agents(gm, charlie)) == len(agents_start) + 1, (
                "Charlie's first-come recruitment must create one agent"
            )
        finally:
            ctx.close()
