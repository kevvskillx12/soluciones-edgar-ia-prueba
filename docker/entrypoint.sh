#!/usr/bin/env sh
set -eu

cd /var/www/html

if [ -n "${APP_DATA_PATH:-}" ]; then
    mkdir -p "${APP_DATA_PATH}/storage" "${APP_DATA_PATH}/rag_chroma"

    if [ ! -e "${APP_DATA_PATH}/storage/framework" ] && [ -d "storage" ] && [ ! -L "storage" ]; then
        cp -a storage/. "${APP_DATA_PATH}/storage/" || true
    fi

    rm -rf storage
    ln -s "${APP_DATA_PATH}/storage" storage

    rm -rf rag/chroma_db
    ln -s "${APP_DATA_PATH}/rag_chroma" rag/chroma_db
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs database rag/chroma_db

DB_WAS_CREATED=false
DATABASE_PATH="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
mkdir -p "$(dirname "${DATABASE_PATH}")"
if [ ! -f "${DATABASE_PATH}" ]; then
    touch "${DATABASE_PATH}"
    DB_WAS_CREATED=true
fi

php artisan config:clear --no-ansi || true
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

if [ "${RUN_SEEDERS:-false}" = "true" ] && [ "${DB_WAS_CREATED}" = "true" ]; then
    php artisan db:seed --force --no-interaction
fi

if [ "${RUN_STRESS_SEED:-false}" = "true" ]; then
    python3 scripts/seed_stress_data.py \
        --database "${DATABASE_PATH}" \
        --records "${STRESS_SEED_RECORDS:-1000}" \
        --batch-size "${STRESS_SEED_BATCH_SIZE:-1000}"
fi

if [ "${RUN_RAG_INGEST:-true}" = "true" ] && [ ! -f "rag/chroma_db/chroma.sqlite3" ]; then
    python3 rag/ingestar.py || true
fi

exec "$@"
