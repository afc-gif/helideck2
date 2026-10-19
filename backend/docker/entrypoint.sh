#!/bin/sh
set -eu

port="${PORT:-8080}"

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY must be set in the deployment environment." >&2
    exit 1
fi
if [ "${APP_ENV:-production}" != "production" ] || [ "${APP_DEBUG:-false}" != "false" ]; then
    echo "Production deployment requires APP_ENV=production and APP_DEBUG=false." >&2
    exit 1
fi
if [ "${DB_CONNECTION:-pgsql}" != "pgsql" ]; then
    echo "Production DB_CONNECTION must be pgsql; SQLite fallback is disabled." >&2
    exit 1
fi

# Railway pre-deploy commands override CMD and arrive here as arguments.
if [ "$#" -gt 0 ]; then
    exec "$@"
fi

sed -i "s/Listen 80/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

su -s /bin/sh www-data -c 'cd /var/www/html && php artisan config:cache'
exec apache2-foreground
