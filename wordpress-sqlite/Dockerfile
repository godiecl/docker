# syntax=docker/dockerfile:1
# build with:
#   time eatmydata docker buildx build --progress=plain --tag registry.liguana.cl/wp-sqlite:7.1 .
# run with:
#   docker run --rm -it -p 8000:8000 -v /tmp:/tmp --name wp-sqlite registry.liguana.cl/wp-sqlite:7.1
#
ARG FRANKENPHP_VERSION=1.12.7-8.4.25
ARG WORDPRESS_VERSION=7.1
ARG SQLITE_PLUGIN_VERSION=3.0.1

# --- wordpress ----------------------------------------------------------------
# Wordpress
FROM wordpress:${WORDPRESS_VERSION} AS wp

# FrankenPHP
FROM registry.liguana.cl/frankenphp:${FRANKENPHP_VERSION} AS release
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

# run unprivileged
USER www-data:www-data

# serve
EXPOSE 8000
ENTRYPOINT ["frankenphp"]
CMD ["run", "--config", "/etc/frankenphp/Caddyfile"]
