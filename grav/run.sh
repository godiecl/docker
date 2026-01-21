#!/usr/bin/env bash
# shellcheck shell=bash
# Improved: strict mode, functions, checks, safer operations

set -euo pipefail
IFS=$'\n\t'

log() { printf '%s\n' "$*" >&2; }

CFG_DIR='/config'
APP_DIR='/app'
FRANKENPHP_BIN='frankenphp'
SED_TARGET="$CFG_DIR/user/plugins/admin/admin.yaml"

# ensure config dir exists and is writable
if [[ ! -d "$CFG_DIR" ]]; then
  log "Creating $CFG_DIR"
  mkdir -p "$CFG_DIR"
fi
if [[ ! -w "$CFG_DIR" ]]; then
  log "Error: $CFG_DIR is not writable"
  exit 1
fi

# check frankenphp availability
if ! command -v "$FRANKENPHP_BIN" >/dev/null 2>&1; then
  log "Error: $FRANKENPHP_BIN not found in PATH"
  exit 1
fi

# create or update symlink for an entry (file or directory)
# usage: create_or_link "/app/something"
create_or_link() {
  local src=$1
  local base
  base=$(basename "$src")
  local cfg="$CFG_DIR/$base"

  # If src is already a symlink, leave it
  if [[ -L "$src" ]]; then
    log "Skipping: $src is already a symlink"
    return
  fi

  # If config copy exists -> remove original (if present) and link
  if [[ -e "$cfg" ]]; then
    if [[ -e "$src" && ! -L "$src" ]]; then
      log "Removing original $src (config exists at $cfg)"
      rm -rf -- "$src"
    fi
    log "Linking $src -> $cfg"
    ln -sfn -- "$cfg" "$src"
    return
  fi

  # If config copy does not exist and source exists -> move source to config and link
  if [[ -e "$src" ]]; then
    log "Moving $src -> $cfg"
    mv -- "$src" "$cfg"
    log "Linking $src -> $cfg"
    ln -sfn -- "$cfg" "$src"
    return
  fi

  log "Warning: neither $src nor $cfg exist; skipping"
}

# Directories to be persistent in /config
symlink_dirs=(
  "$APP_DIR/backup"
  "$APP_DIR/logs"
  "$APP_DIR/user"
)

# Files to be persistent in /config
symlink_files=(
  "$APP_DIR/robots.txt"
)

for d in "${symlink_dirs[@]}"; do
  create_or_link "$d"
done

for f in "${symlink_files[@]}"; do
  create_or_link "$f"
done

# Disable admin auto updates check only if file exists in config
if [[ -f "$SED_TARGET" ]]; then
  log "Disabling enable_auto_updates_check in $SED_TARGET"
  sed -i 's/enable_auto_updates_check: true/enable_auto_updates_check: false/' "$SED_TARGET"
else
  log "Note: $SED_TARGET not found; skipping sed"
fi

# Set ownership if running as root and www-data exists (safe permission fix)
if [[ "$(id -u)" -eq 0 ]] && id www-data >/dev/null 2>&1; then
  log "Setting ownership to www-data:www-data for $APP_DIR and $CFG_DIR"
  chown -R www-data:www-data "$APP_DIR" "$CFG_DIR" || log "chown failed (continuing)"
fi

# Execute final process (use exec so signals are forwarded)
log "Starting $FRANKENPHP_BIN"
exec "$FRANKENPHP_BIN" run --config /etc/frankenphp/Caddyfile
