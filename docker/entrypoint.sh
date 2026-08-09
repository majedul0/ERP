#!/bin/sh
set -e

cd /var/www/html

# The storage volume is empty the first time it is created and mounts over the
# image's copy, so the directories Laravel expects have to be recreated before
# anything tries to write a cache, a session or a log.
mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs

chown -R www-data:www-data storage bootstrap/cache

# Uploaded logos and product photos are served from public/storage. Recreated
# on every start because the symlink points into the mounted volume.
php artisan storage:link --force >/dev/null 2>&1 || true

# Caches are built here, not in the Dockerfile: config:cache freezes whatever
# environment it can see, and at build time that is the CI runner's, not the
# server's. Horizon and the scheduler set CACHE_ON_BOOT=false — they have their
# own filesystem in their own container and nothing to serve.
if [ "${CACHE_ON_BOOT:-true}" = "true" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

exec "$@"
