# syntax=docker/dockerfile:1
#
# time eatmydata docker buildx build --progress=plain --tag 4h/ubuntu .
#

# ---- Ubuntu ----
FROM ubuntu:latest AS base

# environment
ENV TZ=America/Santiago
ENV DEBIAN_FRONTEND=noninteractive
ENV LANG=en_US.UTF-8
ENV LC_ALL=en_US.UTF-8

WORKDIR /root

# install base packages
RUN set -ex; \
    apt-get update && \
    apt-get upgrade -y && \
    apt-get install -y --no-install-recommends \
        apt-utils \
        bash-completion \
        bc \
        bmon \
        btop \
        bzip2 \
        ca-certificates \
        cmatrix \
        command-not-found \
        conntrack \
        curl \
        dmidecode \
        dnsutils \
        eatmydata \
        ethtool \
        fio \
        fping \
        git \
        grc \
        hdparm \
        htop \
        hwinfo \
        inxi \
        iotop-c \
        iperf3 \
        iputils-ping \
        jq \
        landscape-common \
        less \
        links \
        linuxlogo \
        locales \
        lshw \
        lsof \
        mc \
        multitail \
        nano \
        ncdu \
        neovim \
        net-tools \
        nmap \
        p7zip-full \
        pigz \
        psmisc \
        rar \
        rsync \
        s-tui \
        screen \
        sl \
        sqlite3 \
        stress \
        tmux \
        traceroute \
        tree \
        tzdata \
        unzip \
        wrk \
        xxd \
        zip \
    && \
    ln -fs /usr/share/zoneinfo/America/Santiago /etc/localtime && \
    dpkg-reconfigure -f noninteractive tzdata && \
    locale-gen en_US en_US.UTF-8 && \
    dpkg-reconfigure locales && \
    update-locale LC_ALL=en_US.UTF-8 LANG=en_US.UTF-8 && \
    echo 'alias ll="ls --color=auto -alF"' >> /root/.bashrc && \
    echo 'alias ls="ls --color=auto -aF"' >> /root/.bashrc && \
    sed -i 's/GRC_ALIASES=true/GRC_ALIASES=false/' /etc/default/grc && \
    rm -rf /var/lib/apt/lists/*
