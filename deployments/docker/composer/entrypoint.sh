#!/bin/sh
set -e

echo "Installing $APP_ENV dependencies & directory workspace.."
mkdir -p var/cache var/log/${APP_ENV}

# Sync public directory.
# MUST come before the `mkdir -p public/assets/images` below. /srv/app/public is
# a named volume that the web image does not seed (it ships no /srv/app/public),
# so on a first run it starts genuinely empty and this rsync is the ONLY thing
# that puts public/index.php into it. Creating the assets/images directory first
# makes `ls -A` non-empty, the guard takes the "skipping" branch, index.php never
# arrives, and nginx answers every request with a bare 404 "File not found."
if [ -z "$(ls -A /srv/app/public 2>/dev/null)" ]; then
  echo "Destination public directory is empty, syncing..."
  rsync -a /mnt/app/public/ /srv/app/public/
else
  echo "Destination public directory is not empty, skipping rsync."
fi

# config/packages/twig.yaml registers '%kernel.project_dir%/public/assets/images'
# as the "images" namespace, and Twig's FilesystemLoader throws outright when a
# registered path does not exist. public/assets is Encore output and gitignored,
# so on a from-scratch tree it is absent - and composer's own post-install-cmd
# (auto-scripts -> assets:install) boots the kernel, hits that missing path and
# dies, before build-assets has ever had a chance to create it. Ordering cannot
# fix it: assets need vendor, so composer must run first. Creating the directory
# up front is enough; Encore fills it moments later.
mkdir -p public/assets/images

# Sync storage directory if empty
if [ -z "$(ls -A /srv/app/var/storage 2>/dev/null)" ]; then
  echo "Destination storage directory is empty, syncing..."
  rsync -a /mnt/app/var/storage/ /srv/app/var/storage/
else
  echo "Destination storage directory is not empty, skipping rsync."
fi

# Symfony ready
make kill

# Installation of the PHP dependencies (only if not already installed)
echo "Ensuring Composer dependencies for $APP_ENV... (ALLOW_COMPOSER_UPDATE=$ALLOW_COMPOSER_UPDATE)"

# No lock yet (a fresh project): resolve once, which writes composer.lock.
if [ ! -s composer.lock ]; then
  echo "No composer.lock: resolving dependencies..."
  composer update --no-interaction --with-all-dependencies
elif [ "${ALLOW_COMPOSER_UPDATE}" = "1" ]; then
  composer update --no-interaction  --with-all-dependencies
else
  if [ -f vendor/autoload.php ]; then
    echo "Composer dependencies already present."
    case "$APP_ENV" in
      # Dev keeps the fast-path: skip entirely for iteration speed.
       dev*) echo "Skipping installation (dev fast-path)." ;;
      # Test must stay reproducible: a pre-populated vendor volume (e.g. the
      # deploy gate's) would otherwise silently pin stale package refs even
      # after composer.lock moved. Install is a quick no-op when in sync.
      test*) composer install --no-interaction ;;
          *) composer install --prefer-dist --no-dev --no-scripts --no-interaction ;;
    esac
  else
    case "$APP_ENV" in
       dev*) composer install --no-interaction ;;
      test*) composer install --no-interaction ;;
          *) composer install --prefer-dist --no-dev --no-scripts --no-interaction ;;
    esac
  fi
fi