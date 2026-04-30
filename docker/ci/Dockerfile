FROM php:8.4-fpm

ENV TZ=Europe/Paris
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apt-get update

RUN apt-get install -y \
    acl \
    iputils-ping net-tools telnet nano \
    git cron curl wget cron zip unzip \
    libpq-dev libzip-dev libxml2-dev libicu-dev \
    librabbitmq-dev libssl-dev \
    && rm -rf /var/lib/apt/lists/*

RUN pecl install apcu xdebug amqp
RUN docker-php-ext-install xml
RUN docker-php-ext-install zip
RUN docker-php-ext-install sockets
RUN docker-php-ext-install opcache
RUN docker-php-ext-install intl
RUN docker-php-ext-install pdo_pgsql
RUN docker-php-ext-enable apcu xdebug amqp xml zip sockets opcache intl pdo_pgsql

# Ajouter le php.ini modifié
COPY php-fpm/php.ini /usr/local/etc/php/php.ini
COPY --from=composer:2.9.2 /usr/bin/composer /usr/bin/composer
RUN composer --version

WORKDIR /var/www

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

ENTRYPOINT ["docker-entrypoint"]

CMD php-fpm
