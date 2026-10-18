"""Regenerate the player guide captures of /base/systemPresentation.php.

Loads a scenario and logs in as one player at phone size. The player builds a base
if they have none and recruits new agents, so no seeded agent shows readably ; one
end of turn is played so the reports exist, and each capture is saved as
base/guide/<set>/<name>.jpg, with network ids, location information, owned
artefacts and every resource but the first blurred.
The guide looks a capture up in the set the guideCaptures key names, then in
base/guide/testconfig/, so a scenario set only needs the captures that differ.

This RESETS the game of the target installation : never run it against a real game.

Run from the repository root:
    python3 tests/tools/capture_guide.py --scenario TestConfig --set testconfig \
        --user echo_player --password test --zone Theta-Artefacts
    python3 tests/tools/capture_guide.py --scenario Japon1555CSV --set japon1555 \
        --user motochika --password chosone \
        --zone "Montagnes d’Iyo" --zone-user player0 --zone-password yokai
"""
import argparse
import sys
from pathlib import Path

TESTS_DIR = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(TESTS_DIR))

from playwright.sync_api import Page, sync_playwright  # noqa: E402

from conftest import PHP_BASE_URL  # noqa: E402
from helpers import _wait_loaded, end_turn, load_scenario_via_admin, login_as, safe_goto  # noqa: E402

GUIDE_DIR = TESTS_DIR.parent / "base" / "guide"
PHONE = {"width": 412, "height": 860}
MAX_ELEMENT_HEIGHT = 1300
JPEG_QUALITY = 75


# Blurs what a capture must not reveal to the players : network ids, location information, owned artefacts
# and a faction's own resources.
HIDE_SECRETS = r"""() => {
    const blur = (el) => { el.style.filter = 'blur(5px)'; };
    const blurText = (node, pattern) => {
        const span = document.createElement('span');
        span.innerHTML = node.nodeValue.replace(pattern, '$1<span style="filter: blur(5px)">$2</span>');
        node.replaceWith(span);
    };
    document.querySelectorAll('.header').forEach((header) => {
        header.innerHTML = header.innerHTML.replace(/\((\d+)\)/g, '(<span style="filter: blur(5px)">$1</span>)');
    });
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    const texts = [];
    while (walker.nextNode()) { texts.push(walker.currentNode); }
    texts.forEach((text) => {
        if (/réseau\s*$/i.test(text.nodeValue) && text.nextSibling && text.nextSibling.nodeName === 'STRONG') {
            blur(text.nextSibling);
        } else if (/réseau\s+\d+/i.test(text.nodeValue)) {
            blurText(text, /(réseau\s+)(\d+)/gi);
        }
    });
    document.querySelectorAll('blockquote, .notification > strong, select[name$="location_id"], select[multiple]').forEach(blur);
    // Only the first resource, the shared currency, stays readable : the others are a faction's own.
    document.querySelectorAll('.box').forEach((box) => {
        const title = box.querySelector('h3');
        if (!title) { return; }
        if (/Vos Ressources/.test(title.textContent)) {
            box.querySelectorAll(':scope > p:not(:first-of-type)').forEach(blur);
        } else if (/Mes ressources/.test(title.textContent)) {
            box.querySelectorAll('tbody tr:not(:first-child)').forEach(blur);
        } else if (/Règles d'obtention/.test(title.textContent)) {
            let resourcesSeen = 0;
            Array.from(box.children).forEach((child) => {
                if (child.nodeName === 'H4') { resourcesSeen += 1; }
                if (resourcesSeen > 1) { blur(child); }
            });
        }
    });
    // An unfolded zone keeps its public description : its lists of locations and agents are cut short and blurred.
    document.querySelectorAll('[id^="description-"] ul > li').forEach((item) => {
        if (!item.querySelector('ul') && item.textContent.length > 60) { item.textContent = item.textContent.slice(0, 60) + '…'; }
        blur(item);
    });
    // Owned artefacts are listed as « <strong>Name</strong> (Lieu : …) » under their own heading.
    document.querySelectorAll('h4 + ul').forEach((list) => {
        if (/\(Lieu :/.test(list.textContent)) { list.querySelectorAll('li').forEach(blur); }
    });
    document.querySelectorAll('h4').forEach((heading) => {
        if (!/Mes investigations|Mes recherches|lieux découverts|lieux secrets/.test(heading.textContent)) { return; }
        let node = heading.nextSibling;
        while (node && node.nodeName !== 'H4') {
            if (node.nodeType === Node.TEXT_NODE && node.nodeValue.trim() !== '') {
                const span = document.createElement('span');
                span.textContent = node.nodeValue;
                node.replaceWith(span);
                node = span;
            }
            if (node.nodeType === Node.ELEMENT_NODE) { blur(node); }
            node = node.nextSibling;
        }
    });
}"""


def hide_secrets(page: Page):
    page.evaluate(HIDE_SECRETS)


def hide_menu_button(page: Page):
    """The menu button stays fixed on screen and would cover a cropped capture."""
    page.add_style_tag(content=".openbtn { visibility: hidden; }")


def shoot_viewport(page: Page, out_dir: Path, name: str):
    hide_secrets(page)
    page.screenshot(path=str(out_dir / f"{name}.jpg"), type="jpeg", quality=JPEG_QUALITY)
    print(f"  {name}.jpg")


def shoot_element(page: Page, out_dir: Path, name: str, selector: str, index: int = 0):
    """Capture one element, cut at MAX_ELEMENT_HEIGHT so a long report stays readable."""
    if page.locator(selector).count() <= index:
        print(f"  {name}.jpg skipped : no {selector} on {page.url}")
        return
    hide_secrets(page)
    hide_menu_button(page)
    element = page.locator(selector).nth(index)
    element.scroll_into_view_if_needed()
    box = element.bounding_box()
    page.screenshot(
        path=str(out_dir / f"{name}.jpg"), type="jpeg", quality=JPEG_QUALITY, full_page=True,
        clip={"x": box["x"], "y": box["y"] + page.evaluate("window.scrollY"),
              "width": box["width"], "height": min(box["height"], MAX_ELEMENT_HEIGHT)},
    )
    print(f"  {name}.jpg")


def prepare_player(page: Page, base_url: str):
    """Build the player's base if they have none, then recruit, so the captures show freshly recruited agents."""
    safe_goto(page, f"{base_url}/controllers/action.php")
    create = page.locator("input[name='createBase']")
    if create.count() > 0:
        zone_select = page.locator("form:has(input[name='createBase']) select").first
        zone_value = zone_select.locator("option:not([value=''])").first.get_attribute("value")
        zone_select.select_option(zone_value)
        create.click()
        page.wait_for_load_state("load")
        print("  base built")
    for button in ("recrutement", "first_come"):
        safe_goto(page, f"{base_url}/workers/viewAll.php")
        start = page.locator(f"input[name='{button}']")
        if start.count() == 0:
            continue
        start.click()
        _wait_loaded(page, "input[name='chosir']")
        page.locator("input[name='chosir']").first.click()
        page.wait_for_load_state("load")
        print(f"  agent recruited ({button})")


def shoot_between(page: Page, out_dir: Path, name: str, start_heading: str, end_heading: str):
    """Capture a page from the first heading or folded block title matching start_heading down to the one matching end_heading."""
    hide_secrets(page)
    hide_menu_button(page)
    region = page.evaluate("""([startPattern, endPattern]) => {
        const headings = Array.from(document.querySelectorAll('h3, h4, summary'));
        const start = headings.find((h) => new RegExp(startPattern).test(h.textContent));
        const end = headings.find((h) => new RegExp(endPattern).test(h.textContent));
        if (!start || !end) { return null; }
        const frame = (start.closest('.box') || start.parentElement).getBoundingClientRect();
        const top = start.getBoundingClientRect().top + window.scrollY - 8;
        return {x: frame.left, y: top, width: frame.width,
                height: end.getBoundingClientRect().top + window.scrollY - top};
    }""", [start_heading, end_heading])
    if region is None:
        print(f"  {name}.jpg skipped : no {start_heading} … {end_heading} on {page.url}")
        return
    region["height"] = min(region["height"], MAX_ELEMENT_HEIGHT)
    page.screenshot(path=str(out_dir / f"{name}.jpg"), type="jpeg", quality=JPEG_QUALITY,
                    full_page=True, clip=region)
    print(f"  {name}.jpg")


def shoot_zone(page: Page, out_dir: Path, base_url: str, zone_name: str):
    """Capture one zone of the zones page, unfolded."""
    safe_goto(page, f"{base_url}/zones/action.php")
    header = page.locator("div.box[id^='zone-'] > h3", has_text=zone_name).first
    if header.count() == 0:
        print(f"  zone.jpg skipped : no zone {zone_name}")
        return
    header.click()
    zone_id = header.locator("xpath=..").get_attribute("id")
    shoot_element(page, out_dir, "zone", f"#{zone_id}")


def capture(base_url: str, scenario: str, set_name: str, user: str, password: str,
            gm_user: str, gm_password: str, zone_name: str, zone_user: str, zone_password: str):
    out_dir = GUIDE_DIR / set_name
    out_dir.mkdir(parents=True, exist_ok=True)
    with sync_playwright() as p:
        browser = p.chromium.launch()
        load_scenario_via_admin(browser, base_url, scenario)

        context = browser.new_context(viewport=PHONE, device_scale_factor=1.5, is_mobile=True)
        page = context.new_page()
        safe_goto(page, f"{base_url}/connection/loginForm.php")
        shoot_viewport(page, out_dir, "login")
        login_as(page, base_url, user, password)
        prepare_player(page, base_url)

        gm_context = browser.new_context()
        gm_page = gm_context.new_page()
        login_as(gm_page, base_url, gm_user, gm_password)
        end_turn(gm_page, base_url=base_url)
        gm_context.close()

        safe_goto(page, f"{base_url}/base/accueil.php")
        page.locator(".openbtn").click()
        page.wait_for_timeout(400)
        shoot_viewport(page, out_dir, "menu")

        safe_goto(page, f"{base_url}/controllers/action.php")
        shoot_viewport(page, out_dir, "faction")
        # The information gift forms sit in a folded block.
        page.evaluate("""() => document.querySelectorAll('details').forEach((block) => {
            const summary = block.querySelector('summary');
            if (summary && /Donner des informations/.test(summary.textContent)) { block.open = true; }
        })""")
        # The owned artefacts heading is the scenario's textOwnedArtefacts : protégé.es, artefacts…
        shoot_between(page, out_dir, "lieux", "protégé|artefact|Vos lieux découverts", "Donner des informations")
        shoot_between(page, out_dir, "informations", "Donner des informations", "Informations reçues")

        if page.locator("#sidebar a[href*='ressources/view.php']").count() > 0:
            safe_goto(page, f"{base_url}/ressources/view.php")
            shoot_viewport(page, out_dir, "ressources")

        safe_goto(page, f"{base_url}/zones/action.php")
        shoot_viewport(page, out_dir, "zones")

        safe_goto(page, f"{base_url}/workers/viewAll.php")
        shoot_viewport(page, out_dir, "agents")
        page.locator("div.worker-short a[href*='worker_id=']").first.click()
        _wait_loaded(page, "div.box.actions")
        # The sticky agent navigation would cover the top of the captured boxes.
        page.add_style_tag(content=".card-header { position: static; }")
        shoot_element(page, out_dir, "agent", "div.box.actions")
        # The first week is the turn in progress, the second the report of the turn just played.
        shoot_element(page, out_dir, "rapport", "div.box.report.week", index=1)

        # The unfolded zone may be shown from another player, who knows more of it.
        if zone_user == user:
            shoot_zone(page, out_dir, base_url, zone_name)
        else:
            zone_context = browser.new_context(viewport=PHONE, device_scale_factor=1.5, is_mobile=True)
            zone_page = zone_context.new_page()
            login_as(zone_page, base_url, zone_user, zone_password)
            shoot_zone(zone_page, out_dir, base_url, zone_name)
            zone_context.close()

        # Recruitment last : opening it counts as this turn's recruitment.
        safe_goto(page, f"{base_url}/workers/viewAll.php")
        recruit = page.locator("input[name='recrutement']")
        if recruit.count() > 0:
            recruit.click()
            _wait_loaded(page, "input[name='chosir']")
            shoot_viewport(page, out_dir, "recrutement")
        else:
            print("  recrutement.jpg skipped : no recruitment button for this player")

        context.close()
        browser.close()


def main():
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("--scenario", required=True, help="scenario to load, e.g. TestConfig")
    parser.add_argument("--set", required=True, help="capture set, the base/guide/ folder name")
    parser.add_argument("--user", required=True, help="player login whose pages are captured")
    parser.add_argument("--password", required=True)
    parser.add_argument("--gm-user", default="gm")
    parser.add_argument("--gm-password", default="orga")
    parser.add_argument("--zone", required=True, help="zone shown unfolded, e.g. Montagnes d’Iyo")
    parser.add_argument("--zone-user", help="player whose view of the zone is captured, --user by default")
    parser.add_argument("--zone-password", help="password of --zone-user")
    parser.add_argument("--base-url", default=PHP_BASE_URL)
    args = parser.parse_args()
    capture(args.base_url, args.scenario, args.set, args.user, args.password,
            args.gm_user, args.gm_password, args.zone,
            args.zone_user or args.user, args.zone_password or args.password)


if __name__ == "__main__":
    main()
