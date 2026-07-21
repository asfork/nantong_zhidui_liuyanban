FROM php:7.3.4-fpm

RUN docker-php-ext-install pdo_mysql

COPY php.ini /usr/local/etc/php/conf.d/zz-liuyanban-rehearsal.ini
COPY entrypoint.sh /usr/local/bin/liuyanban-rehearsal-entrypoint
RUN chmod +x /usr/local/bin/liuyanban-rehearsal-entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["liuyanban-rehearsal-entrypoint"]
CMD ["php-fpm"]
