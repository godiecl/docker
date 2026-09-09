# syntax=docker/dockerfile:1
# build with:
#   time eatmydata docker buildx build --progress=plain --tag registry.liguana.cl/wp-sqlite:7.1 .
# run with:
#   docker run --rm -it -p 8000:8000 -v /tmp:/tmp --name wp-sqlite registry.liguana.cl/wp-sqlite:7.1
#
ARG FRANKENPHP_VERSION=1.12.7
ARG PHP_VERSION=8.4.25
ARG WORDPRESS_VERSION=7.1
ARG SQLITE_PLUGIN_VERSION=3.0.1

# --- wordpress ----------------------------------------------------------------
# Wordpress
FROM wordpress:${WORDPRESS_VERSION} AS wp

# --- sqlite native parser extension builder -----------------------------------
# The wp_mysql_parser PHP extension (Rust) accelerates the plugin MySQL
# lexer/parser. It must be built against the exact PHP of the frankenphp base
# image; that base is itself built from dunglas/frankenphp:<core>-php<php>-alpine.
# Note: /packages is export-ignore in the repo, so GitHub tarballs omit the
# extension source; clone (git ignores export-ignore) with a sparse checkout.
# Note: Alpine splits libclang (and the compiler resource headers bindgen
# needs) into clangNN-libclang / clangNN-headers; bump LLVM_MAJOR with the base.
FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION}-alpine AS sqlite-parser-ext
ARG SQLITE_PLUGIN_VERSION
ARG LLVM_MAJOR=22

RUN set -eux; \
    apk add --no-cache build-base clang${LLVM_MAJOR}-libclang clang${LLVM_MAJOR}-headers rust cargo git && \
    git clone --depth 1 --branch "v${SQLITE_PLUGIN_VERSION}" --filter=blob:none --sparse \
        https://github.com/WordPress/sqlite-database-integration /tmp/sqlite && \
    git -C /tmp/sqlite sparse-checkout set packages/php-ext-wp-mysql-parser && \
    cd /tmp/sqlite/packages/php-ext-wp-mysql-parser && \
    PHP_CONFIG="$(command -v php-config)" \
    LIBCLANG_PATH="$(dirname "$(find /usr/lib -name 'libclang.so' | head -n1)")" \
    BINDGEN_EXTRA_CLANG_ARGS="-resource-dir=/usr/lib/llvm${LLVM_MAJOR}/lib/clang/${LLVM_MAJOR}" \
    cargo build --release && \
    php -d "extension=$(pwd)/target/release/libwp_mysql_parser.so" -m | grep -q wp_mysql_parser

# --- FrankenPHP ---------------------------------------------------------------
FROM registry.liguana.cl/frankenphp:${FRANKENPHP_VERSION}-${PHP_VERSION} AS release
ARG WORDPRESS_VERSION
ARG SQLITE_PLUGIN_VERSION

# environment variables
ENV WP_DEBUG=false
ENV FORCE_HTTPS=0

COPY --from=wp --chown=www-data:www-data /usr/src/wordpress /app

# run privileged
USER root

VOLUME ["/app/wp-content"]

# Install the pinned sqlite-database-integration plugin release
RUN set -eux; \
    mkdir -p /app/wp-content/cache && \
    curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp && \
    chmod +x /usr/local/bin/wp && \
    curl -fsSL "https://github.com/WordPress/sqlite-database-integration/releases/download/v${SQLITE_PLUGIN_VERSION}/plugin-sqlite-database-integration.zip" -o /tmp/sq.zip && \
    unzip -o /tmp/sq.zip -d /app/wp-content/mu-plugins && \
    mv /app/wp-content/mu-plugins/plugin-sqlite-database-integration /app/wp-content/mu-plugins/sqlite-database-integration && \
    rm -f /tmp/sq.zip && \
    mv /app/wp-content/mu-plugins/sqlite-database-integration/db.copy /app/wp-content/db.php && \
    sed -i "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#/app/wp-content/mu-plugins/sqlite-database-integration#" /app/wp-content/db.php && \
    sed -i "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" /app/wp-content/db.php && \
    chown -R www-data:www-data /app

# enable the native MySQL parser extension (auto-detected by the plugin;
# the pure-PHP lexer/parser remains the fallback)
COPY --from=sqlite-parser-ext /tmp/sqlite/packages/php-ext-wp-mysql-parser/target/release/libwp_mysql_parser.so /usr/local/lib/php/extensions/wp_mysql_parser.so
RUN set -eux; \
    printf '%s\n' 'extension=/usr/local/lib/php/extensions/wp_mysql_parser.so' > "$PHP_INI_DIR/conf.d/wp_mysql_parser.ini"

# run unprivileged
USER www-data:www-data

# serve
EXPOSE 8000
ENTRYPOINT ["frankenphp"]
CMD ["run", "--config", "/etc/frankenphp/Caddyfile"]
