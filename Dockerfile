FROM php:8.2-fpm

# 1. Системные зависимости + расширения PHP
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    mariadb-client \
    vim unzip git curl wget \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    jpegoptim optipng pngquant gifsicle \
    libzip-dev \
    locales \
    && docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ \
    && docker-php-ext-install -j$(nproc) pdo_mysql zip exif pcntl bcmath gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# 2. Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 3. Пользователь www
RUN getent group www || groupadd -g 1000 www \
    && id -u www >/dev/null 2>&1 || useradd -u 1000 -ms /bin/bash -g www www

WORKDIR /var/www

# 4. Composer install
COPY composer.lock composer.json ./
RUN composer install --optimize-autoloader --no-scripts --no-interaction \
    && chown -R www:www /var/www/vendor

# 5. Копируем проект
COPY --chown=www:www . .

# 6. Создаём и фиксируем права на storage + bootstrap/cache
RUN mkdir -p /var/www/storage/framework/{cache,sessions,views} \
    && mkdir -p /var/www/bootstrap/cache \
    && chown -R www:www /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 777 /var/www/storage/framework/sessions

# Финал
USER www
EXPOSE 9000
CMD ["php-fpm"]
