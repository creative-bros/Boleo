#!/usr/bin/env bash

set -e

set_env_value() {
  local key="$1"
  local value="$2"

  if grep -q "^${key}=" .env; then
    sed -i "s|^${key}=.*|${key}=${value}|" .env
  else
    printf '\n%s=%s\n' "$key" "$value" >> .env
  fi
}

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

if [ -z "${APP_KEY:-}" ]; then
  APP_KEY="$(php artisan key:generate --show --no-interaction)"
  export APP_KEY
fi

export SESSION_DRIVER="database"
export SESSION_LIFETIME="${SESSION_LIFETIME:-10080}"

set_env_value "APP_KEY" "$APP_KEY"
set_env_value "SESSION_DRIVER" "$SESSION_DRIVER"
set_env_value "SESSION_LIFETIME" "$SESSION_LIFETIME"

if [ -n "${APP_URL:-}" ]; then
  set_env_value "APP_URL" "$APP_URL"
fi

set_env_value "DB_DATABASE" "$DB_DATABASE"

mkdir -p "$(dirname "$DB_DATABASE")"
touch "$DB_DATABASE"

php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
