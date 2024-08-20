FROM php:8.3-fpm-bookworm

ARG UID
ARG GID

ENV UID=${UID}
ENV GID=${GID}

USER root

# Create user/group
RUN addgroup --gid ${GID} --system invoiceshelf && \
    adduser --gid ${GID} --system --disabled-password --shell /bin/sh -u ${UID} --home /home/invoiceshelf invoiceshelf && \
    sed -i "s/user = www-data/user = invoiceshelf/g" /usr/local/etc/php-fpm.d/www.conf && \
    sed -i "s/group = www-data/group = invoiceshelf/g" /usr/local/etc/php-fpm.d/www.conf

# Install composer & npm
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    curl -sL https://deb.nodesource.com/setup_20.x | bash - && \
    apt install -y nodejs

# install dependencies
RUN apt update && apt install -y \
    libpng-dev \
    zlib1g-dev \
    libxml2-dev \
    libzip-dev \
    libonig-dev \
    libpq-dev \
    sqlite3 \
    postgresql-client \
    mariadb-client \
    zip \
    curl \
    unzip \
    webp \
    && docker-php-ext-configure gd \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install bcmath \
    && docker-php-ext-install mbstring \
    && docker-php-ext-install mysqli \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pgsql \
    && docker-php-ext-install pdo_pgsql \
    && docker-php-ext-install zip \
    && docker-php-ext-install xml \
    && docker-php-ext-install exif \
    && docker-php-source delete

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Set workdir
WORKDIR /home/invoiceshelf/app

# Copy Files
COPY entrypoint.sh /entrypoint.sh

# Entrypoint
ENTRYPOINT ["/entrypoint.sh"]

# Launch php-fpm
CMD ["php-fpm"]
