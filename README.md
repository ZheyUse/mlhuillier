# ML CLI (M Lhuillier)

Quick install (curl)
```bat
curl -L https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/install-ml.bat -o install-ml.bat && install-ml.bat
```

ML CLI is a Windows-first PHP command-line toolkit for scaffolding projects, managing a shared user database, automating local development workflows, and integrating AI capabilities into your stack.

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
- Navigating quickly between projects under `C:\xampp\htdocs`
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
- Opening project folders in Windows File Explorer

## Core Purpose

This repository solves repetitive setup work for local PHP development on Windows.

Instead of manually creating files, wiring auth tables, writing one-off DB scripts, or setting up AI tooling for every project, you can run a small set of `ml` commands and start building features immediately.

## Prerequisites

- Windows (PowerShell / CMD)
- PHP CLI installed (XAMPP `C:\xampp\php\php.exe` is auto-detected)
- MySQL/MariaDB access
- Internet access for remote helper downloads/updates

Optional:

- Node.js (only for rebuilding docs JSON)
- VS Code CLI (`code`) for `ml nav` open-in-editor flow
- MySQL Workbench (for `ml wb` and `ml wb --export`)
- ngrok (for `ml serve -o` online sharing)
- NVIDIA NIM API key (for AI-assisted menu generation)

## Installation

### Option A: Standard Installer (recommended)

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

### Option B: Local Developer Install

```bat
php ml-local.php
```

You can also pass a custom destination:

```bat
php ml-local.php "C:\Some\Path"
```

### Option C: NPM Global Install (CLI command only)

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

- This package is Windows-first and intended to expose the `ml` command.
- PHP is still required on the machine for CLI features that execute PHP scripts.

### PowerShell Wrapper Setup

```powershell
.\install-wrappers.ps1
```

This installs wrappers to `%USERPROFILE%\bin` and updates your PowerShell profile/PATH behavior.

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
- `ml nav` : interactive navigation under `C:\xampp\htdocs`
- `ml nav --<project_name>` : jump directly to project
- `ml nav --new` : jump to `C:\xampp\htdocs`
- `ml serve [project_name]` : open project URL in browser
- `ml serve -o` / `ml serve --online` : open via ngrok tunnel (public URL)
- `ml serve -stop` / `ml serve --stop` : stop active ngrok tunnel
- `ml rev` / `ml reveal [project_name_or_path]` : open folder in File Explorer

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

- `ml install ai` : install Free Claude Code stack to `C:\free-claude-code\free-claude-code`
- `ml --ai` : start uvicorn + Claude Code (visible terminals)
- `ml --ai claude` : start uvicorn in background, Claude Code visible
- `ml --ai bg` : start both processes in the background
- `ml --ai stop` : stop all Free Claude Code processes
- `ml --ai restart` : stop and restart both in the background

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

- `C:\ML CLI\Tools\mlcli-config.json`

2. Run backup:

```bat
ml --b
ml --b userdb
ml --b all
```

Backups are written under:

- `C:\ML CLI\Backup\BACKUP_MM-DD-YY\<schema>\<schema>.sql`

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

Output: `C:\ML CLI\Exports\<MM-DD-YYYY>\<FOLDER_NAME>\<database>.sql`

## Migration Workflow

```bat
ml migrate -db my_project_db
```

This migrates `userdb` table structures and data to a target database, rewrites project `.env` references, and updates all DB configuration files for the new target.

```bat
ml migrate global
```

Restores the project back to centralized `userdb` — copies tables from the project's current (decentralized) database back and resets `.env` to use `userdb`.

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
- CLI constant in `ml.bat`: `ML_VERSION`
- Check remote: `ml --c` (shows release highlights from version history)
- Apply update: `ml update`
- Display changelog: `ml --h --c`
- Batch version bump helper (repo maintenance):

```bat
php v.php <new-version>
```

`v.php` updates:

- `ml.bat`
- `install-ml.bat`
- `uninstall-ml.bat`
- `VERSION`

## Uninstall

```bat
uninstall-ml.bat
```

Uninstaller removes installed runtime (`C:\ML CLI\Tools`), PATH entries, and wrapper/profile artifacts.

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

- `ml.bat`, `ml.cmd`, `ml.ps1` : command entry points/wrappers
- `generate-file-structure.php` : main scaffolder
- `generate-file-remote.php` : remote loader stub
- `ml-nav.php`, `ml-serve.php`, `ml-update.php` : workflow helpers
- `ai-commands.php`, `ai-installer.php` : Free Claude Code lifecycle management
- `account-insert.php`, `userdb-import.php`, `userdb-con-test.php` : DB/account utilities
- `sidebar-add-menu.php`, `script/user-migrate.php` : menu and migration tools
- `reveal-in-folder/` : File Explorer opener
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
- NVIDIA NIM API key is stored in project `.env` — protect it like any credential

## Troubleshooting

### `ml` command not recognized

- reopen terminal after install
- verify PATH contains `C:\ML CLI\Tools` and/or `%USERPROFILE%\bin`
- re-run `install-ml.bat`

### DB connection failures

- run `ml test userdb`
- verify `.env` values (`DB_*`, `USERDB_*`)
- ensure MySQL is running and reachable

### Backup errors (`mysqldump not found`)

- run `ml create --config`
- set valid full path to `mysqldump.exe`

### Remote fetch/update errors

- check internet/proxy/firewall for GitHub raw/API endpoints
- retry command

### ngrok not available for online sharing

- install ngrok or use `ml serve` for local-only access

### AI menu generation fails

- check NVIDIA NIM API key in `.env` (`NIM_API_KEY`)
- fallback: icons still work via font-library defaults; metadata generation is skipped gracefully

## Notes

- This toolchain is primarily Windows-oriented.
- Many helper flows fetch the latest scripts from GitHub at runtime.
- For stable offline behavior, keep local script copies and avoid remote dependency paths where needed.
- Free Claude Code requires Python and Node.js for the uvicorn API server component.