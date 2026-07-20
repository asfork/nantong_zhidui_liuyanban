FROM php:7.3.4-fpm

RUN docker-php-ext-install pdo_mysql

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-liuyanban.ini

WORKDIR /var/www/html

