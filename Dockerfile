# Stage 1: Build Frontend Assets
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci --ignore-scripts
COPY . .
RUN npm run build

# Stage 2: Application Runtime (Aligned to PHP 8.4)
FROM php:8.4-fpm-alpine AS app

# Install system dependencies & PHP extensions
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    sqlite \
    sqlite-dev \
    libpng-dev \
    libzip-dev \
    postgresql-dev \
    oniguruma-dev \
    linux-headers \
    su-exec \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pdo_mysql \
        pdo_sqlite \
        mbstring \
        zip \
        bcmath \
        opcache \
        pcntl \
        gd

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application files (excluding .dockerignore items)
COPY . .

# Copy built frontend assets from Stage 1
COPY --from=frontend /app/public/build ./public/build

# Install PHP production dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Configure Nginx, Supervisor, and Entrypoint
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p /var/log/supervisor /var/run /var/www/html/database /var/www/html/storage/app/backups /var/www/html/storage/logs /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache database

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD curl -f http://localhost/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
