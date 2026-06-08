# syntax=docker/dockerfile:1
# build with:
#   time eatmydata docker buildx build --progress=plain --tag liguana/grav:2.0 .
# run with:
#   docker run --rm -it -p 8000:8000 -v /tmp:/tmp --name grav liguana/grav
#
# https://getgrav.org/downloads
ARG GRAV_VERSION=2.0.0-rc.7?testing
ARG FRANKENPHP_IMAGE=8.4.22

# -----------------------------------------------------------------------------
# FrankenPHP + Grav
# -----------------------------------------------------------------------------
FROM liguana/frankenphp:${FRANKENPHP_IMAGE} AS release
ARG GRAV_VERSION

# the labels
LABEL org.opencontainers.image.title="Grav"
LABEL org.opencontainers.image.version="${GRAV_VERSION}"
LABEL org.opencontainers.image.authors="LiGUANA"

# run privileged for build steps
USER root

# install grav (bash needed for grav cli and run.sh)
RUN set -eux; \
    apk add --no-cache --verbose bash && \
    mkdir -p /config && \
    curl -fSL -o /tmp/grav.zip "https://getgrav.org/download/core/grav-admin/${GRAV_VERSION}" && \
    unzip -q /tmp/grav.zip -d /tmp/grav && \
    cp -a /tmp/grav/grav-admin/. /app/ && \
    rm -fr /tmp/grav /tmp/grav.zip && \
    mkdir -p /tmp/caddy/config /tmp/caddy/data /tmp/caddy/locks && \
    chown -R www-data:www-data /app /config /tmp/caddy

# startup script
COPY --chown=www-data:www-data run.sh /app

# configure caddy
RUN set -eux && \
    chmod +x /app/run.sh && \
    frankenphp fmt --overwrite /etc/frankenphp/Caddyfile

VOLUME ["/config"]

# run unprivileged
USER www-data:www-data

# Reset inherited ENTRYPOINT from base image so run.sh executes directly
ENTRYPOINT []
EXPOSE 8000
CMD ["/app/run.sh"]
