# syntax=docker/dockerfile:1
FROM --platform=$BUILDPLATFORM node:24 AS static_builder
    WORKDIR /var/www/html
    COPY . /var/www/html
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

FROM base AS production
    ENV AUTORUN_ENABLED=true
    ENV PHP_OPCACHE_ENABLE=1
    # Turns off the in-app updater: the image is upgraded by pulling a new one.
    # inject.sh also writes it to .env; this covers INVOICESHELF_DOTENV=false.
    ENV CONTAINERIZED=true

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
    # Nothing under Modules/ is tracked, so the copy above does not create the
    # directory. The compose examples mount a named volume there, and Docker seeds
    # a fresh volume from the image path, ownership included: with no directory in
    # the image the mount point is created root-owned and every marketplace install
    # dies with "mkdir(): Permission denied". Create it here, as www-data.
    RUN mkdir -p /var/www/html/Modules
    # The public disk's link, made here so a read-only root has it too. The
    # entrypoint still repairs it on a writable one.
    RUN ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage
    # Authenticate composer against GitHub when a token is provided (build secret),
    # so multi-arch builds don't exhaust the unauthenticated API rate limit while the
    # merge-plugin resolves modules. The secret is build-time only (never in the image),
    # and the step still works without it for local builds.
    RUN --mount=type=secret,id=composer_auth,mode=0444 \
        COMPOSER_AUTH="$(cat /run/secrets/composer_auth 2>/dev/null || true)" \
        composer install --prefer-dist --no-dev --optimize-autoloader

    # Copy entrypoint and inject script, and make sure they are executable
    COPY --chmod=755 docker/production/inject.sh /inject.sh
    COPY --chmod=755 docker/production/entrypoint.d/ /etc/entrypoint.d/

    # Supervise Laravel's scheduler next to nginx and php-fpm. The base image
    # starts neither a cron daemon nor a scheduler, so without this service
    # nothing in routes/console.php ever runs: no recurring invoices, no
    # overdue flagging, no estimate expiry. Set SCHEDULER_ENABLED=false to run
    # the schedule from somewhere else instead.
    COPY --chmod=755 docker/production/s6-overlay/ /etc/s6-overlay/
