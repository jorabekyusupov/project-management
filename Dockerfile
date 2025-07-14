FROM php:8.3-fpm

WORKDIR /var/www

RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    jpegoptim optipng pngquant gifsicle \
    vim \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zlib1g-dev \
    libicu-dev \
    libxslt-dev \
    libpq-dev

RUN apt-get clean && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo pdo_mysql mbstring zip exif pcntl bcmath opcache intl xsl sockets

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

#COPY entrypoint.sh /usr/local/bin/entrypoint.sh
#RUN chmod +x /usr/local/bin/entrypoint.sh

COPY . .
RUN composer install

RUN groupadd -g 977 nginx && useradd -u 978 -g 977 -d /var/www -s /bin/bash nginx

RUN chmod 775 /var/www/storage /var/www/bootstrap/cache || true \
    && chown -R nginx:nginx /var/www || true

#USER nginx

EXPOSE 9000
CMD ["php-fpm"]
