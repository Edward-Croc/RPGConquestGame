#!/usr/bin/env python3
"""
Audit script: verify Japon1555CSV loads produce the same row counts
as Japon1555SQL for every table. Report any discrepancies.

Run AFTER loading each config via admin:
    1. Load Japon1555SQL, run this script to snapshot counts
    2. Load Japon1555CSV, run this script again — compare to snapshot

Or call with --snapshot to save, --compare to verify.
"""
import os
import sys
import json
import pymysql

HOST = '127.0.0.1'
PORT = 3307
USER = 'rpg_test'
PASSWORD = 'rpg_test'
DB = 'rpgconquestgame_test'
PREFIX = 'game2_'

SNAPSHOT_FILE = '/tmp/japon1555_sql_snapshot.json'

TABLES = [
    'factions', 'players', 'controllers', 'player_controller',
    'power_types', 'powers', 'link_power_type', 'faction_powers',
    'ressources_config', 'controller_ressources',
    'zones', 'locations', 'artefacts', 'config',
    'worker_origins', 'worker_names',
    'workers', 'controller_worker', 'worker_actions', 'worker_powers',
    'mechanics',
]


def get_counts():
    conn = pymysql.connect(
        host=HOST, port=PORT, user=USER, password=PASSWORD,
        database=DB, charset='utf8mb4', cursorclass=pymysql.cursors.DictCursor
    )
    cursor = conn.cursor()
    counts = {}
    for t in TABLES:
        cursor.execute(f"SELECT COUNT(*) AS c FROM {PREFIX}{t}")
        counts[t] = cursor.fetchone()['c']
    conn.close()
    return counts


def main():
    if len(sys.argv) < 2:
        print("Usage: audit_japon1555_conversion.py [snapshot|compare]")
        sys.exit(1)

    mode = sys.argv[1]
    counts = get_counts()

    if mode == 'snapshot':
        with open(SNAPSHOT_FILE, 'w') as f:
            json.dump(counts, f, indent=2)
        print(f"Saved snapshot to {SNAPSHOT_FILE}")
        print("Row counts (SQL load):")
        for t, c in counts.items():
            print(f"  {t}: {c}")
    elif mode == 'compare':
        with open(SNAPSHOT_FILE, 'r') as f:
            snapshot = json.load(f)
        print(f"{'Table':<25} {'SQL':>6} {'CSV':>6} {'Diff':>6} {'Status'}")
        print("-" * 60)
        all_match = True
        for t in TABLES:
            sql_c = snapshot.get(t, 0)
            csv_c = counts.get(t, 0)
            diff = csv_c - sql_c
            status = "OK" if diff == 0 else "MISMATCH"
            if diff != 0:
                all_match = False
            print(f"{t:<25} {sql_c:>6} {csv_c:>6} {diff:+6d} {status}")
        if all_match:
            print("\nAll counts match.")
        else:
            print("\nMismatches found.")
            sys.exit(1)
    else:
        print(f"Unknown mode: {mode}")
        sys.exit(1)


if __name__ == '__main__':
    main()
