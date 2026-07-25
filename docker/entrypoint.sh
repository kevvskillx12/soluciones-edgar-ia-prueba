#!/usr/bin/env sh
set -eu

cd /var/www/html

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs database rag/chroma_db

DB_WAS_CREATED=false
if [ ! -f "${DB_DATABASE:-/var/www/html/database/database.sqlite}" ]; then
    touch "${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    DB_WAS_CREATED=true
fi

php artisan config:clear --no-ansi || true
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

if [ "${RUN_SEEDERS:-false}" = "true" ] && [ "${DB_WAS_CREATED}" = "true" ]; then
    php artisan db:seed --force --no-interaction
fi

if [ "${RUN_RAG_INGEST:-true}" = "true" ] && [ ! -f "rag/chroma_db/chroma.sqlite3" ]; then
    python3 rag/ingestar.py || true
fi

exec "$@"
