#!/bin/bash
#
# Paubox CF7 Integration - Version Update Script
#
# Updates the version string in all plugin files.
#
# Usage: ./scripts/update-version-simple.sh <new-version> [--no-confirm] [--force]
# Example: ./scripts/update-version-simple.sh 1.1.0 --no-confirm

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info()    { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[OK]${NC} $1"; }
log_warn()    { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error()   { echo -e "${RED}[ERROR]${NC} $1"; }

# ---------------------------------------------------------------------------
# Arguments
# ---------------------------------------------------------------------------
if [ $# -eq 0 ] || [ "$1" = "--help" ] || [ "$1" = "-h" ]; then
    echo "Usage: $0 <new-version> [--no-confirm] [--force]"
    exit 0
fi

NEW_VERSION="$1"
NO_CONFIRM=false
FORCE_UPDATE=false

for arg in "${@:2}"; do
    case "$arg" in
        --no-confirm) NO_CONFIRM=true ;;
        --force)      FORCE_UPDATE=true ;;
        *) log_error "Unknown argument: $arg"; exit 1 ;;
    esac
done

if ! [[ $NEW_VERSION =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    log_error "Invalid version format. Expected: x.y.z"
    exit 1
fi

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MAIN_FILE="${PROJECT_ROOT}/paubox-cf7.php"

if [ ! -f "$MAIN_FILE" ]; then
    log_error "Main plugin file not found at $MAIN_FILE"
    exit 1
fi

# ---------------------------------------------------------------------------
# Detect current version
# ---------------------------------------------------------------------------
CURRENT_VERSION=$(grep -o "Version: [0-9]\+\.[0-9]\+\.[0-9]\+" "$MAIN_FILE" | head -1 | cut -d' ' -f2)
log_info "Current version: ${CURRENT_VERSION:-unknown}"
log_info "New version:     ${NEW_VERSION}"

if [ "$CURRENT_VERSION" = "$NEW_VERSION" ] && [ "$FORCE_UPDATE" = false ]; then
    log_warn "Already at $NEW_VERSION. Use --force to update anyway."
    exit 0
fi

# ---------------------------------------------------------------------------
# Confirm
# ---------------------------------------------------------------------------
if [ "$NO_CONFIRM" = false ]; then
    read -rp "Update $CURRENT_VERSION → $NEW_VERSION? [y/N] " confirm
    [[ "$confirm" =~ ^[Yy]$ ]] || { log_warn "Aborted."; exit 0; }
fi

# ---------------------------------------------------------------------------
# Portable sed helper (handles macOS -i requirement)
# ---------------------------------------------------------------------------
sed_inplace() {
    local pattern="$1"
    local file="$2"
    if sed --version &>/dev/null 2>&1; then
        # GNU sed
        sed -i "$pattern" "$file"
    else
        # BSD/macOS sed
        sed -i '' "$pattern" "$file"
    fi
}

# ---------------------------------------------------------------------------
# Update files
# ---------------------------------------------------------------------------
update_file() {
    local file="$1"
    local description="$2"
    if [ ! -f "$file" ]; then
        log_warn "Skipping (not found): $file"
        return
    fi
    # WordPress plugin header
    sed_inplace "s/Version: ${CURRENT_VERSION}/Version: ${NEW_VERSION}/g" "$file"
    # PHP constant
    sed_inplace "s/PAUBOX_CF7_VERSION', '${CURRENT_VERSION}'/PAUBOX_CF7_VERSION', '${NEW_VERSION}'/g" "$file"
    # PHPDoc @version and @since-style version tags
    sed_inplace "s/@version ${CURRENT_VERSION}/@version ${NEW_VERSION}/g" "$file"
    log_success "Updated $description"
}

update_file "$MAIN_FILE" "paubox-cf7.php"

for php_file in "${PROJECT_ROOT}"/includes/**/*.php "${PROJECT_ROOT}"/includes/*.php; do
    [ -f "$php_file" ] || continue
    sed_inplace "s/@version ${CURRENT_VERSION}/@version ${NEW_VERSION}/g" "$php_file"
    log_success "Updated $(basename "$php_file")"
done

log_success "All files updated to v${NEW_VERSION}"
