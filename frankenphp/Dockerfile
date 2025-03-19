# syntax=docker/dockerfile:1
#
# time eatmydata docker buildx build --build-arg BUILD_DATE=$(git show -s --format=%ct) --build-arg VCS_REF=$(git rev-parse --short HEAD) --progress=plain --tag 4h/frankenphp:8.4.5 .
#
ARG PHP_VERSION=8.4.5
ARG ALPINE_VERSION=3.21

# ---- Caddy ----
FROM php:${PHP_VERSION}-zts-alpine${ALPINE_VERSION} AS base

# environment
ENV TZ=America/Santiago
ENV ENV=/root/.profile

# directory
WORKDIR /app

# install base packages
RUN set -ex; \
    apk -U upgrade --verbose && \
    apk add --no-cache --verbose \
        ca-certificates \
#       coreutils \
        curl \
        libcap \
        mailcap \
        musl \
        tzdata \
    && \
    cp /usr/share/zoneinfo/America/Santiago /etc/localtime && \
    echo 'alias ll="ls --color=auto -alF"' >> /root/.profile && \
    echo 'alias ls="ls --color=auto -aF"' >> /root/.profile && \
    rm -rf /var/cache/apk/*

# https://github.com/mlocati/docker-php-extension-installer
ENV IPE_GD_WITHOUTAVIF=1
ENV IPE_ICU_EN_ONLY=1

# install caddy
RUN set -ex; \
    mkdir -p \
        /config/caddy \
        /data/caddy \
        /etc/caddy; \
    echo '<?php phpinfo();' > /app/index.php && \
    curl -sSLf -o /usr/local/bin/install-php-extensions https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions && \
    chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions gd intl opcache yaml zip && \
    mkdir -p /usr/local/etc/php/conf.d && \
    { \
    echo 'opcache.enable=1'; \
    echo 'opcache.enable_cli=1'; \
    echo 'opcache.enable_file_override=1'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.jit=tracing'; \
    echo 'opcache.jit_buffer_size=128M'; \
    echo 'opcache.max_accelerated_files=4000'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.optimization_level=0x7FFFBFFF'; \
    echo 'opcache.revalidate_freq=5'; \
    echo 'opcache.save_comments=1'; \
    echo 'opcache.validate_timestamps=0'; \
    } > $PHP_INI_DIR/conf.d/opcache.ini && \
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
    { \
#   echo 'extension_dir=/usr/lib/php84/modules'; \
    echo 'upload_max_filesize=256M'; \
    echo 'post_max_size=512M'; \
    echo 'memory_limit=256M'; \
    echo 'expose_php=off'; \
    } > $PHP_INI_DIR/conf.d/defaults.ini && \
    rm -f /usr/local/bin/phpdbg; \
    rm -f /usr/local/bin/php-cgi; \
    rm -fr /var/cache/apk/*

# files
COPY --link Caddyfile /etc/caddy/Caddyfile
COPY --link opcache.php /app

CMD ["--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]
HEALTHCHECK CMD curl -f http://localhost/ping || exit 1

#
# ---- BUILDER ----
FROM dunglas/frankenphp:builder-php${PHP_VERSION}-alpine AS builder

# Copy xcaddy in the builder image
COPY --from=caddy:builder /usr/bin/xcaddy /usr/bin/xcaddy

# build FrankenPHP
RUN set -ex; \
    apk add upx && \
    CGO_ENABLED=1 \
    XCADDY_SETCAP=1 \
    XCADDY_GO_BUILD_FLAGS="-tags='nobadger,nomysql,nopgx' -ldflags='-w -s -extldflags \"-Wl,-z,stack-size=0x80000\" -X \"github.com/caddyserver/caddy/v2.CustomVersion=FrankenPHP PHP $PHP_VERSION Caddy\"'" \
    CGO_CFLAGS=$(php-config --includes) \
    CGO_LDFLAGS="$(php-config --ldflags) $(php-config --libs)" \
    xcaddy build \
        --output /usr/local/bin/frankenphp \
        --with github.com/dunglas/frankenphp=./ \
        --with github.com/dunglas/frankenphp/caddy=./caddy/ && \
    upx -v --ultra-brute /usr/local/bin/frankenphp

#
# ---- FINAL ----
FROM base AS final

ENV GODEBUG=cgocheck=0

COPY --from=builder /usr/local/lib/libwatcher* /usr/local/lib/
COPY --from=builder /usr/local/bin/frankenphp /usr/local/bin/frankenphp
COPY --link --from=builder /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

RUN set -ex; \
    sed -i 's/php/frankenphp run/g' /usr/local/bin/docker-php-entrypoint; \
    apk add --no-cache libstdc++ && \
    ldconfig /usr/local/lib; \
    frankenphp version; \
    frankenphp fmt --overwrite /etc/caddy/Caddyfile; \
    setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp

EXPOSE 80 443

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
