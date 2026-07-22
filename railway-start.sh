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

PERSISTENT_VOLUME_PATH="${RAILWAY_VOLUME_MOUNT_PATH:-/data}"

if [ -d "$PERSISTENT_VOLUME_PATH" ]; then
  # Railway rebuilds containers on each deploy. Keep SQLite inside the mounted
  # volume so commits or image rebuilds never start from an empty database.
  export DB_DATABASE="$PERSISTENT_VOLUME_PATH/database.sqlite"
elif [ -z "${DB_DATABASE:-}" ]; then
  export DB_DATABASE="/app/database/database.sqlite"
fi

if [ -d "$PERSISTENT_VOLUME_PATH" ]; then
  export FILESYSTEM_PUBLIC_ROOT="$PERSISTENT_VOLUME_PATH/storage/public"
fi

if [ -z "${APP_KEY:-}" ]; then
  APP_KEY_FILE="$PERSISTENT_VOLUME_PATH/app.key"

  if [ -d "$PERSISTENT_VOLUME_PATH" ] && [ -s "$APP_KEY_FILE" ]; then
    APP_KEY="$(cat "$APP_KEY_FILE")"
  else
    APP_KEY="$(php artisan key:generate --show --no-interaction)"

    if [ -d "$PERSISTENT_VOLUME_PATH" ]; then
      printf '%s\n' "$APP_KEY" > "$APP_KEY_FILE"
      chmod 600 "$APP_KEY_FILE"
    fi
  fi

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
echo "Using persistent database: $DB_DATABASE"

mkdir -p "$(dirname "$DB_DATABASE")"

if [ ! -s "$DB_DATABASE" ]; then
  for candidate in "/app/database/database.sqlite" "database/database.sqlite"; do
    if [ "$candidate" != "$DB_DATABASE" ] && [ -s "$candidate" ]; then
      cp "$candidate" "$DB_DATABASE"
      break
    fi
  done
fi

touch "$DB_DATABASE"

if [ -n "${FILESYSTEM_PUBLIC_ROOT:-}" ]; then
  set_env_value "FILESYSTEM_PUBLIC_ROOT" "$FILESYSTEM_PUBLIC_ROOT"
  set_env_value "FILESYSTEM_DISK" "public"
  mkdir -p "$FILESYSTEM_PUBLIC_ROOT"
  echo "Using persistent public uploads: $FILESYSTEM_PUBLIC_ROOT"
fi

php artisan storage:link --force || true

php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
