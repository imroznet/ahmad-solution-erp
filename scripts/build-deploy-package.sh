#!/usr/bin/env bash
# Build a cPanel upload archive. Run only after:
#   composer install --no-dev --optimize-autoloader
#   npm ci && npm run build
#   php artisan asl:export-access-sql
#   a MySQL schema dump at deploy/mysql-schema.sql
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="${1:-local}"
VERSION="$(printf '%s' "$VERSION" | tr -cd 'A-Za-z0-9._-')"
DIST="$ROOT/dist"
STAGE="$(mktemp -d)"
NAME="ahmad-solution-erp-${VERSION}"
TARGET="$STAGE/$NAME"

cleanup() {
    rm -rf "$STAGE"
}
trap cleanup EXIT

if [[ -d "$ROOT/vendor/phpunit" ]]; then
    echo "Refusing to package development dependencies. Run composer install --no-dev --optimize-autoloader first." >&2
    exit 1
fi

if [[ ! -f "$ROOT/public/build/manifest.json" ]]; then
    echo "Missing compiled assets at public/build/manifest.json. Run npm run build first." >&2
    exit 1
fi

for required in deploy/mysql-schema.sql deploy/reference-data.sql deploy/generate-admin-sql.php; do
    if [[ ! -f "$ROOT/$required" ]]; then
        echo "Missing ${required}." >&2
        exit 1
    fi
done

mkdir -p "$DIST" "$TARGET"

tar -C "$ROOT" \
    --exclude '.git' \
    --exclude 'node_modules' \
    --exclude 'dist' \
    --exclude 'tests' \
    --exclude '.env' \
    --exclude '.env.*' \
    --exclude 'database/database.sqlite' \
    --exclude 'storage/logs' \
    --exclude 'storage/framework/cache/data' \
    --exclude 'storage/framework/sessions' \
    --exclude 'storage/framework/views' \
    --exclude 'storage/pail' \
    --exclude '.phpunit.cache' \
    -cf - . | tar -C "$TARGET" -xf -

mkdir -p \
    "$TARGET/storage/logs" \
    "$TARGET/storage/framework/cache/data" \
    "$TARGET/storage/framework/sessions" \
    "$TARGET/storage/framework/views" \
    "$TARGET/storage/app/private" \
    "$TARGET/storage/app/public" \
    "$TARGET/bootstrap/cache"

find "$TARGET/bootstrap/cache" -type f ! -name '.gitignore' -delete
rm -f "$TARGET/.env" "$TARGET/.env.backup" "$TARGET/.env.production"

cat > "$TARGET/deploy/PACKAGE.txt" <<EOF
Ahmad Solution Ltd. ERP deployment package
Version: ${VERSION}
Built: $(date -u +%Y-%m-%dT%H:%M:%SZ)

This archive contains production Composer dependencies and compiled frontend assets.
It does not contain .env, database passwords, SMTP passwords, or customer records.

Read docs/CPANEL-DEPLOYMENT.md before uploading.
EOF

(
    cd "$STAGE"
    zip -r -q "$DIST/${NAME}.zip" "$NAME"
)

echo "$DIST/${NAME}.zip"
