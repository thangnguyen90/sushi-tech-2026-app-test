#!/usr/bin/env sh
set -e

cd /var/www/html

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ "${APP_ENV:-production}" != "local" ]; then
    php artisan config:cache --no-ansi
    php artisan view:cache --no-ansi
fi

case "$1" in
    web)
        php-fpm -D
        exec nginx -g "daemon off;"
        ;;
    queue)
        shift
        exec php artisan queue:work --sleep="${QUEUE_SLEEP:-3}" --tries="${QUEUE_TRIES:-3}" --timeout="${QUEUE_TIMEOUT:-90}" "$@"
        ;;
    scheduler)
        shift
        exec php artisan schedule:work "$@"
        ;;
    schedule-run)
        shift
        exec php artisan schedule:run "$@"
        ;;
    migrate)
        shift
        exec php artisan migrate --force "$@"
        ;;
    *)
        exec "$@"
        ;;
esac
