#!/bin/sh
set -e

# 1. Initialize SQLite database file if missing
if [ ! -f /var/www/html/database/database.sqlite ]; then
    echo "Initializing SQLite database file..."
    touch /var/www/html/database/database.sqlite
fi

# 2. Ensure permissions for storage, cache, and database
chown -R www-data:www-data /var/www/html/database /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/database /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# 3. Cache configuration, routes, and views if in production
if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "staging" ]; then
    echo "Warming Laravel production caches..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

# 4. Run safe database migrations if requested
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force --no-interaction
fi

# 5. Execute command (defaults to supervisord if no arguments passed)
if [ $# -gt 0 ]; then
    exec "$@"
else
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
fi
