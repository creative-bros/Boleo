# API: Solicitudes de cotizacion

## Objetivo

Permitir que un sistema externo registre solicitudes de cotizacion en Boleo mediante un endpoint JSON.

## Datos para compartir con el sistema externo

- URL base: `https://boleo-production-a081.up.railway.app`
- Endpoint: `POST /api/v1/solicitudes-cotizacion`
- Autenticacion: `Authorization: Bearer <TOKEN_DE_PRUEBA>`
- Content-Type: `application/json`


## Request minimo

```json
{
  "external_reference": "EXT-123",
  "source_system": "sistema-externo",
  "condominium": "Boleo Condominio",
  "contact_name": "Laura Nieto",
  "contact_phone": "5512345678",
  "service_type": "Impermeabilizacion",
  "description": "Se requiere cotizacion para impermeabilizar la azotea principal."
}
```

## Request completo

```json
{
  "external_reference": "EXT-123",
  "source_system": "sistema-externo",
  "condominium_profile_id": 1,
  "condominium": "Boleo Condominio",
  "contact_name": "Laura Nieto",
  "contact_email": "laura@example.com",
  "contact_phone": "5512345678",
  "service_type": "Impermeabilizacion",
  "description": "Se requiere cotizacion para impermeabilizar la azotea principal.",
  "desired_date": "2026-08-15",
  "budget_amount": 25000,
  "priority": "normal",
  "metadata": {
    "department": "mantenimiento",
    "notes": "Prueba de integracion"
  }
}
```

## Campos

| Campo | Requerido | Tipo | Notas |
| --- | --- | --- | --- |
| `external_reference` | No | string | Identificador del sistema externo. Sirve para evitar duplicados. |
| `source_system` | No | string | Nombre del sistema que envia la solicitud. |
| `condominium_profile_id` | No | integer | ID interno del condominio en Boleo. |
| `condominium` | Si, si no hay `condominium_profile_id` | string | Nombre del condominio. |
| `contact_name` | Si | string | Nombre del solicitante. |
| `contact_email` | Si, si no hay telefono | email | Correo de contacto. |
| `contact_phone` | Si, si no hay correo | string | Telefono de contacto. |
| `service_type` | Si | string | Servicio solicitado. |
| `description` | Si | string | Descripcion de la necesidad. |
| `desired_date` | No | date | Formato recomendado: `YYYY-MM-DD`. |
| `budget_amount` | No | number | Monto estimado sin simbolos ni comas. |
| `priority` | No | string | Valores permitidos: `low`, `normal`, `high`, `urgent`. |
| `metadata` | No | object | Datos adicionales del sistema externo. |

## Ejemplo curl

```bash
curl -X POST "https://boleo-production-a081.up.railway.app/api/v1/solicitudes-cotizacion" \
  -H "Authorization: Bearer <TOKEN_DE_PRUEBA>" \
  -H "Content-Type: application/json" \
  -d '{
    "external_reference": "EXT-123",
    "source_system": "sistema-externo",
    "condominium": "Boleo Condominio",
    "contact_name": "Laura Nieto",
    "contact_phone": "5512345678",
    "service_type": "Impermeabilizacion",
    "description": "Se requiere cotizacion para impermeabilizar la azotea principal."
  }'
```

## Respuesta exitosa

```json
{
  "ok": true,
  "folio": "COT-2026-000001",
  "status": "received"
}
```

## Respuesta duplicada

Si se manda el mismo `source_system` y `external_reference`, Boleo responde el mismo folio y no crea otra solicitud.

```json
{
  "ok": true,
  "folio": "COT-2026-000001",
  "status": "received",
  "duplicate": true
}
```

## Errores esperados

Token faltante o incorrecto:

```json
{
  "message": "No autorizado."
}
```

Campos invalidos:

```json
{
  "message": "The contact name field is required.",
  "errors": {
    "contact_name": [
      "The contact name field is required."
    ]
  }
}
```

Token no configurado en el servidor:

```json
{
  "message": "El token de integracion no esta configurado."
}
```

## Checklist antes de compartir

1. Desplegar el codigo que contiene `routes/api.php`.
2. Ejecutar migraciones para crear la tabla `quote_requests`.
3. Configurar `EXTERNAL_QUOTE_API_TOKEN` en el ambiente publico.
4. Probar un request real con `curl` o Postman.
5. Compartir al tercero solo la URL, el token de prueba y este contrato.
