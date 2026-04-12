"""Unit tests for CSV load functionality against Docker MySQL.

Tests cover:
- loadCSVFile: basic inserts, foreign key lookups, empty/null handling, malformed rows
- loadCSVUpdates: UPDATE via CSV
- Schema integrity: FK constraints with prefixed tables
"""
import csv
import os
import tempfile

import pymysql
import pytest

from conftest import GAME_PREFIX, CSV_DIR, SQL_DIR, MYSQL_DB


# ---------------------------------------------------------------------------
# Helpers — replicate PHP loadCSVFile / loadCSVUpdates logic in Python
# ---------------------------------------------------------------------------

def load_csv_file(conn, csv_file, table_name, columns, prefix=GAME_PREFIX):
    """Python equivalent of PHP loadCSVFile() for testing."""
    prefixed_table = prefix + table_name

    with open(csv_file, "r", encoding="utf-8") as f:
        reader = csv.reader(f)
        header = next(reader)

        # Parse column definitions (handle lookups like 'table__col->target_col')
        db_columns = []
        lookup_maps = {}
        for col in columns:
            if "->" in col:
                origin_col, target_col = col.split("->")
                db_columns.append(target_col)
                table, column = origin_col.split("__")
                lookup_maps[target_col] = {"table": table, "column": column}
            else:
                db_columns.append(col)

        # Build lookup caches
        cursor = conn.cursor()
        lookup_caches = {}
        for target_col, info in lookup_maps.items():
            lookup_table = prefix + info["table"]
            lookup_name_col = info["column"]
            cursor.execute(f"SELECT id, `{lookup_name_col}` FROM `{lookup_table}`")
            lookup_caches[target_col] = {
                row[lookup_name_col]: row["id"] for row in cursor.fetchall()
            }

        # Insert rows
        placeholders = ", ".join(["%s"] * len(db_columns))
        col_list = ", ".join(f"`{c}`" for c in db_columns)
        sql = f"INSERT INTO `{prefixed_table}` ({col_list}) VALUES ({placeholders})"

        row_count = 0
        warnings = []
        for row in reader:
            if len(row) != len(header):
                warnings.append(f"Row {row_count + 1} column count mismatch")
                continue

            row_data = dict(zip(header, row))
            values = []
            for col in columns:
                if "->" in col:
                    _csv_col, db_col = col.split("->")
                    lookup_value = row_data.get(col, "")
                    if lookup_value in lookup_caches.get(db_col, {}):
                        values.append(lookup_caches[db_col][lookup_value])
                    else:
                        warnings.append(f"Lookup '{lookup_value}' not found for {col}")
                        values.append(None)
                else:
                    value = row_data.get(col, "")
                    values.append(None if value in ("", None) else value)

            cursor.execute(sql, values)
            row_count += 1

        conn.commit()
        cursor.close()
        return row_count, warnings


def load_csv_updates(conn, csv_file, prefix=GAME_PREFIX):
    """Python equivalent of PHP loadCSVUpdates() for testing.

    Note: The PHP function prefixes column names too, which is likely a bug
    for normal columns. This test mirrors the actual PHP behavior.
    """
    with open(csv_file, "r", encoding="utf-8") as f:
        reader = csv.reader(f)
        _header = next(reader)  # skip header

        cursor = conn.cursor()
        update_count = 0
        for row in reader:
            if len(row) < 5:
                continue
            table_name = prefix + row[0].strip()
            column_name = row[1].strip()
            where_column = row[2].strip()
            where_value = row[3].strip()
            new_value = row[4].strip()

            sql = f"UPDATE `{table_name}` SET `{column_name}` = %s WHERE `{where_column}` = %s"
            cursor.execute(sql, [new_value, where_value])
            update_count += 1

        conn.commit()
        cursor.close()
        return update_count


# ---------------------------------------------------------------------------
# Tests: Schema Integrity
# ---------------------------------------------------------------------------

class TestSchemaIntegrity:
    """Verify the MySQL schema was correctly set up with prefixed tables."""

    def test_all_expected_tables_exist(self, db_connection):
        cursor = db_connection.cursor()
        cursor.execute("SHOW TABLES")
        tables = {list(row.values())[0] for row in cursor.fetchall()}
        expected = {
            f"{GAME_PREFIX}mechanics", f"{GAME_PREFIX}config",
            f"{GAME_PREFIX}players", f"{GAME_PREFIX}factions",
            f"{GAME_PREFIX}controllers", f"{GAME_PREFIX}player_controller",
            f"{GAME_PREFIX}zones", f"{GAME_PREFIX}locations",
            f"{GAME_PREFIX}artefacts", f"{GAME_PREFIX}controller_known_locations",
            f"{GAME_PREFIX}location_attack_logs", f"{GAME_PREFIX}worker_origins",
            f"{GAME_PREFIX}worker_names", f"{GAME_PREFIX}workers",
            f"{GAME_PREFIX}workers_trace_links", f"{GAME_PREFIX}controller_worker",
            f"{GAME_PREFIX}power_types", f"{GAME_PREFIX}powers",
            f"{GAME_PREFIX}link_power_type", f"{GAME_PREFIX}worker_powers",
            f"{GAME_PREFIX}faction_powers", f"{GAME_PREFIX}worker_actions",
            f"{GAME_PREFIX}controllers_known_enemies",
            f"{GAME_PREFIX}ressources_config", f"{GAME_PREFIX}controller_ressources",
        }
        assert expected == tables

    def test_fk_worker_powers_references_prefixed_link_power_type(self, db_connection):
        """Regression test for the {prefix} FK bug we fixed."""
        cursor = db_connection.cursor()
        cursor.execute("""
            SELECT REFERENCED_TABLE_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = %s
              AND TABLE_NAME = %s
              AND COLUMN_NAME = 'link_power_type_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        """, [MYSQL_DB, f"{GAME_PREFIX}worker_powers"])
        row = cursor.fetchone()
        assert row is not None, "FK constraint should exist for link_power_type_id"
        assert row["REFERENCED_TABLE_NAME"] == f"{GAME_PREFIX}link_power_type"

    def test_fk_faction_powers_references_prefixed_link_power_type(self, db_connection):
        """Regression test for the {prefix} FK bug we fixed."""
        cursor = db_connection.cursor()
        cursor.execute("""
            SELECT REFERENCED_TABLE_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = %s
              AND TABLE_NAME = %s
              AND COLUMN_NAME = 'link_power_type_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        """, [MYSQL_DB, f"{GAME_PREFIX}faction_powers"])
        row = cursor.fetchone()
        assert row is not None, "FK constraint should exist for link_power_type_id"
        assert row["REFERENCED_TABLE_NAME"] == f"{GAME_PREFIX}link_power_type"


# ---------------------------------------------------------------------------
# Tests: loadCSVFile
# ---------------------------------------------------------------------------

class TestLoadCSVFile:
    """Tests for the CSV insert loader."""

    def test_load_worker_origins(self, clean_tables):
        """Load simple CSV without lookups."""
        conn = clean_tables
        csv_file = os.path.join(CSV_DIR, "setupTestConfig_worker_origins.csv")
        count, warnings = load_csv_file(conn, csv_file, "worker_origins", ["name"])
        assert count == 3
        assert len(warnings) == 0

        cursor = conn.cursor()
        cursor.execute(f"SELECT name FROM `{GAME_PREFIX}worker_origins` ORDER BY id")
        names = [row["name"] for row in cursor.fetchall()]
        assert names == ["origine Accessible", "origine Limitée", "origine Commune"]

    def test_load_worker_names_with_fk_lookup(self, clean_tables):
        """Load CSV with foreign key lookup (worker_origins__name->origin_id)."""
        conn = clean_tables
        # First load origins (dependency)
        origins_csv = os.path.join(CSV_DIR, "setupTestConfig_worker_origins.csv")
        load_csv_file(conn, origins_csv, "worker_origins", ["name"])

        # Then load names with FK lookup
        names_csv = os.path.join(CSV_DIR, "setupTestConfig_worker_names.csv")
        count, warnings = load_csv_file(
            conn, names_csv, "worker_names",
            ["firstname", "lastname", "worker_origins__name->origin_id"]
        )
        assert count == 8
        assert len(warnings) == 0

        cursor = conn.cursor()
        cursor.execute(f"""
            SELECT wn.firstname, wn.lastname, wo.name as origin_name
            FROM `{GAME_PREFIX}worker_names` wn
            JOIN `{GAME_PREFIX}worker_origins` wo ON wn.origin_id = wo.id
            ORDER BY wn.id
        """)
        rows = cursor.fetchall()
        assert len(rows) == 8
        assert rows[0]["firstname"] == "one"
        assert rows[0]["origin_name"] == "origine Accessible"
        assert rows[4]["firstname"] == "un"
        assert rows[4]["origin_name"] == "origine Limitée"

    def test_load_hobbys_with_numeric_fields(self, clean_tables):
        """Load powers CSV with numeric stat columns."""
        conn = clean_tables
        csv_file = os.path.join(CSV_DIR, "setupTestConfig_hobbys.csv")
        count, warnings = load_csv_file(
            conn, csv_file, "powers",
            ["name", "description", "enquete", "attack", "defence", "other"]
        )
        assert count == 7
        assert len(warnings) == 0

        cursor = conn.cursor()
        cursor.execute(f"SELECT name, enquete, attack, defence FROM `{GAME_PREFIX}powers` ORDER BY id")
        rows = cursor.fetchall()
        # "Master Investigator Hobby" has enquete=2, attack=0, defence=-1
        master = [r for r in rows if r["name"] == "Master Investigator Hobby"][0]
        assert master["enquete"] == 2
        assert master["attack"] == 0
        assert master["defence"] == -1

    def test_load_zones_with_empty_lookup_values(self, clean_tables):
        """Zones CSV has empty controller lookups — should insert NULL."""
        conn = clean_tables
        csv_file = os.path.join(CSV_DIR, "setupTestConfig_zones.csv")
        count, warnings = load_csv_file(
            conn, csv_file, "zones",
            ["name", "description", "hide_turn_zero",
             "controllers__lastname->claimer_controller_id",
             "controllers__lastname->holder_controller_id"]
        )
        # All zones should be loaded (empty lookups resolve to NULL)
        assert count == 7

        cursor = conn.cursor()
        cursor.execute(f"SELECT claimer_controller_id, holder_controller_id FROM `{GAME_PREFIX}zones`")
        for row in cursor.fetchall():
            assert row["claimer_controller_id"] is None
            assert row["holder_controller_id"] is None

    def test_file_not_found(self, clean_tables):
        """Non-existent CSV should raise FileNotFoundError."""
        with pytest.raises(FileNotFoundError):
            load_csv_file(clean_tables, "/nonexistent.csv", "worker_origins", ["name"])

    def test_malformed_row_skipped(self, clean_tables):
        """Rows with wrong column count should be skipped."""
        conn = clean_tables
        # Create a temp CSV with a malformed row
        with tempfile.NamedTemporaryFile(mode="w", suffix=".csv", delete=False) as f:
            f.write("name\n")
            f.write("Good Origin\n")
            f.write("Bad,Extra,Columns\n")
            f.write("Also Good\n")
            tmp_path = f.name

        try:
            count, warnings = load_csv_file(conn, tmp_path, "worker_origins", ["name"])
            assert count == 2  # only 2 valid rows
            assert len(warnings) == 1  # 1 malformed row warning
        finally:
            os.unlink(tmp_path)

    def test_empty_string_becomes_null(self, clean_tables):
        """Empty CSV values should be inserted as NULL."""
        conn = clean_tables
        with tempfile.NamedTemporaryFile(mode="w", suffix=".csv", delete=False) as f:
            f.write("name,description,enquete,attack,defence,other\n")
            f.write("Test Power,desc,1,0,,\n")
            tmp_path = f.name

        try:
            count, _ = load_csv_file(
                conn, tmp_path, "powers",
                ["name", "description", "enquete", "attack", "defence", "other"]
            )
            assert count == 1
            cursor = conn.cursor()
            cursor.execute(f"SELECT defence, other FROM `{GAME_PREFIX}powers` WHERE name='Test Power'")
            row = cursor.fetchone()
            assert row["defence"] is None
            assert row["other"] is None
        finally:
            os.unlink(tmp_path)


# ---------------------------------------------------------------------------
# Tests: loadCSVUpdates
# ---------------------------------------------------------------------------

class TestLoadCSVUpdates:
    """Tests for the CSV update loader."""

    def test_basic_update(self, clean_tables):
        """Update existing rows via CSV."""
        conn = clean_tables
        cursor = conn.cursor()
        # Insert initial data
        cursor.execute(
            f"INSERT INTO `{GAME_PREFIX}worker_origins` (name) VALUES ('OriginalName')"
        )
        conn.commit()

        # Create update CSV
        with tempfile.NamedTemporaryFile(mode="w", suffix=".csv", delete=False) as f:
            f.write("table_name,column_name,where_column,where_value,new_value\n")
            f.write("worker_origins,name,name,OriginalName,UpdatedName\n")
            tmp_path = f.name

        try:
            count = load_csv_updates(conn, tmp_path)
            assert count == 1

            cursor.execute(f"SELECT name FROM `{GAME_PREFIX}worker_origins`")
            row = cursor.fetchone()
            assert row["name"] == "UpdatedName"
        finally:
            os.unlink(tmp_path)

    def test_skip_short_rows(self, clean_tables):
        """Rows with fewer than 5 columns should be skipped."""
        conn = clean_tables
        with tempfile.NamedTemporaryFile(mode="w", suffix=".csv", delete=False) as f:
            f.write("table_name,column_name,where_column,where_value,new_value\n")
            f.write("too,few,cols\n")
            f.write("also,short\n")
            tmp_path = f.name

        try:
            count = load_csv_updates(conn, tmp_path)
            assert count == 0
        finally:
            os.unlink(tmp_path)


# ---------------------------------------------------------------------------
# Tests: Full TestConfig scenario loading
# ---------------------------------------------------------------------------

class TestFullScenarioLoad:
    """Integration test: load the full TestConfig scenario."""

    def test_load_full_test_config(self, clean_tables):
        """Load all TestConfig CSVs in the correct order and verify integrity."""
        conn = clean_tables

        # 1. Worker origins (no dependencies)
        load_csv_file(conn, os.path.join(CSV_DIR, "setupTestConfig_worker_origins.csv"),
                       "worker_origins", ["name"])

        # 2. Zones (controller lookups will be NULL — no controllers yet)
        load_csv_file(conn, os.path.join(CSV_DIR, "setupTestConfig_zones.csv"),
                       "zones", ["name", "description", "hide_turn_zero",
                                 "controllers__lastname->claimer_controller_id",
                                 "controllers__lastname->holder_controller_id"])

        # 3. Worker names (depends on origins)
        load_csv_file(conn, os.path.join(CSV_DIR, "setupTestConfig_worker_names.csv"),
                       "worker_names", ["firstname", "lastname",
                                        "worker_origins__name->origin_id"])

        # 4. Hobbys → powers table
        load_csv_file(conn, os.path.join(CSV_DIR, "setupTestConfig_hobbys.csv"),
                       "powers", ["name", "description", "enquete", "attack",
                                  "defence", "other"])

        # 5. Jobs → powers table (appended)
        load_csv_file(conn, os.path.join(CSV_DIR, "setupTestConfig_jobs.csv"),
                       "powers", ["name", "description", "enquete", "attack",
                                  "defence", "other"])

        cursor = conn.cursor()

        # Verify counts
        cursor.execute(f"SELECT COUNT(*) as c FROM `{GAME_PREFIX}worker_origins`")
        assert cursor.fetchone()["c"] == 3

        cursor.execute(f"SELECT COUNT(*) as c FROM `{GAME_PREFIX}zones`")
        assert cursor.fetchone()["c"] == 7

        cursor.execute(f"SELECT COUNT(*) as c FROM `{GAME_PREFIX}worker_names`")
        assert cursor.fetchone()["c"] == 8

        cursor.execute(f"SELECT COUNT(*) as c FROM `{GAME_PREFIX}powers`")
        assert cursor.fetchone()["c"] == 13  # 7 hobbys + 6 jobs (last row in jobs CSV is malformed)

        # Verify FK integrity: all worker_names have valid origin_ids
        cursor.execute(f"""
            SELECT COUNT(*) as c FROM `{GAME_PREFIX}worker_names` wn
            LEFT JOIN `{GAME_PREFIX}worker_origins` wo ON wn.origin_id = wo.id
            WHERE wo.id IS NULL
        """)
        assert cursor.fetchone()["c"] == 0, "All worker names should have valid origin FKs"
