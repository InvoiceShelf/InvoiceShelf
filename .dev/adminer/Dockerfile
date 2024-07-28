FROM adminer:latest

USER root

RUN set -x && \
    apt update &&  \
    apt install curl -y && \
    cd /var/www/html/plugins-enabled && \
    curl -O https://gist.githubusercontent.com/gdarko/00af6e9a754f09c3f81cd3c606c33311/raw/d5f6a30f00edecf30a5d380340d9dae79a3b7352/login-password-less.php

USER adminer
CMD	[ "php", "-S", "[::]:8080", "-t", "/var/www/html" ]

EXPOSE 8080
