#!/bin/sh
set -e

# Cache configuration, routes, and views if in production
if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "staging" ]; then
    echo "Warming Laravel production caches..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

# Run safe migrations
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force
fi

echo "Starting services via Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
