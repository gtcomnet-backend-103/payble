#!/bin/bash
set -e

role=${1:-serve}

# Run migrations on startup
php artisan migrate --force --no-interaction

# Cache config, routes, views
php artisan config:cache
php artisan route:cache
php artisan view:cache

case "$role" in
    serve)
        echo "Starting Nginx + PHP-FPM..."
        php-fpm -D
        nginx -g "daemon off;"
        ;;
    worker)
        echo "Starting queue worker..."
        php artisan queue:work --sleep=3 --tries=3 --max-time=3600
        ;;
    scheduler)
        echo "Starting scheduler..."
        while true; do
            php artisan schedule:run --no-interaction >> /dev/null 2>&1
            sleep 60
        done
        ;;
    *)
        echo "Unknown role: $role"
        exit 1
        ;;
esac
