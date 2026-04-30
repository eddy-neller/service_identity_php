#!/bin/sh
set -e

if [ "${1#-}" != "$1" ]; then
	set -- php-fpm "$@"
fi

if [ "$1" = 'php-fpm' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
  cd /var/www

  mkdir -p var/cache var/log  vendor public/uploads
  setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX var vendor public/uploads
  setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX var vendor public/uploads

  chown -R www-data:www-data var vendor public/uploads
fi

exec cron -f & docker-php-entrypoint "$@"
