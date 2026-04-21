#!/bin/bash
# Initialize the MySQL database with the game schema.
# This script is mounted into /docker-entrypoint-initdb.d/ and runs
# automatically on first container start (when the data volume is empty).
#
# It replaces {prefix} placeholders in the SQL file before execution.
# Set GAME_PREFIX (primary, e.g. "game_test_") and optional GAME_PREFIX_2
# (secondary, e.g. "game_test2_") to bootstrap both parallel game prefixes.

SQL_FILE="/tmp/setupBDD.sql"

if [ ! -f "$SQL_FILE" ]; then
    echo "WARNING: $SQL_FILE not found, skipping schema init."
    exit 0
fi

for PREFIX in "${GAME_PREFIX:-}" "${GAME_PREFIX_2:-}"; do
    # Skip empty (no secondary set means single-prefix mode)
    [ -z "$PREFIX" ] && continue
    echo "Initializing database with prefix='$PREFIX'..."
    sed "s/{prefix}/$PREFIX/g" "$SQL_FILE" | mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"
    echo "  schema loaded for $PREFIX"
done
