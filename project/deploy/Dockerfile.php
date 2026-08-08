FROM php:8.2-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev libjpeg-dev libfreetype6-dev libzip-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) mysqli pdo_mysql gd zip opcache \
    && rm -rf /var/lib/apt/lists/*

# Сайты монтируются в тот же путь, что видит nginx на хосте
WORKDIR /var/ai-helper/sites

EXPOSE 9000
