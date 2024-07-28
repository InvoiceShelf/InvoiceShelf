#!/bin/bash

echo "############################################"
echo "### InvoiceShelf Development Environment ###"
echo "############################################"

cd /home/invoiceshelf/app

chmod 775 storage/framework
chmod 775 storage/logs
chmod 775 bootstrap/cache

chown -R ${UID}:${GID} /home/invoiceshelf/app

chmod +x artisan

if [ ! -d vendor ]; then
    composer install
fi

if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --force
fi

if [ ! -d node_modules ]; then
    npm install
    npm run build
fi

php artisan storage:link

exec $@
