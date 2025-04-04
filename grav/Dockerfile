# syntax=docker/dockerfile:1
#
# `time eatmydata docker buildx build --progress=plain --tag 4h/grav .`
#
ARG FRANKENPHP_VERSION=8.4.5
ARG GRAV_VERSION=1.8.0-beta.4?testing

# ---- base image ----
FROM 4h/frankenphp:${FRANKENPHP_VERSION} AS release

# install grav
RUN set -ex; \
    mkdir -p /config && \
    curl -o /tmp/grav.zip -SL https://getgrav.org/download/core/grav-admin/${GRAV_VERSION} && \
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
