# ML CLI (M Lhuillier)

ML CLI is a Windows-first PHP command-line toolkit for scaffolding projects, managing a shared user database, and automating common local development workflows.

It is designed for teams building multiple PHP apps (typically under XAMPP) that need:

- fast project bootstrap
- consistent folder/file structure
- centralized user account storage
- quick RBAC/PBAC table provisioning
- repeatable backup and update workflows

## What This System Does

ML CLI provides one command surface (`ml`) for:

- Creating starter PHP project structures
- Navigating quickly between projects under `C:\xampp\htdocs`
- Opening projects in browser (`http://localhost/<project>`)
- Importing and testing a shared `userdb` schema
- Creating user accounts from terminal prompts
- Creating project-specific RBAC and PBAC tables in `userdb`
- Configuring and running MySQL schema backups via `mysqldump`
- Checking and applying CLI updates from GitHub

## Core Purpose

This repository solves repetitive setup work for local PHP development on Windows.

Instead of manually creating files, wiring auth tables, and writing one-off DB scripts for every project, you can run a small set of `ml` commands and start building features immediately.

## Prerequisites

- Windows (PowerShell / CMD)
- PHP CLI installed (XAMPP `C:\xampp\php\php.exe` is auto-detected)
- MySQL/MariaDB access
- Internet access for remote helper downloads/updates

Optional:

- Node.js (only for rebuilding docs JSON)
- VS Code CLI (`code`) for `ml nav` open-in-editor flow

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

6. Open app in browser

```bat
ml serve
```

## Command Reference

### General

- `ml --h` : show help
- `ml --v` : show installed version
- `ml --c` : check remote version
- `ml update` : update installed CLI files
- `ml --d` : download installer helper
- `ml doc` / `ml docs` : open hosted docs site

### Project / Workflow

- `ml create <project_name>` : scaffold a new project
- `ml nav` : interactive navigation under `C:\xampp\htdocs`
- `ml nav --<project_name>` : jump directly to project
- `ml nav --new` : jump to `C:\xampp\htdocs`
- `ml serve [project_name]` : open project URL in browser

### Database / UserDB

- `ml test <database>` : test DB connection
- `ml test userdb` : userdb-specific connectivity check
- `ml add userdb` : import userdb schema SQL
- `ml create --config` : create backup config JSON
- `ml --b [schema|all]` : backup one/all schemas

### Account / Access Control

- `ml create --a` : interactive account creation
- `ml create --rbac <project_name>` : create `<project_name>_rbac`
- `ml create --pbac <project_name>` : create `<project_name>_pbac`

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

## Update and Versioning

- Local version source: `VERSION`
- CLI constant in `ml.bat`: `ML_VERSION`
- Check remote: `ml --c`
- Apply update: `ml update`
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

## Repository Structure

- `ml.bat`, `ml.cmd`, `ml.ps1` : command entry points/wrappers
- `generate-file-structure.php` : main scaffolder
- `generate-file-remote.php` : remote loader stub
- `ml-nav.php`, `ml-serve.php`, `ml-update.php` : workflow helpers
- `account-insert.php`, `userdb-import.php`, `userdb-con-test.php` : DB/account utilities
- `rbac/`, `pbac/` : access-control table generators
- `backup-cli/`, `db-config/` : backup tooling
- `migration/` : SQL sources
- `documentation/` : docs website assets
- `scripts/` : docs/template extraction tooling

## Security Notes

- `.env` and `mlcli-config.json` can contain plaintext DB credentials
- keep these files out of version control
- use least-privilege DB users where possible
- rotate and protect SQL backup files
- review generated auth/permission logic before production use

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

## Notes

- This toolchain is primarily Windows-oriented.
- Many helper flows fetch the latest scripts from GitHub at runtime.
- For stable offline behavior, keep local script copies and avoid remote dependency paths where needed.
