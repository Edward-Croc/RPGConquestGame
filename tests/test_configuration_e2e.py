"""Playwright E2E tests for /base/configuration.php Add/Update/Delete buttons.

Covers UI buttons previously NOT COVERED in
tests/AUDIT_ui_buttons_outside_management.md (base/configuration.php).

Sequential CRUD on a unique row: add → update → delete. Tests run in
definition order (pytest default); the class-scope browser context +
config_name keep the chain pointed at the same row.

Run:
    python3 -m pytest tests/test_configuration_e2e.py -v
"""
import re
import time
import pytest
from playwright.sync_api import Page, expect

from conftest import PHP_BASE_URL
from helpers import (
    DB_AVAILABLE, clear_ui_caches, end_turn, load_minimal_data, load_scenario_via_admin, login_as, safe_goto,
    set_config_via_ui, ui_controller_id, ui_turn_counter, worker_report_html,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(autouse=True)
def ensure_base_data():
    if DB_AVAILABLE:
        load_minimal_data()
    yield


@pytest.fixture(scope="class")
def testconfig_loaded(browser, base_url):
    """The guide tests read and reset TestConfig keys : they load TestConfig first, never touching another game."""
    load_scenario_via_admin(browser, base_url, "TestConfig")


@pytest.fixture(scope="class")
def config_name():
    """Unique name per class run — avoids collision with existing config rows."""
    return f"test_slice9_config_{int(time.time() * 1000)}"


@pytest.fixture(scope="class")
def gm_session(browser):
    """Class-scope gm browser context so the add → update → delete chain
    runs against a single session/row."""
    context = browser.new_context()
    page = context.new_page()
    login_as(page, PHP_BASE_URL, "gm", "orga")
    yield page
    context.close()


class TestConfigurationCRUD:
    """Add a row, update its value, delete it — exercising all three submit
    buttons on /base/configuration.php."""

    def test_add_config_value(self, gm_session: Page, base_url, config_name):
        safe_goto(gm_session, f"{base_url}/base/configuration.php")
        add_form = gm_session.locator("form:has(input[name='add_config'])")
        add_form.locator("input[name='name']").fill(config_name)
        add_form.locator("input[name='value']").fill("initial_value")
        add_form.locator("input[name='add_config']").click()
        gm_session.wait_for_load_state("load")
        expect(
            gm_session.locator(f"td:has-text('{config_name}')")
        ).to_have_count(1)

    def test_update_config_value(self, gm_session: Page, base_url, config_name):
        # Note: the row's `<form>` is inside `<tr>`, which is invalid HTML —
        # the parser strips/repositions it. Locate by the row's tr/td
        # instead; clicking the named submit still POSTs the row.
        safe_goto(gm_session, f"{base_url}/base/configuration.php")
        row = gm_session.locator(f"tr:has(td:has-text('{config_name}'))")
        expect(row).to_have_count(1)
        row.locator("input[name='value']").fill("updated_value")
        row.locator("input[name='update_config']").click()
        gm_session.wait_for_load_state("load")
        row_after = gm_session.locator(f"tr:has(td:has-text('{config_name}'))")
        expect(row_after.locator("input[name='value']")).to_have_value(
            "updated_value"
        )

    def test_delete_config_value(self, gm_session: Page, base_url, config_name):
        safe_goto(gm_session, f"{base_url}/base/configuration.php")
        row = gm_session.locator(f"tr:has(td:has-text('{config_name}'))")
        expect(row).to_have_count(1)
        row.locator("input[name='delete_config']").click()
        gm_session.wait_for_load_state("load")
        expect(
            gm_session.locator(f"td:has-text('{config_name}')")
        ).to_have_count(0)


class TestUiTheme:
    """uiTheme names a file of base/themes/ : the pages load it after Bulma,
    and a name matching no file loads nothing."""

    def test_theme_follows_the_configuration_key(self, gm_session: Page, base_url):
        sepia_marker = "--color-chrome: #3b2a1e"
        try:
            set_config_via_ui(gm_session, "uiTheme", "sepia", base_url=base_url)
            safe_goto(gm_session, f"{base_url}/base/accueil.php")
            assert sepia_marker in gm_session.content(), "uiTheme=sepia should load base/themes/sepia.css"

            # A path built from the name would inline base/style.css a second time.
            set_config_via_ui(gm_session, "uiTheme", "../style", base_url=base_url)
            safe_goto(gm_session, f"{base_url}/base/accueil.php")
            html = gm_session.content()
            assert "--color-chrome: #333" in html, "the default colours must still load"
            assert html.count("--color-chrome:") == 1, "a name matching no theme file must load no stylesheet"
            assert sepia_marker not in html, "a name matching no theme file must load no theme"
        finally:
            set_config_via_ui(gm_session, "uiTheme", "", base_url=base_url)


class TestGuideCaptures:
    """guideCaptures names a folder of base/guide/ : the player guide takes each
    capture from it, else from base/guide/testconfig/, and a name matching no
    folder keeps the testconfig captures."""

    def test_guide_captures_follow_the_configuration_key(self, gm_session: Page, base_url):
        def capture_sources():
            safe_goto(gm_session, f"{base_url}/base/systemPresentation.php")
            sources = gm_session.locator("figure.guide-capture img").evaluate_all(
                "imgs => imgs.map(i => i.getAttribute('src'))")
            # The captures load lazily : fetch them rather than read their rendered size.
            origin = base_url.split("/", 3)
            for src in sources:
                assert gm_session.request.get(f"{origin[0]}//{origin[2]}{src}").status == 200, f"{src} should be served"
            return sources

        try:
            # A scenario loaded before the suite may have left its own set in the key.
            set_config_via_ui(gm_session, "guideCaptures", "", base_url=base_url)
            default_sources = capture_sources()
            assert default_sources and all("/base/guide/testconfig/" in src for src in default_sources), \
                f"an empty guideCaptures should show the testconfig captures, got {default_sources}"

            set_config_via_ui(gm_session, "guideCaptures", "japon1555", base_url=base_url)
            japon_sources = capture_sources()
            assert len(japon_sources) == len(default_sources), "a set should replace the captures, not add or drop any"
            assert all("/base/guide/japon1555/" in src for src in japon_sources), \
                f"guideCaptures=japon1555 should show its own captures, got {japon_sources}"

            set_config_via_ui(gm_session, "guideCaptures", "../guide", base_url=base_url)
            assert capture_sources() == default_sources, "a name matching no folder should keep the testconfig captures"
        finally:
            set_config_via_ui(gm_session, "guideCaptures", "", base_url=base_url)

class TestGuideRules:
    """The rules section of the player guide describes the claim and location
    attack modes the game is set to, with the configured thresholds."""

    def test_guide_rules_follow_the_active_modes(self, testconfig_loaded, gm_session: Page, base_url):
        def rules_text():
            safe_goto(gm_session, f"{base_url}/base/systemPresentation.php")
            return gm_session.locator(".box.guide-section", has_text="Pour les curieux").text_content()

        try:
            # A scenario loaded before the suite may have left its own modes.
            set_config_via_ui(gm_session, "claimMode", "worker", base_url=base_url)
            set_config_via_ui(gm_session, "locationAttackMode", "immediate", base_url=base_url)
            set_config_via_ui(gm_session, "REPORTDIFF3", "7", base_url=base_url)
            text = rules_text()
            assert "tente sa chance seul" in text and "agissent ensemble" not in text, \
                "claimMode=worker should describe the claim of a single agent"
            assert "C'est votre faction qui attaque" in text and "Ce sont vos agents qui attaquent" not in text, \
                "locationAttackMode=immediate should describe the attack by the faction"
            assert "écart d'au moins 7 : le nom de la faction" in text, "the guide should show the configured REPORTDIFF3"
            assert "Un agent qui surveille ou enquête observe" in text, \
                "without agent location attacks, defending a location must not be named as an observing action"

            set_config_via_ui(gm_session, "baseDiscoveryDiff", "9", base_url=base_url)
            set_config_via_ui(gm_session, "attackTimeWindow", "0", base_url=base_url)
            set_config_via_ui(gm_session, "baseDiscoveryDiffAddPowers", "0", base_url=base_url)
            set_config_via_ui(gm_session, "maxBonusDiscoveryDiffTurns", "0", base_url=base_url)
            text = rules_text()
            assert "par agent, jusqu'à +4" in text, "a capped component should state its cap"
            assert "les maîtrises de cette faction" not in text, "a multiplier of 0 should remove its component"
            assert "son ancienneté (+0,5 par Tour)" in text, "a cap of 0 means no cap and should not be stated"
            assert "elle part de 9" in text, "the base discovery difficulty should follow baseDiscoveryDiff"
            assert "aussi longtemps après qu'on l'a vu" in text, "attackTimeWindow=0 should describe an unlimited window"

            set_config_via_ui(gm_session, "investigateActionsList", "'investigate'", base_url=base_url)
            assert "Un agent qui enquête observe" in rules_text(), "the observing actions should follow investigateActionsList"
            # An unusable list falls back to INVESTIGATE_ACTIONS_DEFAULT, as the investigation itself does.
            set_config_via_ui(gm_session, "investigateActionsList", "'not_an_action'", base_url=base_url)
            assert "Un agent qui surveille ou enquête observe" in rules_text(), \
                "an unusable investigateActionsList should show the default observing actions"

            set_config_via_ui(gm_session, "investigateActionsList", "'passive','investigate','defend_location'",
                              base_url=base_url)
            set_config_via_ui(gm_session, "claimMode", "worker_leader", base_url=base_url)
            set_config_via_ui(gm_session, "locationAttackMode", "agent_attack_defence", base_url=base_url)
            text = rules_text()
            assert "agissent ensemble" in text and "tente sa chance seul" not in text, \
                "claimMode=worker_leader should describe the claim led by a group"
            assert "Ce sont vos agents qui attaquent" in text and "C'est votre faction qui attaque" not in text, \
                "locationAttackMode=agent_attack_defence should describe the assault by agents"
            assert "Un agent qui surveille, enquête ou défend le lieu observe" in text, \
                "with agent location attacks, defending a location observes too"

            # A passive action shows the fixed value (PASSIVEVAL 3) and its flat bonus added up into one number.
            set_config_via_ui(gm_session, "HIDE_DEFENCE_FLAT_BONUS", "0", base_url=base_url)
            set_config_via_ui(gm_session, "DEFEND_LOCATION_DEFENCE_FLAT_BONUS", "-1", base_url=base_url)
            safe_goto(gm_session, f"{base_url}/base/systemPresentation.php")
            page_text = " ".join(gm_session.locator("body").text_content().split())
            assert "Se cacher : pas d'enquête. Valeur de discrétion = 7 + enquête, valeur de défense = 3 + défense." in page_text, \
                "hiding should add HIDE_ENQUETE_FLAT_BONUS 4 to the fixed value, and a bonus of 0 should leave it alone"
            assert re.search(r"Défendre le lieu : valeur d'enquête = [^,]+, valeur de défense = 2 \+ défense\.", page_text), \
                "a negative bonus should be taken from the fixed value"
            # An active action keeps its die, and the bonus with its sign.
            set_config_via_ui(gm_session, "activeDefenceActions", "'defend_location'", base_url=base_url)
            safe_goto(gm_session, f"{base_url}/base/systemPresentation.php")
            page_text = " ".join(gm_session.locator("body").text_content().split())
            # The die follows MINROLL / MAXROLL : « 1d6 », or « un dé de 3 à 3 » in TestConfig.
            assert re.search(r"Défendre le lieu : valeur d'enquête = [^,]+, valeur de défense = (1d\d+|un dé de \d+ à \d+) \+ défense − 1\.", page_text), \
                "an active action should show the die, followed by its bonus with its sign"
            # The bonus of a held zone (DEFENCE_ZONE_BONUS 1 by default) is named under the formulas, and only when set.
            assert "ses valeurs gagnent +1 de défense." in page_text, "the held-zone defence bonus should be named"
            assert "un bonus quand votre faction tient le zone : +1 de défense ;" in page_text, \
                "the rules should give the held-zone bonus values"
            set_config_via_ui(gm_session, "DEFENCE_ZONE_BONUS", "0", base_url=base_url)
            safe_goto(gm_session, f"{base_url}/base/systemPresentation.php")
            page_text = " ".join(gm_session.locator("body").text_content().split())
            assert "ses valeurs gagnent" not in page_text, "with no zone bonus, no sentence should name one"
            assert "un bonus quand votre faction tient le" not in page_text, "with no zone bonus, the rules should not list one"
        finally:
            set_config_via_ui(gm_session, "DEFENCE_ZONE_BONUS", "1", base_url=base_url)
            set_config_via_ui(gm_session, "claimMode", "worker", base_url=base_url)
            set_config_via_ui(gm_session, "locationAttackMode", "immediate", base_url=base_url)
            set_config_via_ui(gm_session, "REPORTDIFF3", "4", base_url=base_url)
            set_config_via_ui(gm_session, "baseDiscoveryDiff", "3", base_url=base_url)
            set_config_via_ui(gm_session, "baseDiscoveryDiffAddPowers", "1", base_url=base_url)
            set_config_via_ui(gm_session, "HIDE_DEFENCE_FLAT_BONUS", "1", base_url=base_url)
            set_config_via_ui(gm_session, "DEFEND_LOCATION_DEFENCE_FLAT_BONUS", "1", base_url=base_url)
            set_config_via_ui(gm_session, "activeDefenceActions", "", base_url=base_url)
            set_config_via_ui(gm_session, "maxBonusDiscoveryDiffTurns", "3", base_url=base_url)
            set_config_via_ui(gm_session, "attackTimeWindow", "1", base_url=base_url)
            set_config_via_ui(gm_session, "investigateActionsList", "'passive','investigate','defend_location'",
                              base_url=base_url)

class TestGuideVocabulary:
    """The player guide names the base with the first locationTypeNames pair, and
    states the recruitment limits of turn_recrutable_workers and turn_firstcome_workers."""

    def test_guide_names_the_base_and_the_recruitment_limits(self, testconfig_loaded, gm_session: Page, base_url):
        def guide_text():
            safe_goto(gm_session, f"{base_url}/base/systemPresentation.php")
            return " ".join(gm_session.locator("body").text_content().split())

        def faction_text():
            alpha_id = ui_controller_id(gm_session, "Alpha")
            safe_goto(gm_session, f"{base_url}/base/accueil.php?controller_id={alpha_id}&chosir=Choisir")
            return " ".join(gm_session.locator("body").text_content().split())

        try:
            text = guide_text()
            assert "Votre base : à construire" in text, "the minimalData list should name the base « base »"
            assert "recruter 1 serviteur et prendre 1 premier venu" in text, "the guide should state the default limits"
            assert "Votre Base :" in faction_text(), "the faction page should name the base « base » by default"

            set_config_via_ui(gm_session, "locationTypeNames", '[["forteresse", "forteresse"], ["temple", "temple"]]',
                              base_url=base_url)
            set_config_via_ui(gm_session, "turn_recrutable_workers", "2", base_url=base_url)
            text = guide_text()
            assert "Votre forteresse : à construire" in text and "Votre base" not in text, \
                "the guide should name the base with the first pair of locationTypeNames"
            assert "recruter 2 serviteurs et prendre 1 premier venu" in text, \
                "the guide should follow turn_recrutable_workers, in the plural from 2 on"
            text = faction_text()
            assert "Votre Forteresse :" in text and "Votre Base" not in text, \
                "the faction page should name the base with the first pair of locationTypeNames"

            # An unusable list falls back to the base type, as createBase does.
            set_config_via_ui(gm_session, "locationTypeNames", "not json", base_url=base_url)
            assert "Votre base : à construire" in guide_text(), "an unusable locationTypeNames should fall back on « base »"
        finally:
            set_config_via_ui(gm_session, "locationTypeNames", '[["base", "base"], ["temple", "temple"]]',
                              base_url=base_url)
            set_config_via_ui(gm_session, "turn_recrutable_workers", "1", base_url=base_url)


class TestMalformedTemplate:
    """A template edited with one placeholder too many no longer stops the end
    of turn nor breaks a page : the reader gets the template as typed, followed
    by its values. Covers a plain site (the investigation report) and a composite
    one (the agent history, which inserts the job and hobby sentence as %6$s)."""

    def test_end_of_turn_survives_a_malformed_template(self, browser, base_url):
        load_scenario_via_admin(browser, base_url, "TestConfig")
        clear_ui_caches()
        context = browser.new_context()
        page = context.new_page()
        try:
            login_as(page, base_url, "gm", "orga")
            history_head = "Originaire de <strong>origine Commune</strong>, "
            assert history_head + "is a <strong>Common Folk" in worker_report_html(page, "Searcher_1", base_url=base_url), \
                "the agent history should insert the job and hobby sentence as its sixth value"
            # textViewWorkerJobHobby receives five values : %7$s has none.
            set_config_via_ui(page, "textViewWorkerJobHobby", "MALFORMED %1$s %7$s", base_url=base_url)
            assert history_head + "MALFORMED %1$s %7$s [origine Commune, " in worker_report_html(page, "Searcher_1", base_url=base_url), \
                "the agent history should show the sentence template as typed, followed by its values"

            turn_before = ui_turn_counter(page, base_url=base_url)
            # textesStartInvestigate receives the zone name only : %2$s has no value.
            set_config_via_ui(page, "textesStartInvestigate", "MALFORMED %1$s %2$s", base_url=base_url)
            end_turn(page, base_url=base_url)
            assert ui_turn_counter(page, base_url=base_url) == turn_before + 1, \
                "the end of turn must complete despite the malformed template"
            report_html = worker_report_html(page, "Searcher_1", base_url=base_url)
            assert "MALFORMED %1$s %2$s [" in report_html, \
                "the investigator's report should show the template as typed, followed by its values"
        finally:
            context.close()
            load_scenario_via_admin(browser, base_url, "TestConfig")
            clear_ui_caches()
