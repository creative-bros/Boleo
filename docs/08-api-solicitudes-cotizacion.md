# API: Formulario Boleo

## Objetivo

Permitir que el website registre consultas comerciales del Formulario Boleo mediante un endpoint JSON.

## Datos para compartir con el website

- URL base: `https://boleo-production-a081.up.railway.app`
- Endpoint: `POST /api/v1/solicitudes-cotizacion`
- Autenticacion: `Authorization: Bearer 103a7c51a141ff92bde76aef45a312e6b9792cccc3847caad6f8e7ef456c349d `
- Content-Type: `application/json`

## Request

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

## Campos

| Campo | Requerido | Tipo | Notas |
| --- | --- | --- | --- |
| `nombre_cliente` | Si | string | Nombre del cliente. |
| `correo_cliente` | Si | email | Correo del cliente. |
| `telefono_cliente` | Si | string | Telefono del cliente. |
| `ubicacion_inmueble` | Si | string | Ubicacion del inmueble. |
| `presupuesto_mensual` | Si | string | Texto corto, por ejemplo `$25,000 - $30,000`. |
| `cuenta_con_administracion` | Si | boolean | Acepta `true`/`false`; tambien `si`/`no`. |
| `cuenta_con_certificacion_prosoc` | Si | boolean | Acepta `true`/`false`; tambien `si`/`no`. |
| `cantidad_departamentos` | Si | string | Cantidad de departamentos como texto. |
| `comentario` | Si | string | Comentario del cliente. |
| `fecha_consulta` | Si | string | Fecha de consulta como texto. |
| `source` | No | string | Si no se manda, Boleo guarda `Website Form`. |

## Ejemplo curl

```bash
curl -X POST "https://boleo-production-a081.up.railway.app/api/v1/solicitudes-cotizacion" \
  -H "Authorization: Bearer <TOKEN_DE_PRUEBA>" \
  -H "Content-Type: application/json" \
  -d '{
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
  "message": "The nombre cliente field is required.",
  "errors": {
    "nombre_cliente": [
      "The nombre cliente field is required."
    ]
  }
}
```
