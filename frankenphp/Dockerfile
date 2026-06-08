# syntax=docker/dockerfile:1
# build with:
#   time eatmydata docker buildx build --progress=plain --tag liguana/frankenphp:8.4.22 .
# run with:
#   docker run --rm -it -p 8000:8000 -v /tmp:/tmp --name frankenphp liguana/frankenphp
#
# https://hub.docker.com/r/dunglas/frankenphp/tags?name=php
ARG PHP_VERSION=8.4.22

# ---- builder stage: install, configure, and strip ---------------------------------
FROM dunglas/frankenphp:php${PHP_VERSION}-alpine AS builder

# the labels
LABEL org.opencontainers.image.title="FrankenPHP"
LABEL org.opencontainers.image.version="${PHP_VERSION}"
LABEL org.opencontainers.image.authors="LiGUANA"

# set container timezone
ENV TZ=America/Santiago
ENV ENV=/etc/profile

# disable cgo checks
ENV GODEBUG=cgocheck=0

# set working directory
WORKDIR /app

# install alpine packages and php extensions
RUN set -ex; \
    apk add --no-cache --verbose \
        ca-certificates \
        coreutils \
        curl \
        mailcap \
        ncdu \
        tree \
        tzdata \
        unzip \
    && \
    cp /usr/share/zoneinfo/America/Santiago /etc/localtime && \
    install -d -m 0755 /etc/profile.d && \
    printf '%s\n' \
      'alias ll="ls --color=auto -alF"' \
      'alias ls="ls --color=auto -aF"' \
      > /etc/profile.d/10-aliases.sh && \
    chmod 0644 /etc/profile.d/10-aliases.sh && \
    [ -f /etc/profile.d/color_prompt.sh.disabled ] && \
        ln -s /etc/profile.d/color_prompt.sh.disabled /etc/profile.d/color_prompt.sh || true && \
    install-php-extensions \
        apcu \
        bcmath \
        calendar \
        exif \
        gd \
        intl \
        yaml \
        zip \
    && \
    echo '<?php phpinfo();' > /app/phpinfo.php && \
    echo '<?php echo date("Y-m-d H:i:s");' > /app/index.php && \
    cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    rm -rf /var/cache/apk/* /tmp/*

# write opcache config
RUN set -ex; \
    cat > $PHP_INI_DIR/conf.d/opcache-recommended.ini <<-'EOF'
        opcache.enable=1
        opcache.enable_cli=1
        opcache.enable_file_override=1
        opcache.interned_strings_buffer=16
        opcache.jit=tracing
        opcache.jit_buffer_size=128M
        opcache.max_accelerated_files=20000
        opcache.max_wasted_percentage=10
        opcache.memory_consumption=128
        opcache.optimization_level=0x7FFFBFFF
        opcache.save_comments=1
        opcache.validate_timestamps=0
EOF

# write logging config
RUN set -ex; \
    cat > $PHP_INI_DIR/conf.d/error-logging.ini <<-'EOF'
        display_errors = Off
        display_startup_errors = Off
        error_log = /dev/stderr
        error_reporting = E_ERROR | E_WARNING | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING | E_RECOVERABLE_ERROR
        html_errors = Off
        ignore_repeated_errors = On
        ignore_repeated_source = Off
        log_errors = On
        log_errors_max_len = 4096
EOF

# write default config
RUN set -ex; \
    cat > $PHP_INI_DIR/conf.d/defaults.ini <<-'EOF'
        expose_php=off
        memory_limit=256M
        post_max_size=512M
        upload_max_filesize=256M
EOF

# write frankenphp server config
RUN set -ex; \
    cat > /etc/frankenphp/Caddyfile <<-'CADDYEOF'
{
    skip_install_trust
    auto_https off
    admin off

    frankenphp {
        num_threads 1
        max_threads auto
    }

    log {
        # json
        format console
        # debug info warn error fatal
        level info
    }

    servers {
        # request timeout settings
        timeouts {
            read_body 1m
            read_header 10s
            write 1m
            idle 1m
        }
    }
}

# Main server block
:8000 {
    root * /app/

    # health check endpoint
    respond /ping 204

    # static files
    @static {
        file
        path *.ico *.css *.js *.gif *.jpg *.jpeg *.png *.avif *.webp *.svg *.woff *.woff2 *.ttf *.eot *.pdf *.txt *.html
    }
    handle @static {
        header Cache-Control "public, max-age=86400"
        file_server
    }

    encode zstd br gzip

    # deny all direct access for these folders
    @forbidden_dirs {
        path_regexp forbidden_dirs (?i)^/(?:\.git|cache|bin|logs|backups|tests)(?:/|$)
    }
    respond @forbidden_dirs "Forbidden: dirs" 403

    # deny access to dotfiles
    @dotfiles {
        file
        path_regexp dotfiles ^/(?:[^/]+/)*\.[^/]+$
    }
    respond @dotfiles "Forbidden: dot" 403

    # deny access to sensitive files
    @sensitive_files {
        file
        path_regexp sensitive_files (?i)^/.*(?:LICENSE\.txt|composer\.json|composer\.lock|nginx\.conf|web\.config|htaccess\.txt|\.htaccess|\.env)$
    }
    respond @sensitive_files "Forbidden: files" 403

    # deny access to specific file types in core system and vendor folders
    @blocked_core_scripts {
        file
        path_regexp blocked_core_scripts (?i)^/(?:system|vendor)/(?:.*/)?[^/]+\.(?:txt|xml|md|yaml|yml|html|htm|shtml|shtm|php(?:[2-5])?|phar|phtml|pl|py|cgi|twig|sh|bat)$
    }
    respond @blocked_core_scripts "Forbidden: core" 403

    # deny access to specific file types in user folder
    @blocked_user_scripts {
        file
        path_regexp blocked_user_scripts (?i)^/user/(?:.*/)?[^/]+\.(?:txt|md|yaml|yml|php(?:[2-5])?|phar|phtml|pl|py|cgi|twig|sh|bat)$
    }
    respond @blocked_user_scripts "Forbidden: user" 403

    # protection for opcache.php
    basic_auth /opcache.php {
        admin $2a$14$MDoST6iwAvmMnULwQjrwnuymGqtDJnHUKO3/GPeBY7BSqzC6gKYLK
    }

    # protection for phpinfo.php
    basic_auth /phpinfo.php {
        admin $2a$14$DLmcKyxU2rdNWCQBFmHncuCOu/Si7h3DUmqM.bWBGN8WyZE5rpDu2
    }

    header {
        -Server
        +X-Build-By "Laboratorio de Inteligencia Computacional Aplicada (LiGUANA): https://liguana.disc.cl"
        +X-Powered-By "Departamento de Ingenieria de Sistemas y Computacion, UCN, Antofagasta"
    }

    php_server

    log {
        output stdout
        format json
    }

    handle_errors {
        @5xx expression {http.error.status_code} >= 500
        handle @5xx {
            respond `{http.error.status_code} Server Error` 500
        }
        handle {
            respond `{http.error.status_code} {http.error.status_text}`
        }
    }
}
CADDYEOF

# cleanup
RUN set -ex; \
    rm -f /usr/local/bin/phpdbg; \
    rm -f /usr/local/bin/php-cgi; \
    rm -fr /usr/local/lib/php/PEAR; \
    rm -fr /usr/local/lib/php/build; \
    rm -fr /usr/local/lib/php/test; \
    rm -fr /usr/local/lib/php/.registry; \
    rm -fr /usr/local/lib/php/doc; \
    rm -fr /usr/local/lib/php/.channels; \
    rm -fr /usr/local/include/php; \
    rm -fr /usr/src/php.tar.xz*; \
    rm -fr /var/cache/apk/* /tmp/*

# validate
RUN set -ex; \
    mkdir -p /tmp/caddy/config /tmp/caddy/data /tmp/caddy/locks && \
    chown -R www-data:www-data /tmp/caddy && \
    chown -R www-data:www-data /app && \
    frankenphp fmt --overwrite /etc/frankenphp/Caddyfile && \
    frankenphp version && \
    frankenphp build-info

# copy the opcache.php to /app
COPY --chown=www-data:www-data opcache.php /app/opcache.php

# ---- release stage: only the stripped filesystem ---------------------------------
FROM scratch
ARG PHP_VERSION

COPY --from=builder / /

# the labels
LABEL org.opencontainers.image.title="FrankenPHP"
LABEL org.opencontainers.image.version="${PHP_VERSION}"
LABEL org.opencontainers.image.authors="LiGUANA"

# set container timezone
ENV TZ=America/Santiago
ENV ENV=/etc/profile

# disable cgo checks
ENV GODEBUG=cgocheck=0

# set working directory
WORKDIR /app

# Caddy storage paths for unprivileged user
ENV XDG_CONFIG_HOME=/tmp/caddy/config
ENV XDG_DATA_HOME=/tmp/caddy/data

# copy the opcache.php to /app for testing
COPY --chown=www-data:www-data opcache.php /app/opcache.php

# add healthcheck
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 CMD curl -fS http://localhost:8000/ping || exit 1

# run unprivileged
USER www-data:www-data

# serve
EXPOSE 8000
ENTRYPOINT ["frankenphp"]
CMD ["run", "--config", "/etc/frankenphp/Caddyfile"]
