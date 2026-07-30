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
DB_DATABASE=/data/database.sqlite
FILESYSTEM_PUBLIC_ROOT=/data/storage/public
FILESYSTEM_DISK=public
SESSION_DRIVER=database
CACHE_STORE=file
QUEUE_CONNECTION=sync
LOG_CHANNEL=stack
EXTERNAL_QUOTE_API_TOKEN=token-largo-y-secreto
```

Para conservar la informacion entre commits, deploys o actualizaciones, Railway debe tener un volumen persistente montado en `/data`.

La base de datos se guarda en:

```env
DB_DATABASE=/data/database.sqlite
```

Los PDFs y archivos adjuntos se guardan en:

```env
FILESYSTEM_PUBLIC_ROOT=/data/storage/public
```

El script `railway-start.sh` crea las carpetas necesarias, ejecuta migraciones sin borrar datos y vuelve a enlazar `public/storage` al volumen. Si no existe un volumen montado en `/data`, Railway usara almacenamiento del contenedor y los datos podrian perderse al reconstruir la imagen.

## Endpoint de solicitudes de cotizacion

El sistema expone un endpoint JSON para que sistemas externos registren solicitudes de cotizacion:

```http
POST /api/v1/solicitudes-cotizacion
Authorization: Bearer ${EXTERNAL_QUOTE_API_TOKEN}
Content-Type: application/json
```

Payload minimo:

```json
{
  "external_reference": "EXT-123",
  "source_system": "erp-demo",
  "condominium": "Boleo Condominio",
  "contact_name": "Laura Nieto",
  "contact_phone": "5512345678",
  "service_type": "Impermeabilizacion",
  "description": "Se requiere cotizacion para impermeabilizar la azotea principal."
}
```

Respuesta exitosa:

```json
{
  "ok": true,
  "folio": "COT-2026-000001",
  "status": "received"
}
```
