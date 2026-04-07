#!/bin/bash

set -e

# Read version information
version=$(head -n 1 /var/www/html/version.md)

echo "
-------------------------------------
InvoiceShelf Version:  $version
-------------------------------------"

cd /var/www/html

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

echo "**** Setting up artisan permissions ****"
chmod +x artisan

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

echo "**** Running migrations (if app is installed) ****"
if ./artisan migrate:status > /dev/null 2>&1; then
    ./artisan migrate --force
fi
