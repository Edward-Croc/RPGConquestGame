# Windows setup guide

This walks through running RPGConquestGame and its test suite on Windows 10/11
using Docker Desktop + WSL2.

## 1. Install prerequisites

1. **Docker Desktop for Windows** — https://www.docker.com/products/docker-desktop/
   - During installation, ensure "Use WSL 2 instead of Hyper-V" is checked.
   - After install, open Docker Desktop and wait until the status bar says *Docker Desktop is running*.

2. **WSL2 + a Linux distro** (Ubuntu 22.04 recommended)
   - Open PowerShell as Administrator:
     ```powershell
     wsl --install -d Ubuntu-22.04
     ```
   - Reboot when prompted, then set a username/password on first launch.

3. **Git** — inside WSL: `sudo apt update && sudo apt install -y git`

4. **Python 3 + pip** (only needed for running tests) — inside WSL:
   ```bash
   sudo apt install -y python3 python3-pip python3-venv
   ```

## 2. Clone the repository (inside WSL, NOT a Windows drive)

Docker file-mount performance on Windows drives (`/mnt/c/...`) is significantly
slower than on the WSL filesystem. Clone into the WSL user home:

```bash
cd ~
git clone git@github.com:Edward-Croc/RPGConquestGame.git
cd RPGConquestGame
```

Before cloning, disable the default CRLF conversion to avoid shell-script
line-ending issues:

```bash
git config --global core.autocrlf false
```

(The repo also has a `.gitattributes` file that enforces LF on shell scripts,
but setting the global option first is defense-in-depth.)

## 3. Start the app

From the repo root inside WSL:

```bash
docker compose up -d --build
```

First build takes 1–2 minutes (downloading PHP + MySQL images, installing
`default-mysql-client`). Subsequent starts are near-instant.

Once running:

- **App:** http://localhost:8080/RPGConquestGame/ (open in your Windows browser)
- **Login:** `gm` / `orga`
- **MySQL:** `localhost:3307` (user `rpg_user`, pass `rpg_pass`, db `rpgconquestgame`)

Stop with `docker compose down`, or `docker compose down -v` to also wipe
the database volume.

## 4. Run the smoke test

Verifies a clean boot + schema loaded + gm user present:

```bash
./docker/smoke_test.sh
```

On success prints `=== PASS: Docker setup is healthy ===`.

## 5. Run the full test suite

Install Python dependencies (one-time):

```bash
python3 -m pip install --user -r requirements.txt
playwright install chromium
```

Then from the repo root:

```bash
python3 -m pytest tests/ -v
```

Expected: **164 tests pass** in ~5 minutes (first run is slower due to
Chromium caching).

### Preserving DB state after a test run (for manual browsing):

```bash
KEEP_DB=1 python3 -m pytest tests/test_agent_combat_e2e.py -v
```

Then browse http://localhost:8080/RPGConquestGame/ (login gm / orga,
pick a controller, browse workers) to see the post-combat state.

## 6. Common Windows pitfalls

| Symptom | Cause | Fix |
|---------|-------|-----|
| `bash: bad interpreter: \r\n` | CRLF line endings on `*.sh` | `git config --global core.autocrlf false` then re-clone |
| Slow `docker compose up` | Repo on `/mnt/c/...` | Move repo to `~/` inside WSL |
| Port 8080 in use | Another service (IIS, Jenkins, etc.) | Change `"8080:80"` to `"9080:80"` in `docker-compose.yml` |
| Port 3307 in use | Local MySQL installed | Change `"3307:3306"` to `"3308:3306"` and set `MYSQL_PORT=3308` when running pytest |
| Docker Desktop not running | Windows service stopped | Start "Docker Desktop" from Start Menu |
| Tests fail with permission errors | Repo cloned as Administrator | Re-clone as normal user inside WSL |
| Playwright install hangs | Browsers not downloaded | Run `playwright install --with-deps chromium` once |

## 7. Updating the code

Inside WSL:

```bash
cd ~/RPGConquestGame
git pull
docker compose up -d --build   # rebuild if Dockerfile or dependencies changed
```

## 8. Running just one test file

```bash
python3 -m pytest tests/test_agent_combat_e2e.py -v
python3 -m pytest tests/test_controller_recruitment_e2e.py -v
```

See `tests/` for the full list.
