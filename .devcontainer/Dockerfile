FROM mcr.microsoft.com/devcontainers/php:dev-8.3-apache-bookworm

# update package list and install dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    sqlite3 \
    xz-utils \
    && rm -rf /var/lib/apt/lists/*

# install PHP extensions using docker-php-ext-install
RUN docker-php-ext-install \
    exif \
    zip \
    bcmath

# Disable Xdebug by default
RUN echo 'export XDEBUG_MODE=off' >> /home/vscode/.bashrc
