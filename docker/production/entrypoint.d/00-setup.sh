#!/bin/bash

set -e

# Read version information
version=$(head -n 1 /var/www/html/version.md)

echo "
-------------------------------------
InvoiceShelf Version:  $version
-------------------------------------"

cd /var/www/html

# These carry no tracked content — only .gitignore stubs — so a mount over
# storage/ can arrive without them, and Laravel then dies at boot with "Please
# provide a valid cache path" (the compiled view path is resolved with
# realpath(), which returns false for a missing directory). Recreate them before
# anything writes there, including the sqlite database placed in storage/app
# below. See InvoiceShelf/docker#75, #69 and #77.
echo "**** Ensuring storage directories exist ****"
if ! mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache 2>/dev/null; then
    echo "!!!! Cannot write to /var/www/html/storage."
    echo "!!!! This container runs as uid $(id -u) (www-data), but the mounted"
    echo "!!!! directory belongs to someone else — usually a bind mount pointing"
    echo "!!!! at a host directory owned by your own user."
    echo "!!!! Give that directory to uid 82 on the host and start again:"
    echo "!!!!"
    echo "!!!!     sudo chown -R 82:82 /path/to/your/storage"
    echo "!!!!"
    echo "!!!! See https://github.com/InvoiceShelf/docker/issues/77"
    exit 1
fi

if [ ! -e /var/www/html/.env ]; then
    cp .env.example .env
    echo "**** Setup initial .env values ****" && \
    	/inject.sh
fi

if [ "$DB_CONNECTION" = "sqlite" ] || [ -z "$DB_CONNECTION" ]; then
    echo "**** Configure SQLite3 database ****"
    if [ ! -n "$DB_DATABASE" ]; then
        echo "**** DB_DATABASE not defined. Fall back to default /storage/app/database.sqlite location ****"
        DB_DATABASE='/var/www/html/storage/app/database.sqlite'
    fi

    if [ ! -e "$DB_DATABASE" ]; then
        echo "**** Specified sqlite database doesn't exist. Creating it ****"
        echo "**** Please make sure your database is on a persistent volume ****"
        cp /var/www/html/database/stubs/sqlite.empty.db "$DB_DATABASE"
    fi
    chown www-data:www-data "$DB_DATABASE"
fi

echo "**** Setting up folder permissions ****"
chmod +x artisan

# Only root may change ownership. The image runs as www-data, where chown of a
# foreign-owned file is EPERM — and under `set -e` that stopped the container
# from starting at all, on exactly the mounted-volume setups this was meant to
# help. Skip it there rather than fail the boot; still applied when running as
# root. See InvoiceShelf/docker#77 and #69.
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache
    chmod -R 775 storage bootstrap/cache
fi

if [ ! -L /var/www/html/public/storage ]; then
    echo "**** Creating storage symlink (public/storage) ****"
    ./artisan storage:link --force -n || true
fi

# .env is rebuilt from .env.example whenever the container is recreated, and
# .env.example used to carry a key. Every install without an APP_KEY of its
# own therefore ran on the same public key. A key is now generated once and
# kept on the storage volume, so it survives recreating the container.
SHIPPED_APP_KEY='base64:kgk/4DW1vEVy7aEvet5FPp5un6PIGe/so8H0mvoUtW0='
APP_KEY_FILE=/var/www/html/storage/app/.app_key

if [ "$APP_KEY" = "$SHIPPED_APP_KEY" ]; then
    echo "!!!! APP_KEY in this container's environment is the key InvoiceShelf"
    echo "!!!! used to ship in .env.example, and that key is public. Remove APP_KEY"
    echo "!!!! from the environment (a key is then generated and kept in storage),"
    echo "!!!! or set one of your own, which you can make with:"
    echo "!!!!"
    echo "!!!!     echo \"base64:\$(openssl rand -base64 32)\""
    echo "!!!!"
elif [ -z "$APP_KEY" ]; then
    if ! grep -q "^APP_KEY=" /var/www/html/.env; then
        echo "$(printf "APP_KEY=\n"; cat /var/www/html/.env)" > /var/www/html/.env
    fi

    current_key=$(sed -n 's/^APP_KEY=//p' /var/www/html/.env | head -n 1 | tr -d "\"'")

    if [ -n "$current_key" ] && [ "$current_key" != "$SHIPPED_APP_KEY" ]; then
        # A key of this install's own, in a .env that survived; keep a copy.
        if [ ! -s "$APP_KEY_FILE" ]; then
            (umask 077 && printf '%s\n' "$current_key" > "$APP_KEY_FILE")
        fi
    elif [ -s "$APP_KEY_FILE" ]; then
        echo "**** Using the APP_KEY kept in storage ****"
        sed -i "s|^APP_KEY=.*|APP_KEY=$(head -n 1 "$APP_KEY_FILE")|" /var/www/html/.env
    else
        echo "**** Generating new APP_KEY and keeping it in storage ****"
        ./artisan key:generate --force -n
        (umask 077 && sed -n 's/^APP_KEY=//p' /var/www/html/.env | head -n 1 > "$APP_KEY_FILE")
    fi
fi
