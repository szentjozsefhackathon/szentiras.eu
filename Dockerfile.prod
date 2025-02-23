ARG NODE_VERSION=22-alpine
FROM node:${NODE_VERSION} AS node
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

FROM php:8.2-apache AS php
WORKDIR /var/www/html
COPY docker/sites-available/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/docker-php-entrypoint /usr/local/bin/docker-php-entrypoint
COPY --from=node /app .
COPY .env.prod.dist .env
RUN apt-get update && \
    apt-get install -y \
    curl
# Add extensions not included in the base image
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/download/2.7.24/install-php-extensions /usr/local/bin/
RUN install-php-extensions gd gmp pdo_pgsql zip

RUN php composer.phar install --no-dev --no-interaction
RUN chown -R www-data:www-data bootstrap/cache
RUN chown -R www-data:www-data storage

RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
RUN a2enmod rewrite headers