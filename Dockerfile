# Build the Quasar web client.
FROM node:22-bookworm-slim AS frontend
WORKDIR /build/frontend
COPY frontend/ ./
RUN npm ci
ARG VITE_API_URL=/api
ENV VITE_API_URL=${VITE_API_URL}
RUN npm run build

# Serve Laravel and the built SPA from one Render web service.
FROM php:8.3-apache
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev unzip \
    && docker-php-ext-install pdo_pgsql \
    && sed -i 's/^Listen 80$/Listen 10000/' /etc/apache2/ports.conf \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY deploy/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
WORKDIR /var/www/html
COPY backend/ ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# The static index takes priority at the site root; Laravel's rewrite still
# handles /api/* and /up requests through public/index.php.
COPY --from=frontend /build/frontend/dist/spa/ /var/www/html/public/
COPY deploy/start-render.sh /usr/local/bin/start-render
RUN chmod +x /usr/local/bin/start-render

EXPOSE 10000
CMD ["/usr/local/bin/start-render"]
