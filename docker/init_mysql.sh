#!/bin/bash
# Initialize the MySQL database with the game schema.
# This script is mounted into /docker-entrypoint-initdb.d/ and runs
# automatically on first container start (when the data volume is empty).
#
# It replaces {prefix} placeholders in the SQL file before execution.
# Set GAME_PREFIX environment variable to use table prefixes (e.g. "game2_").

PREFIX="${GAME_PREFIX:-}"
SQL_FILE="/tmp/setupBDD.sql"

if [ -f "$SQL_FILE" ]; then
    echo "Initializing database with prefix='$PREFIX'..."
    sed "s/{prefix}/$PREFIX/g" "$SQL_FILE" | mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"
    echo "Database schema loaded."
else
    echo "WARNING: $SQL_FILE not found, skipping schema init."
fi
