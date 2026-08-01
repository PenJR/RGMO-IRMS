#!/usr/bin/env bash

set -Eeuo pipefail

required_variables=(APP_KEY)

for variable in "${required_variables[@]}"; do
    if [[ -z "${!variable:-}" ]]; then
        echo "Required environment variable ${variable} is not set." >&2
        exit 1
    fi
done

if [[ "${DB_CONNECTION:-}" == "sqlite" ]]; then
    if [[ -z "${DB_DATABASE:-}" ]]; then
        export DB_DATABASE="/var/www/html/database/database.sqlite"
    fi

    if [[ "${DB_DATABASE}" != /* ]]; then
        export DB_DATABASE="/var/www/html/${DB_DATABASE}"
    fi

    mkdir -p "$(dirname "${DB_DATABASE}")"
fi

export PORT="${PORT:-10000}"

if [[ -z "${APP_URL:-}" && -n "${RENDER_EXTERNAL_HOSTNAME:-}" ]]; then
    export APP_URL="https://${RENDER_EXTERNAL_HOSTNAME}"
fi

if [[ -z "${ASSET_URL:-}" && -n "${APP_URL:-}" ]]; then
    export ASSET_URL="${APP_URL}"
fi

envsubst '${PORT}' \
    < /etc/nginx/templates/rgmo-irms.conf.template \
    > /etc/nginx/conf.d/default.conf

echo "Clearing stale Laravel config..."
php artisan config:clear --no-interaction || true

echo "Skipping database migrations during startup because the SQLite database is preloaded."

echo "Building Laravel production caches..."
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

echo "Starting PHP-FPM and Nginx on port ${PORT}..."
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
