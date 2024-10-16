FROM node AS static_builder
    WORKDIR /var/www/html/InvoiceShelf
    COPY . /var/www/html
    RUN yarn && yarn build

FROM serversideup/php:8-fpm-alpine AS base
    USER root
    RUN install-php-extensions exif
    RUN install-php-extensions pgsql
    RUN install-php-extensions sqlite3
    RUN install-php-extensions imagick/imagick@28f27044e435a2b203e32675e942eb8de620ee58
    RUN install-php-extensions mbstring
    RUN install-php-extensions gd
    RUN install-php-extensions xml
    RUN install-php-extensions zip
    RUN install-php-extensions redis
    RUN install-php-extensions bcmath
    RUN install-php-extensions intl
    RUN install-php-extensions curl

FROM base AS development
    ARG UID
    ARG GID

    USER root
    RUN docker-php-serversideup-set-id www-data $UID:$GID
    USER www-data

FROM base AS production
    ENV AUTORUN_ENABLED=true
    COPY --from=static_builder --chown=www-data:www-data /var/www/html/public /var/www/html/public
    COPY --chown=www-data:www-data . /var/www/html
    RUN composer install --prefer-dist
    USER www-data
