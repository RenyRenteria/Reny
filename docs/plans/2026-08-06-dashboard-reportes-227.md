# Dashboard de reportes #227

## Objetivo

Convertir la vista de estadísticas del CMS en un dashboard operativo y exportable, usando fuentes canónicas para comercio, usuarios, RSVP y tickets, y eventos analíticos versionados para interacción y embudo.

## Arquitectura

1. `ReportRange` normaliza presets/rangos personalizados en la zona horaria configurada, expone límites UTC inclusivo/exclusivo y el periodo anterior de igual duración.
2. `DashboardReportService` concentra todos los cálculos compartidos por la UI y los CSV. Cada módulo se resuelve de forma independiente para conservar resultados parciales cuando otro falle.
3. `DashboardController` valida filtros y renderiza el dashboard; `DashboardExportController` reutiliza el mismo servicio y emite un CSV UTF-8 por reporte.
4. `access_events` gana versión de esquema, timestamp de servidor, sesión anónima, resultado e idempotencia. Los eventos de interacción alimentan visitas, checkout y consumo; pagos, órdenes, usuarios, RSVP y tickets continúan viniendo de tablas canónicas.
5. `orders` gana timestamps explícitos de captura y un ledger append-only de reembolsos. Los registros legacy sin fecha de captura verificable se excluyen con cobertura parcial visible; no se fabrica `completed_at` desde `created_at`.

## Entrega

- [x] Migraciones e índices para órdenes y analítica.
- [x] Rango global, comparación y query params.
- [x] KPIs multi-moneda, serie de ventas comparativa y ranking de productos.
- [x] Embudo por sesión con cobertura histórica y fallos de pago separados.
- [x] Ranking de contenido por tipo/métrica.
- [x] Shows con RSVP, tickets, check-ins y tasas disponibles.
- [x] CSV separado para resumen, ventas, embudo, productos, contenido y shows.
- [x] Estados vacío/error/cobertura parcial/loading y responsive sin overflow.
- [x] Instrumentación allowlisted, anónima e idempotente.
- [x] Cobertura automatizada y fixture QA documentado.
- [ ] UAT browser y revisión final de Cuatro.

## Riesgos tratados

- Las monedas nunca se agregan entre sí; cada moneda conserva su propia fila/serie.
- El historial de Royals no es reconstruible con el modelo actual, por lo que se etiqueta como valor actual y no se inventa comparación.
- La conversión de compra se obtiene de órdenes capturadas; los eventos del navegador no sustituyen la contabilidad.
- Fechas se filtran con límites UTC derivados de `admin.publishing_timezone`; la UI muestra la zona explícitamente.
- Los CSV exportan solo agregados y claves/títulos de contenido, nunca PII.
