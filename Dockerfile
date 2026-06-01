FROM php:8.3-fpm

USER root

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    supervisor \
    libsqlite3-dev \
    libpq-dev \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libicu-dev \
    libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        pdo_sqlite \
        zip \
        gd \
        pcntl \
        intl

RUN pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY ./src /var/www/html

RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction

RUN mkdir -p /var/log/supervisor

COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

EXPOSE 9000

CMD ["/usr/bin/supervisord", "-n"]