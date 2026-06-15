# User Hub V1 Manifesto

Fecha de definicion: 2026-06-14

## Vision

User Hub V1 es el centro de cuenta de cada fan en RenyRenteria.com. Debe mostrar el estado real de su relacion con la plataforma: perfil, Royal Pass, eventos, compras, billing, contenido desbloqueado, puntos y seguridad basica.

La ruta principal del Hub es `/account`.

El Hub no es una pagina de marketing. Es una superficie operativa donde el usuario puede confirmar que lo que compro, desbloqueo o gano existe en su cuenta sin depender de recargas manuales ni estados locales del navegador.

## Estados de acceso

Todos los usuarios tienen cuenta. No todos tienen acceso Royal activo.

Estados de membresia:

- `active`
- `grace`
- `on_hold`
- `expired`
- `cancelled`
- `refunded`

Solo `active` y `grace` conceden acceso Royal.

Reglas:

- `grace` dura 2 dias.
- Si el usuario cancela, conserva acceso hasta el final del periodo pagado.
- Si hay refund, el acceso se revoca inmediatamente.
- El usuario expirado conserva cuenta, compras, puntos, comentarios e historial.
- El contenido premium debe protegerse en backend, no solo ocultarse en UI.

## Dashboard `/account`

El primer dashboard debe priorizar:

1. Perfil principal:
   - Foto/avatar.
   - Nombre.
   - Username publico.
   - Badge visual `Active Royal Member` cuando aplique.
2. Upcoming events.
3. Productos comprados o desbloqueados.
4. Billing information.
5. Puntos y leaderboard.
6. Preferencias y seguridad basica.

Estados vacios requeridos:

- Sin eventos proximos.
- Sin compras o unlocks.
- Sin billing activo.
- Sin puntos.
- Sin membresia activa.

## Perfil

Campos de perfil esperados:

- Nombre.
- Foto/avatar.
- Pais.
- Idioma.
- Zona horaria.
- Username publico.
- Bio corta.

Registro V1:

- Email o telefono.
- Password.
- Username, nombre, email y pais cuando el flujo lo permita.
- Email verification obligatoria.
- Magic link permitido.
- Login social fuera de V1.

## Billing y PayPal

PayPal/backend es la fuente de verdad para billing y pagos.

Reglas:

- El frontend nunca debe marcar una compra como final si PayPal/backend no confirma.
- Si PayPal confirma y el frontend falla, el backend conserva la compra y el Hub debe recuperarla al volver a cargar.
- Si el frontend recibe exito visual pero PayPal no confirma, la compra debe quedar en `processing` o `failed`, nunca en `completed`.
- Failed payment deja membresia `on_hold` y despues entra a `grace` si aplica segun la politica de 2 dias.
- Billing debe mostrar estado derivado del procesador o backend sincronizado, no solo estado local.
- El usuario debe recibir un mensaje claro cuando una parte del flujo falle y debe saber que accion tomar.

## Compras, biblioteca y unlocks

Compras digitales compradas/desbloqueadas siguen accesibles aunque el usuario no tenga Royal Pass activo.

La biblioteca debe incluir, cuando aplique:

- Canciones.
- Albums digitales.
- Videos.
- Drops comprados o desbloqueados.
- Tickets y eventos.

Cada unlock debe tener historial trazable con fuente, orden o evento que lo genero.

## Puntos

En V1 los puntos sirven solo para leaderboard. No hay redenciones reales.

Los puntos:

- No expiran.
- Se ganan por comentarios, ver videos, chatear en community, responder polls y escuchar musica.
- Se publican desde backend despues de validar la accion.
- No pueden ser creados, editados ni borrados desde cliente.

Puntos por compra quedan fuera de V1. Si se agregan despues, deben quedar `pending` hasta confirmacion PayPal/backend.

Ledger de puntos:

- Append-only.
- Server-side.
- Idempotente.
- Auditable.

Campos del ledger:

- `id`
- `user_id`
- `event_type`
- `source_type`
- `source_id`
- `delta`
- `status`
- `balance_after`
- `idempotency_key`
- `created_at`
- `posted_at`
- `reversed_at`
- `actor_type`
- `actor_id`
- `reason`
- `metadata`

Estados del ledger:

- `pending`
- `posted`
- `reversed`
- `void`

## Leaderboard

Leaderboard V1 usa el balance de puntos publicado por backend.

Debe evitar:

- Sumar entradas `pending`.
- Permitir que el cliente altere ranking.
- Duplicar puntos por reintentos o eventos repetidos.

## Eventos, tickets y QR

Tickets, QR y check-in se generan internamente.

Datos visibles por ticket:

- `ticket_id`
- Codigo o QR.
- Nombre del evento.
- Fecha.
- Hora.
- Zona horaria.
- Venue.
- Direccion.
- RSVP.
- Estado de check-in.
- Estado del ticket.
- Nombre del holder.
- Fecha de compra o reserva.

Estados de ticket:

- `reserved`
- `confirmed`
- `cancelled`
- `refunded`
- `checked_in`
- `expired`

QR/codigo:

- Debe ser opaco o firmado.
- Debe validarse server-side.
- No debe contener datos personales legibles.

## Community

Open puede ver previews. Royal activo puede participar.

Reglas V1:

- Todos pueden ver previews de community.
- Chatear, crear o interactuar requiere Royal Pass activo.
- Polls: todos pueden ver resultados, solo Royal activo puede votar.

## Preferencias y seguridad

Preferencias esperadas:

- Notificaciones.
- Newsletter.
- Idioma.
- Moneda preferida.

Seguridad V1:

- Email/password.
- Sesion autenticada.
- Solicitud manual de exportacion/eliminacion de datos.

Fuera de V1:

- 2FA.
- Exportacion/eliminacion self-serve.
- Redenciones reales de puntos.
- Login social.

## Criterios QA de cierre

- Despues de una compra confirmada, el producto aparece en `/account` sin recarga manual de datos.
- Despues de comprar Royal Pass, la membresia cambia a estado activo y desbloquea contenido In.
- Refund revoca acceso Royal inmediatamente.
- Cancelacion conserva acceso hasta el final del periodo pagado.
- Albums, videos y drops comprados siguen accesibles aunque Royal Pass expire.
- El ledger de puntos no se puede alterar desde cliente.
- El leaderboard usa solo puntos `posted`.
- Billing muestra la fuente de verdad del backend/PayPal, no solo estado local.
- Tickets muestran QR/codigo interno y estado correcto de RSVP/check-in.
- QR/codigo no expone datos personales legibles.
- Usuarios nuevos ven estados vacios claros.

## Riesgos de release

- Incongruencia entre PayPal, backend y frontend.
- Acceso premium protegido solo en UI.
- Refunds que no revocan permisos.
- Compras digitales perdidas al expirar Royal Pass.
- Ledger mutable o manipulable desde cliente.
- QR con datos personales embebidos.
- Dashboard que depende de datos mock sin fuente backend.
