# AI Commands Study - Free Claude Code Architecture

**Date:** 2026-07-20  
**Purpose:** Document current AI command architecture, identify outdated references, and plan updates

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Current ML CLI AI Commands](#current-ml-cli-ai-commands)
3. [ml install ai - Installation Command](#ml-install-ai---installation-command)
4. [Free Claude Code Installation](#free-claude-code-installation)
4. [Entry Points & Commands](#entry-points--commands)
5. [What Needs Updates](#what-needs-updates)
6. [Comparison: Current vs Expected](#comparison-current-vs-expected)

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                        ML CLI (ml.bat)                       │
│                    C:\xampp\htdocs\mlhuillier\               │
└──────────────────────────┬──────────────────────────────────┘
                           │ ml --ai [subcommand]
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                 ai-commands.php                              │
│  - Starts/stops processes                                    │
│  - Manages .env configuration                                │
│  - Handles cross-platform launching                          │
└──────────────────────────┬──────────────────────────────────┘
                           │ uv run / npm
                           ▼
┌─────────────────────────────────────────────────────────────┐
│            Free Claude Code                                  │
│   C:\free-claude-code\free-claude-code\                       │
│                                                             │
│   ┌──────────────────────────────────────────────────────┐ │
│   │              fcc-server (FastAPI/uvicorn)               │ │
│   │         Port 8082 - Admin UI + Proxy                   │ │
│   └──────────────────────────────────────────────────────┘ │
│                                                             │
│   ┌────────────┐ ┌────────────┐ ┌────────────┐             │
│   │ fcc-claude │ │ fcc-codex   │ │ fcc-pi     │             │
│   └────────────┘ └────────────┘ └────────────┘             │
└─────────────────────────────────────────────────────────────┘
```

---

## Free Claude Code Version Details

| Info | Value |
|------|-------|
| **Version** | `4.11.0` |
| **Python** | `>=3.14.0` |
| **Location** | `C:\free-claude-code\free-claude-code` |
| **Config** | `C:\free-claude-code\free-claude-code\.env` |
| **Server Port** | `8082` (default) |
| **Admin UI** | `http://127.0.0.1:8082/admin` |

---

## Current ML CLI AI Commands

### Entry Point: `ml.bat`

**File:** `C:\xampp\htdocs\mlhuillier\ml.bat` (lines 26-27, 130-137, 264-287)

```batch
if /I "%~1"=="--ai" if /I "%~2"=="update" goto :cmd_ai_update
if /I "%~1"=="--ai" goto :cmd_ai
```

### Command Dispatch (ml.bat)

| CLI Command | Action | Location |
|-------------|--------|----------|
| `ml install ai` | Downloads and runs `ai-installer.php` | Line 44 |
| `ml --ai` | Starts fcc-server and fcc-claude (both visible) | Line 27 → `:cmd_ai` |
| `ml --ai update` | Pulls latest from free-claude-code git | Line 26 → `:cmd_ai_update` |
| `ml --ai help` | Shows integrated AI help | Via `:help_ai` (lines 264-286) |

### Handler Scripts

| Script | Purpose |
|--------|---------|
| `ai-commands.php` | Core AI command handler (start/stop/manage) |
| `ai-installer.php` | Installation automation |

**Source:** `C:\xampp\htdocs\mlhuillier\ai-commands.php`

---

## ml install ai - Installation Command

### Command Summary

| Item | Value |
|------|-------|
| **Command** | `ml install ai` |
| **Purpose** | Download and install Free Claude Code stack |
| **Installer Script** | `ai-installer.php` |
| **Installation Location** | `C:\free-claude-code\free-claude-code` (Windows) / `~/.free-claude-code/free-claude-code` (Unix) |
| **Prerequisites** | Git, Node.js/npm, internet connection |

### What It Does

The `ml install ai` command performs a complete installation of the Free Claude Code AI stack:

```
┌─────────────────────────────────────────────────────────────┐
│                   ml install ai                              │
│                   (from ml.bat line ~44)                     │
└──────────────────────────┬──────────────────────────────────┘
                           │ Downloads ai-installer.php
                           │ Runs it with PHP
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                    ai-installer.php                         │
│  C:\xampp\htdocs\mlhuillier\ai-installer.php                 │
└──────────────────────────┬──────────────────────────────────┘
                           │
         ┌─────────────────┴─────────────────┐
         ▼                                   ▼
┌─────────────────┐              ┌─────────────────┐
│  Clone Repo      │              │  Install uv      │
│  (git clone)     │              │  + Python 3.14   │
└────────┬────────┘              └────────┬────────┘
         │                                │
         ▼                                ▼
┌─────────────────┐              ┌─────────────────┐
│  Configure .env │              │  Install npm    │
│  (models, keys)  │              │  (claude-code)   │
└────────┬────────┘              └────────┬────────┘
         │                                │
         └────────────┬───────────────────┘
                      ▼
            ┌─────────────────┐
            │  Create .venv    │
            │  (uv venv)       │
            └────────┬────────┘
                     ▼
            ┌─────────────────┐
            │  Run uv sync    │
            │  (install deps) │
            └────────┬────────┘
                     ▼
            ┌─────────────────┐
            │  Install FCC    │
            │  (uv pip install)│
            └─────────────────┘
```

### Installation Steps (from ai-installer.php)

| Step | Action | Details |
|------|--------|---------|
| 1 | Detect platform | Windows/macOS/Linux |
| 2 | Check prerequisites | Git, Node.js, npm, PHP |
| 3 | Create directory | `C:\free-claude-code` or `~/.free-claude-code` |
| 4 | Clone repo | `git clone https://github.com/Alishahryar1/free-claude-code.git` |
| 5 | Install uv | Download and install uv package manager |
| 6 | Setup Python | Install Python 3.14 via uv |
| 7 | Create .env | Copy `.env.example` to `.env` |
| 8 | Configure models | Set default MODEL config |
| 9 | Prompt for API key | Ask for NVIDIA_NIM_API_KEY |
| 10 | Install npm | `npm install -g @anthropic-ai/claude-code` |
| 11 | Create venv | `uv venv` |
| 12 | Sync deps | `uv sync` |
| 13 | Install FCC | `uv pip install -e .` |

### Key Files Created/Modified

| File | Purpose |
|------|---------|
| `C:\free-claude-code\free-claude-code\.env` | Configuration with API keys |
| `C:\free-claude-code\free-claude-code\.venv\` | Python virtual environment |
| `C:\free-claude-code\free-claude-code\logs\` | Log directory |
| `%APPDATA%\Code\User\settings.json` | VS Code settings (patched) |

### After Installation

Once installed, you can use:

```bash
ml --ai              # Start server + Claude Code (visible)
ml --ai claude       # Start Claude Code in current directory
ml --ai bg           # Start both in background
ml --ai codex        # Start Codex in current directory
ml --ai admin        # Open admin panel
ml --ai stop         # Stop all processes
ml --ai update       # Update Free Claude Code
```

### Troubleshooting

| Issue | Solution |
|-------|----------|
| "Git not found" | Install Git from https://git-scm.com |
| "Node.js not found" | Install from https://nodejs.org |
| "NVIDIA API key invalid" | Get key from https://build.nvidia.com and run `ml --ai key` |
| Installation fails | Check internet connection, try running as administrator |

### Source Files

| File | Path | Line |
|------|------|------|
| ml.bat handler | `ml.bat` | ~44 |
| ai-installer.php | `C:\xampp\htdocs\mlhuillier\ai-installer.php` | Full file |

---

## ai-commands.php - Detailed Breakdown

### Platform Detection

```php
function isWindows(): bool   // stripos(PHP_OS, 'WIN') === 0
function isMac(): bool       // stripos(PHP_OS, 'DAR') === 0
function isUnix(): bool      // !isWindows()
```

### Directory Locations

| OS | Base Directory | Install Directory |
|----|----------------|-------------------|
| Windows | `C:\free-claude-code` | `C:\free-claude-code\free-claude-code` |
| macOS/Linux | `~/.free-claude-code` | `~/.free-claude-code/free-claude-code` |

### State File

- **Location:** `C:\free-claude-code\ml-ai-pids.json`
- **Contains:** `started_at`, `scripts[]`, `pids[]`

---

## Current Subcommands

### Listed in ml.bat Help (lines 128-136):

```
   --ai               Start uvicorn + Claude Code (visible)
   --ai claude        Start Claude Code in current directory (bg uvicorn)
   --ai bg            Start both in background
   --ai admin       Open Free Claude Code admin panel in browser
   --ai update-info Check what updates are available before pulling
   --ai stop          Stop all processes
   --ai restart       Stop and start both in background
   --ai cm            Change model (Opus/Sonnet/Haiku/Default)
   --ai key           Update NVIDIA_NIM_API_KEY
```

### Implementation in ai-commands.php:

| Subcommand | Function | Description |
|------------|----------|-------------|
| `(empty)` | `startAiWindows(true, true)` | Both visible |
| `claude` | `startAiWindows(false, true)` | Uvicorn bg, Claude visible |
| `bg` | `startAiWindows(false, false)` | Both background |
| `stop` | `stopAiWindows()` | Kill processes |
| `restart` | `stopAiWindows()` + `startAiWindows(false, false)` | Stop + start bg |
| `admin` | `openAdminBrowser()` | Open http://127.0.0.1:8082/admin |
| `cm` | `changeModel()` | Interactive model selection |
| `key` | `changeApiKey()` | Update NVIDIA_NIM_API_KEY |
| `update-info` | `checkGitUpdates()` | Show commits behind/ahead |
| `update` | Handled in ml.bat `:cmd_ai_update` | Git pull |

---

## Free Claude Code Installation

### Location: `C:\free-claude-code\free-claude-code`

### Directory Structure:

```
C:\free-claude-code\free-claude-code\
├── .env                      # Configuration file
├── .env.example               # Template
├── pyproject.toml             # Python project config (v4.11.0)
├── uv.lock                   # Dependency lock
├── README.md                  # Documentation
├── ARCHITECTURE.md            # Detailed architecture
├── AGENTS.md                  # Agent documentation
├── CLAUDE.md                  # Claude AI guidance
├── src/free_claude_code/      # Main source
│   ├── api/                   # FastAPI app, routes, handlers
│   ├── cli/                   # CLI tools
│   │   ├── entrypoints.py     # fcc-server, fcc-claude, etc.
│   │   └── launchers/         # Claude, Codex, Pi launchers
│   ├── core/                  # Core logic
│   ├── providers/             # AI provider implementations
│   └── messaging/             # Discord/Telegram integration
├── smoke/                     # Test scripts
├── tests/                     # Unit tests
├── logs/                      # Log files
└── .venv/                     # Python virtual environment
```

---

## Entry Points & Commands

### Installed Entry Points (from pyproject.toml):

```toml
[project.scripts]
fcc-server = "free_claude_code.cli.entrypoints:serve"
free-claude-code = "free_claude_code.cli.entrypoints:serve"
fcc-init = "free_claude_code.cli.entrypoints:init"
fcc-claude = "free_claude_code.cli.launchers.claude:launch"
fcc-codex = "free_claude_code.cli.launchers.codex:launch"
fcc-pi = "free_claude_code.cli.launchers.pi:launch"
```

### Available Commands (from README.md):

| Command | Description |
|---------|-------------|
| `fcc-server` | Start the fcc-server proxy (uses uvicorn under the hood) |
| `fcc-server --version` | Print version without starting |
| `fcc-claude` | Launch Claude Code via local proxy |
| `fcc-codex` | Launch Codex CLI via local proxy |
| `fcc-pi` | Launch Pi via local proxy |

### Server Options:

```bash
# Default start
fcc-server

# With environment variables
HOST=0.0.0.0 PORT=8082 fcc-server

# With config file
FCC_CONFIG=/path/to/config.toml fcc-server
```

---

## What Needs Updates

### 1. HELP TEXT IN ml.bat (Lines 129-137)

**Current (Outdated):**
```batch
echo   --ai               Start fcc-server and fcc-claude (both visible)
echo   --ai claude        Start fcc-claude only (server must be running)
echo   --ai bg            Start fcc-server and fcc-claude in background
echo   --ai codex         Start fcc-codex (server must be running)
echo   --ai bg            Start both in background
echo   --ai admin       Open Free Claude Code admin panel in browser
echo   --ai update-info Check what updates are available before pulling
echo   --ai stop          Stop all processes
echo   --ai restart       Stop and start both in background
echo   --ai cm            Change model (Opus/Sonnet/Haiku/Default)
echo   --ai key           Update NVIDIA_NIM_API_KEY
```

**Issues:**
- Mentions "Opus/Sonnet/Haiku/Default" models - these are outdated
- Doesn't mention new launchers (fcc-codex, fcc-pi) that are now available
- Doesn't document the new `fcc-server` command structure

### 2. HELP TEXT MODEL INFO (Lines 137-142 of :help_ai)

**Current (Outdated):**
```batch
echo Models auto-configured on install:
echo   MODEL_OPUS     - nvidia_nim/deepseek-ai/deepseek-v4-pro
echo   MODEL_SONNET   - nvidia_nim/minimaxai/minimax-m2.7
echo   MODEL_HAIKU    - nvidia_nim/z-ai/glm4.7
echo   MODEL          - nvidia_nim/z-ai/glm-5.1
```

**Issues:**
- These specific model slugs are not the current defaults
- The Admin UI now allows selection from 25+ providers and many models
- Models are configurable via the Admin UI, not just fixed at install

### 3. MISSING fcc-codex AND fcc-pi SUPPORT

The ml.bat file doesn't include options to launch Codex or Pi, even though free-claude-code v4.11.0 supports them.

### 4. ai-commands.php Limitations

**Current capabilities:**
- Start/stop uvicorn and Claude Code
- Change .env values for models and API keys
- Open Admin UI

**Missing capabilities:**
- Can't start fcc-server directly (uses `uv run uvicorn`)
- No explicit support for starting fcc-codex or fcc-pi
- No support for `fcc-server --version`

### 5. ADMIN UI URL

**Current:** Hardcoded to `http://127.0.0.1:8082/admin`  
**Note:** The port can be changed via environment variable `PORT`

---

## Comparison: Current vs Expected

### Current (ml.bat/ai-commands.php)

| Feature | Status |
|---------|--------|
| Start uvicorn + Claude Code | ✅ Works |
| Background mode | ✅ Works |
| Stop/restart | ✅ Works |
| Change API key | ✅ Works |
| Change models (cm) | ⚠️ Limited to .env |
| Admin UI | ✅ Works |
| Update checking | ✅ Works |
| fcc-codex support | ❌ Missing |
| fcc-pi support | ❌ Missing |
| fcc-server --version | ❌ Missing |

### What Free Claude Code v4.11.0 Supports

| Feature | Status |
|---------|--------|
| Multiple providers: NVIDIA NIM, OpenRouter, Gemini, Vertex, DeepSeek, Mistral, etc. | ⚠️ Not exposed |
| fcc-codex launcher | ⚠️ Not integrated |
| fcc-pi launcher | ⚠️ Not integrated |
| Admin UI model picker | ✅ Works |
| Native model picker in agents | ✅ Works |

---

## Providers Available (from free-claude-code README)

| Provider | Key Variable | Example Model |
|----------|-------------|----------------|
| NVIDIA NIM | `NVIDIA_NIM_API_KEY` | `nvidia_nim/nvidia/nemotron-3-super-120b-a12b` |
| OpenRouter | `OPENROUTER_API_KEY` | `open_router/openrouter/free` |
| Google AI | `GEMINI_API_KEY` | `gemini/models/gemini-3.1-flash-lite` |
| Vertex AI | `VERTEX_PROJECT_ID` + ADC | `vertex/google/gemini-3.5-flash` |
| DeepSeek | `DEEPSEEK_API_KEY` | `deepseek/deepseek-chat` |
| Mistral | `MISTRAL_API_KEY` | `mistral/devstral-small-latest` |
| OpenCode | `OPENCODE_API_KEY` | `opencode/gpt-5.3-codex` |
| Vercel AI | `AI_GATEWAY_API_KEY` | `vercel/openai/gpt-5.5` |
| Amazon Bedrock | `AWS_BEARER_TOKEN_BEDROCK` | `bedrock/openai.gpt-oss-120b` |

---

## Changes Applied (2026-07-20)

### ai-commands.php Changes

1. **Added constants** for FCC installation directory and project flag:
```php
const FCC_INSTALL_DIR = 'C:\\free-claude-code\\free-claude-code';
const FCC_PROJECT_FLAG = '--project ' . FCC_INSTALL_DIR;
```

2. **Replaced uvicorn commands with fcc-server/fcc-claude:**
   - Old: `uv run uvicorn server:app --host 0.0.0.0 --port 8082`
   - New: `uv run --project C:\free-claude-code\free-claude-code fcc-server`

3. **Replaced claude commands with fcc-claude:**
   - Old: `claude` (with env vars)
   - New: `uv run --project C:\free-claude-code\free-claude-code fcc-claude`

4. **Added new `codex` subcommand:**
   - New function `startCodexWindows()` and `startCodexUnix()`
   - Uses: `uv run --project C:\free-claude-code\free-claude-code fcc-codex`

5. **Updated stop logic** to kill `fcc-server`, `fcc-claude`, `fcc-codex` processes

### ml.bat Changes

**Main help section updated:**
```batch
echo   --ai               Start fcc-server and fcc-claude (both visible)
echo   --ai claude        Start fcc-claude only (server must be running)
echo   --ai bg            Start fcc-server and fcc-claude in background
echo   --ai codex         Start fcc-codex (server must be running)
echo   --ai admin         Open Free Claude Code admin panel in browser
echo   --ai update-info   Check what updates are available before pulling
echo   --ai stop          Stop all fcc-server, fcc-claude, fcc-codex processes
echo   --ai restart       Stop and start fcc-server/fcc-claude in background
echo   --ai cm            Change model selection in .env
echo   --ai key           Update NVIDIA_NIM_API_KEY in .env
```

**:help_ai section updated with:**
- New command descriptions
- Added `ml --ai codex` documentation
- Removed hardcoded model list
- Added provider info message

### ai-installer-guide.md Updates
- Updated After Installation section with new commands
- Updated Quick Reference table

---

## What Was Changed - Summary

| Command | Before | After |
|---------|--------|-------|
| `ml --ai` | `fcc-server + fcc-claude` visible | `fcc-server + fcc-claude` visible |
| `ml --ai bg` | `fcc-server + fcc-claude` bg | `fcc-server + fcc-claude` bg |
| `ml --ai claude` | `fcc-claude` only | `fcc-claude` only |
| `ml --ai codex` | ❌ Did not exist | `fcc-codex` only |
| `ml --ai codex` | ❌ Did not exist | `fcc-codex` only |

---

## Recommended Updates

- [x] Update ml.bat help text ✅
- [x] Update ai-commands.php with fcc-* commands ✅
- [x] Add codex subcommand ✅
- [x] Update ai-installer-guide.md ✅
- [ ] Test all commands
- [ ] Update ai-installer.php (optional - for future provider support)

---

## Scripts to Check

| File | Path | Purpose |
|------|------|---------|
| ml.bat | `C:\xampp\htdocs\mlhuillier\ml.bat` | Main entry point |
| ai-commands.php | `C:\xampp\htdocs\mlhuillier\ai-commands.php` | AI command handler |
| ai-installer.php | `C:\xampp\htdocs\mlhuillier\ai-installer.php` | Installation script |
| entrypoints.py | `C:\free-claude-code\free-claude-code\src\free_claude_code\cli\entrypoints.py` | FCC start commands |
| launchers/ | `C:\free-claude-code\free-claude-code\src\free_claude_code\cli\launchers/` | Agent launchers |

---

## Verification Checklist

- [x] Test `ml --ai` starts fcc-server and fcc-claude
- [ ] Test `ml --ai bg` starts in background
- [ ] Test `ml --ai stop` kills processes
- [ ] Test `ml --ai admin` opens browser
- [ ] Test Admin UI at http://127.0.0.1:8082/admin
- [ ] Test model selection in Admin UI
- [ ] Check if `fcc-claude`, `fcc-codex`, `fcc-pi` work
- [ ] Verify `ml --ai update-info` git command

---

## References

- Free Claude Code Repo: https://github.com/Alishahryar1/free-claude-code
- Free Claude Code README: `C:\free-claude-code\free-claude-code\README.md`
- Architecture Docs: `C:\free-claude-code\free-claude-code\ARCHITECTURE.md`
- NVIDIA NIM: https://build.nvidia.com/