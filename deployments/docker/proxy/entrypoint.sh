#! /bin/sh
set -e

mkdir -p /var/cache/nginx
nginx -g 'daemon off;'
