# syntax=docker/dockerfile:1
# build with:
#   time eatmydata docker buildx build --progress=plain --tag liguana/frankenphp .
# run with:
#   docker run --rm -it -p 9000:9000 -v /tmp:/tmp --name frankenphp liguana/frankenphp

# https://hub.docker.com/r/dunglas/frankenphp/tags?name=php
ARG PHP_VERSION=8.4.11

# ---- release image -----------------------------------------------------------
FROM dunglas/frankenphp:php${PHP_VERSION}-alpine AS release

# set container timezone
ENV TZ=America/Santiago
ENV ENV=/root/.profile

# set working directory
WORKDIR /app

# install alpine packages and php extensions
RUN set -ex; \
    apk -U upgrade --verbose && \
    apk add --no-cache --verbose \
        ca-certificates \
        coreutils \
        curl \
        libcap \
        mailcap \
        musl \
        tree \
        tzdata \
    && \
    cp /usr/share/zoneinfo/America/Santiago /etc/localtime && \
    echo 'alias ll="ls --color=auto -alF"' >> /root/.profile && \
    echo 'alias ls="ls --color=auto -aF"' >> /root/.profile && \
    install-php-extensions \
        gd \
        intl \
        opcache \
        yaml \
        zip \
    && \
    echo '<?php phpinfo();' > /app/index.php && \
    cp $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini && \
    rm -rf /var/cache/apk/* /tmp/*

# write opcache config
RUN set -ex; \
    cat > $PHP_INI_DIR/conf.d/opcache-recommended.ini <<-'EOF'
        opcache.enable=1
        opcache.enable_cli=1
        opcache.enable_file_override=1
        opcache.fast_shutdown=1
        opcache.interned_strings_buffer=8
        opcache.jit=tracing
        opcache.jit_buffer_size=128M
        opcache.max_accelerated_files=4000
        opcache.memory_consumption=128
        opcache.optimization_level=0x7FFFBFFF
        opcache.revalidate_freq=5
        opcache.save_comments=1
        opcache.validate_timestamps=0
EOF

# write logging config
RUN set -ex; \
    cat > $PHP_INI_DIR/conf.d/error-logging.ini <<-'EOF'
        error_reporting = E_ERROR | E_WARNING | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING | E_RECOVERABLE_ERROR
        display_errors = Off
        display_startup_errors = Off
        log_errors = On
        error_log = /dev/stderr
        log_errors_max_len = 1024
        ignore_repeated_errors = On
        ignore_repeated_source = Off
        html_errors = Off
EOF

# write default config
RUN set -ex; \
    cat > $PHP_INI_DIR/conf.d/defaults.ini <<-'EOF'
        upload_max_filesize=256M
        post_max_size=512M
        memory_limit=256M
        expose_php=off
EOF

# write frankenphp server config
RUN set -ex; \
    cat > /etc/frankenphp/Caddyfile <<-'EOF'
{
    auto_https off
    admin off

    # debug

    frankenphp {
        num_threads 1
        max_threads 8
    }

    log {
        format console # json
        level debug # debug info warn error fatal
    }

    metrics /metrics {
        disable_openmetrics
    }
}

# Main server block
:9000 {
    # root directory
    root * /app/

    # health check endpoint
    respond /ping 204

    # static file handling with cache control
    @static {
        file
        path *.ico *.css *.js *.gif *.jpg *.jpeg *.png *.svg *.woff *.woff2 *.ttf *.eot *.pdf *.txt
    }
    handle @static {
        header Cache-Control "public, max-age=86400"  # Cache for 24 hours
        file_server
    }

    # enable compression for better performance
    # encode zstd br gzip

    header {
        -Server
        header X-Build-By "Laboratorio de Inteligencia Computacional Aplicada (LIGUANA)"
        header X-Powered-By "Departamento de Ingenieria de Sistemas y Computacion, UCN, Antofagasta"
    }

    # PHP handling
    php_server

    # access logging with rotation
    log {
        output file /var/log/access.log {
            roll_size 10MiB
            roll_keep 10
            roll_keep_for 168h  # 7 days
        }
        format json
    }

    # custom error handling
    handle_errors {
        @5xx expression {http.error.status_code} >= 500
        handle @5xx {
            respond `{http.error.status_code} Server Error` 500
        }

        handle {
            respond `{http.error.status_code} {http.error.status_text}`
        }
    }

    # request timeout settings
    timeouts {
        read 30s
        write 1m
        idle 2m
    }

}
EOF

# cleanup
RUN set -ex; \
    rm -f /usr/local/bin/phpdbg; \
    rm -f /usr/local/bin/php-cgi; \
    rm -fr /var/cache/apk/* /tmp/*

# configuration
RUN set -ex; \
    tree --du -h -I 'node_modules|vendor' && \
    setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp && \
    frankenphp fmt --overwrite /etc/frankenphp/Caddyfile && \
    frankenphp version && \
    frankenphp build-info

# serve
EXPOSE 80
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
