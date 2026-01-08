#!/bin/bash
function replace_or_insert() {
    # Voodoo magic: https://superuser.com/a/976712
    grep -q "^${1}=" /var/www/html/.env && sed "s|^${1}=.*|${1}=${2}|" -i /var/www/html/.env || sed "$ a\\${1}=${2}" -i /var/www/html/.env
}

replace_or_insert "CONTAINERIZED" "true"

if [ "$APP_NAME" != '' ]; then
   replace_or_insert "APP_NAME" "$APP_NAME"
fi
if [ "$APP_ENV" != '' ]; then
   replace_or_insert "APP_ENV" "$APP_ENV"
fi
if [ "$APP_KEY" != '' ]; then
   replace_or_insert "APP_KEY" "$APP_KEY"
fi
if [ "$APP_DEBUG" != '' ]; then
   replace_or_insert "APP_DEBUG" "$APP_DEBUG"
fi
if [ "$APP_URL" != '' ]; then
   replace_or_insert "APP_URL" "$APP_URL"
fi
if [ "$ASSET_URL" != '' ]; then
   replace_or_insert "ASSET_URL" "$ASSET_URL"
fi
if [ "$APP_DIR" != '' ]; then
   replace_or_insert "APP_DIR" "$APP_DIR"
fi
if [ "$DB_CONNECTION" != '' ]; then
   replace_or_insert "DB_CONNECTION" "$DB_CONNECTION"
fi
if [ "$DB_HOST" != '' ]; then
   replace_or_insert "DB_HOST" "$DB_HOST"
fi
if [ "$DB_PORT" != '' ]; then
   replace_or_insert "DB_PORT" "$DB_PORT"
fi
if [ "$DB_DATABASE" != '' ]; then
   replace_or_insert "DB_DATABASE" "$DB_DATABASE"
fi
if [ "$DB_USERNAME" != '' ]; then
   replace_or_insert "DB_USERNAME" "$DB_USERNAME"
fi
if [ "$DB_PASSWORD" != '' ]; then
   replace_or_insert "DB_PASSWORD" "$DB_PASSWORD"
elif [ "$DB_PASSWORD_FILE" != '' ]; then
  value=$(<$DB_PASSWORD_FILE)
   replace_or_insert "DB_PASSWORD" "$value"
fi
if [ "$TIMEZONE" != '' ]; then
   replace_or_insert "TIMEZONE" "$TIMEZONE"
fi
if [ "$CACHE_STORE" != '' ]; then
   replace_or_insert "CACHE_STORE" "$CACHE_STORE"
fi
if [ "$CACHE_DRIVER" != '' ]; then
   replace_or_insert "CACHE_STORE" "$CACHE_DRIVER" # deprecated (will be removed later)
fi
if [ "$SESSION_DRIVER" != '' ]; then
   replace_or_insert "SESSION_DRIVER" "$SESSION_DRIVER"
fi
if [ "$SESSION_LIFETIME" != '' ]; then
   replace_or_insert "SESSION_LIFETIME" "$SESSION_LIFETIME"
fi
if [ "$QUEUE_CONNECTION" != '' ]; then
   replace_or_insert "QUEUE_CONNECTION" "$QUEUE_CONNECTION"
fi
if [ "$BROADCAST_CONNECTION" != '' ]; then
   replace_or_insert "BROADCAST_CONNECTION" "$BROADCAST_CONNECTION"
fi
if [ "$TRUSTED_PROXIES" != '' ]; then
   replace_or_insert "TRUSTED_PROXIES" "$TRUSTED_PROXIES"
fi
if [ "$SANCTUM_STATEFUL_DOMAINS" != '' ]; then
   replace_or_insert "SANCTUM_STATEFUL_DOMAINS" "$SANCTUM_STATEFUL_DOMAINS"
fi
if [ "$SESSION_DOMAIN" != '' ]; then
   replace_or_insert "SESSION_DOMAIN" "$SESSION_DOMAIN"
fi

