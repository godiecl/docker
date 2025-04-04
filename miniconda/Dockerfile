# syntax=docker/dockerfile:1
#
# time eatmydata docker buildx build --progress=plain --tag 4h/miniconda .
#

# ---- Debian ----
FROM debian:bookworm-slim AS base

# environment
# environment
ENV LANG=C.UTF-8
ENV TZ=America/Santiago
# ENV PATH=/opt/miniconda/bin:$PATH

# Prevents Python from writing pyc files to disc (equivalent to python -B option)
ENV PYTHONDONTWRITEBYTECODE=1
# Prevents Python from buffering stdout and stderr (equivalent to python -u option)
ENV PYTHONUNBUFFERED=1

WORKDIR /app

# install base packages
RUN set -ex; \
    apt-get update && \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        tzdata \
    && \
    ln -fs /usr/share/zoneinfo/America/Santiago /etc/localtime && \
    dpkg-reconfigure -f noninteractive tzdata && \
    echo 'alias ll="ls --color=auto -alF"' >> /root/.bashrc && \
    echo 'alias ls="ls --color=auto -aF"' >> /root/.bashrc && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# install miniconda
RUN set -ex; \
    curl -SLf --progress-bar -o /tmp/miniconda.sh https://repo.anaconda.com/miniconda/Miniconda3-latest-Linux-x86_64.sh && \
    chmod +x /tmp/miniconda.sh && \
    /tmp/miniconda.sh -b -p /opt/miniconda && \
    rm -rf /tmp/miniconda.sh && \
    /opt/miniconda/bin/conda config --set report_errors false && \
    /opt/miniconda/bin/conda config --set auto_activate_base false && \
    /opt/miniconda/bin/conda config --set channel_priority flexible && \
    /opt/miniconda/bin/conda config --set auto_update_conda false && \
    /opt/miniconda/bin/conda config --set verbosity 1 && \
    /opt/miniconda/bin/conda config --set show_channel_urls true && \
    /opt/miniconda/bin/conda config --add channels conda-forge && \
    /opt/miniconda/bin/conda info
