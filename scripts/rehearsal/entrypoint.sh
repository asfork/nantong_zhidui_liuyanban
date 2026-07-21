#!/bin/sh

set -eu

chown -R www-data:www-data /var/www/html/var/log /var/www/html/var/session

exec "$@"
