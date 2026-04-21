#!/bin/bash
# Smoke test for the Docker Compose setup.
# Boots from scratch, waits for the app, checks login page + DB connectivity.
#
# Usage:
#     ./docker/smoke_test.sh
#
# Exits 0 on success, non-zero on failure. Leaves the containers running.

set -e

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT"

echo "=== Docker smoke test ==="

echo "-> Resetting containers + data volume..."
docker compose down -v >/dev/null 2>&1 || true
rm -f "$PROJECT_ROOT/var/local_config.ini"

echo "-> Building and starting stack..."
docker compose up -d --build >/dev/null

echo "-> Waiting for PHP to answer on http://localhost:8080 ..."
ATTEMPTS=0
until curl -sf http://localhost:8080/RPGConquestGameTest/connection/loginForm.php >/dev/null 2>&1; do
    ATTEMPTS=$((ATTEMPTS + 1))
    if [ "$ATTEMPTS" -gt 60 ]; then
        echo "FAIL: PHP did not respond within 3 minutes"
        docker compose logs php | tail -30
        exit 1
    fi
    sleep 3
done
echo "   PHP is up after ${ATTEMPTS} attempts."

echo "-> Checking login page renders..."
BODY=$(curl -s http://localhost:8080/RPGConquestGameTest/connection/loginForm.php)
if ! echo "$BODY" | grep -q "name=\"username\""; then
    echo "FAIL: login form not found in response"
    echo "$BODY" | head -20
    exit 1
fi
echo "   Login form present."

echo "-> Checking gm user exists in DB..."
GM_COUNT=$(docker exec rpg_mysql mysql -u rpg_user -prpg_pass rpgconquestgame \
    -sN -e "SELECT COUNT(*) FROM game_test_players WHERE username='gm'" 2>/dev/null || echo "0")
if [ "$GM_COUNT" != "1" ]; then
    echo "FAIL: gm user not found (got count=${GM_COUNT})"
    exit 1
fi
echo "   gm user present."

echo "-> Checking schema tables were created..."
TABLE_COUNT=$(docker exec rpg_mysql mysql -u rpg_user -prpg_pass rpgconquestgame \
    -sN -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='rpgconquestgame'" 2>/dev/null)
if [ -z "$TABLE_COUNT" ] || [ "$TABLE_COUNT" -lt 20 ]; then
    echo "FAIL: expected 20+ tables, got ${TABLE_COUNT}"
    exit 1
fi
echo "   ${TABLE_COUNT} tables created."

echo ""
echo "=== PASS: Docker setup is healthy ==="
echo "You can now browse: http://localhost:8080/RPGConquestGameTest/"
echo "Login: gm / orga"
