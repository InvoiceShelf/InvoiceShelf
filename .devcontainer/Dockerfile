FROM mcr.microsoft.com/devcontainers/php:1-8.2-bullseye

# Install the xz-utils package
RUN apt-get update && apt-get install -y \
    libzip-dev \
    xz-utils \
    && rm -rf /var/lib/apt/lists/*

# install PHP extensions using docker-php-ext-install
RUN docker-php-ext-install \
    exif \
    zip \
    bcmath

# Disable Xdebug by default
RUN echo 'export XDEBUG_MODE=off' >> /home/vscode/.bashrc