###############################################################################
# Stage 1 — Base PHP image with extensions
###############################################################################
FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    linux-headers \
    && docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    bcmath \
    zip \
    intl \
    mbstring \
    opcache \
    sockets \
    && rm -rf /var/cache/apk/*

WORKDIR /var/www/html

###############################################################################
# Stage 2 — Composer dependencies
###############################################################################
FROM base AS composer

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

###############################################################################
# Stage 3 — Node build (Vite / Tailwind)
###############################################################################
FROM node:22-alpine AS node

WORKDIR /var/www/html

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

###############################################################################
# Stage 4 — Development image (all tools included)
###############################################################################
FROM base AS development

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache \
    nodejs \
    npm \
    git \
    bash

# Use the default php.ini for development
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY . .
RUN composer install --prefer-dist

EXPOSE 8000 5173

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

###############################################################################
# Stage 5 — Production image (optimised, minimal)
###############################################################################
FROM base AS production

# Use the production php.ini
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# PHP OPcache tuning
RUN echo "opcache.enable=1" >> "$PHP_INI_DIR/conf.d/opcache.ini" \
    && echo "opcache.memory_consumption=128" >> "$PHP_INI_DIR/conf.d/opcache.ini" \
    && echo "opcache.interned_strings_buffer=8" >> "$PHP_INI_DIR/conf.d/opcache.ini" \
    && echo "opcache.max_accelerated_files=10000" >> "$PHP_INI_DIR/conf.d/opcache.ini" \
    && echo "opcache.validate_timestamps=0" >> "$PHP_INI_DIR/conf.d/opcache.ini"

# Install Nginx
RUN apk add --no-cache nginx bash \
    && rm -rf /var/cache/apk/*

# Nginx config
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# Copy app with Composer deps
COPY --from=composer --chown=www-data:www-data /var/www/html /var/www/html

# Copy built frontend assets
COPY --from=node --chown=www-data:www-data /var/www/html/public/build /var/www/html/public/build

# Copy entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["serve"]
