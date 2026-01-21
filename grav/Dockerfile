# syntax=docker/dockerfile:1
#
# `time eatmydata docker buildx build --progress=plain --tag liguana/grav .`
#
ARG GRAV_VERSION=1.8.0-beta.29?testing

# -----------------------------------------------------------------------------
# FrankenPHP + Grav
# -----------------------------------------------------------------------------
FROM liguana/frankenphp:latest AS release
ARG GRAV_VERSION

# install grav (bash needed for grav cli and run.sh)
RUN set -eux; \
    apk add --no-cache --verbose bash && \
    mkdir -p /config && \
    curl -o /tmp/grav.zip -SL https://getgrav.org/download/core/grav-admin/${GRAV_VERSION} && \
    unzip -q /tmp/grav.zip -d /tmp/grav && \
    mv /tmp/grav/grav-admin/* /app/ && \
    rm -fr /tmp/* && \
    (crontab -l; echo "* * * * * cd /app;/usr/local/bin/php bin/grav scheduler 1>> /dev/null 2>&1") | crontab -

# startup script
COPY run.sh /app

# configure caddy
RUN set -eux && \
    chmod +x /app/run.sh && \
    frankenphp fmt --overwrite /etc/caddy/Caddyfile && \
    setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp

VOLUME ["/config"]

EXPOSE 9000

CMD ["/app/run.sh"]
