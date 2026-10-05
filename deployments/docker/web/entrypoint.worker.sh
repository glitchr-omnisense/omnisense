#!/bin/sh
set -e

# Symfony ready
make kill

# Before `make clear`, which ends up in `bin/console cache:clear`: that boots
# the kernel, so it cannot repair a compiled container that is itself broken -
# it dies with the very error it is meant to clear. /srv/app/var/cache is an
# anonymous volume and docker compose carries it over when the container is
# recreated, so a stale one persists across deploys. cron had exactly this and
# ran a container requiring a since-uninstalled EasyAdmin class for months;
# here `set -e` would turn the same thing into a crash-loop instead.
rm -rf "/srv/app/var/cache/${APP_ENV:-dev}"

make clear

# Message consumption
echo "Consuming transport messages in $APP_ENV mode..."
exec php bin/console messenger:consume async --memory-limit=256M -vv