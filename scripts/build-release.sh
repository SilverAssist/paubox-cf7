#!/bin/bash
#
# Paubox CF7 Integration - Release Build Script
#
# Builds a clean, production-ready ZIP for the GitHub Release.
#
# Usage: ./scripts/build-release.sh [version]
# Output: build/paubox-cf7-vX.Y.Z.zip (+ .md5 + .sha256)

set -euo pipefail

BLUE='\033[0;34m'
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m'

log_info()    { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[OK]${NC} $1"; }
log_error()   { echo -e "${RED}[ERROR]${NC} $1"; }

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="paubox-cf7"

# ---------------------------------------------------------------------------
# Version detection
# ---------------------------------------------------------------------------
if [ -n "${1:-}" ]; then
    VERSION="$1"
else
    VERSION=$(grep -o "Version: [0-9]\+\.[0-9]\+\.[0-9]\+" "${PROJECT_ROOT}/${PLUGIN_SLUG}.php" | head -1 | cut -d' ' -f2)
fi

if [ -z "$VERSION" ]; then
    log_error "Could not determine version. Pass it as an argument or set it in ${PLUGIN_SLUG}.php."
    exit 1
fi

ZIP_NAME="${PLUGIN_SLUG}-v${VERSION}.zip"
BUILD_DIR="${PROJECT_ROOT}/build"
STAGE_DIR="${BUILD_DIR}/${PLUGIN_SLUG}"

log_info "Building ${PLUGIN_SLUG} v${VERSION}"
log_info "Output: ${BUILD_DIR}/${ZIP_NAME}"

# ---------------------------------------------------------------------------
# Prepare staging directory
# ---------------------------------------------------------------------------
rm -rf "$STAGE_DIR"
mkdir -p "$STAGE_DIR"

rsync -a \
    --exclude='.git' \
    --exclude='.github' \
    --exclude='build' \
    --exclude='tests' \
    --exclude='scripts' \
    --exclude='vendor/silverassist/wp-coding-standards' \
    --exclude='vendor/silverassist/coding-standards' \
    --exclude='vendor/squizlabs' \
    --exclude='vendor/phpstan' \
    --exclude='vendor/phpunit' \
    --exclude='vendor/yoast' \
    --exclude='vendor/php-stubs' \
    --exclude='vendor/szepeviktor' \
    --exclude='vendor/phpcompatibility' \
    --exclude='vendor/wp-coding-standards' \
    --exclude='vendor/phpcsstandards' \
    --exclude='vendor/slevomat' \
    --exclude='vendor/dealerdirect' \
    --exclude='vendor/miguelcolmenares' \
    --exclude='.gitignore' \
    --exclude='phpcs.xml' \
    --exclude='phpstan.neon' \
    --exclude='phpunit.xml' \
    --exclude='*.md' \
    --exclude='composer.lock' \
    --exclude='*.min.css.map' \
    --exclude='.phpcs-cache' \
    "${PROJECT_ROOT}/" "${STAGE_DIR}/"

# ---------------------------------------------------------------------------
# Build ZIP
# ---------------------------------------------------------------------------
cd "$BUILD_DIR"
zip -r "$ZIP_NAME" "$PLUGIN_SLUG" -x "*.DS_Store"

# ---------------------------------------------------------------------------
# Checksums
# ---------------------------------------------------------------------------
if command -v md5sum &>/dev/null; then
    md5sum "$ZIP_NAME" > "${ZIP_NAME}.md5"
elif command -v md5 &>/dev/null; then
    md5 -r "$ZIP_NAME" > "${ZIP_NAME}.md5"
fi

if command -v sha256sum &>/dev/null; then
    sha256sum "$ZIP_NAME" > "${ZIP_NAME}.sha256"
elif command -v shasum &>/dev/null; then
    shasum -a 256 "$ZIP_NAME" > "${ZIP_NAME}.sha256"
fi

# ---------------------------------------------------------------------------
# Clean up staging dir
# ---------------------------------------------------------------------------
rm -rf "$STAGE_DIR"

log_success "Built: ${BUILD_DIR}/${ZIP_NAME}"
[ -f "${BUILD_DIR}/${ZIP_NAME}.md5"    ] && log_success "MD5:   ${ZIP_NAME}.md5"
[ -f "${BUILD_DIR}/${ZIP_NAME}.sha256" ] && log_success "SHA256: ${ZIP_NAME}.sha256"
