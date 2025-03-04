# Build command:
# time eatmydata docker buildx build --build-arg BUILD_DATE=$(git show -s --format=%ct) --build-arg VCS_REF=$(git rev-parse --short HEAD) --progress=plain --tag 4h/alpine:3.21.3 .
#
# ---- base image ----
FROM scratch

ARG BUILD_DATE
ARG VCS_REF

LABEL maintainer="Diego Urrutia-Astorga <info@4h.cl>" \
    architecture="amd64/x86_64" \
    alpine-version="3.21.3" \
    build="04-Mar-2025" \
    org.opencontainers.image.title="alpine" \
    org.opencontainers.image.description="Container image running Alpine Linux" \
    org.opencontainers.image.authors="Diego Urrutia-Astorga <info@4h.cl>" \
    org.opencontainers.image.vendor="Cuatro Hache Spa" \
    org.opencontainers.image.version="3.21.3" \
    org.opencontainers.image.url="https://4h.cl" \
    org.opencontainers.image.source="https://github.com/godiecl/docker/alpine" \
    org.opencontainers.image.revision=$VCS_REF \
    org.opencontainers.image.created=$BUILD_DATE

ADD alpine-minirootfs-3.21.3-x86_64.tar.gz /
CMD ["/bin/sh"]
