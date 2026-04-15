#!/usr/bin/env bash

set -e

if [ ! -f .env ]; then
  cp .env.example .env
fi

if [ -z "${DB_DATABASE:-}" ]; then
  if [ -d /data ]; then
    export DB_DATABASE="/data/database.sqlite"
  else
    export DB_DATABASE="/app/database/database.sqlite"
  fi
fi

mkdir -p "$(dirname "$DB_DATABASE")"
touch "$DB_DATABASE"

php artisan key:generate --force
php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
