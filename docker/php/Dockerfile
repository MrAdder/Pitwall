FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        git \
        unzip \
        icu-dev \
        oniguruma-dev \
        linux-headers \
    && docker-php-ext-install \
        pdo_mysql \
        bcmath \
        intl \
        opcache \
    # pcntl is required by queue:work for signal handling, which is what makes
    # a graceful shutdown mid-job possible instead of killing it outright.
    && docker-php-ext-install pcntl \
    && apk del linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencies are installed in their own layer so application changes do not
# invalidate the vendor cache on every build.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

RUN chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
