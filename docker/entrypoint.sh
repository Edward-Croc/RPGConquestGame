#!/bin/bash
set -e

# Always regenerate var/local_config.ini from environment variables.
# (Skipping regeneration left stale credentials when the bind-mounted
# source directory carried over a previous run's config file.)
CONFIG_FILE="/var/www/html/RPGConquestGame/var/local_config.ini"
echo "Generating $CONFIG_FILE from environment variables..."
cat > "$CONFIG_FILE" <<EOF
host = ${DB_HOST:-mysql}
dbname = ${DB_NAME:-rpgconquestgame}
username = ${DB_USER:-rpg_user}
password = ${DB_PASS:-rpg_pass}
db_type = ${DB_TYPE:-mysql}
folder = ${FOLDER:-RPGConquestGame}
game_prefix = ${GAME_PREFIX:-}
EOF
echo "Config file created."

# Initialize the database schema if requested
if [ "${INIT_DB:-false}" = "true" ]; then
    SQL_FILE="/var/www/html/RPGConquestGame/var/mysql/setupBDD.sql"
    if [ -f "$SQL_FILE" ]; then
        echo "Initializing database schema..."
        PREFIX="${GAME_PREFIX:-}"
        sed "s/{prefix}/$PREFIX/g" "$SQL_FILE" | mysql -h "${DB_HOST:-mysql}" -u "${DB_USER:-rpg_user}" -p"${DB_PASS:-rpg_pass}" "${DB_NAME:-rpgconquestgame}" 2>/dev/null || echo "DB init skipped (may already exist or MySQL not ready yet)."
    fi
fi

exec "$@"
