FROM php:8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libpng-dev libjpeg-dev libfreetype6-dev libcurl4-openssl-dev libonig-dev libxml2-dev \
 && docker-php-ext-configure gd --with-jpeg --with-freetype \
 && docker-php-ext-install mysqli pdo_mysql gd curl mbstring simplexml fileinfo \
 && rm -rf /var/lib/apt/lists/*
