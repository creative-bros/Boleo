# Alta de Residentes

Este archivo sirve como guía para preparar el archivo de carga masiva de residentes.

## Campos obligatorios

```text
nombre_completo
correo_electronico
telefono
rol
torre
unidad
tipo_unidad
cuota_ordinaria
estatus
```

### Descripción de obligatorios

- `nombre_completo`: nombre del residente o usuario del portal.
- `correo_electronico`: correo con el que ingresará o quedará vinculado.
- `telefono`: número de contacto del residente.
- `rol`: usar `resident`.
- `torre`: torre, edificio, privada o sección.
- `unidad`: número o identificador del departamento.
- `tipo_unidad`: ejemplo `Departamento`, `Penthouse`, `Casa`, `Local`.
- `cuota_ordinaria`: monto mensual ordinario.
- `estatus`: usar `Pagado`, `Atrasado` o `Vacante`.

## Campos recomendados

```text
apellido_paterno
apellido_materno
tipo_residente
nombre_propietario
correo_vinculado
fecha_ingreso
fecha_salida
cuota_extraordinaria
renta_bodega
renta_cajon_estacionamiento
numero_cajones
numero_bodegas
numero_jaulas_tendido
porcentaje_indiviso
numero_emergencia
contacto_emergencia
parentesco_contacto_emergencia
placas_vehiculo
marca_modelo_vehiculo
numero_personas_habitan
mascotas
adeudo_inicial
saldo_a_favor
forma_pago_preferida
activo_en_portal
observaciones
```

### Descripción de recomendados

- `apellido_paterno`: primer apellido.
- `apellido_materno`: segundo apellido.
- `tipo_residente`: ejemplo `Propietario`, `Inquilino`, `Familiar`.
- `nombre_propietario`: si el residente no es el propietario.
- `correo_vinculado`: correo que quedará ligado a la unidad.
- `fecha_ingreso`: fecha de alta o entrada.
- `fecha_salida`: si aplica para inquilinos.
- `cuota_extraordinaria`: monto extraordinario si existe.
- `renta_bodega`: renta mensual de bodega, si aplica.
- `renta_cajon_estacionamiento`: renta mensual de cajón, si aplica.
- `numero_cajones`: cantidad de cajones asignados.
- `numero_bodegas`: cantidad de bodegas asignadas.
- `numero_jaulas_tendido`: cantidad de jaulas asignadas.
- `porcentaje_indiviso`: si el condominio usa cobro por indiviso.
- `numero_emergencia`: teléfono de emergencia.
- `contacto_emergencia`: nombre del contacto de emergencia.
- `parentesco_contacto_emergencia`: relación con el residente.
- `placas_vehiculo`: placas del vehículo.
- `marca_modelo_vehiculo`: descripción del vehículo.
- `numero_personas_habitan`: cantidad de habitantes en la unidad.
- `mascotas`: indicar si tiene mascotas o cuántas.
- `adeudo_inicial`: saldo pendiente al momento del alta.
- `saldo_a_favor`: saldo positivo si existe.
- `forma_pago_preferida`: transferencia, efectivo, SPEI, etc.
- `activo_en_portal`: usar `Si` o `No`.
- `observaciones`: notas adicionales.

## Recomendación de formato

- Archivo sugerido: Excel o CSV.
- Mantener una fila por residente.
- No mezclar dos residentes en la misma unidad en una sola fila.
- Si no tienen un dato recomendado, dejar la celda vacía.

## Valores sugeridos

- `rol`: `resident`
- `estatus`: `Pagado`, `Atrasado`, `Vacante`
- `tipo_residente`: `Propietario`, `Inquilino`, `Familiar`
- `activo_en_portal`: `Si`, `No`

