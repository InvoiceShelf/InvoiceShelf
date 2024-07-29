#!/bin/bash

echo "############################################"
echo "### InvoiceShelf Development Environment ###"
echo "############################################"

cd /home/invoiceshelf/app


# Composer build
if [ ! -d vendor ]; then
    composer install
fi

# Empty sqlite database
if [ ! -f database/database.sqlite ]; then
    cp database/stubs/sqlite.empty.db database/database.sqlite
fi

# .env file set up
if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --force
fi

# NPM build
if [ ! -d node_modules ]; then
    npm install
    npm run build
fi

# Storage symlink
php artisan storage:link

# Permissions
chmod 775 storage/framework
chmod 775 storage/logs
chmod 775 bootstrap/cache
chown -R ${UID}:${GID} /home/invoiceshelf/app
chmod +x artisan

echo "Entrypoint complete."

exec $@
