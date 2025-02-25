# `time eatmydata docker buildx build --progress=plain --tag liguana/grav .`
#
# ---- base image ----
FROM dunglas/frankenphp:php8.4-alpine AS base

# must utilities
RUN set -ex && \
    echo "**** upgrading base ****" && \
    apk update --verbose && \
    apk upgrade --verbose && \
    apk add --no-cache --verbose \
        bash \
        ca-certificates \
        coreutils \
        curl \
        git \
        tini \
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
    echo 'opcache.jit=tracing'; \
    echo 'opcache.jit_buffer_size=128M'; \
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

# opcache gui
COPY opcache.php /app

#
# ---- grav ----
FROM base AS release

# install grav
RUN set -ex && \
    mkdir -p /config && \
    curl -o /tmp/grav.zip -SL https://getgrav.org/download/core/grav-admin/1.8.0-beta.4?testing && \
    unzip -q /tmp/grav.zip -d /tmp/grav && \
    mv /tmp/grav/grav-admin/* /app/ && \
    rm -fr /tmp/* && \
    (crontab -l; echo "* * * * * cd /app;/usr/local/bin/php bin/grav scheduler 1>> /dev/null 2>&1") | crontab -

# configure webserver
COPY Caddyfile /etc/caddy/Caddyfile
COPY run.sh /app
RUN set -ex && \
    chmod +x /app/run.sh && \
    frankenphp fmt --overwrite /etc/caddy/Caddyfile && \
    setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp

VOLUME ["/config"]

EXPOSE 9000

CMD ["/app/run.sh"]
