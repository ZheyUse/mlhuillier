# ML CLI - Quick Start Guide

This guide is for users who just cloned this repository and want to start using the `ml` command on Windows.

## 1) Prerequisites

- Windows
- PHP installed (XAMPP is supported)
- Git (to clone the repo)
- Command Prompt or PowerShell

> If PHP is not in PATH, this project still works with XAMPP because `ml.bat` checks `C:\xampp\php\php.exe` automatically.

## 2) Clone the repository

```bash
git clone <your-repo-url>
cd mlgen
```

## 3) Install the global `ml` command (recommended)

Run:

```bat
install-ml.bat
```

What this installer does:
- Creates `C:\tools\ml`
- Copies the CLI files there
- Adds `C:\tools\ml` to your **User PATH**

After installation, open a **new terminal**.

## 4) Create your first project

Move to the folder where you want the project to be created, then run:

```bash
ml create banking-system
```

Example output:

```text
Creating project: banking-system
Creating src ... OK
Creating public ... OK
Creating .env ... OK
Project created successfully
```

## 5) Open the generated project

```bash
cd banking-system
```

Project structure includes:

- `src/`
- `public/`
- `.env`
- starter PHP/CSS/component files

## 6) Run with XAMPP/Apache

If your repo is under `C:\xampp\htdocs`, open in browser:

```text
http://localhost/banking-system/public/
```

## 7) Optional: run generator directly (without global install)

From this repo folder:

```bash
php generate-file-structure.php create my-project
```

Legacy mode (scaffold in current folder):

```bash
php generate-file-structure.php
```

## 8) Uninstall global command

If you want to remove the global setup:

```bat
uninstall-ml.bat
```

This removes:
- `C:\tools\ml`
- PATH entry for `C:\tools\ml`

## 9) Troubleshooting

### `'ml' is not recognized`

- Close and reopen terminal after running installer.
- Run `echo %PATH%` and confirm `C:\tools\ml` exists.
- Re-run `install-ml.bat`.

### Cannot create `C:\tools\ml`

- Run terminal as Administrator.
- Re-run `install-ml.bat`.

### Project folder already exists

- Choose another project name, or delete existing folder first.

## 10) Next planned commands

The CLI is prepared for future commands:
- `ml make:page`
- `ml make:component`
- `ml serve`

These are reserved for future releases.
