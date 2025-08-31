#!/bin/bash

set -e

# Read version information
version=$(head -n 1 /var/www/html/version.md)

echo "
-------------------------------------
InvoiceShelf Version:  $version
-------------------------------------"

if [ -n "$STARTUP_DELAY" ]
then echo "**** Delaying startup ($STARTUP_DELAY seconds)... ****"
	sleep $STARTUP_DELAY
fi

cd /var/www/html

cp .env.example .env

if [ "$DB_CONNECTION" = "sqlite" ] || [ -z "$DB_CONNECTION" ]; then
    echo "**** Configure SQLite3 database ****"
    if [ ! -n "$DB_DATABASE" ]; then
        echo "**** DB_DATABASE not defined. Fall back to default /database/database.sqlite location ****"
        DB_DATABASE='/var/www/html/database/database.sqlite'
    fi

    if [ ! -e "$DB_DATABASE" ]; then
        echo "**** Specified sqlite database doesn't exist. Creating it ****"
        echo "**** Please make sure your database is on a persistent volume ****"
        sqlite3 "$DB_DATABASE" "VACUUM;"
    fi
    chown www-data:www-data "$DB_DATABASE"
fi

echo "**** Inject .env values ****" && \
	/inject.sh

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
