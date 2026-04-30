# ML CLI (M Lhuillier)

Quick install (Windows)
```bat
curl -L https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/install-ml.bat -o install-ml.bat && install-ml.bat
```

Quick install (macOS/Linux)
```bash
curl -L https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/ml -o ml
chmod +x ml
sudo mv ml /usr/local/bin/ml
```

ML CLI is a PHP command-line toolkit for scaffolding projects, managing a shared user database, automating local development workflows, and integrating AI capabilities into your stack. It supports Windows through `ml.bat`/wrappers and macOS/Linux through the Unix `ml` launcher.

It is designed for teams building multiple PHP apps (typically under XAMPP) that need:

- fast project bootstrap
- consistent folder/file structure
- centralized user account storage
- quick RBAC/PBAC table provisioning
- repeated backup and update workflows
- AI-powered tooling via NVIDIA NIM and Free Claude Code
- dynamic sidebar menu generation with AI assistance

## What This System Does

ML CLI provides one command surface (`ml`) for:

- Creating starter PHP project structures
- Navigating quickly between projects under your XAMPP `htdocs` directory
- Opening projects in browser (`http://localhost/<project>`) or online via ngrok tunnel
- Importing and testing a shared `userdb` schema
- Creating user accounts from terminal prompts
- Creating project-specific RBAC and PBAC tables in `userdb`
- Configuring and running MySQL schema backups via `mysqldump`
- Checking and applying CLI updates from GitHub
- Adding interactive sidebar menus with Material Icons (AI-assisted via NVIDIA NIM)
- Installing and managing **Free Claude Code** (uvicorn API server + Claude Code agent)
- Migrating userdb tables between databases (decentralize / centralize)
- Exporting MySQL databases via MySQL Workbench (6 export methods)
- Opening project folders in File Explorer, Finder, or the Linux desktop file manager

## Core Purpose

This repository solves repetitive setup work for local PHP development across Windows, macOS, and Linux.

Instead of manually creating files, wiring auth tables, writing one-off DB scripts, or setting up AI tooling for every project, you can run a small set of `ml` commands and start building features immediately.

## Prerequisites

- PHP CLI installed and on `PATH` (auto-detected on Windows XAMPP)
- MySQL/MariaDB access
- Git installed (for `ml install ai`)
- Node.js/npm installed (for `ml install ai` and Claude Code)
- Internet access for remote helper downloads/updates

Platform-specific notes:
- **Windows**: PHP from XAMPP (`C:\xampp\php\php.exe`) is auto-detected
- **macOS / Linux**: `php`, `git`, `npm`, and `curl` need to be on your PATH; projects default to `~/xampp/htdocs/`

Optional:

- Node.js (only for rebuilding docs JSON if you are not using AI features)
- VS Code CLI (`code`) for `ml nav` open-in-editor flow
- MySQL Workbench (for `ml wb` and `ml wb --export`)
- ngrok (for `ml serve -o` online sharing)
- NVIDIA NIM API key (for AI-assisted menu generation)

## Installation

### Option A: Standard Installer (Windows)

From the repository root:

```bat
install-ml.bat
```

What it does:

- installs CLI runtime to `C:\ML CLI\Tools`
- copies shell wrapper(s)
- updates user PATH
- installs version metadata (`VERSION`, `version.txt`)

After install, open a new terminal and verify:

```bat
ml --v
```

### Option B: Mac / Linux Shell Install

Download and set up the Unix shell wrapper from the repo:

On macOS/Linux, `ml` requires PHP to be installed. Project lookup uses the first available path from:

- `ML_HTDOCS` environment variable
- `~/xampp/htdocs`
- `/Applications/XAMPP/htdocs`
- `/opt/lampp/htdocs`
- `/var/www/html`

```bash
# Clone or copy the ml file somewhere on your PATH
chmod +x ml
sudo cp ml /usr/local/bin/ml

# Verify
ml --v
```

### Option C: Local Developer Install

```bat
php ml-local.php
```

You can also pass a custom destination:

```bat
php ml-local.php "C:\Some\Path"
```

On macOS/Linux, the default local developer destination is `~/.ml-cli`:

```bash
php ml-local.php
```

### Option D: NPM Global Install (CLI command only)

This installs the `ml` command through npm so users do not need to run the
manual curl installer command.

```bat
npm install -g mlhuillier-cli
```

After install, open a new terminal and verify:

```bat
ml --v
```

Note:

- PHP is still required on the machine for CLI features that execute PHP scripts.

### PowerShell Wrapper Setup

```powershell
.\install-wrappers.ps1
```

This installs wrappers to `%USERPROFILE%\bin` and updates your PowerShell profile/PATH behavior.

## Platform Paths

| Purpose | Windows | macOS/Linux |
|--------|---------|-------------|
| CLI runtime | `C:\ML CLI\Tools` | `~/.ml-cli` for local developer installs, or wherever `ml` is placed on PATH |
| Project root | `C:\xampp\htdocs` | `ML_HTDOCS`, `~/xampp/htdocs`, `/Applications/XAMPP/htdocs`, `/opt/lampp/htdocs`, then `/var/www/html` |
| Backup config | `C:\ML CLI\Tools\mlcli-config.json` | `~/.ml-cli/mlcli-config.json` |
| Backup output | `C:\ML CLI\Backup` | `~/ML CLI/Backup` |
| Workbench export output | `C:\ML CLI\Exports` | `~/ML CLI/Exports` |
| Free Claude Code | `C:\free-claude-code\free-claude-code` | `~/.free-claude-code/free-claude-code` |

You can override paths with environment variables:

- `ML_HTDOCS` for project lookup
- `ML_CLI_TOOLS` for config/runtime helper files
- `ML_CLI_BACKUP` for backup output
- `ML_CLI_EXPORTS` for Workbench export output

## Quick Start

1. Create project

```bat
ml create my-project
cd my-project
```

2. Set DB values in `.env` (especially `USERDB_NAME`)

3. Import and test shared user database

```bat
ml add userdb
ml test userdb
```

4. Create access-control tables for this project

```bat
ml create --rbac my_project
ml create --pbac my_project
```

5. Create first account

```bat
ml create --a
```

6. Add a sidebar menu with AI-assisted metadata

```bat
ml add menu
```

7. Serve locally or share online

```bat
ml serve
ml serve -o       (share via ngrok tunnel)
```

## Command Reference

### General

- `ml --h` : show help
- `ml --v` : show installed version
- `ml --c` : check remote version (shows changelog highlights)
- `ml update` : update installed CLI files
- `ml --d` : download installer helper
- `ml doc` / `ml docs` : open hosted docs site

### Project / Workflow

- `ml create <project_name>` : scaffold a new project
- `ml nav` : interactive navigation under your configured `htdocs`
- `ml nav --<project_name>` : jump directly to project
- `ml nav --new` : jump to your configured `htdocs`
- `ml serve [project_name]` : open project URL in browser
- `ml serve -o` / `ml serve --online` : open via ngrok tunnel (public URL)
- `ml serve -stop` / `ml serve --stop` : stop active ngrok tunnel
- `ml rev` / `ml reveal [project_name_or_path]` : open folder in File Explorer, Finder, or file manager

### Database / UserDB

- `ml test <database>` : test DB connection
- `ml test userdb` : userdb-specific connectivity and schema check
- `ml add userdb` : import userdb schema SQL
- `ml create --config` : create backup config JSON
- `ml --b [schema|all]` : backup one/all schemas
- `ml wb` : open MySQL Workbench GUI
- `ml wb --export -db <name> [-tb <table>] [-m 1-6] [-fn <folder>]` : export DB to SQL
- `ml migrate -db <DATABASE>` : migrate userdb tables to a target database
- `ml migrate global` : restore project's DB back to centralized userdb

### Account / Access Control

- `ml create --a` : interactive account creation
- `ml create --rbac <project_name>` : create `<project_name>_rbac` in userdb
- `ml create --pbac <project_name>` : create `<project_name>_pbac` in userdb and scaffold PBAC files
- `ml gen [project_name]` : generate local PBAC access map

### AI Integration

- `ml install ai` : install Free Claude Code stack (`C:\free-claude-code\free-claude-code` on Windows, `~/.free-claude-code/free-claude-code` on macOS/Linux)
- `ml --ai` : start uvicorn + Claude Code (visible terminals)
- `ml --ai claude` : start uvicorn in background, Claude Code visible
- `ml --ai bg` : start both processes in the background
- `ml --ai stop` : stop all Free Claude Code processes
- `ml --ai restart` : stop and restart both in the background
- `ml --ai cm` : change Opus, Sonnet, Haiku, or default model in Free Claude Code `.env`
- `ml --ai key` : update or rotate `NVIDIA_NIM_API_KEY` in Free Claude Code `.env`

`ml install ai` follows the upstream Free Claude Code requirements:

- Windows: installs `uv` through Astral's PowerShell installer
- macOS/Linux: installs `uv` through `curl -LsSf https://astral.sh/uv/install.sh | sh`
- then runs `uv self update` and `uv python install 3.14`
- installs Claude Code globally with `npm install -g @anthropic-ai/claude-code`

### Menu / UI Scaffolding

- `ml add menu` : interactively add sidebar menu and submenus with Material Icons
  - Optionally scaffolds `src/pages/<menu>/` PHP and CSS files
  - Uses NVIDIA NIM AI to generate dynamic menu/submenu metadata with descriptions and tags

## Generated Project Scaffold

The scaffolder (`generate-file-structure.php`) creates a complete starter app including:

- `src/config` (env/db/auth/session/csrf/middleware/error helpers)
- `src/controllers`, `src/models`, `src/pages`, `src/modals`, `src/templates`
- `public`, `public/api`, `public/components`
- starter UI/layout files (`index.php`, CSS templates, modal/components)
- project `.env` and starter `README.md`

## Database Model

Main shared database: `userdb`

Included migrations:

- `migration/userdb/userdb_users.sql`
- `migration/userdb/userdb_userlogs.sql`

### `users`

Important columns:

- `id_number` (unique)
- `username` (unique)
- `firstname`, `middlename`, `lastname`
- `role` (default `Public`)
- `password` (hashed)
- `dateCreated`

### `userlogs`

Important columns:

- `log_id`
- `id_number` (FK -> `users.id_number`)
- `last_online`
- `status`
- `dateModified`

### Project RBAC Table

Created by `ml create --rbac <project_name>`

- table name: `<project_name>_rbac`
- PK: `<project_name>_no`
- includes `id_number` FK and `user_role`

### Project PBAC Table

Created by `ml create --pbac <project_name>`

- table name: `<project_name>_pbac`
- PK: `<project_name>_no`
- includes `id_number` FK, `access_level`, `permissions` (text/JSON-style payload)

## Account Creation Behavior

`account-insert.php` / `ml create --a`:

- reads nearest `.env`
- requires valid `USERDB_NAME`
- prompts for ID, first/last name, role
- generates username from last name + ID
- stores password as a hash (`password_hash`)
- inserts default user log status

## Sidebar Menu with AI

`ml add menu` interactively:
1. Prompts for menu name and comma-separated submenus
2. Creates the menu and submenu entries in the sidebar database table
3. Optionally scaffolds PHP/CSS files under `src/pages/<menu>/`
4. Validates Material Icons using NVIDIA NIM (with per-submenu metadata generation)

The NVIDIA NIM integration (`src/sidebar/nvidia-nim-*.php`) handles:
- Dynamic `description` and `tags` generation per submenu
- Validation and fallback to font-library defaults when NIM is unavailable
- Connectivity and performance diagnostic tooling

## Backup Workflow

1. Configure backup settings:

```bat
ml create --config
```

This writes:

- Windows: `C:\ML CLI\Tools\mlcli-config.json`
- macOS/Linux: `~/.ml-cli/mlcli-config.json`

2. Run backup:

```bat
ml --b
ml --b userdb
ml --b all
```

Backups are written under:

- Windows: `C:\ML CLI\Backup\BACKUP_MM-DD-YY\<schema>\<schema>.sql`
- macOS/Linux: `~/ML CLI/Backup/BACKUP_MM-DD-YY/<schema>/<schema>.sql`

## Workbench Export

`ml wb --export` dumps databases via MySQL Workbench with 6 methods:

| Method | Description |
|--------|-------------|
| 1 | Structure Only |
| 2 | Data Only |
| 3 | Data + Structure |
| 4 | Structure + Schema |
| 5 | Data + Schema |
| 6 | Full Export (Data + Structure + Schema) |

Examples:

```bat
ml wb --export -db userdb -tb users -m 6 -fn backup1
ml wb --export -db userdb,gledb -tb * -tb users -m 6 -fn mydump
```

Output:

- Windows: `C:\ML CLI\Exports\<MM-DD-YYYY>\<FOLDER_NAME>\<database>.sql`
- macOS/Linux: `~/ML CLI/Exports/<MM-DD-YYYY>/<FOLDER_NAME>/<database>.sql`

## Migration Workflow

```bat
ml migrate -db my_project_db
```

This migrates `userdb` table structures and data to a target database, rewrites project `.env` references, and updates all DB configuration files for the new target.

```bat
ml migrate global
```

Restores the project back to centralized `userdb` by copying tables from the project's current decentralized database back and resetting `.env` to use `userdb`.

## Online Sharing

```bat
ml serve -o
ml serve projectname -o
ml serve projectname --online
ml serve -stop
```

Uses ngrok to create a public HTTPS URL (`https://<subdomain>.ngrok-free.app/<project>`).
Falls back to port 8080 if port 80 is unavailable.

## Update and Versioning

- Local version source: `VERSION`
- CLI constants: `ML_VERSION` in `ml.bat` and `ml`
- Check remote: `ml --c` (shows release highlights from version history)
- Apply update: `ml update`
- Display changelog: `ml --h --c`
- Batch version bump helper (repo maintenance):

```bat
php v.php <new-version>
```

`v.php` updates:

- `ml.bat`
- `ml`
- `install-ml.bat`
- `uninstall-ml.bat`
- `VERSION`

## Uninstall

Windows:

```bat
uninstall-ml.bat
```

Uninstaller removes installed runtime (`C:\ML CLI\Tools`), PATH entries, and wrapper/profile artifacts.

macOS/Linux:

```bash
rm -f /usr/local/bin/ml
rm -rf ~/.ml-cli
```

## Documentation Site

This repo includes a static docs app in `documentation/`.

Build command metadata JSON from `ml.bat`:

```bash
npm run docs:build
```

Watch mode:

```bash
npm run docs:watch
```

Output:

- `documentation/assets/data/commands.json`

Open docs page:

- `documentation/index.html`
- Or via `ml doc` / `ml docs`

## Repository Structure

- `ml.bat`, `ml.cmd`, `ml.ps1`, `ml` : command entry points/wrappers
- `generate-file-structure.php` : main scaffolder
- `generate-file-remote.php` : remote loader stub
- `ml-nav.php`, `ml-serve.php`, `ml-update.php` : workflow helpers
- `ai-commands.php`, `ai-installer.php` : Free Claude Code lifecycle management
- `account-insert.php`, `userdb-import.php`, `userdb-con-test.php` : DB/account utilities
- `sidebar-add-menu.php`, `script/user-migrate.php` : menu and migration tools
- `reveal-in-folder/` : folder opener for File Explorer, Finder, and Linux desktop file managers
- `rbac/`, `pbac/` : access-control table generators
- `workbench/`, `backup-cli/`, `db-config/` : database tooling
- `migration/` : SQL sources
- `documentation/` : docs website assets
- `scripts/` : docs/template extraction tooling
- `scripts/nvidia-nim-*.php` : NVIDIA NIM API integration for AI menu metadata
- `version-history/` : changelog generation tooling

## Security Notes

- `.env` and `mlcli-config.json` can contain plaintext DB credentials
- keep these files out of version control
- use least-privilege DB users where possible
- rotate and protect SQL backup files
- review generated auth/permission logic before production use
- NVIDIA NIM API key is stored in project `.env`; protect it like any credential

## Troubleshooting

### `ml` command not recognized

- reopen terminal after install
- Windows: verify PATH contains `C:\ML CLI\Tools` and/or `%USERPROFILE%\bin`, then re-run `install-ml.bat`
- macOS/Linux: verify `ml` is executable and available in PATH, for example `/usr/local/bin/ml`

### DB connection failures

- run `ml test userdb`
- verify `.env` values (`DB_*`, `USERDB_*`)
- ensure MySQL is running and reachable

### Backup errors (`mysqldump not found`)

- run `ml create --config`
- set a valid full path to `mysqldump` / `mysqldump.exe`

### Remote fetch/update errors

- check internet/proxy/firewall for GitHub raw/API endpoints
- retry command

### ngrok not available for online sharing

- install ngrok or use `ml serve` for local-only access

### AI menu generation fails

- check NVIDIA NIM API key in `.env` (`NIM_API_KEY`)
- fallback: icons still work via font-library defaults; metadata generation is skipped gracefully

## Notes

- Windows, macOS, and Linux are supported through platform-specific wrappers and helper behavior.
- Many helper flows fetch the latest scripts from GitHub at runtime.
- For stable offline behavior, keep local script copies and avoid remote dependency paths where needed.
- Free Claude Code requires Python and Node.js for the uvicorn API server component.
