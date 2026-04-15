# Manual 03: Cobranza y reportes

## Objetivo

Explicar el flujo para registrar pagos, revisar estados de cuenta y descargar reportes de cobranza y deudores.

## Funciones principales del módulo

- Buscar unidades o residentes.
- Ver detalle de cuenta por unidad.
- Registrar pagos.
- Descargar estado de cuenta en PDF.
- Descargar recibos de pago.
- Descargar reporte general de cobranza.
- Descargar reporte de deudores.

## Búsqueda de cuenta

1. Entrar al módulo `Finanzas` o `Módulo de Cobranza`.
2. Usar el buscador por condominio o por departamento.
3. Seleccionar la unidad deseada.
4. Revisar:
   - Propietario
   - Unidad
   - Cuota mensual
   - Pagado en el período
   - Saldo pendiente
   - Estatus

## Registrar un pago

Solo disponible para administradores.

1. Entrar al módulo `Finanzas`.
2. Seleccionar la unidad.
3. Capturar:
   - Concepto
   - Monto
   - Fecha de pago
4. Guardar.
5. Revisar que el movimiento aparezca en el historial.

## Validaciones recomendadas

- Confirmar que el pago no se duplique.
- Confirmar que el saldo pendiente disminuya.
- Confirmar que el estatus cambie cuando la cuota quede cubierta.

## Descargar estado de cuenta

1. Seleccionar una unidad.
2. Dar clic en `Estado de Cuenta PDF`.
3. Verificar que el PDF incluya:
   - Período
   - Unidad
   - Propietario
   - Correo vinculado
   - Cuota mensual
   - Pagado
   - Pendiente
   - Estatus

## Descargar recibo de pago

1. Registrar o ubicar un pago en el historial.
2. Dar clic en la descarga del recibo.
3. Confirmar que el archivo abra correctamente.

## Descargar reporte de cobranza

1. Entrar al módulo de cobranza.
2. Dar clic en `Reporte de Cobranza`.
3. Revisar que el PDF incluya las unidades registradas con cuota, pagado, pendiente y estatus.

## Descargar reporte de deudores

1. Entrar al módulo de cobranza.
2. Dar clic en `Reporte de Deudores`.
3. Revisar que solo aparezcan unidades con saldo pendiente.

## Qué debe comentar el revisor

- Si el saldo mostrado coincide con lo esperado.
- Si el estatus de pago es claro.
- Si el buscador encuentra rápido la cuenta.
- Si los PDFs son legibles y completos.

## Resultado esperado

El módulo debe permitir registrar pagos y generar reportes confiables para seguimiento de cobranza.
