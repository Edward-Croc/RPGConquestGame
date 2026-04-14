#!/usr/bin/env python3
"""
One-time extraction script: convert Japon1555SQL data to CSV files.

Prerequisite: Japon1555SQL must be loaded in the database first
(via admin reset). This script queries the live DB and dumps each
table to a setupJapon1555CSV_*.csv file with the correct format.

Run:
    python3 tests/scripts/extract_japon1555_csv.py
"""
import os
import csv
import json
import pymysql

HOST = '127.0.0.1'
PORT = 3307
USER = 'rpg_test'
PASSWORD = 'rpg_test'
DB = 'rpgconquestgame_test'
PREFIX = 'game2_'

OUT_DIR = os.path.join(
    os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))),
    'var', 'csv'
)


def conn():
    return pymysql.connect(
        host=HOST, port=PORT, user=USER, password=PASSWORD,
        database=DB, charset='utf8mb4', cursorclass=pymysql.cursors.DictCursor
    )


def write_csv(filename, fieldnames, rows):
    path = os.path.join(OUT_DIR, filename)
    with open(path, 'w', newline='', encoding='utf-8') as f:
        w = csv.DictWriter(f, fieldnames=fieldnames, quoting=csv.QUOTE_MINIMAL)
        w.writeheader()
        for row in rows:
            w.writerow(row)
    print(f"  wrote {len(rows)} rows -> {filename}")


def extract_factions(c):
    c.execute(f"SELECT name FROM {PREFIX}factions ORDER BY id")
    write_csv('setupJapon1555CSV_factions.csv', ['name'], c.fetchall())


def extract_players(c):
    c.execute(f"SELECT username, passwd, is_privileged FROM {PREFIX}players WHERE username != 'gm' ORDER BY id")
    write_csv('setupJapon1555CSV_players.csv', ['username', 'passwd', 'is_privileged'], c.fetchall())


def extract_controllers(c):
    c.execute(f"""
        SELECT ctr.firstname, ctr.lastname, ctr.ia_type, ctr.secret_controller,
               ctr.url, ctr.story, ctr.can_build_base, ctr.start_workers,
               ctr.turn_recruited_workers, ctr.turn_firstcome_workers,
               f1.name AS fac, f2.name AS fake_fac
        FROM {PREFIX}controllers ctr
        LEFT JOIN {PREFIX}factions f1 ON f1.id = ctr.faction_id
        LEFT JOIN {PREFIX}factions f2 ON f2.id = ctr.fake_faction_id
        ORDER BY ctr.id
    """)
    rows = []
    for r in c.fetchall():
        rows.append({
            'firstname': r['firstname'] or '',
            'lastname': r['lastname'] or '',
            'ia_type': r['ia_type'] or '',
            'secret_controller': r['secret_controller'] if r['secret_controller'] is not None else 0,
            'url': r['url'] or '',
            'story': r['story'] or '',
            'can_build_base': r['can_build_base'] if r['can_build_base'] is not None else 1,
            'start_workers': r['start_workers'] if r['start_workers'] is not None else 1,
            'turn_recruited_workers': r['turn_recruited_workers'] if r['turn_recruited_workers'] is not None else 0,
            'turn_firstcome_workers': r['turn_firstcome_workers'] if r['turn_firstcome_workers'] is not None else 0,
            'factions__name->faction_id': r['fac'] or '',
            'factions__name->fake_faction_id': r['fake_fac'] or '',
        })
    write_csv('setupJapon1555CSV_controllers.csv',
        ['firstname', 'lastname', 'ia_type', 'secret_controller', 'url', 'story',
         'can_build_base', 'start_workers', 'turn_recruited_workers',
         'turn_firstcome_workers',
         'factions__name->faction_id', 'factions__name->fake_faction_id'], rows)


def extract_player_controller(c):
    c.execute(f"""
        SELECT p.username, ctr.lastname
        FROM {PREFIX}player_controller pc
        JOIN {PREFIX}players p ON p.id = pc.player_id
        JOIN {PREFIX}controllers ctr ON ctr.id = pc.controller_id
        WHERE p.username != 'gm'
        ORDER BY pc.player_id, pc.controller_id
    """)
    rows = [{'players__username->player_id': r['username'],
             'controllers__lastname->controller_id': r['lastname']} for r in c.fetchall()]
    write_csv('setupJapon1555CSV_player_controller.csv',
        ['players__username->player_id', 'controllers__lastname->controller_id'], rows)


def extract_power_types(c):
    c.execute(f"SELECT id, name, description FROM {PREFIX}power_types ORDER BY id")
    write_csv('setupJapon1555CSV_power_types.csv', ['id', 'name', 'description'], c.fetchall())


def extract_powers_by_type(c, type_name, out_filename):
    c.execute(f"""
        SELECT p.name, p.description, p.enquete, p.attack, p.defence, p.other,
               pt.name AS pt_name
        FROM {PREFIX}powers p
        JOIN {PREFIX}link_power_type lpt ON lpt.power_id = p.id
        JOIN {PREFIX}power_types pt ON pt.id = lpt.power_type_id
        WHERE pt.name = %s
        ORDER BY p.id
    """, (type_name,))
    rows = []
    for r in c.fetchall():
        other = r['other']
        if isinstance(other, (dict, list)):
            other = json.dumps(other)
        rows.append({
            'name': r['name'] or '',
            'description': r['description'] or '',
            'enquete': r['enquete'] if r['enquete'] is not None else 0,
            'attack': r['attack'] if r['attack'] is not None else 0,
            'defence': r['defence'] if r['defence'] is not None else 0,
            'other': other or '',
            'linkTable_power_types__name->link_power_type__power_type_id': r['pt_name'],
        })
    write_csv(out_filename,
        ['name', 'description', 'enquete', 'attack', 'defence', 'other',
         'linkTable_power_types__name->link_power_type__power_type_id'], rows)


def extract_faction_powers(c):
    c.execute(f"""
        SELECT f.name AS faction, p.name AS power
        FROM {PREFIX}faction_powers fp
        JOIN {PREFIX}factions f ON f.id = fp.faction_id
        JOIN {PREFIX}link_power_type lpt ON lpt.id = fp.link_power_type_id
        JOIN {PREFIX}powers p ON p.id = lpt.power_id
        ORDER BY fp.id
    """)
    rows = [{'factions__name->faction_id': r['faction'],
             'powers__name->link_power_type__power_id': r['power']} for r in c.fetchall()]
    write_csv('setupJapon1555CSV_faction_powers.csv',
        ['factions__name->faction_id', 'powers__name->link_power_type__power_id'], rows)


def extract_ressources_config(c):
    c.execute(f"""
        SELECT ressource_name, presentation, stored_text, is_rollable, is_stored,
               base_building_cost, base_moving_cost, location_repaire_cost
        FROM {PREFIX}ressources_config ORDER BY id
    """)
    write_csv('setupJapon1555CSV_ressources_config.csv',
        ['ressource_name', 'presentation', 'stored_text', 'is_rollable', 'is_stored',
         'base_building_cost', 'base_moving_cost', 'location_repaire_cost'],
        c.fetchall())


def extract_controller_ressources(c):
    c.execute(f"""
        SELECT ctr.lastname AS ctrl, rc.ressource_name AS rn,
               cr.amount, cr.amount_stored, cr.end_turn_gain
        FROM {PREFIX}controller_ressources cr
        JOIN {PREFIX}controllers ctr ON ctr.id = cr.controller_id
        JOIN {PREFIX}ressources_config rc ON rc.id = cr.ressource_id
        ORDER BY cr.id
    """)
    rows = [{'controllers__lastname->controller_id': r['ctrl'],
             'ressources_config__ressource_name->ressource_id': r['rn'],
             'amount': r['amount'] or 0,
             'amount_stored': r['amount_stored'] or 0,
             'end_turn_gain': r['end_turn_gain'] or 0} for r in c.fetchall()]
    write_csv('setupJapon1555CSV_controller_ressources.csv',
        ['controllers__lastname->controller_id',
         'ressources_config__ressource_name->ressource_id',
         'amount', 'amount_stored', 'end_turn_gain'], rows)


def extract_locations(c):
    """Extract all locations with zone and controller FK name resolution."""
    c.execute(f"""
        SELECT l.name, l.description, l.hidden_description, l.discovery_diff,
               z.name AS zone_name, ctr.lastname AS ctrl_lastname,
               l.is_base, l.can_be_destroyed, l.can_be_repaired, l.activate_json
        FROM {PREFIX}locations l
        LEFT JOIN {PREFIX}zones z ON z.id = l.zone_id
        LEFT JOIN {PREFIX}controllers ctr ON ctr.id = l.controller_id
        ORDER BY l.id
    """)
    rows = []
    for r in c.fetchall():
        activate = r['activate_json']
        if isinstance(activate, (dict, list)):
            activate = json.dumps(activate)
        rows.append({
            'name': r['name'] or '',
            'description': r['description'] or '',
            'hidden_description': r['hidden_description'] or '',
            'discovery_diff': r['discovery_diff'] if r['discovery_diff'] is not None else 0,
            'zones__name->zone_id': r['zone_name'] or '',
            'controllers__lastname->controller_id': r['ctrl_lastname'] or '',
            'is_base': r['is_base'] if r['is_base'] is not None else 0,
            'can_be_destroyed': r['can_be_destroyed'] if r['can_be_destroyed'] is not None else 0,
            'can_be_repaired': r['can_be_repaired'] if r['can_be_repaired'] is not None else 0,
            'activate_json': activate or '',
        })
    write_csv('setupJapon1555CSV_locations.csv',
        ['name', 'description', 'hidden_description', 'discovery_diff',
         'zones__name->zone_id', 'controllers__lastname->controller_id',
         'is_base', 'can_be_destroyed', 'can_be_repaired', 'activate_json'], rows)


def extract_artefacts(c):
    c.execute(f"""
        SELECT a.name, a.description, a.full_description, l.name AS loc_name
        FROM {PREFIX}artefacts a
        LEFT JOIN {PREFIX}locations l ON l.id = a.location_id
        ORDER BY a.id
    """)
    rows = [{'name': r['name'] or '',
             'description': r['description'] or '',
             'full_description': r['full_description'] or '',
             'locations__name->location_id': r['loc_name'] or ''} for r in c.fetchall()]
    write_csv('setupJapon1555CSV_artefacts.csv',
        ['name', 'description', 'full_description', 'locations__name->location_id'], rows)


def extract_workers(c):
    c.execute(f"""
        SELECT w.id, w.firstname, w.lastname,
               wo.name AS origin, z.name AS zone,
               ctr.lastname AS controller,
               wa.action_choice, wa.action_params
        FROM {PREFIX}workers w
        LEFT JOIN {PREFIX}worker_origins wo ON wo.id = w.origin_id
        LEFT JOIN {PREFIX}zones z ON z.id = w.zone_id
        LEFT JOIN {PREFIX}controller_worker cw ON cw.worker_id = w.id
        LEFT JOIN {PREFIX}controllers ctr ON ctr.id = cw.controller_id
        LEFT JOIN {PREFIX}worker_actions wa ON wa.worker_id = w.id AND wa.turn_number = 0
        ORDER BY w.id
    """)
    workers = c.fetchall()

    c.execute(f"""
        SELECT wp.worker_id, p.name
        FROM {PREFIX}worker_powers wp
        JOIN {PREFIX}link_power_type lpt ON lpt.id = wp.link_power_type_id
        JOIN {PREFIX}powers p ON p.id = lpt.power_id
        ORDER BY wp.worker_id, p.name
    """)
    powers_by_worker = {}
    for r in c.fetchall():
        powers_by_worker.setdefault(r['worker_id'], []).append(r['name'])

    rows = []
    for w in workers:
        action_params = w['action_params']
        if isinstance(action_params, (dict, list)):
            action_params = json.dumps(action_params)
        rows.append({
            'firstname': w['firstname'] or '',
            'lastname': w['lastname'] or '',
            'worker_origins__name->origin_id': w['origin'] or '',
            'zones__name->zone_id': w['zone'] or '',
            'controllers__lastname->controller_id': w['controller'] or '',
            'action_choice': w['action_choice'] or 'passive',
            'action_params': action_params or '{}',
            'powers': '|'.join(powers_by_worker.get(w['id'], [])),
        })
    write_csv('setupJapon1555CSV_advanced.csv',
        ['firstname', 'lastname',
         'worker_origins__name->origin_id', 'zones__name->zone_id',
         'controllers__lastname->controller_id',
         'action_choice', 'action_params', 'powers'], rows)


def main():
    c = conn().cursor()
    print("Extracting Japon1555 to CSV:")
    extract_factions(c)
    extract_players(c)
    extract_controllers(c)
    extract_player_controller(c)
    extract_power_types(c)
    extract_powers_by_type(c, 'Hobby', 'setupJapon1555CSV_hobbys.csv')
    extract_powers_by_type(c, 'Metier', 'setupJapon1555CSV_jobs.csv')
    extract_powers_by_type(c, 'Discipline', 'setupJapon1555CSV_disciplines.csv')
    extract_powers_by_type(c, 'Transformation', 'setupJapon1555CSV_transformations.csv')
    extract_faction_powers(c)
    extract_ressources_config(c)
    extract_controller_ressources(c)
    extract_locations(c)
    extract_artefacts(c)
    extract_workers(c)
    print("Done.")


if __name__ == '__main__':
    main()
