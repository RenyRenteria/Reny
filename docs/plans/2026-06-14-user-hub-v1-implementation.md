# User Hub V1 Implementation Plan

Fecha: 2026-06-14

## PR 1 - Manifesto and QA contract

Objetivo:

- Versionar la vision, reglas de negocio, criterios QA y alcance fuera de V1.

Entregables:

- `docs/user-hub-v1-manifesto.md`
- `docs/plans/2026-06-14-user-hub-v1-implementation.md`

Validacion:

- Documento contiene estados de membresia, reglas de refund/cancelacion, puntos, tickets, billing y criterios QA.

## PR 2 - Account data foundation

Objetivo:

- Agregar la base de datos y modelos para que `/account` no dependa de estado local ni mocks.

Entregables:

- Campos de perfil en `users`: username, avatar, pais, idioma, zona horaria, bio corta y moneda preferida.
- `user_unlocks` para biblioteca/compras desbloqueadas.
- `billing_profiles` o equivalente para estado PayPal/backend sincronizado.
- Relaciones de usuario para ordenes, unlocks y billing.
- Tests de persistencia y acceso basico.

Validacion:

- Usuario puede tener compras/unlocks persistidos.
- Compras digitales quedan asociadas aunque Royal expire.
- Billing se lee desde backend.

## PR 3 - `/account` dashboard V1

Objetivo:

- Reemplazar la pagina minima actual por el dashboard User Hub V1.

Entregables:

- Perfil principal con avatar, nombre, username y badge `Active Royal Member`.
- Upcoming events debajo del perfil.
- Productos/unlocks despues de eventos.
- Billing information despues de productos.
- Puntos y leaderboard summary.
- Estados vacios claros.

Validacion:

- Usuario nuevo ve estados vacios sin errores.
- Usuario Royal activo ve badge correcto.
- Usuario Open ve CTA de Royal Pass.

## PR 4 - Points ledger and leaderboard

Objetivo:

- Implementar ledger append-only y leaderboard V1.

Entregables:

- `point_ledger_entries`.
- Servicio server-side de posting con idempotencia.
- Estados `pending`, `posted`, `reversed`, `void`.
- Balance calculado o persistido desde entradas `posted`.
- Leaderboard visible/consultable.

Validacion:

- Cliente no tiene ruta para mutar ledger.
- Entradas duplicadas con misma idempotency key no duplican puntos.
- Leaderboard ignora `pending`, `reversed` y `void`.

## PR 5 - Events, tickets, QR and check-in

Objetivo:

- Agregar eventos proximos, tickets internos y QR/codigo opaco.

Entregables:

- `events`.
- `tickets`.
- Generacion de codigo opaco o firmado.
- Estados de ticket: `reserved`, `confirmed`, `cancelled`, `refunded`, `checked_in`, `expired`.
- Datos visibles requeridos en `/account`.

Validacion:

- Ticket muestra evento, fecha, zona horaria, venue, RSVP, check-in y holder.
- QR/codigo no contiene datos personales legibles.
- Check-in valida codigo server-side.

## PR 6 - PayPal source-of-truth hardening

Objetivo:

- Reducir incongruencias entre PayPal, backend y frontend.

Entregables:

- Estados `processing`, `completed`, `failed`, `refunded` para ordenes.
- Mensajes claros cuando PayPal/backend/frontend fallen.
- Reconciliacion desde backend cuando el frontend falle despues de confirmacion PayPal.
- Tests de refund, cancelacion y acceso.

Validacion:

- Frontend no marca `completed` sin confirmacion backend.
- Refund revoca acceso inmediatamente.
- Cancelacion conserva acceso hasta fin del periodo pagado.
- Hub recupera estado real desde backend al cargar.
