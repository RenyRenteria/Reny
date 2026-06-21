# Project 3 Admin CMS Implementation Plan

Fecha: 2026-06-15

## PR 1 - QA contract and implementation split

Objetivo:

- Versionar alcance cerrado, reglas QA, riesgos de release y slices de implementacion.

Entregables:

- `docs/project-3-admin-cms-qa-contract.md`
- `docs/plans/2026-06-15-project-3-admin-cms-implementation.md`

Validacion:

- Documento contiene roles, estados editoriales, visibilidad, release windows, Mux, storage, limites de upload, auditoria y criterios QA.
- Documento no contiene secretos.

## PR 2 - Admin auth, RBAC and shell

Objetivo:

- Crear `/admin` separado del sitio publico con autenticacion real y permisos por rol.

Entregables:

- Login admin con email/password.
- Sesiones con expiracion.
- Roles `admin`, `artist_admin` y `editor` o equivalente.
- Middleware de acceso admin.
- Shell de admin responsive.
- Bloqueo de publicacion para editor.

Validacion:

- Usuario publico no entra a `/admin`.
- Editor puede guardar pero no publicar.
- Admin/artista puede aprobar y publicar.
- Requests manipulados no saltan RBAC.

## PR 3 - Editorial domain, workflow and taxonomy

Objetivo:

- Agregar modelos/tablas base para tipos de contenido, workflow editorial, visibilidad, release windows y taxonomia.

Entregables:

- Content records para Song, Album musical, Album/deluxe, Video, Photo, Galeria, Post, Poll, Product, Event, Drop y exclusivo.
- Estados `draft`, `scheduled`, `published`, `archived`.
- Flag `needs_approval`.
- Visibilidades `open`, `member`, `royal`, `purchased`.
- Release windows por audiencia.
- Categorias, tags, campanas y paises.
- Audit log editorial.

Validacion:

- Cada tipo puede crearse como draft.
- `scheduled` no aparece antes de fecha.
- Ventanas por audiencia se evalua por usuario.
- Auditoria registra actor, accion y timestamp.

## PR 4 - Media library, app-server storage and Mux

Objetivo:

- Implementar media library reutilizable con storage local de app server y soporte de video corto en Mux.

Entregables:

- Upload de imagen, audio, video corto, documento, asset de producto y thumbnail.
- Validacion de MIME, extension, tamano por archivo y tamano por lote.
- Alt text para imagenes publicas.
- Metadata reusable por contenido.
- Integracion Mux via env vars.
- Webhooks Mux con validacion de firma.
- Estados de procesamiento de media.

Validacion:

- Limites V1 aprobados se aplican.
- Upload fallido no deja registros corruptos.
- Disco/permiso/error de storage devuelve mensaje claro.
- Mux cubre upload, transcoding, playback y errores.
- Secretos Mux rotados y fuera del repo.

## PR 5 - Editorial forms, preview and scheduling

Objetivo:

- Construir formularios admin por tipo de contenido con preview privada y programacion editorial.

Entregables:

- Formularios para todos los tipos V1.
- Autosave o guardado defensivo si se implementa en V1.
- Seleccion/reuso desde media library.
- Preview privada.
- Programacion en timezone Panama.
- Validaciones por tipo de contenido.

Validacion:

- Editor crea/prepara una pieza de cada tipo.
- Admin publica una pieza de cada tipo.
- Preview no es publica ni indexable.
- Fecha programada usa timezone Panama.

## PR 6 - Public CMS consumption, cache fallback and access gates

Objetivo:

- Hacer que el sitio publico consuma el CMS como fuente principal con fallback al ultimo contenido publicado cacheado.

Entregables:

- Music, Videos, Photos, Store y Community consumen contenido CMS donde aplique.
- Cache del ultimo contenido publicado.
- Fallback controlado si CMS/storage falla.
- Gates backend para `open`, `member`, `royal` y `purchased`.
- Soporte de unlocks comprados desde Proyecto 2.

Validacion:

- CMS caido no deja sitio publico vacio.
- `open` a `member` bloquea UI y backend.
- `purchased` sobrevive a expiracion Royal.
- Archivar contenido no rompe paginas existentes.

## PR 7 - Admin regression and release gate suite

Objetivo:

- Cubrir flujos criticos con tests automatizados y checklist de ship/no-ship.

Entregables:

- Tests feature para RBAC admin.
- Tests de workflow editorial.
- Tests de release windows.
- Tests de visibility gates.
- Tests de upload failure.
- Tests de Mux webhook.
- Checklist QA de release.

Validacion:

- Suite automatizada cubre criterios QA de cierre.
- Playwright o UAT equivalente valida flujos admin/publicos criticos.
- Reporte final incluye veredicto ship/no-ship y bloqueadores.

## Nota sobre PR existente

El PR `#7 Add admin panel for site content` esta abierto pero en conflicto y cubre solo hero, albums y singles. No debe tratarse como implementacion completa de Proyecto 3 sin rebase y revision contra este contrato.
