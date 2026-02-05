FROM --platform=$BUILDPLATFORM node AS static_builder
    WORKDIR /var/www/html
    COPY . /var/www/html
    RUN yarn && yarn build

FROM serversideup/php:8.4-fpm-alpine AS base
    USER root
    RUN apk add --no-cache sqlite
    RUN install-php-extensions exif
    RUN install-php-extensions pgsql
    RUN install-php-extensions sqlite3
    RUN install-php-extensions imagick
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

