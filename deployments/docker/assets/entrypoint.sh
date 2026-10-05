#!/bin/sh
set -e
umask 007

echo "Installing $APP_ENV dependencies & directory workspace.."
mkdir -p var/cache var/log/"$APP_ENV"

echo "Installing NPM dependencies... (ALLOW_ASSETS_UPDATE=$ALLOW_ASSETS_UPDATE)"
if [ "$ALLOW_ASSETS_UPDATE" = "1" ]; then
  yarn upgrade
else
  yarn install --immutable
fi

# Pass --watch as first argument to run in watch mode (used by docker compose up)
if [ "${1:-}" = "--watch" ]; then
  echo "Starting Encore watch mode..."
  yarn encore dev --watch
  exit
fi

# One-shot build (used by make build)
#
# Asset build mode is decoupled from APP_ENV so an environment can run the app
# in dev (APP_ENV=dev: profiler, verbose errors) while still shipping minified +
# versioned production assets. Set ASSETS_ENV=prod to force a production asset
# build regardless of APP_ENV; it defaults to APP_ENV when unset.
ASSETS_ENV="${ASSETS_ENV:-$APP_ENV}"
case "$ASSETS_ENV" in
  dev*)
    echo "Building dev assets (one-shot)... (ASSETS_ENV=$ASSETS_ENV)"
    yarn encore dev
    ;;
  *)
    echo "Building production assets... (ASSETS_ENV=$ASSETS_ENV)"
    yarn encore production
    ;;
esac