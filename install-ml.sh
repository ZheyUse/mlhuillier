#!/usr/bin/env bash
#
# install-ml.sh — ML CLI Shell Installer (macOS / Linux)
#
# Usage:
#   curl -LsSf https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/install-ml.sh | bash
#   curl -LsSf https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/install-ml.sh | bash -s -- --update
#
# Supports --update flag to reinstall/overwrite existing files.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MODE="INSTALL"
UPDATE_FLAG=""

RAW_BASE="https://raw.githubusercontent.com/ZheyUse/mlhuillier/main"
TARGET_DIR="${ML_CLI_TOOLS:-"$HOME/.ml-cli"}"

# Read version from bundled VERSION file if present, otherwise use fallback
CLI_VERSION="1.1.14"
if [[ -f "$SCRIPT_DIR/VERSION" ]] && [[ "$SCRIPT_DIR" != "/dev/null" ]]; then
    CLI_VERSION="$(cat "$SCRIPT_DIR/VERSION" 2>/dev/null | tr -d '[:space:]')"
fi

# Detect if script was downloaded (piped) vs. run from cloned repo
if [[ -f "$SCRIPT_DIR/install-ml.sh" ]]; then
    SOURCE_DIR="$SCRIPT_DIR"
else
    SOURCE_DIR=""
fi

# --update flag support
for arg in "$@"; do
    case "$arg" in
        --update) MODE="UPDATE" ;;
    esac
done

# Determine target binary path
install_ml_path=""
if command -v ml &>/dev/null; then
    install_ml_path="$(command -v ml)"
elif [[ -d "$TARGET_DIR" ]]; then
    install_ml_path="$TARGET_DIR/ml"
fi

echo ""
printf ' +--------------------------------------+\n'
printf ' |%30s   |\n' "ML CLI INSTALL"
printf ' +--------------------------------------+\n'
printf ' | %-12s : %-21s |\n' "Version" "$CLI_VERSION"
printf ' | %-12s : %-21s |\n' "Mode" "$MODE"
printf ' | %-12s : %-21s |\n' "Target" "$TARGET_DIR"
printf ' +--------------------------------------+\n'
echo ""

TOTAL_STEPS=5
CURRENT_STEP=0

show_progress() {
    local step=$1 total=$2
    local pct=$((step * 100 / total))
    local bar=""
    bar=$(printf '%*s' "$((pct / 10))" '' | tr ' ' '#')$(printf '%*s' "$((10 - pct / 10))" '' | tr ' ' '.')
    printf ' [%s] %3d%%\n' "$bar" "$pct"
}

echo "[1/5] Installing core files..."
mkdir -p "$TARGET_DIR"

if [[ -n "$SOURCE_DIR" && -f "$SOURCE_DIR/ml" ]]; then
    echo "  [OK] Copying ml from local repo..."
    cp "$SOURCE_DIR/ml" "$TARGET_DIR/ml"
else
    echo "  [..] Downloading ml from GitHub..."
    if curl -LsSf "${RAW_BASE}/ml" -o "$TARGET_DIR/ml" 2>/dev/null; then
        echo "  [OK] Download complete"
    else
        echo "  [X] Failed to download ml from GitHub"
        exit 1
    fi
fi

chmod +x "$TARGET_DIR/ml"

# Write VERSION file
echo "$CLI_VERSION" > "$TARGET_DIR/VERSION"
echo "  [OK] VERSION file written"

# Write version.txt
cat > "$TARGET_DIR/version.txt" <<EOF
ML CLI Installer
Version: $CLI_VERSION
Source: $RAW_BASE
InstalledAt: $(date)
EOF
echo "  [OK] version.txt written"

CURRENT_STEP=$((CURRENT_STEP + 1))
show_progress $CURRENT_STEP $TOTAL_STEPS
echo ""

echo "[2/5] Installing uninstaller..."
if [[ -n "$SOURCE_DIR" && -f "$SOURCE_DIR/uninstall-ml.sh" ]]; then
    cp "$SOURCE_DIR/uninstall-ml.sh" "$TARGET_DIR/uninstall-ml.sh"
else
    curl -LsSf "${RAW_BASE}/uninstall-ml.sh" -o "$TARGET_DIR/uninstall-ml.sh" 2>/dev/null || {
        echo "  [!] Failed to download uninstaller"
    }
fi
chmod +x "$TARGET_DIR/uninstall-ml.sh" 2>/dev/null || true
echo "  [OK] Uninstaller installed"

CURRENT_STEP=$((CURRENT_STEP + 1))
show_progress $CURRENT_STEP $TOTAL_STEPS
echo ""

echo "[3/5] Downloading helper scripts..."
HELPERS=(
    "generate-file-structure.php"
    "generate-file-remote.php"
    "ml-nav.php"
    "ml-serve.php"
    "ml-update.php"
    "ai-commands.php"
    "ai-installer.php"
    "account-insert.php"
    "userdb-import.php"
    "userdb-con-test.php"
    "sidebar-add-menu.php"
    "rbac/rbac-create-table.php"
    "pbac/pbac-create-table.php"
    "pbac/pbac-scaffold.php"
)
for helper in "${HELPERS[@]}"; do
    if [[ -n "$SOURCE_DIR" && -f "$SOURCE_DIR/$helper" ]]; then
        mkdir -p "$(dirname "$TARGET_DIR/$helper")"
        cp "$SOURCE_DIR/$helper" "$TARGET_DIR/$helper"
    else
        mkdir -p "$(dirname "$TARGET_DIR/$helper")"
        curl -LsSf "${RAW_BASE}/${helper}" -o "$TARGET_DIR/$helper" 2>/dev/null || true
    fi
done
echo "  [OK] Helper scripts installed"

CURRENT_STEP=$((CURRENT_STEP + 1))
show_progress $CURRENT_STEP $TOTAL_STEPS
echo ""

echo "[4/5] Configuring environment..."
SHELL_NAME="$(basename "${SHELL:-bash}")"
PROFILE_FILE=""

case "$SHELL_NAME" in
    zsh)  PROFILE_FILE="${ZDOTDIR:-$HOME}/.zshrc" ;;
    bash) PROFILE_FILE="$HOME/.bashrc" ;;
    fish) PROFILE_FILE="$HOME/.config/fish/config.fish" ;;
esac

PATH_EXPORT_LINE=""
case "$SHELL_NAME" in
    zsh|bash) PATH_EXPORT_LINE="export PATH=\"\$HOME/.ml-cli:\$PATH\"" ;;
    fish)     PATH_EXPORT_LINE="set -gx PATH \$HOME/.ml-cli \$PATH" ;;
esac

if [[ -n "$PROFILE_FILE" && -n "$PATH_EXPORT_LINE" ]]; then
    if ! grep -qF '.ml-cli' "$PROFILE_FILE" 2>/dev/null; then
        printf '\n# ML CLI\n%s\n' "$PATH_EXPORT_LINE" >> "$PROFILE_FILE"
        echo "  [OK] Added to $PROFILE_FILE"
    else
        echo "  [OK] PATH entry already present in $PROFILE_FILE"
    fi
fi

# Warn if ml is not on PATH
if command -v ml &>/dev/null; then
    echo "  [OK] ml is on PATH"
elif [[ -d "$TARGET_DIR" ]]; then
    echo "  [!] ml not on PATH yet — restart your terminal or run:"
    echo "      export PATH=\"$TARGET_DIR:\$PATH\""
fi

CURRENT_STEP=$((CURRENT_STEP + 1))
show_progress $CURRENT_STEP $TOTAL_STEPS
echo ""

echo "[5/5] Finalizing setup..."
cat > "$TARGET_DIR/made-by.txt" <<EOF
ML CLI
M LHUILLIER FILE GENERATOR
Version: $CLI_VERSION
Follow: https://github.com/ZheyUse
EOF
echo "  [OK] Metadata written"

echo ""
printf ' +------------------------------+\n'
printf ' |%28s|\n' "INSTALLATION COMPLETE"
printf ' +------------------------------+\n'
echo ""
echo "  Command: ml create my-project"
echo ""
echo "  Note: Restart your terminal or run:"
echo "        export PATH=\"$TARGET_DIR:\$PATH\""
echo ""

CURRENT_STEP=$((CURRENT_STEP + 1))
show_progress $CURRENT_STEP $TOTAL_STEPS