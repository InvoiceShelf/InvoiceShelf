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
# provide a valid cache path" (config/view.php resolves its compiled path with
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

# Marketplace installs unpack modules into Modules/, which the compose examples
# mount as a named volume. An unwritable directory does not stop the app from
# serving, so warn instead of aborting, with the same fix as for storage/.
if ! mkdir -p Modules 2>/dev/null || ! touch Modules/.writable 2>/dev/null; then
    echo "!!!! Cannot write to /var/www/html/Modules."
    echo "!!!! Installing modules from the marketplace will fail until the mounted"
    echo "!!!! directory belongs to uid 82 (www-data):"
    echo "!!!!"
    echo "!!!!     sudo chown -R 82:82 /path/to/your/Modules"
    echo "!!!!"
else
    rm -f Modules/.writable
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

# Only root may change ownership. The image normally runs as www-data, where
# this is both impossible and unnecessary — the files it created are already
# owned correctly — so it is skipped rather than failing the boot on a chown we
# are not permitted to make. It still helps anyone running the image as root.
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache
fi

if ! grep -q "APP_KEY" /var/www/html/.env
then
    echo "**** Creating empty APP_KEY variable ****"
    echo "$(printf "APP_KEY=\n"; cat /var/www/html/.env)" > /var/www/html/.env
fi
if ! grep -q '^APP_KEY=[^[:space:]]' /var/www/html/.env; then
    echo "**** Generating new APP_KEY variable ****"
    ./artisan key:generate -n
fi

echo "**** Clearing cached config ****"
./artisan config:clear 2>/dev/null || true
./artisan cache:clear 2>/dev/null || true

echo "**** Creating storage link ****"
./artisan storage:link --force 2>/dev/null || true

# The OAuth server (used by the MCP server) signs tokens with a key pair kept
# in storage/. Create it once, so it exists before anyone switches the server
# on; PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY take precedence when set.
echo "**** Ensuring OAuth signing keys ****"
./artisan oauth:keys --if-missing 2>/dev/null || true
if [ "$(id -u)" = "0" ]; then
    chown www-data:www-data storage/oauth-*.key 2>/dev/null || true
fi

echo "**** Running migrations (if app is installed) ****"
if ./artisan migrate:status > /dev/null 2>&1; then
    ./artisan migrate --force
fi
