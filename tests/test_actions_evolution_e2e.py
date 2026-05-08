"""Playwright E2E tests for the 'Evolutions' section on workers/view.php:
Teach Discipline + Transform.

Audit gap (Slice 14, once-per-file UI-click rule):
  - Teach: age gate (`age_discipline`) hides/shows the button; dropdown
    must contain common (basePowerNames=Focused Mind) + own-faction
    discipline (Offensive Stance for Alpha) and exclude other faction
    (Defensive Posture for Beta).
  - Transform: NO age gate (`age_transformation`={'action':'check'} is
    enough); location gate (worker_in_zone) filters Combat Vest.
  - Click-through: ui_transform_click and ui_teach_discipline_click
    persist the new power (post-click dropdown excludes the chosen one).

Status gate is intentionally NOT covered here — see
`tests/AUDIT_next_steps.md` for the planned follow-up issue
(`worker_is_alive='0'` semantic + γ/δ red-green TDD).

Subjects:
  Transform_Subject : Alpha controller, passive, Beta-Combat zone
                      → matches Combat Vest's location gate.
  Bystander_1       : Beta, passive, Alpha-Investigation
                      → fails Combat Vest's location gate.

CSV additions for this slice (setupTestConfig):
  textes:           age_discipline, age_transformation, recrutement_disciplines
  transformations:  Combat Vest (worker_in_zone gate)
  advanced:         Transform_Subject

Run:
    python3 -m pytest tests/test_actions_evolution_e2e.py -v
"""
import pytest
from playwright.sync_api import Page

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, load_minimal_data, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
    ui_teach_button_visible, ui_teach_discipline_options,
    ui_transform_options, ui_teach_discipline_click, ui_transform_click,
    end_turn,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def load_test_config(browser):
    """Load TestConfig with the new evolution rows (age_discipline,
    age_transformation, recrutement_disciplines + Combat Vest +
    Transform_Subject)."""
    if DB_AVAILABLE:
        load_minimal_data()

    context = browser.new_context()
    page = context.new_page()
    register_php_error_listener(page)
    safe_goto(page, f"{PHP_BASE_URL}/connection/loginForm.php")
    page.wait_for_load_state("load")
    page.locator("input[name='username']").fill("gm")
    page.locator("input[name='passwd']").fill("orga")
    page.locator("input[type='submit']").first.click()
    page.wait_for_load_state("load")
    safe_goto(page, f"{PHP_BASE_URL}/base/admin.php")
    page.wait_for_load_state("load")
    page.locator("select[name='config_name']").select_option("TestConfig")
    page.locator("input[name='submit'][value='Submit']").click()
    page.wait_for_timeout(5000)
    page.wait_for_load_state("load", timeout=90000)
    assert_no_collected_php_errors(page)
    context.close()
    yield


@pytest.fixture(scope="module")
def evolution_state(browser):
    """Capture transform + teach state across two phases:
      Phase 1 (age=0): transform dropdown options for Transform_Subject
                       and Bystander_1, teach button visibility on
                       Transform_Subject (must be hidden), then click
                       transform once to prove no-age-gate.
      Phase 2 (age>=2 after 2 end-turns): teach button visibility (now
                       visible), teach dropdown contents, then click
                       teach once to prove the form persists state."""
    context = browser.new_context()
    page = context.new_page()
    register_php_error_listener(page)
    ensure_gm_login(page, PHP_BASE_URL)

    # --- Phase 1: age = 0 ---------------------------------------------
    age0_teach_visible_subject = ui_teach_button_visible(page, "Transform_Subject")
    age0_transform_subject = set(ui_transform_options(page, "Transform_Subject"))
    age0_transform_bystander = set(ui_transform_options(page, "Bystander_1"))

    # Click transform at age=0 — proves transform has NO age gate.
    ui_transform_click(page, "Transform_Subject", "Combat Vest")
    after_transform_click = set(ui_transform_options(page, "Transform_Subject"))

    # --- Phase 2: age up to 2 -----------------------------------------
    end_turn(page)
    end_turn(page)

    age2_teach_visible_subject = ui_teach_button_visible(page, "Transform_Subject")
    age2_teach_options_subject = set(ui_teach_discipline_options(page, "Transform_Subject"))

    # Click teach — verify the discipline persists (post-click dropdown
    # no longer offers Focused Mind because the worker now possesses it).
    ui_teach_discipline_click(page, "Transform_Subject", "Focused Mind")
    after_teach_click = set(ui_teach_discipline_options(page, "Transform_Subject"))

    assert_no_collected_php_errors(page)
    context.close()

    return {
        "age0_teach_visible_subject": age0_teach_visible_subject,
        "age0_transform_subject": age0_transform_subject,
        "age0_transform_bystander": age0_transform_bystander,
        "after_transform_click": after_transform_click,
        "age2_teach_visible_subject": age2_teach_visible_subject,
        "age2_teach_options_subject": age2_teach_options_subject,
        "after_teach_click": after_teach_click,
    }


class TestTransformAtAgeZero:
    """Transform UI is gated only by `age_transformation`={'action':'check'},
    not by worker age. With a fresh seeded worker (age=0), the transform
    select must render and click-through must succeed."""

    def test_transform_select_visible_no_age_gate(self, evolution_state):
        """Transform dropdown rendered for an age-0 worker — proves
        absence of an age gate on transform."""
        assert evolution_state["age0_transform_subject"], (
            "Transform select should render for Transform_Subject at age=0; "
            "got empty options"
        )

    def test_transform_location_gate_filters_combat_vest(self, evolution_state):
        """Combat Vest carries `on_transformation.worker_in_zone='Beta-Combat'`.
        Transform_Subject (Beta-Combat) must see it; Bystander_1
        (Alpha-Investigation) must not."""
        assert "Combat Vest" not in evolution_state["age0_transform_bystander"], (
            f"Bystander_1 (Alpha-Investigation) must NOT see Combat Vest "
            f"(Beta-Combat-gated); got {evolution_state['age0_transform_bystander']}"
        )
        assert "Combat Vest" in evolution_state["age0_transform_subject"], (
            f"Transform_Subject (Beta-Combat) must see Combat Vest before "
            f"the click; got {evolution_state['age0_transform_subject']}"
        )

    def test_transform_click_persists_combat_vest(self, evolution_state):
        """After ui_transform_click('Combat Vest'), the worker possesses
        Combat Vest — `cleanPowerListFromJsonConditions` skips powers
        already on the worker, so the post-click dropdown must NOT
        offer Combat Vest again."""
        assert "Combat Vest" not in evolution_state["after_transform_click"], (
            f"Combat Vest should be removed from the dropdown after the "
            f"click (worker already possesses it); got "
            f"{evolution_state['after_transform_click']}"
        )


class TestTeachAtAgeTwo:
    """Teach button is gated by `age_discipline.age <= worker.age` AND
    `nb_current_disciplines < nb_disciplines` (workers/view.php:359-372).
    With recrutement_disciplines=0 and age_discipline={'age':['2']}:
      age 0: nb_disciplines=0 → teach hidden.
      age 2: nb_disciplines=1 → teach visible (worker has 0 disciplines)."""

    def test_teach_hidden_when_too_young(self, evolution_state):
        """Transform_Subject at age=0 has 0 discipline slots → teach
        button is not rendered."""
        assert evolution_state["age0_teach_visible_subject"] is False, (
            "Teach button should be hidden at age=0 when "
            "recrutement_disciplines=0 and no age threshold met"
        )

    def test_teach_visible_at_age_threshold(self, evolution_state):
        """After 2 end-turns, Transform_Subject reaches age=2 → 1 slot
        granted via age_discipline → teach button rendered."""
        assert evolution_state["age2_teach_visible_subject"] is True, (
            "Teach button should be visible once age >= "
            "age_discipline threshold (2)"
        )

    def test_teach_dropdown_includes_common_and_own_faction(self, evolution_state):
        """Alpha-controlled subject's teach dropdown must contain
        Focused Mind (basePowerNames common) AND Offensive Stance
        (FactionAlpha-exclusive); must NOT contain Defensive Posture
        (FactionBeta-exclusive)."""
        opts = evolution_state["age2_teach_options_subject"]
        assert "Focused Mind" in opts, (
            f"Alpha teach dropdown should include common 'Focused Mind'; "
            f"got {opts}"
        )
        assert "Offensive Stance" in opts, (
            f"Alpha teach dropdown should include own-faction "
            f"'Offensive Stance'; got {opts}"
        )
        assert "Defensive Posture" not in opts, (
            f"Alpha teach dropdown must NOT include Beta's "
            f"'Defensive Posture'; got {opts}"
        )

    def test_teach_click_persists_focused_mind(self, evolution_state):
        """After teaching Focused Mind, the worker possesses it;
        `cleanPowerListFromJsonConditions` removes it from subsequent
        renderings of the dropdown."""
        assert "Focused Mind" not in evolution_state["after_teach_click"], (
            f"Focused Mind should be removed from the teach dropdown "
            f"after the click; got {evolution_state['after_teach_click']}"
        )
