#!/usr/bin/env bash

set -Eeuo pipefail

required_variables=(APP_KEY DB_CONNECTION)

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

    if [[ ! -f "${DB_DATABASE}" && "${APP_ENV:-production}" == "production" && "${ALLOW_EMPTY_SQLITE:-false}" != "true" ]]; then
        echo "Production SQLite database is missing at ${DB_DATABASE}. Mount persistent storage, use PostgreSQL, or explicitly set ALLOW_EMPTY_SQLITE=true for a new installation." >&2
        exit 1
    fi

    touch "${DB_DATABASE}"
    chown www-data:www-data "${DB_DATABASE}"

    if [[ -s "${DB_DATABASE}" ]] && ! sqlite3 "${DB_DATABASE}" "PRAGMA integrity_check;" | grep -q '^ok$'; then
        echo "SQLite database file is malformed at ${DB_DATABASE}" >&2
        exit 1
    fi
fi

export PORT="${PORT:-10000}"

if [[ -n "${RENDER_EXTERNAL_HOSTNAME:-}" ]]; then
    if [[ -z "${APP_URL:-}" || "${APP_URL}" =~ ^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?/?$ ]]; then
        export APP_URL="https://${RENDER_EXTERNAL_HOSTNAME}"
    elif [[ "${APP_URL}" == http://* ]]; then
        export APP_URL="https://${APP_URL#http://}"
    fi

    if [[ -z "${ASSET_URL:-}" || "${ASSET_URL}" =~ ^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?/?$ ]]; then
        export ASSET_URL="${APP_URL}"
    elif [[ "${ASSET_URL}" == http://* ]]; then
        export ASSET_URL="https://${ASSET_URL#http://}"
    fi

    # Render web services are externally served over HTTPS.
    export SESSION_SECURE_COOKIE=true
elif [[ -z "${ASSET_URL:-}" && -n "${APP_URL:-}" ]]; then
    export ASSET_URL="${APP_URL}"
fi

envsubst '${PORT}' \
    < /etc/nginx/templates/rgmo-irms.conf.template \
    > /etc/nginx/conf.d/default.conf

echo "Clearing stale Laravel config..."
php artisan config:clear --no-interaction || true

echo "Applying pending database migrations..."
migration_attempt=1
until php artisan migrate --force --no-interaction; do
    if (( migration_attempt >= 5 )); then
        echo "Database migrations failed after ${migration_attempt} attempts." >&2
        exit 1
    fi

    echo "Database migration attempt ${migration_attempt} failed; retrying..." >&2
    migration_attempt=$((migration_attempt + 1))
    sleep 3
done

if [[ "${BOOTSTRAP_ADMIN:-false}" == "true" ]]; then
    echo "Bootstrapping administrator if the users table is empty..."
    php artisan db:seed --class=BootstrapAdminSeeder --force --no-interaction
fi

echo "Building Laravel production caches..."
php artisan config:cache --no-interaction
php artisan event:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

echo "Starting PHP-FPM and Nginx on port ${PORT}..."
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
