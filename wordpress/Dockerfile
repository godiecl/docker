# syntax=docker/dockerfile:1
# build with:
#   time eatmydata docker buildx build --progress=plain --tag liguana/wordpress .
# run with:
#   docker run --rm -it -p 9000:9000 -v /tmp:/tmp --name grav liguana/wordpress
#
# https://getgrav.org/downloads
ARG WORDPRESS_VERSION=latest

# -----------------------------------------------------------------------------
# FrankenPHP + Wordpress
# -----------------------------------------------------------------------------
FROM wordpress:${WORDPRESS_VERSION} AS wp
FROM liguana/frankenphp:latest AS release

ENV WP_DEBUG=false
ENV FORCE_HTTPS=0

COPY --from=wp /usr/src/wordpress /app
COPY wp-config.php /app

VOLUME ["/app/wp-content"]

RUN set -eux; \
    apk add --no-cache --verbose bash git && \
    mkdir -p /app/wp-content/cache && \
    curl -fSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp && \
        chmod +x /usr/local/bin/wp && \
    export SQLITE_PLUGIN_VERSION=$(curl -sI "https://github.com/WordPress/sqlite-database-integration/releases/latest" | grep -i location | awk -F'/' '{print $NF}' | tr -d '\r') && \
        mkdir -p /app/wp-content/mu-plugins/sqlite-database-integration && \
        curl -L "https://github.com/WordPress/sqlite-database-integration/archive/refs/tags/${SQLITE_PLUGIN_VERSION}.tar.gz" | \
        tar xz --strip-components=1 -C /app/wp-content/mu-plugins/sqlite-database-integration && \
    mv "/app/wp-content/mu-plugins/sqlite-database-integration/db.copy" "/app/wp-content/db.php" && \
        sed -i "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#/app/wp-content/mu-plugins/sqlite-database-integration#" "/app/wp-content/db.php" && \
        sed -i "s#{SQLITE_PLUGIN}#/app/wp-content/mu-plugins/sqlite-database-integration/load.php#" "/app/wp-content/db.php"

EXPOSE 9000

# lightweight healthcheck
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD curl -fS http://127.0.0.1:9000/ping || exit 1

# ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
