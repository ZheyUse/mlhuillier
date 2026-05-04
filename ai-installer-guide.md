# AI Installer Guide (Free Claude Code)

A beginner-friendly guide to installing and using Free Claude Code with your ML CLI setup.

---

## What This Installs

Running `ml install ai` installs **Free Claude Code** — a local AI coding assistant that runs through NVIDIA NIM models. It sets up:

- A local API server (uvicorn) that routes AI requests to NVIDIA NIM
- Claude Code agent for interactive AI assistance in your terminal
- Proper VS Code settings for AI integration

---

## Prerequisites

Before running `ml install ai`, make sure you have:

| Requirement | Windows | macOS | Linux | Why It's Needed |
|-------------|---------|-------|-------|----------------|
| **Git** | [Download](https://git-scm.com/download/win) | `brew install git` | `sudo apt install git` | Clones the Free Claude Code repository |
| **Node.js / npm** | [Download Node.js](https://nodejs.org) | `brew install node` | `sudo apt install nodejs npm` | Runs Claude Code agent (npm package) |
| **PHP CLI** | XAMPP includes it | `brew install php` | `sudo apt install php` | Runs the ML CLI installer scripts |
| **curl** | Already included in Windows 10/11 | Already included | Usually pre-installed | Used by `ml` command and uv installer |
| **Python 3.14** | Installed automatically | Installed automatically | Installed automatically | Required by the uvicorn API server |

### How to Check If You Have curl

Open your terminal and run:

**Windows (PowerShell or Command Prompt):**
```powershell
curl --version
```

**macOS / Linux:**
```bash
curl --version
```

You should see output like `curl 7.x.x`. If you get an error, install curl:

- **Windows**: Windows 10/11 already includes curl. If not, install [Git for Windows](https://git-scm.com/download/win) which includes curl.
- **macOS**: `brew install curl`
- **Linux**: `sudo apt install curl` or `sudo yum install curl`

---

## Installation Steps

### Step 1: Open Your Terminal

**Windows:**
- Press `Win + R`, type `cmd` or `powershell`, press Enter
- Or right-click Start menu → "Terminal" or "PowerShell"

**macOS:**
- Press `Cmd + Space`, type `terminal`, press Enter

**Linux:**
- Press `Ctrl + Alt + T` or open your terminal app

### Step 2: Check Prerequisites

Run these commands to verify you have everything:

```powershell
# Check git
git --version

# Check npm
npm --version

# Check PHP
php --version

# Check curl
curl --version
```

If any command fails with "not recognized", install the missing tool before proceeding.

### Step 3: Run the Installer

```powershell
ml install ai
```

The installer will:

1. **Check prerequisites** — verifies git, npm, and curl are available
2. **Install uv and Python** — sets up the Python environment manager
3. **Clone the repository** — downloads Free Claude Code to your computer
   - Windows: `C:\free-claude-code\free-claude-code`
   - macOS/Linux: `~/.free-claude-code/free-claude-code`
4. **Create .env file** — copies the example configuration
5. **Ask for NVIDIA NIM API Key** — you'll need this to use AI features
6. **Configure models** — sets up the AI models to use
7. **Install Claude Code via npm** — globally installs the Claude Code agent
8. **Configure VS Code settings** — enables AI integration in your editor

### Step 4: Get Your NVIDIA NIM API Key

When prompted, you'll need to enter your NVIDIA NIM API key. Here's how to get one:

1. Go to [https://build.nvidia.com/](https://build.nvidia.com/)
2. Create a free account or sign in
3. Generate an API key
4. Copy and paste it when the installer asks

> **Note:** If you don't have a key yet, you can press Enter to skip. You can add the key later with `ml --ai key`.

---

## After Installation

### Starting the AI Features

There are several ways to start Free Claude Code:

**Option 1: Show both windows (recommended for beginners)**
```powershell
ml --ai
```

**Option 2: Background uvicorn, visible Claude Code**
```powershell
ml --ai claude
```

**Option 3: Both in background**
```powershell
ml --ai bg
```

### Stopping the AI Features

```powershell
ml --ai stop
```

### Restarting

```powershell
ml --ai restart
```

### Updating Your NVIDIA API Key

```powershell
ml --ai key
```

### Changing AI Models

```powershell
ml --ai cm
```

You can choose from different AI models:
- **Opus** — Most capable, for complex tasks
- **Sonnet** — Balanced, good for most tasks
- **Haiku** — Fastest, for simple tasks

---

## Common Issues and Solutions

### "git is not recognized"

**Problem:** Git is not installed or not on your PATH.

**Solution:**
1. Download Git from [https://git-scm.com/download/win](https://git-scm.com/download/win)
2. During installation, choose "Use Git from Windows Command Prompt"
3. Restart your terminal
4. Try `ml install ai` again

---

### "npm is not recognized"

**Problem:** Node.js/npm is not installed.

**Solution:**
1. Download Node.js from [https://nodejs.org/](https://nodejs.org/)
2. Install it (npm comes bundled)
3. Restart your terminal
4. Verify with `npm --version`

---

### "curl is not recognized" (Windows)

**Problem:** curl is not in your PATH.

**Solution:**
- Windows 10/11 should have curl. Try opening PowerShell instead of Command Prompt
- If still not working, install [Git for Windows](https://git-scm.com/download/win) which includes curl

---

### "CLI: Failed cloning free-claude-code"

**Problem:** Git could not download the repository.

**Solution:**
1. Check your internet connection
2. Make sure Git is properly installed: `git --version`
3. Try again: `ml install ai`

---

### "CLI: Failed installing uv"

**Problem:** The Python package manager (uv) failed to install.

**Solution (Windows):**
```powershell
irm https://astral.sh/uv/install.ps1 | iex
```

**Solution (macOS/Linux):**
```bash
curl -LsSf https://astral.sh/uv/install.sh | sh
```

---

### "Invalid API Key" When Entering NVIDIA Key

**Problem:** The API key you entered is not valid.

**Solution:**
1. Go to [https://build.nvidia.com/](https://build.nvidia.com/)
2. Make sure you've created an API key (click "Get API Key" button)
3. Copy the exact key and paste it when prompted
4. The key should look like: `nvapi-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`

---

### Claude Code Installation Failed

**Problem:** npm could not install the Claude Code package.

**Solution:**
```powershell
# Clear npm cache
npm cache clean --force

# Update npm
npm install -g npm@latest

# Try installing again
npm install -g @anthropic-ai/claude-code
```

---

### VS Code Settings Not Applied

**Problem:** Settings.json could not be written.

**Solution:**
The installer will skip this if it can't find VS Code's settings location. You can configure manually:

1. Open VS Code
2. Go to File → Preferences → Settings
3. Click the file icon (Open Settings JSON)
4. Add these settings:

```json
{
    "liveServer.settings.CustomBrowser": "chrome",
    "workbench.editor.empty.hint": "hidden",
    "github.copilot.nextEditSuggestions.enabled": true,
    "files.autoSave": "afterDelay",
    "git.autofetch": true,
    "chat.mcp.gallery.enabled": true,
    "python.terminal.useEnvFile": true,
    "terminal.integrated.initialHint": false,
    "claudeCode.environmentVariables": [
        { "name": "ANTHROPIC_BASE_URL", "value": "http://localhost:8082" },
        { "name": "ANTHROPIC_AUTH_TOKEN", "value": "freecc" }
    ],
    "claudeCode.disableLoginPrompt": true
}
```

---

## Troubleshooting Checklist

If something goes wrong, run through this checklist:

- [ ] Is your internet connection working?
- [ ] Did you restart your terminal after installing dependencies?
- [ ] Is git available? Run `git --version`
- [ ] Is npm available? Run `npm --version`
- [ ] Is curl available? Run `curl --version`
- [ ] Is PHP available? Run `php --version`

---

## Files and Locations

| What | Windows Path | macOS/Linux Path |
|------|--------------|------------------|
| Installation directory | `C:\free-claude-code\free-claude-code` | `~/.free-claude-code/free-claude-code` |
| Configuration file | `C:\free-claude-code\free-claude-code\.env` | `~/.free-claude-code/free-claude-code/.env` |
| VS Code settings | `%APPDATA%\Code\User\settings.json` | `~/.config/Code/User/settings.json` |

---

## Quick Reference

| Command | What It Does |
|---------|--------------|
| `ml install ai` | Install Free Claude Code |
| `ml --ai` | Start uvicorn + Claude Code |
| `ml --ai stop` | Stop all AI processes |
| `ml --ai key` | Update NVIDIA API key |
| `ml --ai cm` | Change AI model |
| `ml --ai restart` | Restart all AI processes |

---

## Need More Help?

- **ML CLI Help:** `ml --h`
- **NVIDIA NIM Setup:** [https://build.nvidia.com/](https://build.nvidia.com/)
- **Free Claude Code Repo:** [https://github.com/Alishahryar1/free-claude-code](https://github.com/Alishahryar1/free-claude-code)
- **Claude Code Docs:** [https://docs.anthropic.com/en/docs/claude-code](https://docs.anthropic.com/en/docs/claude-code)