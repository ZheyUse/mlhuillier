#!/usr/bin/env bash
#
# uninstall-ml.sh — ML CLI Shell Uninstaller (macOS / Linux)
#
# Usage:
#   curl -LsSf https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/uninstall-ml.sh | bash
#   bash install-ml.sh uninstall
#
# Or run directly:
#   ./uninstall-ml.sh

set -euo pipefail

UNINSTALL_VERSION="1.1.14"
TARGET_DIR="${ML_CLI_TOOLS:-"$HOME/.ml-cli"}"

# Determine installed CLI version from VERSION file if present
CLI_VERSION="$UNINSTALL_VERSION"
if [[ -f "$TARGET_DIR/VERSION" ]]; then
    CLI_VERSION="$(cat "$TARGET_DIR/VERSION" 2>/dev/null | tr -d '[:space:]')"
fi

echo "Uninstalling ML CLI v.$CLI_VERSION..."
echo "Target: $TARGET_DIR"

echo ""
echo "[1/3] Removing .ml-cli from shell PATH..."

SHELL_NAME="$(basename "${SHELL:-bash}")"
PROFILE_FILES=()

case "$SHELL_NAME" in
    zsh)
        PROFILE_FILES+=("${ZDOTDIR:-$HOME}/.zshrc")
        PROFILE_FILES+=("$HOME/.zshrc")
        ;;
    bash)
        PROFILE_FILES+=("$HOME/.bashrc")
        PROFILE_FILES+=("$HOME/.bash_profile")
        ;;
    fish)
        PROFILE_FILES+=("$HOME/.config/fish/config.fish")
        ;;
esac

# Always check default locations regardless of current shell
if [[ "$SHELL_NAME" != "bash" ]]; then
    PROFILE_FILES+=("$HOME/.bashrc")
    PROFILE_FILES+=("$HOME/.bash_profile")
fi
if [[ "$SHELL_NAME" != "zsh" ]]; then
    PROFILE_FILES+=("${ZDOTDIR:-$HOME}/.zshrc")
    PROFILE_FILES+=("$HOME/.zshrc")
fi
if [[ "$SHELL_NAME" != "fish" ]]; then
    PROFILE_FILES+=("$HOME/.config/fish/config.fish")
fi

# Deduplicate
read -ra PROFILE_FILES <<< "$(printf '%s\n' "${PROFILE_FILES[@]}" | sort -u | tr '\n' ' ')"

ML_PATH_LINE_BASH='export PATH="$HOME/.ml-cli:$PATH"'
ML_PATH_LINE_FISH='set -gx PATH $HOME/.ml-cli $PATH'

PATH_REMOVED=0

for pf in "${PROFILE_FILES[@]}"; do
    [[ -z "$pf" ]] && continue
    [[ ! -f "$pf" ]] && continue

    case "$pf" in
        *.fish)
            if grep -qF '.ml-cli' "$pf" 2>/dev/null; then
                # Remove the path line for fish
                grep -vF "$ML_PATH_LINE_FISH" "$pf" > "$pf.tmp" && mv "$pf.tmp" "$pf"
                # Also remove common variations
                grep -v -E 'set -gx PATH.*\.ml-cli' "$pf" > "$pf.tmp" && mv "$pf.tmp" "$pf"
                echo "  [OK] Updated $pf (removed .ml-cli PATH entry)"
                PATH_REMOVED=1
            fi
            ;;
        *)
            if grep -qF '.ml-cli' "$pf" 2>/dev/null; then
                # Remove the export PATH line for bash/zsh
                grep -vF "$ML_PATH_LINE_BASH" "$pf" > "$pf.tmp" && mv "$pf.tmp" "$pf"
                # Also remove common variations
                grep -v -E 'export PATH=.*\.ml-cli' "$pf" > "$pf.tmp" && mv "$pf.tmp" "$pf"
                echo "  [OK] Updated $pf (removed .ml-cli PATH entry)"
                PATH_REMOVED=1
            fi
            ;;
    esac
done

if [[ "$PATH_REMOVED" -eq 1 ]]; then
    echo "  [OK] Removed .ml-cli from shell profile(s)"
else
    echo "  [SKIP] No .ml-cli PATH entry found in shell profiles"
fi

echo ""
echo "[2/3] Cleaning installed files..."

echo "  Checking for system-wide ml installation..."
if [[ -w "$(dirname "$(command -v ml 2>/dev/null || echo "/usr/local/bin/ml")")" ]]; then
    if command -v ml &>/dev/null; then
        ML_PATH="$(command -v ml)"
        if rm -f "$ML_PATH" 2>/dev/null; then
            echo "  [OK] Removed $ML_PATH"
        else
            echo "  [WARN] Could not remove $ML_PATH — try with sudo"
        fi
    fi
else
    echo "  [SKIP] ml is installed in a protected location (system-wide install)"
    echo "         Run with sudo to fully remove: sudo rm /usr/local/bin/ml"
fi

echo ""
echo "[3/3] Removing $TARGET_DIR..."

if [[ ! -d "$TARGET_DIR" ]] || [[ -z "$(ls -A "$TARGET_DIR" 2>/dev/null)" ]]; then
    if [[ ! -d "$TARGET_DIR" ]]; then
        echo "  [OK] Directory already removed"
    else
        rmdir "$TARGET_DIR" 2>/dev/null && echo "  [OK] Empty directory removed" || echo "  [WARN] Could not remove empty directory"
    fi
    echo ""
    echo "[SUCCESS] Uninstall complete. Restart your terminal or run:"
    echo "          source ~/.bashrc   (or ~/.zshrc, etc.)"
    exit 0
fi

# Retry loop — handles locked files from open terminals
ATTEMPTS=8
for ((i=1; i<=ATTEMPTS; i++)); do
    if rm -rf "$TARGET_DIR" 2>/dev/null; then
        echo "  [OK] $TARGET_DIR removed"
        echo ""
        echo "[SUCCESS] Uninstall complete."
        echo "Open a new terminal or run:"
        echo "    source ~/.bashrc   # bash"
        echo "    source ~/.zshrc    # zsh"
        exit 0
    fi
    echo "  [...] Attempt $i/$ATTEMPTS — retrying in 1s..."
    sleep 1
done

echo ""
echo "[FAIL] Could not remove $TARGET_DIR."
echo "Close any terminals or editors referencing that directory and run uninstaller again."
echo "Remaining files:"
ls -la "$TARGET_DIR" 2>/dev/null || true