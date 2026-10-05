#!/bin/sh
set -e

echo "Running crontab in ${APP_ENV:-dev} mode."

# Debian cron gives jobs a minimal environment; expose the container's env
# (DATABASE_URL, APP_ENV, …) via /etc/environment which cron reads (pam_env).
printenv | grep -Ev '^(PWD|SHLVL|HOME|_)=' > /etc/environment

# Rebuild the compiled container before handing over to cron.
#
# /srv/app/var/cache is an anonymous volume, and docker compose CARRIES IT
# OVER when the container is recreated - so whatever was compiled in it
# survives deploys indefinitely. web's entrypoint clears the cache on every
# start and never noticed; this one did not, and the result was that after
# EasyAdmin was uninstalled the stale App_KernelProdContainer.bundles.php
# here kept requiring a class that no longer exists. Every scheduled job -
# orphan cleanup, publishable, the menu rollover - died on
# `Class "EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle" not found`
# at kernel boot, silently, for months. Only the heartbeat survived, because
# it is `date` and not php.
#
# rm -rf, NOT `bin/console cache:clear`: the console boots the kernel, which
# require()s the very file that is broken, so cache:clear dies with the exact
# error it is meant to fix. Verified on the live container - clear failed,
# rm succeeded and the console came straight back.
rm -rf "/srv/app/var/cache/${APP_ENV:-dev}"

crontab /srv/app/deployments/docker/cron/crontab

# Debian ships /usr/sbin/cron (crond is the Alpine/BusyBox name)
exec cron -f
