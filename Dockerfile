FROM php:7.0-cli-alpine

RUN apk add git

RUN mv $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini

RUN apk add unzip \
    zip zlib-dev

COPY ./php-development.ini /usr/local/etc/php/conf.d/development.ini

ENV COMPOSER_ALLOW_SUPERUSER 1

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/bin --filename=composer --quiet

WORKDIR /app
