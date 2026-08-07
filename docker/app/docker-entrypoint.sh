#!/bin/sh
set -e

if [ "${1#-}" != "$1" ]; then
	set -- php-fpm "$@"
fi

# On prépare toujours l'environnement applicatif, même lorsque Supervisor pilotera les processus.
if [ "$1" = 'php-fpm' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ] || [ "$1" = 'supervisord' ] || [ "$1" = '/usr/bin/supervisord' ]; then
  cd /var/www
  host_uid="$(stat -c '%u' .)"

  mkdir -p var/cache var/log public/uploads
  chown -R www-data:www-data var public/uploads

  setfacl -R -m u:www-data:rwX -m u:"${host_uid}":rwX var
  setfacl -dR -m u:www-data:rwX -m u:"${host_uid}":rwX var
  find var -type d -exec chmod 0770 {} +
  find var -type f -exec chmod 0660 {} +

  setfacl -Rb public/uploads
  find public/uploads -type d -exec chmod 0755 {} +
  find public/uploads -type f -exec chmod 0644 {} +
fi

# On délègue l'exécution finale à l'entrypoint officiel de l'image PHP.
exec docker-php-entrypoint "$@"
