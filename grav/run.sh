#!/usr/bin/env bash
# shellcheck shell=bash

# Symlink directories
symlinks=(
    /app/backup
    /app/logs
    /app/user
)

shopt -s globstar dotglob

for i in "${symlinks[@]}"; do
    if [[ -d /config/"$(basename "$i")" && ! -L "$i" ]]; then
        rm -rf "$i"
    fi
    if [[ ! -d /config/"$(basename "$i")" && ! -L "$i" ]]; then
        mv "$i" /config/
    fi
    if [[ -d /config/"$(basename "$i")" && ! -L "$i" ]]; then
        ln -s /config/"$(basename "$i")" "$i"
    fi
done

# Symlink files
symlinks=(
    /app/robots.txt
)

shopt -s globstar dotglob

for i in "${symlinks[@]}"; do
    if [[ -f /config/"$(basename "$i")" && ! -L "$i" ]]; then
        rm -rf "$i"
    fi
    if [[ ! -f /config/"$(basename "$i")" && ! -L "$i" ]]; then
        mv "$i" /config/
    fi
    if [[ -f /config/"$(basename "$i")" && ! -L "$i" ]]; then
        ln -s /config/"$(basename "$i")" "$i"
    fi
done

shopt -u globstar dotglob

sed -i 's/enable_auto_updates_check: true/enable_auto_updates_check: false/' /config/user/plugins/admin/admin.yaml

# permissions
#lsiown -R www-data:www-data \
#    /app \
#    /config

# run!
frankenphp run --config /etc/caddy/Caddyfile
