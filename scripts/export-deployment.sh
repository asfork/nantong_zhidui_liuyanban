#!/bin/sh

set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
PROJECT_DIR=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
OUTPUT_DIR=${1:-"$PROJECT_DIR/dist"}
BUILD_ID=$(date '+%Y%m%d-%H%M%S')
STAGE_DIR=$(mktemp -d "${TMPDIR:-/tmp}/liuyanban-export.XXXXXX")
PACKAGE_DIR="$STAGE_DIR/liuyanban"

cleanup()
{
    rm -rf "$STAGE_DIR"
}
trap cleanup EXIT INT TERM

mkdir -p "$OUTPUT_DIR"
OUTPUT_DIR=$(CDPATH= cd -- "$OUTPUT_DIR" && pwd)
ZIP_PATH="$OUTPUT_DIR/liuyanban-deployment-$BUILD_ID.zip"
mkdir -p "$PACKAGE_DIR" "$PACKAGE_DIR/var/log" "$PACKAGE_DIR/var/session"

for item in app config public database/production database/migrations/002_reply_status.sql deploy; do
    destination="$PACKAGE_DIR/$(dirname "$item")"
    mkdir -p "$destination"
    cp -R "$PROJECT_DIR/$item" "$destination/"
done

touch "$PACKAGE_DIR/var/log/.gitkeep" "$PACKAGE_DIR/var/session/.gitkeep"

COMMIT=$(git -C "$PROJECT_DIR" rev-parse --short HEAD 2>/dev/null || printf 'unversioned')
if git -C "$PROJECT_DIR" diff --quiet 2>/dev/null && git -C "$PROJECT_DIR" diff --cached --quiet 2>/dev/null; then
    WORKTREE_STATE=clean
else
    WORKTREE_STATE=dirty
fi

{
    printf 'Build ID: %s\n' "$BUILD_ID"
    printf 'Git commit: %s\n' "$COMMIT"
    printf 'Worktree state: %s\n' "$WORKTREE_STATE"
    printf 'Compatibility: Apache 2.4.39 / PHP 7.3.4 / MySQL 5.7.26\n'
    printf 'Base path: /liuyanban/\n'
} > "$PACKAGE_DIR/BUILD-INFO.txt"

cp "$PROJECT_DIR/deploy/INSTALL-WINDOWS.md" "$PACKAGE_DIR/README-DEPLOYMENT.md"

if grep -R -n -E 'root-dev-password|liuyanban-dev-password' "$PACKAGE_DIR"; then
    printf 'Deployment export aborted: development credentials found.\n' >&2
    exit 1
fi

(
    cd "$PACKAGE_DIR"
    find . -type f ! -name MANIFEST.sha256 -print | LC_ALL=C sort | while IFS= read -r file; do
        shasum -a 256 "$file"
    done > MANIFEST.sha256
)

mkdir -p "$OUTPUT_DIR"
(
    cd "$STAGE_DIR"
    zip -qr "$ZIP_PATH" liuyanban
)
shasum -a 256 "$ZIP_PATH" > "$ZIP_PATH.sha256"

printf '%s\n' "$ZIP_PATH"
