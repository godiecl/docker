# syntax=docker/dockerfile:1
# build with:
#   time eatmydata docker buildx build --progress=plain --tag registry.liguana.cl/grav:2.0.26 .
# run with:
#   docker run --rm -it -p 8000:8000 -v /tmp/grav-config:/config --name grav registry.liguana.cl/grav:2.0.26
#
# https://getgrav.org/downloads
ARG GRAV_VERSION=2.0.26
ARG FRANKENPHP_VERSION=1.12.7
ARG PHP_VERSION=8.4.25

# -----------------------------------------------------------------------------
# FrankenPHP + Grav
# -----------------------------------------------------------------------------
FROM registry.liguana.cl/frankenphp:${FRANKENPHP_VERSION}-${PHP_VERSION} AS release
ARG GRAV_VERSION

# run privileged for build steps
USER root

# install grav (bash needed for grav cli and run.sh)
RUN set -eux; \
    apk add --no-cache --verbose bash; \
    mkdir -p /config; \
    curl -fSL -o /tmp/grav.zip "https://getgrav.org/download/core/grav-admin/${GRAV_VERSION}"; \
    unzip -q /tmp/grav.zip -d /tmp/grav; \
    cp -a /tmp/grav/grav-admin/. /app/; \
    rm -fr /tmp/grav /tmp/grav.zip; \
    chown -R www-data:www-data /app /config

# startup script
COPY --chown=www-data:www-data --chmod=0755 run.sh /app

VOLUME ["/config"]

# run unprivileged
USER www-data:www-data

EXPOSE 8000
CMD ["/app/run.sh"]
