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

# Railway may expose additional Apache MPM modules at runtime. Keep the
# mod_php-compatible prefork MPM as the only enabled MPM before Apache starts.
find /etc/apache2/mods-enabled -maxdepth 1 -name 'mpm_*.load' ! -name 'mpm_prefork.load' -delete
find /etc/apache2/mods-enabled -maxdepth 1 -name 'mpm_*.conf' ! -name 'mpm_prefork.conf' -delete
a2enmod mpm_prefork

mpm_count="$(apache2ctl -M 2>&1 | grep -Ec 'mpm_[[:alnum:]_]+_module' || true)"
if [ "$mpm_count" -ne 1 ]; then
    echo "Expected exactly one Apache MPM module; found ${mpm_count}." >&2
    exit 1
fi

sed -i "s/Listen 80/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

su -s /bin/sh www-data -c 'cd /var/www/html && php artisan config:cache'
echo "Running database migrations..."
su -s /bin/sh www-data -c 'cd /var/www/html && php artisan migrate --force'
echo "Seeding baseline roles and permissions..."
su -s /bin/sh www-data -c 'cd /var/www/html && php artisan db:seed --force'
exec apache2-foreground
