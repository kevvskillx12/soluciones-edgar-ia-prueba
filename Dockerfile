# syntax=docker/dockerfile:1

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --ignore-platform-req=ext-intl \
    --ignore-platform-req=ext-zip \
    --optimize-autoloader

FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY --from=vendor /app/vendor ./vendor
COPY resources ./resources
COPY public ./public
COPY vite.config.js postcss.config.js tailwind.config.js ./
RUN npm run build

FROM php:8.3-cli-bookworm AS app
WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libsqlite3-dev \
        python3 \
        python3-pip \
        python3-venv \
        curl \
    && docker-php-ext-install intl zip pdo pdo_sqlite pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN python3 -m pip install --break-system-packages --no-cache-dir -r rag/requirements.txt \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs database rag/chroma_db \
    && chmod -R ug+rw storage bootstrap/cache database rag/chroma_db \
    && composer dump-autoload --optimize --no-dev

COPY docker/entrypoint.sh /usr/local/bin/soluciones-edgar-entrypoint
RUN chmod +x /usr/local/bin/soluciones-edgar-entrypoint

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/database/database.sqlite \
    CACHE_STORE=database \
    SESSION_DRIVER=database \
    QUEUE_CONNECTION=database \
    PYTHON_PATH=/usr/bin/python3 \
    OLLAMA_URL=http://host.docker.internal:11434/api/generate

EXPOSE 8000

ENTRYPOINT ["soluciones-edgar-entrypoint"]
CMD ["sh", "-lc", "APP_PORT=${PORT:-8000}; case \"$APP_PORT\" in ''|*[!0-9]*) APP_PORT=8000;; esac; php artisan serve --host=0.0.0.0 --port=$APP_PORT"]
