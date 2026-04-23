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

echo "-> Waiting for PHP + DB readiness (login form must render without DB error)..."
ATTEMPTS=0
# curl -sf only checks HTTP status; a 200 page can still contain a DB error.
# Wait until the response body actually contains the login form username input.
until curl -s http://localhost:8080/RPGConquestGameTest/connection/loginForm.php 2>/dev/null | grep -q 'name="username"'; do
    ATTEMPTS=$((ATTEMPTS + 1))
    if [ "$ATTEMPTS" -gt 60 ]; then
        echo "FAIL: login form did not render within 3 minutes (PHP or DB not ready)"
        docker compose logs php | tail -30
        exit 1
    fi
    sleep 3
done
echo "   Login form rendering after ${ATTEMPTS} attempts."

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
