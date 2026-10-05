#!/usr/bin/env sh
set -eu
umask 007

# -------------------------------
# Ensure log and cache directories exist
# Create xdebug log file with proper permissions
# -------------------------------
touch /tmp/xdebug.log
chmod 777 /tmp/xdebug.log
chmod 775 /srv/app 2> /dev/null || true

mkdir -p var/cache var/log/${APP_ENV}

# Start PHP-FPM
echo "Starting web server... $APP_ENV environment"
cat >/tmp/opcache.php <<'PHP'
<?php
if (!function_exists('opcache_reset')) {
    http_response_code(500);
    echo "OPcache not enabled\n";
    exit;
}
opcache_reset();
echo "OPcache cleared\n";
PHP

# -------------------------------
# Helper functions
# -------------------------------
log() { printf '%s\n' "$*"; }
have() { command -v "$1" >/dev/null 2>&1; }

# Resolve PHP ini scan dir (works for both php:*-fpm and Alpine php83)
PHP_SCAN_DIR="$(php -i | awk -F'=> ' '/^Scan this dir for additional .ini files/{print $2}')"
[ -z "${PHP_SCAN_DIR:-}" ] && PHP_SCAN_DIR="/usr/local/etc/php/conf.d"
mkdir -p "$PHP_SCAN_DIR"

enable_ini() {
  local name="$1"; shift
  local path="$PHP_SCAN_DIR/$name"
  printf '%s\n' "$*" > "$path"
  log "enabled ini: $path"
}

disable_ini() {
  local name="$1"; shift || true
  local path="$PHP_SCAN_DIR/$name"
  if [ -f "$path" ]; then rm -f "$path" && log "disabled ini: $path"; fi
}

# -------------------------------
# Environment defaults
# -------------------------------
APP_ENV="${APP_ENV:-dev}"

BLACKFIRE_ENABLED="${BLACKFIRE_ENABLED:-0}"            # 1 to enable

# -------------------------------
# Blackfire toggle
# -------------------------------
if [ "$BLACKFIRE_ENABLED" = "1" ]; then
  enable_ini "99-blackfire.ini" \
"extension=blackfire.so
blackfire.agent_socket=${BLACKFIRE_AGENT_SOCKET}"
else
  disable_ini "99-blackfire.ini"
fi

make kill

# -------------------------------
# PHP-FPM foreground start
# -------------------------------
log "Starting PHP-FPM..."
php-fpm -RF &

# -------------------------------
# Clear Symfony cache
# -------------------------------
php bin/console cache:clear -vv

# -------------------------------
# Optional: start Nginx if present
# -------------------------------
if have nginx; then
  log "Starting NGINX in foreground mode..."
  exec nginx -g 'daemon off;'
else
  log "NGINX Not Found; Running PHP-FPM only."
  wait
fi
