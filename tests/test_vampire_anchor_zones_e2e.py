"""Verify A1.11 + A1.15 admin-view rendering against Vampire1966CSV.

Loads the Vampire1966CSV scenario via the admin form (restores gm via
minimalData since scenario CSVs don't carry gm), then visits the two
admin pages that now expose the new columns:

- `controllers/management.php` — new "Origin Zone" column showing the
  zone name resolved via the post-zones forward-reference fixup
- `zones/management_zones.php` — new "Zones adjacentes" column showing
  comma-separated zone names resolved from the adjacent_zones IDs

UI-only — scrapes the rendered HTML with no direct DB.

Run:
    python3 -m pytest tests/test_vampire_anchor_zones_e2e.py -v
"""
import html as _html
import re

import pytest

from conftest import PHP_BASE_URL, ensure_gm_login
from helpers import (
    DB_AVAILABLE, load_minimal_data, load_scenario_via_admin, safe_goto,
    register_php_error_listener, assert_no_collected_php_errors,
)


@pytest.fixture(scope="session")
def base_url():
    return PHP_BASE_URL


@pytest.fixture(scope="module", autouse=True)
def load_vampire(browser):
    load_scenario_via_admin(browser, PHP_BASE_URL, "Vampire1966CSV")
    if DB_AVAILABLE:
        load_minimal_data()
    yield


class TestControllerOriginZoneAdminView:
    """Each Vampire controller should render with its expected origin
    zone name in the controllers/management.php table."""

    EXPECTED = {
        "Calabreze": "Santa Maria Novella",
        "Mazzino": "Palazzo Pitti",
        "da Firenze": "Michelangelo-Gavinana",
        "Ben Hasan": "Railway Station",
        "Lorenzo": "Santa Croce - Oberdan",
        "Ricci": "Campo di Marte",
        "Cacciatore": "Bosco Bello",
        "Bonapart": "Indipendenza",
        "Trentini": "Fortezza Basso",
        "Franco": "Duomo",
        "Vizirof": "Le Cascine",
        "de Toscane": "Piazza della Liberta & Savonarola",
        "Der Swartz": "Monticelli",
    }

    @pytest.fixture(scope="class", autouse=True)
    def admin_state(self, browser):
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)
        safe_goto(page, f"{PHP_BASE_URL}/controllers/management.php")
        page.wait_for_load_state("load")
        html = page.content()
        assert_no_collected_php_errors(page)
        ctx.close()
        type(self)._html = html
        yield

    def test_origin_zone_column_header_present(self):
        assert "Origin Zone" in self._html

    def test_each_controller_has_expected_origin_zone(self):
        """Find the controller row by lastname, then check the origin_zone
        td (data-field='origin_zone') shows the expected zone name."""
        for lastname, expected_zone in self.EXPECTED.items():
            row_re = re.compile(
                rf'<tr class="controller-row"[^>]*data-controller-name="{re.escape(lastname)}".*?</tr>',
                re.DOTALL,
            )
            row = row_re.search(self._html)
            assert row is not None, f"No row found for controller {lastname!r}"
            cell = re.search(
                r'<td data-field="origin_zone">([^<]+)</td>',
                row.group(0),
            )
            assert cell is not None, (
                f"No origin_zone cell in row for {lastname!r}"
            )
            shown = _html.unescape(cell.group(1).strip())
            assert shown == expected_zone, (
                f"{lastname}: expected origin {expected_zone!r}, got {shown!r}"
            )


class TestZoneAdjacencyAdminView:
    """Each Vampire zone should render with its expected adjacent zone
    names (resolved from the adjacent_zones id list) in the
    zones/management_zones.php table."""

    EXPECTED = {
        "Railway Station": {"Le Cascine", "Fortezza Basso"},
        "Le Cascine": {"Monticelli", "Railway Station", "Santa Maria Novella"},
        "Monticelli": {"Palazzo Pitti", "Le Cascine", "Santa Maria Novella"},
        "Palazzo Pitti": {"Monticelli", "Michelangelo-Gavinana", "Duomo", "Santa Maria Novella"},
        "Santa Maria Novella": {"Palazzo Pitti", "Monticelli", "Le Cascine", "Duomo", "Indipendenza"},
        "Duomo": {"Palazzo Pitti", "Santa Maria Novella", "Santa Croce - Oberdan", "Indipendenza"},
        "Indipendenza": {"Santa Maria Novella", "Santa Croce - Oberdan", "Duomo", "Fortezza Basso", "Piazza della Liberta & Savonarola"},
        "Fortezza Basso": {"Railway Station", "Indipendenza", "Piazza della Liberta & Savonarola", "Bosco Bello"},
        "Piazza della Liberta & Savonarola": {"Fortezza Basso", "Indipendenza", "Santa Croce - Oberdan", "Bosco Bello", "Campo di Marte"},
        "Campo di Marte": {"Bosco Bello", "Piazza della Liberta & Savonarola", "Santa Croce - Oberdan", "Michelangelo-Gavinana"},
        "Bosco Bello": {"Fortezza Basso", "Piazza della Liberta & Savonarola", "Campo di Marte"},
        "Santa Croce - Oberdan": {"Duomo", "Indipendenza", "Piazza della Liberta & Savonarola", "Campo di Marte", "Michelangelo-Gavinana"},
        "Michelangelo-Gavinana": {"Palazzo Pitti", "Campo di Marte", "Santa Croce - Oberdan"},
    }

    @pytest.fixture(scope="class", autouse=True)
    def admin_state(self, browser):
        ctx = browser.new_context()
        page = ctx.new_page()
        register_php_error_listener(page)
        ensure_gm_login(page, PHP_BASE_URL)
        safe_goto(page, f"{PHP_BASE_URL}/zones/management_zones.php")
        page.wait_for_load_state("load")
        html = page.content()
        assert_no_collected_php_errors(page)
        ctx.close()
        type(self)._html = html
        yield

    def test_adjacency_column_header_present(self):
        assert "Zones adjacentes" in self._html

    def test_each_zone_has_expected_adjacents(self):
        """Find each zone row by name in the table, then verify the
        adjacent_zones td contains the expected comma-separated names."""
        for zone, expected in self.EXPECTED.items():
            # Zone name may contain & which renders as &amp; in the cell
            row_re = re.compile(
                rf'<td>\s*\d+\s*</td>\s*<td>{re.escape(_html.escape(zone))}</td>'
                rf'.*?<td data-field="adjacent_zones">([^<]+)</td>',
                re.DOTALL,
            )
            m = row_re.search(self._html)
            assert m is not None, f"No row / adjacent_zones cell found for zone {zone!r}"
            shown = {_html.unescape(n.strip()) for n in m.group(1).split(",")}
            assert shown == expected, (
                f"{zone}: expected adjacents {sorted(expected)}, got {sorted(shown)}"
            )
