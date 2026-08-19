# ==============================================================================
# base — systeme, extensions PHP, supervisor.
#
# Tout ce qui est ici finit en production. Rien d'autre ne doit y entrer : ni
# Composer, ni outil de diagnostic. Les deux roles (`web` et `worker`)
# et les deux environnements partagent cette etape.
# ==============================================================================
FROM php:8.4-fpm AS base

ENV TZ=Europe/Paris
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apt-get update && apt-get install -y --no-install-recommends \
    supervisor procps acl \
    git cron curl zip unzip \
    libpq-dev libzip-dev libxml2-dev libicu-dev \
    librabbitmq-dev libssl-dev \
    && rm -rf /var/lib/apt/lists/*

RUN pecl install apcu amqp redis
RUN docker-php-ext-install xml zip sockets opcache intl pdo_pgsql
RUN docker-php-ext-enable apcu amqp redis xml zip sockets opcache intl pdo_pgsql

COPY docker/app/php-fpm/php.ini /usr/local/etc/php/php.ini
COPY docker/app/cron/crontab /etc/crontab

RUN echo "apc.enabled=1" >> /usr/local/etc/php/conf.d/docker-php-ext-apcu.ini \
    && echo "apc.enable_cli=1" >> /usr/local/etc/php/conf.d/docker-php-ext-apcu.ini

# Une seule image, deux roles : `web` (php-fpm) et `worker` (messenger + cron).
# Le role est lu par supervisor.conf dans son [include]. Defaut `web` pour qu'un
# `docker run` nu serve des requetes sans configuration supplementaire.
ENV SUPERVISOR_ROLE=web
COPY docker/app/supervisor/supervisor.conf /etc/supervisor/supervisord.conf
COPY docker/app/supervisor/conf.d/ /etc/supervisor/conf.d/

WORKDIR /var/www
RUN git config --global --add safe.directory /var/www

COPY docker/app/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint
ENTRYPOINT ["docker-entrypoint"]

CMD ["/usr/bin/supervisord","-n","-c","/etc/supervisor/supervisord.conf"]


# ==============================================================================
# vendor — dependances de PRODUCTION uniquement.
#
# Avant, `vendor/` n'entrait meme pas dans l'image (il est exclu par
# .dockerignore) : elle etait donc inutilisable sans le bind-mount de dev.
# Ici les dependances sont resolues dans l'image, sans les 18 paquets de
# require-dev (phpunit, phpstan, rector, grumphp, faker, maker, profiler).
# ==============================================================================
FROM base AS vendor

COPY --from=composer:2.10.2 /usr/bin/composer /usr/bin/composer

# Les dependances d'abord, le code ensuite : tant que composer.lock ne bouge pas,
# Docker reutilise cette couche et le build ne re-telecharge rien.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

# `public/bundles/` est exclu du contexte (.dockerignore) parce que c'est un
# artefact regenerable, pas une source. On le regenere donc ici, sans quoi l'UI
# d'API Platform servirait des 404 sur une image construite hors du poste de dev.
#
# APP_ENV=prod n'est PAS decoratif : `.env` est lui aussi exclu du contexte, donc
# sans lui le noyau demarre en `dev` et charge les bundles reserves au dev — dont
# MakerBundle, que `--no-dev` vient precisement de ne pas installer. Le build
# echoue alors sur un « class not found » qui n'a aucun rapport apparent avec les
# assets. APP_SECRET n'a qu'a exister ; sa valeur ne sert a rien ici et ne persiste
# pas dans l'image (les variables d'un RUN ne sont pas des ENV).
RUN APP_ENV=prod APP_SECRET=build php bin/console assets:install public --no-interaction


# ==============================================================================
# prod — image deployee. Etape par defaut (cf. `target:` du docker-compose).
# ==============================================================================
FROM base AS prod

COPY --from=vendor /var/www /var/www


# ==============================================================================
# dev — image locale uniquement, selectionnee par docker-compose.override.yaml.
#
# Le code n'y est pas copie : l'override monte `./:/var/www`, qui masquerait de
# toute facon ce que l'image contient. Seul l'outillage compte ici.
# ==============================================================================
FROM base AS dev

RUN apt-get update && apt-get install -y --no-install-recommends \
    iputils-ping net-tools telnet nano wget \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.10.2 /usr/bin/composer /usr/bin/composer

# Xdebug est disponible uniquement dans l'image de developpement et reste eteint
# par defaut. `make unit-coverage` active explicitement son mode coverage.
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && echo "xdebug.mode=off" > /usr/local/etc/php/conf.d/xdebug-mode.ini

RUN { \
        echo "alias ls='ls --color=auto'"; \
        echo "alias ll='ls --color=auto -alF'"; \
        echo "alias la='ls --color=auto -A'"; \
        echo "alias l='ls --color=auto -CF'"; \
    } >> ~/.bashrc
