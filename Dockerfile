  FROM php:8.3-apache
  RUN apt-get update && apt-get install -y --no-install-recommends libpng-dev libjpeg-dev libwebp-dev \
      && docker-php-ext-configure gd --with-jpeg --with-webp \
      && docker-php-ext-install pdo pdo_mysql mysqli gd \
      && rm -rf /var/lib/apt/lists/*