"""Issue #128 — an owner knows every location it owns, not just its base.

UI-only (no pymysql): runnable under UI_ONLY=1.

TestConfig fixtures used (var/csv/setupTestConfig_locations.csv):
  - Golf-Chapel  (Golf, is_base=0, can_be_repaired=1)  → rules 1 and 2
  - Golf-Shrine  (Golf, is_base=0, can_be_repaired=0,
                  activate_json → "Golf-Shrine-Ruined" can_be_repaired=1)
                                                        → rule 3
  - Echo-Base    (Echo, is_base=1)  → rule 2 positive (base secret is known)
  - Charlie      owns nothing       → rule 1 negative (no repair form at all)

Both Golf locations carry discovery_diff=9 so no investigating agent in
Theta-Artefacts can discover them: the owner's knowledge under test comes
from the scenario-load CKL seed, never from an enquiry.

Rules under test (issue #128):
  1. A row exists in controller_known_locations for every owned location,
     so the owner finds its own non-base ruin in the repair dropdown.
  2. found_secret is per-row: config-driven for a base, always false for a
     non-base owned location.
  3. A swap that turns an owned location into a ruin keeps the CKL row
     (the location id is unchanged), so the owner still finds it.
"""

import re

import pytest
from playwright.sync_api import Page

from conftest import PHP_BASE_URL, ensure_gm_login

from helpers import (
    as_controller,
    assert_no_collected_php_errors,
    clear_ui_caches,
    load_scenario_via_admin,
    register_php_error_listener,
    safe_goto,
    ui_known_locations_for_controller,
    ui_known_secret_locations_for_controller,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


# ---------------------------------------------------------------------------
# UI helpers
# ---------------------------------------------------------------------------

def _repair_option_labels(page, controller_lastname):
    """Return the real option labels of a controller's repair dropdown.

    The placeholder option (value="") is dropped. Returns [] when the repair
    form is not rendered at all — showRepairableControllerKnownLocations()
    returns null and controllers/view.php emits nothing, so absence of the
    select is the only observable for "nothing repairable"."""
    ensure_gm_login(page, PHP_BASE_URL)
    as_controller(page, controller_lastname, base_url=PHP_BASE_URL)
    safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
    page.wait_for_load_state("load")
    options = page.locator("select#repairLocationSelect option").all()
    return [
        (opt.inner_text() or "").strip()
        for opt in options
        if (opt.get_attribute("value") or "") != ""
    ]


def _own_pages_html(page, controller_lastname):
    """Return (zones page, controller page) HTML as that controller reads them.

    zones/action.php carries the per-zone « Vos lieux secrets » block
    (showcontrollerKnownSecrets), controllers/action.php the owner's own base
    preview. Both are where a secret becomes readable, so both are captured."""
    ensure_gm_login(page, PHP_BASE_URL)
    as_controller(page, controller_lastname, base_url=PHP_BASE_URL)
    safe_goto(page, f"{PHP_BASE_URL}/zones/action.php")
    page.wait_for_load_state("load")
    zones_html = page.content()
    safe_goto(page, f"{PHP_BASE_URL}/controllers/action.php")
    page.wait_for_load_state("load")
    return zones_html, page.content()


def _toggle_update_location_admin(page, location_name):
    """POST the management-page toggle_destruction form for a location.

    Applies activate_json.update_location via updateLocation() — the same
    swap path a lost location attack takes. The button sits inside a
    `display:none` span, so it is submitted via JS rather than clicked."""
    ensure_gm_login(page, PHP_BASE_URL)
    safe_goto(page, f"{PHP_BASE_URL}/zones/management_locations.php")
    page.wait_for_load_state("load")
    m = re.search(
        rf'<h3>[^<]*{re.escape(location_name)}[^<]*\(discovery[^<]+</h3>'
        rf'.*?name="toggle_destruction"\s+value="(\d+)"',
        page.content(), re.DOTALL,
    )
    if not m:
        raise AssertionError(
            f"toggle_destruction form for '{location_name}' not found on "
            f"management_locations.php"
        )
    location_id = int(m.group(1))
    with page.expect_navigation(wait_until="load"):
        page.evaluate(
            f"""
            const inp = document.querySelector(
                'input[name="toggle_destruction"][value="{location_id}"]'
            );
            if (inp && inp.form) inp.form.submit();
            """
        )
    return location_id


class TestOwnerKnowsOwnLocations:
    """One scenario load, all UI state captured once, tests are assertions."""

    @pytest.fixture(scope="class", autouse=True)
    def owner_knows_state(self, browser):
        load_scenario_via_admin(browser, PHP_BASE_URL, "TestConfig")

        context = browser.new_context()
        page = context.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)
        clear_ui_caches()

        # gm oracle before the swap: management_locations.php renders a
        # per-location, per-controller known/secret flag pair.
        golf_known = ui_known_locations_for_controller(page, "Golf")
        golf_secret = ui_known_secret_locations_for_controller(page, "Golf")
        echo_known = ui_known_locations_for_controller(page, "Echo")
        echo_secret = ui_known_secret_locations_for_controller(page, "Echo")

        # Repair dropdowns before the swap.
        golf_repair_before = _repair_option_labels(page, "Golf")
        charlie_repair = _repair_option_labels(page, "Charlie")

        # Swap Golf-Shrine into its ruined, repairable variant.
        shrine_id = _toggle_update_location_admin(page, "Golf-Shrine")

        golf_repair_after = _repair_option_labels(page, "Golf")
        ensure_gm_login(page, PHP_BASE_URL)
        golf_known_after = ui_known_locations_for_controller(page, "Golf")

        # What each owner actually READS, as opposed to what CKL stores.
        golf_zones_html, golf_ctrl_html = _own_pages_html(page, "Golf")
        echo_zones_html, echo_ctrl_html = _own_pages_html(page, "Echo")

        assert_no_collected_php_errors(page)
        context.close()

        cls = type(self)
        cls._golf_known = golf_known
        cls._golf_secret = golf_secret
        cls._echo_known = echo_known
        cls._echo_secret = echo_secret
        cls._golf_repair_before = golf_repair_before
        cls._charlie_repair = charlie_repair
        cls._golf_repair_after = golf_repair_after
        cls._golf_known_after = golf_known_after
        cls._shrine_id = shrine_id
        cls._golf_zones_html = golf_zones_html
        cls._golf_ctrl_html = golf_ctrl_html
        cls._echo_zones_html = echo_zones_html
        cls._echo_ctrl_html = echo_ctrl_html
        yield

    # --- Rule 1: the owner knows, and can repair, its own non-base location ---

    def test_rule1_owner_finds_own_non_base_location_in_repair_dropdown(self):
        """Golf owns Golf-Chapel (is_base=0, can_be_repaired=1) and finds it
        in its own repair dropdown.

        Positive: Golf-Chapel is offered. Negative pair: Charlie, who owns
        nothing, gets no repair option at all — so the positive cannot be
        an artefact of the dropdown listing every location in the game."""
        labels = self._golf_repair_before
        assert any("Golf-Chapel" in label for label in labels), (
            f"Golf owns the non-base repairable Golf-Chapel and must find it "
            f"in its repair dropdown (issue #128 rule 1); got options: "
            f"{labels!r}"
        )
        assert self._charlie_repair == [], (
            f"Charlie owns no location and must get no repair option at all, "
            f"otherwise the Golf-Chapel assertion above proves nothing; got "
            f"options: {self._charlie_repair!r}"
        )

    def test_rule1_unowned_and_unrepairable_locations_stay_out(self):
        """The dropdown is not simply 'every location'.

        Civic-Site (unowned, can_be_repaired=0) and Echo-Base (owned by
        another controller) must not be offered to Golf, while Golf-Chapel
        is."""
        labels = self._golf_repair_before
        joined = " | ".join(labels)
        assert "Civic-Site" not in joined, (
            f"Civic-Site is unowned and can_be_repaired=0 — it must not be "
            f"offered to Golf; got options: {labels!r}"
        )
        assert "Echo-Base" not in joined, (
            f"Echo-Base belongs to Echo — it must not be offered to Golf; "
            f"got options: {labels!r}"
        )
        assert any("Golf-Chapel" in label for label in labels), (
            f"Golf-Chapel must still be offered, otherwise the two negatives "
            f"above pass on an empty dropdown; got options: {labels!r}"
        )

    def test_rule1_owner_knows_every_owned_location(self):
        """The CKL seed covers all owned locations, not only bases.

        Positive: Golf knows both of its non-base locations. Negative:
        Golf does not know Location A, which it neither owns nor has
        investigated (discovery_diff keeps it out of reach)."""
        assert {"Golf-Chapel", "Golf-Shrine"} <= self._golf_known, (
            f"Golf owns Golf-Chapel and Golf-Shrine (both is_base=0) and must "
            f"know both after the scenario-load CKL seed (issue #128 rule 1); "
            f"known: {sorted(self._golf_known)}"
        )
        assert "Location A" not in self._golf_known, (
            f"Golf owns neither Location A nor can it reach its "
            f"discovery_diff — knowing it would mean the seed grants "
            f"knowledge of unowned locations; known: "
            f"{sorted(self._golf_known)}"
        )

    # --- Rule 2: the secret follows is_base, not ownership ---

    def test_rule2_owner_does_not_know_own_non_base_secret(self):
        """found_secret is false for an owned non-base location.

        Negative: neither Golf location is flagged secret-known. Paired
        positive: Golf does know the locations themselves (so the negative
        is not vacuous on an empty knowledge set), and Echo does know its
        own base's secret under owner_knows_own_base_secret=TRUE."""
        assert "Golf-Chapel" not in self._golf_secret, (
            f"Golf-Chapel is is_base=0 — its secret must NOT be seeded to the "
            f"owner (issue #128 rule 2); secret-known: "
            f"{sorted(self._golf_secret)}"
        )
        assert "Golf-Shrine" not in self._golf_secret, (
            f"Golf-Shrine is is_base=0 — its secret must NOT be seeded to the "
            f"owner (issue #128 rule 2); secret-known: "
            f"{sorted(self._golf_secret)}"
        )
        assert {"Golf-Chapel", "Golf-Shrine"} <= self._golf_known, (
            f"Golf must still KNOW both locations — without this the two "
            f"negatives above would pass simply because Golf knows nothing; "
            f"known: {sorted(self._golf_known)}"
        )

    def test_rule2_owner_does_know_own_base_secret(self):
        """The positive half of rule 2: a base's secret IS seeded.

        Echo owns Echo-Base (is_base=1) and owner_knows_own_base_secret
        defaults to TRUE, so Echo-Base is flagged secret-known. This is what
        proves the rule-2 negatives above come from the is_base condition
        and not from a seed that never sets found_secret at all."""
        assert "Echo-Base" in self._echo_known, (
            f"Echo owns Echo-Base and must know it; known: "
            f"{sorted(self._echo_known)}"
        )
        assert "Echo-Base" in self._echo_secret, (
            f"Echo-Base is is_base=1 and owner_knows_own_base_secret is TRUE, "
            f"so its secret must be seeded to Echo (issue #128 rule 2 "
            f"positive); secret-known: {sorted(self._echo_secret)}"
        )

    # --- Rule 2, render side: what the owner actually READS ---

    _CHAPEL_SECRET = "Hidden crypt beneath the Golf chapel"
    _SHRINE_SECRET = "Hidden reliquary beneath the Golf shrine"
    _BASE_SECRET = "Hidden tactical details only owner sees"

    def test_rule2_owner_cannot_read_its_non_base_secrets(self):
        """The seed storing found_secret=false is only half the rule; the
        owner-facing renders must honour it.

        listControllerLinkedLocations gates hidden_description in SQL, and
        showcontrollerKnownSecrets delegates to it, so neither Golf secret can
        reach Golf's own pages. The name assertions are the paired positives:
        without them these negatives would pass on an empty page."""
        for html, label in ((self._golf_zones_html, "zones"),
                            (self._golf_ctrl_html, "controllers")):
            assert "Golf-Chapel" in html, (
                f"Golf must see its own Golf-Chapel on its {label} page, "
                f"otherwise the secret assertions below prove nothing"
            )
            assert self._CHAPEL_SECRET not in html, (
                f"Golf-Chapel is is_base=0 : its secret must not be readable "
                f"by its owner on the {label} page (issue #128 rule 2)"
            )
            assert self._SHRINE_SECRET not in html, (
                f"Golf-Shrine is is_base=0 : its secret must not be readable "
                f"by its owner on the {label} page (issue #128 rule 2)"
            )

    def test_rule2_owner_reads_its_base_secret(self):
        """Positive counterpart on the render side, and the witness that the
        negatives above come from is_base and not from a blanket hide.

        Echo-Base is is_base=1 with owner_knows_own_base_secret TRUE, so its
        secret is readable — through listControllerLinkedLocations on the zones
        page and through the base preview on the controllers page."""
        assert "Echo-Base" in self._echo_zones_html, (
            "Echo must see its own base on its zones page"
        )
        assert self._BASE_SECRET in self._echo_zones_html, (
            "a base secret must stay readable by its owner when "
            "owner_knows_own_base_secret is TRUE (issue #128 rule 2 positive)"
        )
        assert self._BASE_SECRET in self._echo_ctrl_html, (
            "the base preview on controllers/action.php must carry the secret "
            "when owner_knows_own_base_secret is TRUE"
        )

    # --- Rule 3: a swap into a ruin keeps the owner's knowledge ---

    def test_rule3_swapped_ruin_stays_repairable_by_its_owner(self):
        """Golf-Shrine swaps into Golf-Shrine-Ruined (can_be_repaired=1).

        Negative before: the intact shrine is can_be_repaired=0, so it is
        not offered. Positive after: the ruin is offered under its new name.
        The swap keeps the location id, so this can only pass if the CKL row
        survived updateLocation (issue #128 rule 3)."""
        before = " | ".join(self._golf_repair_before)
        after = " | ".join(self._golf_repair_after)
        assert "Golf-Shrine" not in before, (
            f"The intact Golf-Shrine is can_be_repaired=0 and must NOT be "
            f"offered before the swap; got options: "
            f"{self._golf_repair_before!r}"
        )
        assert "Golf-Shrine-Ruined" in after, (
            f"After the swap Golf-Shrine-Ruined is can_be_repaired=1 and "
            f"owned by Golf, so its owner must find it in the repair "
            f"dropdown (issue #128 rule 3); got options: "
            f"{self._golf_repair_after!r}"
        )

    def test_rule3_swap_keeps_the_owner_knowledge_row(self):
        """The CKL row survives the swap.

        Positive: the ruined name is known to Golf after the swap. Negative:
        the pre-swap name is gone from the knowledge set, which confirms the
        row was renamed in place rather than a fresh row appearing beside a
        stale one."""
        assert "Golf-Shrine-Ruined" in self._golf_known_after, (
            f"Golf must still know its location after the swap turned it "
            f"into a ruin (issue #128 rule 3); known after: "
            f"{sorted(self._golf_known_after)}"
        )
        assert "Golf-Shrine" not in self._golf_known_after, (
            f"The swap renames the row in place, so the pre-swap name must "
            f"be gone; seeing both names would mean a duplicate row; known "
            f"after: {sorted(self._golf_known_after)}"
        )
        assert "Golf-Chapel" in self._golf_known_after, (
            f"Golf's other owned location must be untouched by the swap; "
            f"known after: {sorted(self._golf_known_after)}"
        )
