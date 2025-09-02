FROM --platform=$BUILDPLATFORM node:20 AS static_builder
    WORKDIR /var/www/html
    COPY . /var/www/html
    RUN yarn && yarn build

FROM serversideup/php:8.3-fpm-nginx-alpine AS base
    USER root
    RUN apk add --no-cache bash nano
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

FROM base AS production
    ENV AUTORUN_ENABLED=true
    ENV PHP_OPCACHE_ENABLE=1

    # Set `www-data` as the user to start FPM
    USER root
    RUN echo "" >> /usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf && \
        echo "user = www-data" >> /usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf && \
        echo "group = www-data" >> /usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf

    # Revert back to www-data, non-root user
    USER www-data

    # Copy application files
    COPY --from=static_builder --chown=www-data:www-data /var/www/html/public /var/www/html/public
    COPY --chown=www-data:www-data . /var/www/html
    RUN composer install --prefer-dist --no-dev --optimize-autoloader

    # Copy entrypoint and inject script, and make sure they are executable
    COPY --chmod=755 docker/production/inject.sh /inject.sh
    COPY --chmod=755 docker/production/entrypoint.d/ /etc/entrypoint.d/
