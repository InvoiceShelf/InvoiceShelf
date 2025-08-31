FROM --platform=$BUILDPLATFORM node AS static_builder
    WORKDIR /var/www/html
    COPY . /var/www/html
    RUN yarn && yarn build

FROM nginx AS production
    ENV PHP_FPM_HOST="php-fpm:9000"
    COPY --chown=www-data:www-data . /var/www/html
    COPY --from=static_builder --chown=www-data:www-data /var/www/html/public /var/www/html/public
    # Map the PHP-FPM host from the PHP_FPM_HOST environment variable to an nging variable
    RUN mkdir /etc/nginx/templates && cat <<EOF > /etc/nginx/templates/20-invoiceshelf.conf.template
server {
    listen 80 default_server;
    listen [::]:80 default_server;

    root /var/www/html/public;

# Set allowed "index" files
    index index.html index.htm index.php;

    server_name _;

    charset utf-8;

# Set max upload to 2048M
    client_max_body_size 2048M;

# Healthchecks: Set /healthcheck to be the healthcheck URL
    location /healthcheck {
        access_log off;

        # set max 5 seconds for healthcheck
        fastcgi_read_timeout 5s;

        include        fastcgi_params;
        fastcgi_param  SCRIPT_NAME     /healthcheck;
        fastcgi_param  SCRIPT_FILENAME /healthcheck;
        fastcgi_pass   \${PHP_FPM_HOST};
    }

# Have NGINX try searching for PHP files as well
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

# Pass "*.php" files to PHP-FPM
    location ~ \.php\$ {
        fastcgi_pass   \${PHP_FPM_HOST};
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME  \$document_root\$fastcgi_script_name;
        include        fastcgi_params;
        fastcgi_buffers 8 8k;
        fastcgi_buffer_size 8k;
    }
}
EOF
