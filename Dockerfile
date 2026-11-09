FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends libpng-dev libjpeg-dev libwebp-dev unzip git \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql mysqli gd \
    && rm -rf /var/lib/apt/lists/*

# Composer + PHPMailer (dipasang di /opt/mail, di luar volume ./src)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN mkdir -p /opt/mail && composer require phpmailer/phpmailer --working-dir=/opt/mail --no-interaction --no-progress