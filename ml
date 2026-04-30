#!/usr/bin/env bash
#
# ml - ML CLI (M Lhuillier) Unix/macOS entry point
# Equivalent to ml.bat on Windows
#
# Usage: ml <command> [args...]

set -u

ML_VERSION="1.1.14"

# ── Detect PHP ─────────────────────────────────────────────────────────────────
find_php() {
    if command -v php &>/dev/null; then
        echo "php"
        return
    fi
    if [[ -x "/usr/local/bin/php" ]]; then
        echo "/usr/local/bin/php"
        return
    fi
    if [[ -x "/opt/homebrew/bin/php" ]]; then
        echo "/opt/homebrew/bin/php"
        return
    fi
    return 1
}

PHP_EXE="$(find_php)"
if [[ -z "$PHP_EXE" ]]; then
    echo "ml: PHP not found. Please install PHP and ensure it is on your PATH." >&2
    exit 1
fi

# ── Resolve repo root (where ml, ml.bat, etc. live) ──────────────────────────────
# When installed: SCRIPT_DIR = C:\ML CLI\Tools (or /usr/local/bin etc.)
# When running from repo: dir containing this script and ml.bat
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Detect if running from the repo checkout (has ml.bat) vs installed tool
if [[ -f "$SCRIPT_DIR/ml.bat" ]]; then
    REPO_ROOT="$SCRIPT_DIR"
else
    # Installed — resolve the repo root via GitHub URL stored in config file
    REPO_ROOT=""
    # Try to find ml.bat up the tree or fall back to current directory
    REPO_ROOT="$(pwd)"
fi

# ── Temp file helpers ───────────────────────────────────────────────────────────
ml_tmp() {
    local name="$1"
    local ext="${2:-php}"
    local stamp="$$-$(date +%s)"
    echo "/tmp/ml-${name}-${stamp}.${ext}"
}

ml_fetch() {
    # Downloads a raw URL to a temp file. Sets ML_FETCH_RC on exit.
    # Usage: ml_fetch <url> <out_file>
    local url="$1"
    local out="$2"
    if command -v curl &>/dev/null; then
        curl -fsSL -o "$out" "$url" 2>/dev/null
        ML_FETCH_RC=$?
    elif command -v wget &>/dev/null; then
        wget -q -O "$out" "$url" 2>/dev/null
        ML_FETCH_RC=$?
    else
        echo "ml: neither curl nor wget found. Install one to use remote helpers." >&2
        ML_FETCH_RC=1
    fi
}

# ── Remote fetch + run pattern ──────────────────────────────────────────────────
remote_run() {
    local url="$1"; shift
    local tmp_file
    tmp_file="$(ml_tmp "remote")"
    ml_fetch "$url" "$tmp_file"
    if [[ $ML_FETCH_RC -ne 0 ]] || [[ ! -s "$tmp_file" ]]; then
        echo "ml: failed to fetch remote script: $url" >&2
        rm -f "$tmp_file"
        exit 1
    fi
    "$PHP_EXE" "$tmp_file" "$@"
    local rc=$?
    rm -f "$tmp_file"
    return $rc
}

# GitHub raw base URL
GH_RAW="https://raw.githubusercontent.com/ZheyUse/mlhuillier/main"

ml_htdocs() {
    if [[ -n "${ML_HTDOCS:-}" ]]; then
        echo "$ML_HTDOCS"
    elif [[ -d "$HOME/xampp/htdocs" ]]; then
        echo "$HOME/xampp/htdocs"
    elif [[ -d "/Applications/XAMPP/htdocs" ]]; then
        echo "/Applications/XAMPP/htdocs"
    elif [[ -d "/opt/lampp/htdocs" ]]; then
        echo "/opt/lampp/htdocs"
    else
        echo "/var/www/html"
    fi
}

# ── Command routing ────────────────────────────────────────────────────────────
dispatch() {
CMD="${1:-}"
[[ -n "$CMD" ]] && shift || true

case "$CMD" in
    # ── Flags ──────────────────────────────────────────────────────────────────
    --v|-v)
        echo "ML CLI version $ML_VERSION"
        ;;

    --h|-h|--help)
        cmd_show_help "$@"
        ;;

    --c)
        cmd_check_version
        ;;

    --d)
        cmd_download_installer
        ;;

    --b)
        cmd_backup "${1:-}"
        [[ $# -gt 0 ]] && shift
        ;;

    --ai)
        cmd_ai "$@"
        ;;

    # ── Commands ───────────────────────────────────────────────────────────────
    test)
        if [[ -z "${1:-}" ]]; then
            echo "Missing arguments for ml test"
            echo "Test list:"
            echo "  test <database>     Run DB connection test"
            echo "  test userdb         Run userdb connectivity and schema check"
            exit 2
        fi
        remote_run "${GH_RAW}/userdb-con-test.php" "$@"
        ;;

    add)
        cmd_add "$@"
        ;;

    install)
        if [[ "${1:-}" == "ai" ]]; then
            cmd_install_ai
        else
            echo "Unknown install target: ${1:-}"
            echo "Usage: ml install ai"
            exit 1
        fi
        ;;

    create)
        cmd_create "$@"
        ;;

    gen)
        cmd_gen "$@"
        ;;

    wb)
        cmd_wb "$@"
        ;;

    update)
        cmd_update
        ;;

    nav)
        cmd_nav "$@"
        ;;

    serve)
        cmd_serve "$@"
        ;;

    migrate)
        cmd_migrate "$@"
        ;;

    clone)
        if [[ "${1:-}" == "local" ]]; then
            cmd_clone_local "${2:-}"
        else
            echo "Usage: ml clone local [destination]"
            exit 1
        fi
        ;;

    rev|reveal)
        cmd_reveal "$@"
        ;;

    doc|docs)
        if command -v open &>/dev/null; then
            open "https://zheyuse.github.io/mlhuillier/documentation/"
        elif command -v xdg-open &>/dev/null; then
            xdg-open "https://zheyuse.github.io/mlhuillier/documentation/"
        else
            echo "Open your browser at: https://zheyuse.github.io/mlhuillier/documentation/"
        fi
        ;;

    "") # No command — run project generator
        echo "ML CLI - M LHUILLIER FILE GENERATOR"
        echo "https://github.com/ZheyUse"
        echo ""
        local_gen "$@"
        ;;

    *)
        # Treat as project name: ml create <name>
        local_gen "$CMD" "$@"
        ;;
esac
}
# ── Helpers ─────────────────────────────────────────────────────────────────────

cmd_show_help() {
    local arg1="${1:-}" arg2="${2:-}"
    if [[ -n "$arg1" ]]; then
        show_help_command "$arg1" "$arg2"
    else
        show_top_help
    fi
}

show_top_help() {
    echo "ML CLI - M LHUILLIER FILE GENERATOR"
    echo "https://github.com/ZheyUse"
    echo ""
    echo "Usage: ml <command> [options]"
    echo ""
    echo "Flags:"
    echo "  --h              Show this help"
    echo "  --v              Show version"
    echo "  --c              Check remote version"
    echo "  --d              Download installer helper"
    echo "  --b [schema]     Backup schemas"
    echo "  --ai [subcmd]    Manage Free Claude Code servers"
    echo ""
    echo "Project / Workflow:"
    echo "  create <name>    Scaffold new project"
    echo "  serve [name] [-o]    Open locally or via ngrok"
    echo "  serve --stop     Stop ngrok tunnel"
    echo "  nav [name]       Navigate to project under ~/xampp/htdocs"
    echo "  rev [name]       Reveal folder in File Explorer / Finder"
    echo "  clone local      Copy CLI files to ~/.ml-cli/ for testing"
    echo ""
    echo "Database / UserDB:"
    echo "  add userdb       Import userdb SQL"
    echo "  add menu         Add sidebar menu with AI"
    echo "  test <database>  Test DB connection"
    echo "  create --a       Create user account"
    echo "  create --rbac    Create RBAC table"
    echo "  create --pbac    Create PBAC table"
    echo "  create --config  Create DB backup config"
    echo "  migrate -db <DB> Migrate userdb tables to target"
    echo "  migrate global   Restore project to userdb"
    echo "  wb [options]     Export DB via MySQL Workbench"
    echo ""
    echo "AI:"
    echo "  install ai       Install Free Claude Code"
    echo "  --ai             Start uvicorn + Claude Code"
    echo "  --ai bg          Start both in background"
    echo "  --ai stop        Stop all processes"
    echo "  --ai cm          Change configured model"
    echo "  --ai key         Update NVIDIA_NIM_API_KEY"
    echo ""
    echo "Docs: ml doc"
    echo ""
}

show_help_command() {
    local cmd="${1:-}" sub="${2:-}"
    case "$cmd" in
        test)       show_help_test ;;
        create)     show_help_create ;;
        serve)      show_help_serve ;;
        add)        show_help_add ;;
        wb)         show_help_wb ;;
        --ai)       echo "Usage: ml --ai [subcommand]"
                    echo "  (no arg)  Start uvicorn + Claude Code"
                    echo "  claude    Start Claude Code with bg uvicorn"
                    echo "  bg        Start both in background"
                    echo "  stop      Stop all processes"
                    echo "  restart   Stop then start both in background"
                    echo "  cm        Change Opus, Sonnet, Haiku, or default model"
                    echo "  key       Update NVIDIA_NIM_API_KEY in .env"
                    ;;
        install)    if [[ "$sub" == "ai" ]]; then
                        echo "Usage: ml install ai"
                        echo "Clones Free Claude Code to ~/.free-claude-code/"
                    fi
                    ;;
        nav)        echo "Usage: ml nav [--projectname]"
                    echo "  ml nav --new           Go to ~/xampp/htdocs"
                    ;;
        migrate)    echo "Usage: ml migrate -db <DB>   Migrate to target DB"
                    echo "       ml migrate global     Restore to userdb"
                    ;;
        *)          echo "No help available for '$cmd'" ;;
    esac
}

show_help_test() {
    echo "Usage: ml test <database>"
    echo "  ml test userdb   Test userdb connectivity and schema"
}

show_help_create() {
    echo "Usage: ml create <project_name>"
    echo "       ml create --a         Create user account"
    echo "       ml create --config     Create DB config for backups"
    echo "       ml create --rbac       Create RBAC table"
    echo "       ml create --pbac       Create PBAC table"
}

show_help_add() {
    echo "Usage: ml add userdb   Import userdb schema SQL"
    echo "       ml add menu     Add sidebar menu with AI metadata"
}

show_help_serve() {
    echo "Usage: ml serve [project] [-o]"
    echo "       ml serve --stop     Stop ngrok tunnel"
    echo ""
    echo "  -o, --online   Create ngrok tunnel for public URL"
}

show_help_wb() {
    echo "Usage: ml wb --export -db <name> [-tb <table>] [-m 1-6] [-fn <folder>]"
    echo ""
    echo "Methods:"
    echo "  1  Structure Only"
    echo "  2  Data Only"
    echo "  3  Data + Structure"
    echo "  4  Structure + Schema"
    echo "  5  Data + Schema"
    echo "  6  Full Export"
}

# ── Download installer helper ───────────────────────────────────────────────────
cmd_download_installer() {
    echo "Downloading installer helper..."
    local tmp_file
    tmp_file="$(ml_tmp "downloader")"
    ml_fetch "${GH_RAW}/download-installer.php" "$tmp_file"
    if [[ $ML_FETCH_RC -ne 0 ]]; then
        echo "ml: failed to fetch installer" >&2
        exit 1
    fi
    "$PHP_EXE" "$tmp_file"
    rm -f "$tmp_file"
}

# ── Check remote version ───────────────────────────────────────────────────────
cmd_check_version() {
    echo "Checking remote ML CLI version..."
    local tmp_ver="/tmp/ml-remote-version-$$"
    ml_fetch "https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/VERSION" "$tmp_ver"
    if [[ $ML_FETCH_RC -ne 0 ]]; then
        echo "ml: failed to fetch remote version" >&2
        exit 1
    fi
    local remote_ver
    remote_ver=$(cat "$tmp_ver" | tr -d '[:space:]')
    rm -f "$tmp_ver"

    if [[ "$remote_ver" == "$ML_VERSION" ]]; then
        echo "Version is up to date. ($ML_VERSION)"
    else
        echo "New version available: $remote_ver (current: $ML_VERSION)"
        echo "Run: ml update"
    fi
}

# ── Update CLI ─────────────────────────────────────────────────────────────────
cmd_update() {
    echo "Updating ML CLI..."
    local tmp_file
    tmp_file="$(ml_tmp "update")"
    ml_fetch "${GH_RAW}/ml-update.php" "$tmp_file"
    if [[ $ML_FETCH_RC -ne 0 ]]; then
        echo "ml: failed to fetch updater" >&2
        exit 1
    fi
    "$PHP_EXE" "$tmp_file"
    local rc=$?
    rm -f "$tmp_file"
    exit $rc
}

# ── Local project generator ─────────────────────────────────────────────────────
local_gen() {
    local script="$REPO_ROOT/generate-file-structure.php"
    if [[ ! -f "$script" ]]; then
        echo "ml: generate-file-structure.php not found in $REPO_ROOT" >&2
        exit 1
    fi
    "$PHP_EXE" "$script" "$@"
}

# ── Account creation ───────────────────────────────────────────────────────────
cmd_create_account() {
    remote_run "${GH_RAW}/account-insert.php" "$@"
}

cmd_create_config() {
    remote_run "${GH_RAW}/db-config/db-config.php" "$@"
}

cmd_create_rbac() {
    remote_run "${GH_RAW}/rbac/ml-rbac.php" "$@"
}

cmd_create_pbac() {
    remote_run "${GH_RAW}/pbac/ml-pbac.php" "$@"
}

cmd_create() {
    case "${1:-}" in
        --a)
            shift; cmd_create_account "$@"
            ;;
        --config)
            shift; cmd_create_config "$@"
            ;;
        --rbac)
            shift; cmd_create_rbac "$@"
            ;;
        --pbac)
            shift; cmd_create_pbac "$@"
            ;;
        "")
            echo "Usage: ml create <project>"
            echo "       ml create --a         Create interactive account"
            echo "       ml create --config    Create DB config"
            echo "       ml create --rbac      Create RBAC table"
            echo "       ml create --pbac      Create PBAC table"
            echo ""
            echo "You can also pass a project name directly:"
            echo "       ml create my-project"
            echo ""
            echo "Create list:"
            echo "  create <project>  Generate project scaffold"
            echo "  create --a         Create interactive account"
            echo "  create --config    Create DB config for backups"
            echo "  create --pbac      Create PBAC table + scaffold"
            echo "  create --rbac      Create RBAC table in userdb"
            echo "  gen                Generate local PBAC access map"
            exit 2
            ;;
        *)
            local_gen "$@"
            ;;
    esac
}

# ── Generate PBAC access map ───────────────────────────────────────────────────
cmd_gen() {
    local target="${1:-}"
    local map_script=""

    if [[ -n "$target" ]]; then
        # Check common paths for generate_access_map.php
        local project_root="$(ml_htdocs)/$target"
        if [[ -f "$project_root/tools/generate_access_map.php" ]]; then
            map_script="$project_root/tools/generate_access_map.php"
        fi
    else
        # Try current directory
        if [[ -f "$(pwd)/tools/generate_access_map.php" ]]; then
            map_script="$(pwd)/tools/generate_access_map.php"
        fi
    fi

    if [[ -z "$map_script" ]]; then
        echo "No access map script found."
        echo "Run: ml create --pbac <project_name>"
        exit 2
    fi

    echo "Generating PBAC access map..."
    "$PHP_EXE" "$map_script"
}

# ── Nav ─────────────────────────────────────────────────────────────────────────
cmd_nav() {
    local name="${1:-}" opts=()
    if [[ "$name" == --remote ]]; then
        name="${2:-}"
        opts+=("--remote")
    elif [[ "$name" =~ ^--.+$ ]]; then
        name="${name#--}"
    elif [[ "$name" == --new ]]; then
        name=""
    fi

    local tmp_file
    tmp_file="$(ml_tmp "nav")"
    ml_fetch "${GH_RAW}/ml-nav.php" "$tmp_file"
    if [[ $ML_FETCH_RC -ne 0 ]]; then
        echo "ml: failed to fetch nav helper" >&2
        exit 1
    fi

    if [[ -n "$name" ]]; then
        "$PHP_EXE" "$tmp_file" --"$name" "${opts[@]}"
    else
        "$PHP_EXE" "$tmp_file" --new
    fi
    local rc=$?
    rm -f "$tmp_file"
    exit $rc
}

# ── Serve ──────────────────────────────────────────────────────────────────────
cmd_serve() {
    local tmp_file
    tmp_file="$(ml_tmp "serve")"
    ml_fetch "${GH_RAW}/ml-serve.php" "$tmp_file"
    if [[ $ML_FETCH_RC -ne 0 ]]; then
        echo "ml: failed to fetch serve helper" >&2
        exit 1
    fi
    "$PHP_EXE" "$tmp_file" "$@"
    local rc=$?
    rm -f "$tmp_file"
    exit $rc
}

# ── Add ─────────────────────────────────────────────────────────────────────────
cmd_add() {
    case "${1:-}" in
        userdb)
            shift
            remote_run "${GH_RAW}/userdb-import.php" "$@"
            ;;
        menu)
            shift
            # Local script (needs updating for Unix paths too)
            local script="$REPO_ROOT/script/sidebar-add-menu.php"
            if [[ -f "$script" ]]; then
                "$PHP_EXE" "$script" "$@"
            else
                local tmp_file
                tmp_file="$(ml_tmp "menu")"
                ml_fetch "${GH_RAW}/script/sidebar-add-menu.php" "$tmp_file"
                if [[ $ML_FETCH_RC -ne 0 ]]; then
                    echo "ml: failed to fetch menu helper" >&2
                    exit 1
                fi
                "$PHP_EXE" "$tmp_file" "$@"
                rm -f "$tmp_file"
            fi
            ;;
        "")
            echo "Usage: ml add userdb   Import userdb schema"
            echo "       ml add menu     Add sidebar menu with AI"
            exit 2
            ;;
        *)
            echo "Unknown add target: $1"
            echo "Usage: ml add userdb | ml add menu"
            exit 1
            ;;
    esac
}

# ── Workbench ──────────────────────────────────────────────────────────────────
cmd_wb() {
    local sub="${1:-}"
    if [[ "$sub" == "--export" ]]; then
        shift
        local tmp_file
        tmp_file="$(ml_tmp "wb")"
        ml_fetch "${GH_RAW}/workbench/open-workbench.php" "$tmp_file"
        if [[ $ML_FETCH_RC -ne 0 ]]; then
            echo "ml: failed to fetch workbench helper" >&2
            exit 1
        fi
        "$PHP_EXE" "$tmp_file" --export "$@"
        rm -f "$tmp_file"
    else
        # Open Workbench GUI — find the app
        if command -v MySQLWorkbench &>/dev/null; then
            MySQLWorkbench &
        elif [[ -d "/Applications/MySQLWorkbench.app" ]]; then
            open "/Applications/MySQLWorkbench.app"
        else
            echo "ml: MySQL Workbench not found."
            echo "  Install it from: https://dev.mysql.com/downloads/workbench/"
            exit 1
        fi
    fi
}

# ── Backup ──────────────────────────────────────────────────────────────────────
cmd_backup() {
    local schema="${1:-}"
    local tmp_file
    tmp_file="$(ml_tmp "backup")"
    ml_fetch "${GH_RAW}/backup-cli/backup-db.php" "$tmp_file"
    if [[ $ML_FETCH_RC -ne 0 ]]; then
        echo "ml: failed to fetch backup helper" >&2
        exit 1
    fi
    if [[ -n "$schema" ]]; then
        "$PHP_EXE" "$tmp_file" "$schema"
    else
        "$PHP_EXE" "$tmp_file"
    fi
    rm -f "$tmp_file"
}

# ── AI commands ────────────────────────────────────────────────────────────────
cmd_ai() {
    local sub="${1:-}"
    local tmp_file
    tmp_file="$(ml_tmp "ai")"
    ml_fetch "${GH_RAW}/ai-commands.php" "$tmp_file"
    if [[ $ML_FETCH_RC -ne 0 ]]; then
        echo "ml: failed to fetch AI helper" >&2
        exit 1
    fi
    "$PHP_EXE" "$tmp_file" "$sub"
    local rc=$?
    rm -f "$tmp_file"
    exit $rc
}

cmd_install_ai() {
    local tmp_file
    tmp_file="$(ml_tmp "ai-installer")"
    ml_fetch "${GH_RAW}/ai-installer.php" "$tmp_file"
    if [[ $ML_FETCH_RC -ne 0 ]]; then
        echo "ml: failed to fetch AI installer" >&2
        exit 1
    fi
    "$PHP_EXE" "$tmp_file"
    local rc=$?
    rm -f "$tmp_file"
    exit $rc
}

# ── Migration ──────────────────────────────────────────────────────────────────
cmd_migrate() {
    local tmp_file
    tmp_file="$(ml_tmp "migrate")"
    local local_script="$REPO_ROOT/script/user-migrate.php"

    if [[ -f "$local_script" ]]; then
        "$PHP_EXE" "$local_script" "$@"
    else
        ml_fetch "${GH_RAW}/script/user-migrate.php" "$tmp_file"
        if [[ $ML_FETCH_RC -ne 0 ]]; then
            echo "ml: failed to fetch migrate helper" >&2
            exit 1
        fi
        "$PHP_EXE" "$tmp_file" "$@"
        rm -f "$tmp_file"
    fi
}

# ── Reveal in Explorer/Finder ──────────────────────────────────────────────────
cmd_reveal() {
    local target="${1:-}"
    local path=""

    if [[ -z "$target" ]]; then
        path="$(pwd)"
    elif [[ -d "$target" ]]; then
        path="$(cd "$target" && pwd)"
    elif [[ -d "$(ml_htdocs)/$target" ]]; then
        path="$(ml_htdocs)/$target"
    else
        echo "ml rev: path not found: $target" >&2
        exit 1
    fi

    if command -v open &>/dev/null; then
        open "$path"
    elif command -v xdg-open &>/dev/null; then
        xdg-open "$path"
    else
        echo "Opening: $path"
        echo "(no suitable opener found — install 'open' on macOS or 'xdg-utils' on Linux)"
    fi
}

# ── Clone local (dev) ──────────────────────────────────────────────────────────
cmd_clone_local() {
    local dest="${1:-$HOME/.ml-cli}"
    echo "Cloning ML CLI to $dest..."
    mkdir -p "$dest"
    cp -r "$REPO_ROOT"/*.php "$REPO_ROOT"/*.bat "$REPO_ROOT"/ml "$dest/" 2>/dev/null || true
    cp -r "$REPO_ROOT"/{migration,script,rbac,pbac,backup-cli,db-config,workbench,reveal-in-folder} "$dest/" 2>/dev/null || true
    echo "Done. Run with: php $dest/generate-file-structure.php <project>"
}

dispatch "$@"
