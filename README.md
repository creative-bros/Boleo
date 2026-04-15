# Boleo

Portal de administracion condominal construido con Laravel.

## Modulos principales

- Login, registro y recuperacion de cuenta
- Dashboard administrativo
- Unidades y residentes
- Cobranza, pagos, estado de cuenta y PDFs
- Amenidades y reservas
- Mantenimiento, tareas, gastos y proveedores
- Configuracion del condominio y roles de acceso

## Desarrollo local

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
php artisan serve
```

## Deploy en Railway

Este repositorio ya incluye configuracion para Railway:

- `nixpacks.toml`
- `railway-start.sh`

Variables recomendadas:

```env
APP_NAME=Boleo
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.up.railway.app
DB_CONNECTION=sqlite
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
LOG_CHANNEL=stack
```

Si agregas un volumen en Railway, usa:

```env
DB_DATABASE=/data/database.sqlite
```

Si no agregas volumen, el arranque usara una SQLite local dentro del contenedor.
