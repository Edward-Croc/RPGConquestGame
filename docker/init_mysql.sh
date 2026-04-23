#!/bin/bash
# Initialize the MySQL database with the game schema + minimal seed data.
# This script is mounted into /docker-entrypoint-initdb.d/ and runs
# automatically on first container start (when the data volume is empty).
#
# For each prefix (GAME_PREFIX and optional GAME_PREFIX_2):
#   1. Load setupBDD.sql — creates all tables with {prefix} substituted
#   2. Load minimalData.sql — inserts gm user, core config, starting
#      mechanics row, fixed power type ids (idempotent)

SCHEMA_FILE="/tmp/setupBDD.sql"
MINIMAL_FILE="/tmp/minimalData.sql"

if [ ! -f "$SCHEMA_FILE" ]; then
    echo "WARNING: $SCHEMA_FILE not found, skipping schema init."
    exit 0
fi

for PREFIX in "${GAME_PREFIX:-}" "${GAME_PREFIX_2:-}"; do
    # Skip empty (no secondary set means single-prefix mode)
    [ -z "$PREFIX" ] && continue
    echo "Initializing database with prefix='$PREFIX'..."
    sed "s/{prefix}/$PREFIX/g" "$SCHEMA_FILE" | mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"
    echo "  schema loaded for $PREFIX"
    if [ -f "$MINIMAL_FILE" ]; then
        sed "s/{prefix}/$PREFIX/g" "$MINIMAL_FILE" | mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"
        echo "  minimal data loaded for $PREFIX"
    fi
done
