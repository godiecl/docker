# `time eatmydata docker buildx build --progress=plain --tag disc/wordpress .`
#
# ---- base image ----
FROM dunglas/frankenphp:php8.2-alpine AS base

# must utilities
RUN set -ex && \
    echo "**** upgrading base ****" && \
    apk update --verbose && \
    apk upgrade --verbose && \
    apk add --no-cache --verbose \
        7zip \
        bash \
        btop \
        ca-certificates \
        coreutils \
        curl \
        git \
        htop \
        libcap \
        nss-tools \
        sqlite \
        tini \
        tree \
        tzdata \
    && \
    cp /usr/share/zoneinfo/America/Santiago /etc/localtime && \
    echo 'alias ll="ls --color=auto -alF"' >> /root/.profile && \
    echo 'alias ls="ls --color=auto -aF"' >> /root/.profile && \
    install-php-extensions intl opcache gd zip && \
    cp $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini && \
    { \
    echo 'opcache.enable=1'; \
    echo 'opcache.enable_cli=1'; \
    echo 'opcache.enable_file_override=1'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.jit_buffer_size=256M'; \
    echo 'opcache.max_accelerated_files=4000'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.optimization_level=0x7FFFBFFF'; \
    echo 'opcache.revalidate_freq=5'; \
    echo 'opcache.save_comments=1'; \
    echo 'expose_php=off'; \
    echo 'opcache.validate_timestamps=0'; \
    } > $PHP_INI_DIR/conf.d/opcache-recommended.ini && \
    { \
    echo 'error_reporting = E_ERROR | E_WARNING | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING | E_RECOVERABLE_ERROR'; \
    echo 'display_errors = Off'; \
    echo 'display_startup_errors = Off'; \
    echo 'log_errors = On'; \
    echo 'error_log = /dev/stderr'; \
    echo 'log_errors_max_len = 1024'; \
    echo 'ignore_repeated_errors = On'; \
    echo 'ignore_repeated_source = Off'; \
    echo 'html_errors = Off'; \
    } > $PHP_INI_DIR/conf.d/error-logging.ini && \
    rm -fr /var/cache/apk/*

# set timezone
ENV TZ=America/Santiago
ENV ENV=/root/.profile

# set working directory
WORKDIR /app

#
# ---- build vite ----
FROM node:22.13-alpine AS npm_build

# set working directory
WORKDIR /app

# copy only necessary files for dependencies installation
COPY package*.json ./

# install dependencies
RUN set -ex && \
    npm install -g npm@11.0.0 && \
    npm ci

# copy the whole project
COPY . .

# run the build
RUN set -ex && \
    npm run build

#
# ---- install composer vendor ----
#   FROM base AS build
FROM base AS php_build

# copy only necessary files for dependencies installation
COPY composer.json composer.lock ./

# install dependencies
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN set -ex && \
    composer install --no-dev --prefer-dist --verbose --no-scripts --no-autoloader

# copy the whole project
COPY . .

# run the build
RUN set -ex && \
    composer dump-autoload --optimize --verbose && \
    tree --du -h -I 'node_modules|vendor'

#
# ---- release ----
FROM base AS release

COPY --chown=www-data:www-data --from=npm_build /app/public/build ./public/build
COPY --chown=www-data:www-data --from=php_build /app/vendor ./vendor
COPY --chown=www-data:www-data . .
COPY Caddyfile /etc/caddy/Caddyfile

# configure php
RUN set -ex && \
    mv .env.production .env && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    php artisan optimize && \
    php artisan event:clear && \
    php artisan storage:link && \
    php artisan about && \
    frankenphp fmt --overwrite /etc/caddy/Caddyfile && \
    setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp

EXPOSE 9000

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
