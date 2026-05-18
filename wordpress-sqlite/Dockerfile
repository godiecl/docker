# syntax=docker/dockerfile:1
# build with:
#   time eatmydata docker buildx build --progress=plain --tag liguana/wordpress-sqlite .
# run with:
#   docker run --rm -it -p 8000:8000 -v /tmp:/tmp --name wordpress-sqlite liguana/wordpress-sqlite
#
ARG WORDPRESS_VERSION=6.9.4

# --- wordpress ----------------------------------------------------------------
# FrankenPHP + Wordpress
FROM wordpress:${WORDPRESS_VERSION} AS wp
FROM liguana/frankenphp:latest AS release
ARG WORDPRESS_VERSION

# the labels
LABEL org.opencontainers.image.title="Wordpress-SQLite"
LABEL org.opencontainers.image.version="${WORDPRESS_VERSION}"
LABEL org.opencontainers.image.authors="LiGUANA"

# environment variables
ENV WP_DEBUG=false
ENV FORCE_HTTPS=0

COPY --from=wp --chown=www-data:www-data /usr/src/wordpress /app
# COPY --chown=www-data:www-data wp-config.php /app

# run privileged
USER root

VOLUME ["/app/wp-content"]

RUN set -eux; \
    apk add --no-cache --verbose unzip && \
    mkdir -p /app/wp-content/cache && \
    curl -fSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp && \
        chmod +x /usr/local/bin/wp && \
    VERSION=$(curl -sI "https://github.com/WordPress/sqlite-database-integration/releases/latest" | grep -i '^location' | awk -F'/' '{print $NF}' | tr -d '\r\n') && \
        curl -sL "https://github.com/WordPress/sqlite-database-integration/releases/download/${VERSION}/plugin-sqlite-database-integration.zip" -o /tmp/sq.zip && \
        unzip -o /tmp/sq.zip -d "/app/wp-content/mu-plugins" && \
        mv "/app/wp-content/mu-plugins/plugin-sqlite-database-integration" "/app/wp-content/mu-plugins/sqlite-database-integration" && \
        rm -f /tmp/sq.zip && \
    mv "/app/wp-content/mu-plugins/sqlite-database-integration/db.copy" "/app/wp-content/db.php" && \
        sed -i "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#/app/wp-content/mu-plugins/sqlite-database-integration#" "/app/wp-content/db.php" && \
        sed -i "s#{SQLITE_PLUGIN}#/app/wp-content/mu-plugins/sqlite-database-integration/load.php#" "/app/wp-content/db.php" && \
        sed -i "s#<?php#<?php\ndefine( 'WP_SQLITE_AST_DRIVER', true );#" "/app/wp-content/db.php"

# run unprivileged
USER www-data:www-data

# set the port
EXPOSE 8000

# lightweight healthcheck
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD curl -fS http://127.0.0.1:8000/ping || exit 1

# ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
