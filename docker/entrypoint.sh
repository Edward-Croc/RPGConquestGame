#!/bin/bash
set -e

# Always regenerate var/local_config.ini from environment variables.
# (Skipping regeneration left stale credentials when the bind-mounted
# source directory carried over a previous run's config file.)
CONFIG_FILE="/var/www/html/RPGConquestGameTest/var/local_config.ini"
echo "Generating $CONFIG_FILE from environment variables..."
cat > "$CONFIG_FILE" <<EOF
host = ${DB_HOST:-mysql}
dbname = ${DB_NAME:-rpgconquestgame}
username = ${DB_USER:-rpg_user}
password = ${DB_PASS:-rpg_pass}
db_type = ${DB_TYPE:-mysql}
folder = ${FOLDER:-RPGConquestGameTest}
game_prefix = ${GAME_PREFIX:-}
EOF
echo "Config file created."

# Ensure the auto-backup directory exists and is writable by www-data.
# The bind-mount from the host into /var/www/html/... shadows any dir
# created at image-build time, so we have to (re)create it at container
# start after the mount is in place.
BACKUP_DIR="/var/www/html/RPGConquestGameTest/var/backups"
mkdir -p "$BACKUP_DIR"
chown -R www-data:www-data "$BACKUP_DIR" 2>/dev/null || chmod 777 "$BACKUP_DIR"
echo "Backup directory ready at $BACKUP_DIR."

# Initialize the database schema if requested
if [ "${INIT_DB:-false}" = "true" ]; then
    SQL_FILE="/var/www/html/RPGConquestGameTest/var/mysql/setupBDD.sql"
    if [ -f "$SQL_FILE" ]; then
        echo "Initializing database schema..."
        PREFIX="${GAME_PREFIX:-}"
        sed "s/{prefix}/$PREFIX/g" "$SQL_FILE" | mysql -h "${DB_HOST:-mysql}" -u "${DB_USER:-rpg_user}" -p"${DB_PASS:-rpg_pass}" "${DB_NAME:-rpgconquestgame}" 2>/dev/null || echo "DB init skipped (may already exist or MySQL not ready yet)."
    fi
fi

exec "$@"
