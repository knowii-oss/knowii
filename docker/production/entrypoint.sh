#!/bin/sh
# Entrypoint of the production image (#1172, ADR 0046). Every service of compose.production.yaml starts here, then runs
# its own command. The caches need the real environment, so they are built at start, not at build.
set -eu

cd /app

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is empty. Generate one with: docker run --rm --entrypoint php <image> artisan key:generate --show" >&2
    exit 1
fi

# The folders of storage/app can be empty on a new volume
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs

# The browser build reads its Reverb settings and the app name at build time. The image is built with placeholders
# (see the frontend stage of the Dockerfile): put the values of this environment in their place. They default to the
# server values, like .env.example does. Values with a double quote or a backslash are not supported.
replace_build_value() {
    placeholder="$1"
    value="$2"
    escaped="$(printf '%s' "$value" | sed -e 's/[\\&|]/\\&/g')"
    for file in public/build/assets/*.js; do
        [ -f "$file" ] || continue
        if grep -q -F "$placeholder" "$file"; then
            sed -i -e "s|$placeholder|$escaped|g" "$file"
        fi
    done
}
replace_build_value __KNOWII_VITE_APP_NAME__ "${VITE_APP_NAME:-${APP_NAME:-Knowii}}"
replace_build_value __KNOWII_VITE_REVERB_APP_KEY__ "${VITE_REVERB_APP_KEY:-${REVERB_APP_KEY:-}}"
replace_build_value __KNOWII_VITE_REVERB_HOST__ "${VITE_REVERB_HOST:-${REVERB_HOST:-}}"
replace_build_value __KNOWII_VITE_REVERB_PORT__ "${VITE_REVERB_PORT:-${REVERB_PORT:-443}}"
replace_build_value __KNOWII_VITE_REVERB_SCHEME__ "${VITE_REVERB_SCHEME:-${REVERB_SCHEME:-https}}"

# public/storage -> storage/app/public (the uploaded files)
php artisan storage:link --force

php artisan package:discover --ansi
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Only the app service sets MIGRATE_ON_START=true: one process runs the migrations. Its host port is fixed in
# compose.production.yaml, so a second replica of the app service cannot start and race with it. The other services
# wait for the app service to be healthy, which it is after the migrations.
if [ "${MIGRATE_ON_START:-false}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
