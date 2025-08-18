#!/bin/bash

# Set Apache DocumentRoot to the workspace public directory
sudo chmod a+x "$(pwd)/public"
sudo rm -rf /var/www/html
sudo ln -s "$(pwd)/public" /var/www/html

# rewrite Apache configuration, PHP error reporting level, and restart Apache
sudo a2enmod rewrite
echo 'error_reporting=E_ALL' | sudo tee /usr/local/etc/php/conf.d/no-warn.ini
service apache2 restart

# Set up the database, run migrations, and seed it
cp "$(pwd)/.env.testing" "$(pwd)/.env"
php artisan key:generate --force
cp "$(pwd)/database/stubs/sqlite.empty.db" "$(pwd)/database/database.sqlite"
rm -f "$(pwd)/storage/app/database_created"
# php artisan migrate
# php artisan db:seed

# Install Composer and Yarn dependencies
composer install
yarn install

# Storage symlink
php artisan storage:link

# Permissions
# TODO: Find a better way to handle permissions
chmod 775 storage/framework
chmod 775 storage/logs
chmod 775 bootstrap/cache

echo "Devcontainer 'post create command' script completed successfully."
