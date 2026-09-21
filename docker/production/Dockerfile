# syntax=docker/dockerfile:1
FROM --platform=$BUILDPLATFORM node:24 AS static_builder
    WORKDIR /var/www/html
    COPY . /var/www/html
    # corepack activates the pnpm version pinned in package.json's packageManager field.
    RUN corepack enable && pnpm install --frozen-lockfile && pnpm build

FROM serversideup/php:8.4-fpm-nginx-alpine AS base
    USER root
    RUN apk add --no-cache bash nano mariadb-client postgresql-client sqlite
    # One invocation, not twelve: the tool sets up and tears down its build
    # environment per call, and several of these compile from source.
    RUN install-php-extensions \
        bcmath \
        curl \
        exif \
        gd \
        imagick \
        intl \
        mbstring \
        pgsql \
        redis \
        sqlite3 \
        xml \
        zip

    # Copy entrypoint and inject script, and make sure they are executable
    COPY --chmod=755 docker/production/inject.sh /inject.sh
    COPY --chmod=755 docker/production/entrypoint.d/ /etc/entrypoint.d/

    # Supervise Laravel's scheduler next to nginx and php-fpm. The base image
    # starts neither a cron daemon nor a scheduler, so without this service
    # nothing in routes/console.php ever runs: no recurring invoices, no
    # overdue flagging, no estimate expiry. Set SCHEDULER_ENABLED=false to run
    # the schedule from somewhere else instead.
    COPY --chmod=755 docker/production/s6-overlay/ /etc/s6-overlay/

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

    # Copy application files first, then overlay the freshly-built frontend assets
    # LAST. public/build is gitignored, so it's absent in CI but may exist in a local
    # working tree — copying it after `COPY .` guarantees the stale host build can
    # never clobber the fresh one produced by static_builder.
    COPY --chown=www-data:www-data . /var/www/html
    COPY --from=static_builder --chown=www-data:www-data /var/www/html/public/build /var/www/html/public/build
    # Authenticate composer against GitHub when a token is provided (build secret),
    # so multi-arch builds don't exhaust the unauthenticated API rate limit while the
    # merge-plugin resolves modules. The secret is build-time only (never in the image),
    # and the step still works without it for local builds.
    RUN --mount=type=secret,id=composer_auth,mode=0444 \
        COMPOSER_AUTH="$(cat /run/secrets/composer_auth 2>/dev/null || true)" \
        composer install --prefer-dist --no-dev --optimize-autoloader
