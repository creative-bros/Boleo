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

Los respaldos de base de datos se guardan en:

```env
DB_BACKUP_PATH=/data/backups
DB_BACKUP_KEEP=14
DB_BACKUP_URL=${{Postgres.DATABASE_URL}}
```

Los PDFs y archivos adjuntos se guardan en:

```env
FILESYSTEM_PUBLIC_ROOT=/data/storage/public
```

El script `railway-start.sh` crea las carpetas necesarias, ejecuta migraciones sin borrar datos y vuelve a enlazar `public/storage` al volumen. Si no existe un volumen montado en `/data`, Railway usara almacenamiento del contenedor y los datos podrian perderse al reconstruir la imagen.

## Respaldo de base de datos

La app actualmente usa SQLite en el volumen persistente de Railway. Para generar un respaldo manual:

```bash
php artisan db:backup
```

El comando crea un archivo `.zip` con la base de datos y un `manifest.json` sin contrasenas. Por defecto conserva los 14 respaldos mas recientes; puede cambiarse con `DB_BACKUP_KEEP` o con:

```bash
php artisan db:backup --keep=30
```

Para Railway, configura `DB_BACKUP_PATH=/data/backups` para que los respaldos queden dentro del volumen persistente. Si mas adelante se migra a MariaDB/MySQL o PostgreSQL, el mismo comando funciona con `DB_CONNECTION=mariadb`, `DB_CONNECTION=mysql` o `DB_CONNECTION=pgsql`; en esos casos el contenedor debe tener disponible `mariadb-dump`/`mysqldump` o `pg_dump`.

Si se va a cambiar de motor, PostgreSQL es la opcion recomendada para esta app por estabilidad operativa y buen soporte administrado. MariaDB tambien es viable, pero no es necesario migrar solo para tener respaldos.

Para usar PostgreSQL como respaldo secundario sin cambiar la base principal:

```bash
php artisan db:backup-postgres --force
```

Ese comando mantiene `DB_CONNECTION=sqlite`, ejecuta las migraciones en la conexion `backup_pgsql` y sincroniza las tablas actuales hacia PostgreSQL. En Railway debe configurarse `DB_BACKUP_URL=${{Postgres.DATABASE_URL}}` en el servicio `Boleo`.

## Endpoint del Formulario Boleo

El sistema expone un endpoint JSON para que el website registre consultas del Formulario Boleo:

```http
POST /api/v1/solicitudes-cotizacion
Authorization: Bearer ${EXTERNAL_QUOTE_API_TOKEN}
Content-Type: application/json
```

Payload:

```json
{
  "nombre_cliente": "Laura Nieto",
  "correo_cliente": "laura@example.com",
  "telefono_cliente": "5512345678",
  "ubicacion_inmueble": "Av. Paseo de la Reforma 123, CDMX",
  "presupuesto_mensual": "$25,000 - $30,000",
  "cuenta_con_administracion": true,
  "cuenta_con_certificacion_prosoc": false,
  "cantidad_departamentos": "24",
  "comentario": "Requiere propuesta de administracion mensual.",
  "fecha_consulta": "2026-07-30 10:45",
  "source": "Website Form"
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
