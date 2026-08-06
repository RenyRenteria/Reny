# QA fixture — dashboard de reportes

Fecha de referencia: 2026-08-06 07:44 America/Panama

Rango activo: 2026-08-01 a 2026-08-06

Periodo anterior: 2026-07-26 a 2026-07-31

## Comercio

- Captura USD A: USD 60.00, producto `bundle`, sesión `checkout-a`.
- Captura USD B: USD 40.00, mismo capture ID/producto/sesión que A.
- Captura EUR: EUR 50.00, producto `bundle`, sesión `checkout-b`.
- Reembolso USD registrado en el rango: USD 40.00 de una captura anterior.
- Pendiente, fallido y cancelado: USD 9,999.99 cada uno; deben excluirse.
- Captura exactamente en 2026-08-07 00:00 America/Panama: debe excluirse por límite superior.

Resultados esperados:

- Ventas netas: USD 60.00 y EUR 50.00, nunca USD/EUR 110.00 combinado.
- Órdenes completadas: 2 transacciones distintas para A+B y EUR.
- `bundle` USD: 2 unidades, 1 orden, USD 100.00 netos antes de cualquier reembolso propio.
- Periodo anterior cero: variación porcentual `N/A`, no infinito.

## Embudo y contenido

- Dos visitas/eventos de Store en `session-a`: 1 sesión, 2 eventos.
- Dos checkout iniciados en `session-a`: 1 sesión, 2 eventos.
- Una compra canónica en `checkout-a`: 1 sesión de compra.
- Un pago fallido en `session-a`: diagnóstico separado.
- Dos reproducciones de `Luna` en `music-session`: 2 reproducciones, 1 sesión única.
- Una reproducción de `Capri Live` en `video-session`: 1 reproducción, 1 sesión única.

## Shows

- RSVP lead con cantidad 2.
- RSVP autenticado adicional: 1.
- Ticket pagado: 1.
- Check-in del ticket pagado: 1.
- Check-in de RSVP gratuito: 1 (cuenta en el total, no en la conversión pagada).
- Check-in en el rango de un ticket pagado en un periodo anterior: 1 (cuenta en el total, no en la cohorte pagada del rango).

Resultados esperados:

- RSVP: 3.
- Tickets: 1.
- Check-ins: 3.
- RSVP → ticket: 33.3%.
- Ticket → check-in: 100.0%.

## Verificaciones manuales

1. Probar presets 7/30/90 días, 12 meses y rango personalizado; recargar y confirmar query params.
2. Confirmar labels, foco por teclado, tooltip de cada punto y que las series no dependan solo del color.
3. Revisar 320, 375, 430, 768, 1024 y 1440 px sin overflow del documento.
4. Descargar los seis CSV y comprobar BOM UTF-8, fechas ISO-8601, moneda separada y ausencia de PII.
5. Simular error de `access_events`: embudo/contenido muestran retry y comercio/shows siguen visibles.
6. Probar un rango anterior a la instrumentación: contenido y embudo deben mostrar “No disponible”, no un vacío real.
7. Exportar un título que empiece con `=`, `+`, `-`, `@`, tab o retorno y confirmar que se neutraliza como texto.
