# Project 3 Admin CMS QA Contract

Fecha de definicion: 2026-06-15

## Veredicto

Proyecto 3 queda cerrado para planificacion de QA, pero no queda aprobado para ship hasta que los secretos de Mux expuestos en Slack sean rotados y cargados solo como secrets/env vars.

Este contrato cubre el CMS/admin de RenyRenteria.com. No es multi-app.

## Objetivo

Construir un admin CMS separado del sitio publico para que el equipo admin pueda cargar, organizar, publicar, programar y archivar contenido sin tocar codigo.

El website debe consumir el CMS como fuente principal de contenido. Si el CMS falla, el sitio publico debe mostrar el ultimo contenido publicado cacheado.

## Usuarios y permisos

Usuarios V1:

- Artista/admin.
- Editor.

Reglas:

- V1 requiere login real con email/password, sesiones, expiracion y bloqueo por rol.
- Editor puede crear, editar, preparar y guardar contenido.
- Editor no publica directamente.
- Editor marca contenido con `needs_approval`.
- Artista/admin aprueba, publica, programa, archiva y retira contenido.
- Toda accion editorial relevante debe quedar auditada.

## Estados editoriales

Estados requeridos:

- `draft`
- `scheduled`
- `published`
- `archived`

Campo adicional requerido:

- `needs_approval`

Reglas:

- `draft` no aparece en UI publica.
- `scheduled` no aparece antes de su fecha efectiva.
- `published` aparece segun reglas de visibilidad.
- `archived` deja de aparecer en listados publicos, pero no debe romper paginas o referencias historicas.
- V1 no requiere eliminacion fisica.

## Visibilidad y acceso

Visibilidades V1:

- `open`
- `member`
- `royal`
- `purchased`

Reglas:

- `open` es visible sin cuenta o sin Royal activo.
- `member` y `royal` requieren cuenta con acceso Royal activo.
- `purchased` mantiene acceso aunque el Royal Pass expire.
- Cambiar contenido de `open` a `member` o `royal` debe bloquearlo en la UI publica y en backend.
- La proteccion no puede depender solo de ocultar UI.

## Release windows

El CMS debe soportar calendario por audiencia, no solo `scheduled_at`.

Caso requerido:

- Una pieza puede salir primero para `member` o `royal`.
- La misma pieza puede volverse `open` despues.

QA debe validar que cada audiencia vea el contenido solo dentro de su ventana correspondiente.

## Tipos de contenido

Contenido V1:

- Song.
- Album musical.
- Album/deluxe de contenido especial.
- Video.
- Photo.
- Album visual o galeria.
- Post.
- Poll.
- Product.
- Event.
- Drop.
- Contenido exclusivo.

Reglas por tipo:

- Song: audio, portada, duracion, lyrics, credits, release date, preview/open, full/in.
- Album musical: tracks, portada, narrativa, assets, precio opcional y release cycle.
- Album/deluxe: pagina o paquete separado del album musical.
- Video: YouTube embed, thumbnail, categoria, premium/free y playlist.
- Video corto: Mux con upload, transcoding, playback, webhooks y errores de procesamiento.
- Photo/galeria: imagen, caption, ubicacion, tags, acceso y agrupacion.
- Post: texto, media, links, visibilidad, comentarios y pinning.
- Poll: pregunta, opciones, fecha, elegibilidad y resultados publicos/privados.
- Product: digital, physical, subscription, drop y bundle.
- Event: fisico, digital o listening session, inventario, precio y RSVP/ticket.

## Media library

La media library debe ser reutilizable entre Photos, Store, Videos, Community y contenido editorial.

Tipos de archivo:

- Imagen.
- Audio.
- Video corto.
- Documento.
- Assets de producto.
- Thumbnail.

Limites V1 aprobados:

```text
Imagen             50 MB
Audio              1 GB
Video corto/Mux    5 GB o 20 min
Documento          100 MB
Asset producto     250 MB
Thumbnail          20 MB
Carga por lote     10 GB total
```

Validaciones requeridas:

- Tipo MIME real.
- Extension permitida.
- Tamano por archivo.
- Tamano por lote.
- Thumbnail requerido cuando aplique.
- Alt text requerido en imagenes publicas.
- Errores claros en uploads fallidos.
- Upload fallido no debe dejar registros corruptos.
- Archivos huerfanos deben limpiarse o quedar marcados para limpieza.

## Mux

Video corto usa Mux.

Bloqueador de seguridad:

- Los secretos de Mux compartidos en Slack deben rotarse antes de QA/prod.
- Los valores no deben documentarse ni versionarse.
- La app debe consumirlos solo desde secrets/env vars.

QA Mux requerido:

- Upload exitoso.
- Error de upload.
- Transcoding exitoso.
- Error de transcoding.
- Webhook valido.
- Webhook invalido o con firma incorrecta.
- Playback disponible despues de procesamiento.
- UI clara mientras procesa.

## Storage

Storage V1 significa el mismo backend/app server.

QA debe cubrir:

- Escritura correcta en storage.
- Permisos de filesystem.
- Disco lleno o quota excedida.
- Archivo removido o inaccesible.
- Reintento de upload.
- Limpieza de registros/archivos parciales.
- Recovery sin romper UI publica.

## Taxonomia

Taxonomia V1:

- Categorias.
- Tags.
- Campanas.
- Paises.
- Release windows.

Reglas:

- Taxonomia debe ser reutilizable por tipos de contenido.
- Pais debe poder alimentar reglas de grupos por pais cuando aplique.
- Tags/categorias no deben romper URLs o filtros si se renombran.

## Preview

Preview privada antes de publicar es obligatorio.

QA debe validar:

- Preview de `draft`.
- Preview de `scheduled`.
- Preview por visibilidad.
- Preview con assets no publicados.
- Preview no indexable y no accesible por usuario publico sin permiso.

## Auditoria

Auditoria minima:

- Quien creo.
- Quien edito.
- Quien marco `needs_approval`.
- Quien aprobo.
- Quien publico.
- Quien programo.
- Quien archivo.
- Quien retiro contenido.

Cada registro debe conservar timestamp, actor y accion.

## Criterios QA de cierre

- Un editor puede crear y preparar una pieza de cada tipo sin intervencion tecnica.
- Un admin/artista puede aprobar y publicar una pieza de cada tipo.
- Un contenido programado no aparece antes de su fecha.
- Una pieza con ventana `member` primero y `open` despues respeta ambas fechas.
- Cambiar visibilidad de `open` a `member` o `royal` bloquea UI publica y backend.
- `purchased` sigue accesible aunque Royal Pass expire.
- Archivar contenido no rompe paginas que lo referencian.
- Uploads fallidos dan error claro y no dejan registros corruptos.
- Mux procesa video corto y maneja errores de upload/transcoding/webhook.
- Media library reutiliza assets entre Photos, Store, Videos y Community.
- Si CMS falla, el website muestra ultimo contenido publicado cacheado.
- Admin no es accesible sin login valido.
- Editor no puede publicar aunque manipule requests.
- Auditoria registra acciones editoriales criticas.

## Riesgos de release

- Secretos de Mux comprometidos si no se rotan.
- Admin protegido solo por URL larga.
- Editor publicando sin aprobacion.
- Contenido premium protegido solo en frontend.
- Programacion por fecha unica que no soporta ventanas por audiencia.
- Upload grande saturando app server.
- Archivos huerfanos o registros corruptos tras fallos.
- CMS caido dejando sitio publico vacio.
- PR viejo de admin panel en conflicto cubriendo solo un subconjunto del alcance actual.
