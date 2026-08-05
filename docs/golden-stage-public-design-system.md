# Reny Golden Stage — referencia de diseño público

Estado: aprobado a partir de Home y Royals, agosto de 2026.

Esta guía es la fuente de verdad para rediseñar las demás páginas públicas de RenyRenteria.com. El objetivo es que todo el website comparta el mismo escenario dorado sin perder el layout, contenido, navegación, CMS ni comportamiento que ya existen.

## 1. Principio central

- Cada página se trabaja como un **reskin minucioso**, no como un rebuild.
- Se conserva la estructura, orden, contenido, rutas, estados, permisos, datos y lógica del CMS.
- No se agregan, eliminan ni reposicionan bloques funcionales durante el reskin sin una aprobación separada.
- Home es la referencia para el fondo dorado, las luces, el sidebar, el logo blanco, los botones y el Royal Pass de desktop.
- Royals es la referencia para las superficies de comunidad, contenido editorial, Live Chat y la navegación móvil flotante.
- Las formas son rectangulares o cuadradas con bordes redondeados. No usar blobs, curvas decorativas grandes ni cápsulas exageradas como lenguaje principal.
- El resultado debe sentirse como un escenario: cálido, teatral, energético y premium, sin perder legibilidad.

## 2. Paleta oficial

### Dorados principales

- `#AC7031` — dorado profundo; sombras, extremos del gradiente y bordes oscuros.
- `#E7A551` — dorado cálido; color base del fondo.
- `#E7AA51` — dorado brillante; transiciones, acentos y luces.
- `#FFE499` — dorado claro; highlights, iconos activos, bordes iluminados y texto destacado.

El gradiente principal aprobado es:

```css
linear-gradient(135deg, #E7A551 0%, #FFE499 30%, #E7AA51 64%, #AC7031 100%)
```

### Oscuros y texto

- `#17110B` — tinta principal y superficie casi negra.
- `#2B1F14` — marrón oscuro secundario.
- `#FFF8E5` — texto crema principal sobre fondos oscuros.
- `rgba(255, 248, 229, 0.72)` — texto crema secundario.

### Rojos Royal Pass

- `#45040F` — vino profundo.
- `#8C102B` — borgoña medio.
- `#BD2342` — rojo brillante.
- `#65091D` — cierre oscuro del gradiente.
- `#C51D35` — acento rojo funcional para estados Live, badges o acciones puntuales.

El Royal Pass usa:

```css
linear-gradient(112deg, #45040F 0%, #8C102B 42%, #BD2342 68%, #65091D 100%)
```

### Reglas de color

- El fondo general siempre es dorado; no convertir páginas completas a blanco, beige o negro plano.
- Los fondos oscuros se usan como superficies de contraste encima del dorado.
- El rojo se reserva para Royal Pass, estados Live, alertas o acciones que realmente necesitan énfasis.
- No introducir morados, azules o verdes como color de marca principal.
- Texto normal: contraste mínimo 4.5:1. Texto grande e iconografía esencial: mínimo 3:1.

## 3. Fondo dorado y textura

- Todas las páginas públicas deben envolver el contenido con `.home-shell` o reutilizar exactamente sus tokens y comportamiento.
- El fondo combina el gradiente principal con una línea diagonal sutil y textura de grano al `20%` de opacidad.
- El gradiente se mueve lentamente entre posiciones con una duración de `18s`, `ease-in-out`, alternada e infinita.
- El contenido siempre queda por encima de luces y textura mediante capas; ningún efecto decorativo puede bloquear clics.
- No usar imágenes fotográficas como background general.

Configuración de referencia:

```css
background:
    linear-gradient(115deg, rgba(172, 112, 49, 0.16) 0 1px, transparent 1px 8.5rem),
    linear-gradient(135deg, #E7A551 0%, #FFE499 30%, #E7AA51 64%, #AC7031 100%);
background-size: 100% 100%, 170% 170%;
animation: home-molten-gold 18s ease-in-out infinite alternate;
```

## 4. Luces de escenario

- Deben existir tres spotlights montados desde el borde superior.
- Posiciones horizontales: `18%`, `50%` y `82%`.
- Cada luz nace en un fixture oscuro visible y se abre hacia abajo como un cono suave.
- Desktop: ancho entre `27rem` y `44rem`, altura aproximada `88vh`.
- Mobile: las luces se adaptan al viewport sin causar overflow; pueden acortarse cuando el contenido necesita más contraste.
- Opacidad visual aproximada entre `0.18` y `0.44`; usar `mix-blend-mode: screen` y blur suave.
- Barridos independientes: `10s`, `13s` y `11.5s`, con delays distintos para evitar movimiento sincronizado.
- Las luces son decorativas: `aria-hidden="true"` y `pointer-events: none`.
- Con `prefers-reduced-motion: reduce`, animaciones y transiciones se reducen a `0.01ms` y una sola iteración.

## 5. Logo y marca

- Usar siempre el logo blanco aprobado: `images/reny-renteria-logo-white.png`.
- Desktop: ancho de referencia `10.7rem`, sin filtros ni blend modes.
- Mobile: ancho máximo `7.125rem` o `48vw`.
- El logo enlaza a Home y tiene `aria-label="Reny Renteria home"`.
- Sobre mobile, el logo puede vivir en una superficie oscura con borde dorado tenue y radio de `0.9rem`.
- No recolorear el logo por página.

## 6. Navegación

### Desktop

- Mantener el sidebar fijo/sticky de `17.125rem` de ancho y `100vh` de alto.
- Fondo: gradiente vertical de `rgba(23,17,11,.99)` a `rgba(43,31,20,.96)`.
- Borde derecho dorado claro al `42%` y sombra cálida hacia el contenido.
- Orden del menú: Royals, Videos, Music, Shows, Store.
- Cada entrada conserva icono más label; labels en mayúsculas, `0.875rem`, peso `800`, tracking `0.12em`.
- Estado normal: crema al `62%`.
- Hover, focus y página activa: `#FFE499`.
- Hover/focus puede mover el elemento `0.125rem` y mostrar una línea dorada sutil; el movimiento no debe alterar el layout.
- La página activa usa `aria-current="page"`.
- El member card permanece al fondo del sidebar con borde dorado, superficie oscura translúcida y link dorado.

### Mobile

- Breakpoint principal: `53.75rem` / `860px`.
- Ocultar el sidebar y usar cinco iconos en una cuadrícula de columnas iguales.
- Mantener exactamente el mismo orden y las mismas rutas del menú de desktop.
- Usar el formato flotante de Royals para páginas interiores: inset lateral `0.7rem`, min-height `4.15rem`, padding `0.45rem`, radio `1rem` y fondo `rgba(20,14,9,.94)`.
- Estado normal: crema al `48%`.
- Estado activo: gradiente sutil rojo/dorado y icono `#FFE499`.
- Cada enlace debe tener un target mínimo de `44 × 44px`.
- Respetar `env(safe-area-inset-bottom)` y dejar suficiente padding final para que ningún contenido o composer quede detrás del menú.

## 7. Tipografía

- Familia: `DM Sans`, con fallbacks del sistema.
- Títulos principales: peso `800–950`, line-height entre `0.9` y `1.05`; mayúsculas solo cuando la sección actual ya las usa.
- Títulos de sección: color crema sobre oscuro o `#17110B` sobre superficies claras.
- Labels/eyebrows: `0.625–0.875rem`, peso `800–950`, tracking `0.08–0.12em` y uppercase.
- Body: mínimo `1rem` en formularios móviles y no menor de `0.875rem` para texto legible.
- Texto auxiliar: crema al `72%` sobre oscuro; nunca bajar la opacidad hasta perder contraste.
- Los títulos destacados pueden usar texto con gradiente dorado, pero deben conservar fallback legible.

## 8. Contenedores, secciones y cards

- Radio principal de secciones: `1.2rem` desktop y `0.9–1rem` mobile.
- Radio de cards internas y media: `0.62–0.9rem`.
- Borde estándar: `1px solid rgba(255, 228, 153, 0.28–0.50)`.
- Sombra estándar: cálida y suave; evitar sombras negras duras o halos excesivos.
- Superficie oscura recomendada:

```css
linear-gradient(145deg, rgba(23,17,11,.98), rgba(43,31,20,.94))
```

- Superficie clara recomendada:

```css
linear-gradient(135deg, rgba(255,248,229,.94), rgba(231,165,81,.50))
```

- Las secciones grandes pueden usar una línea animada de `0.28rem` en el borde superior.
- En desktop, usar profundidad, bordes y padding de `1.5rem` cuando el layout actual lo permite.
- En mobile, simplificar sombras y padding; no convertir la estructura ni cambiar el orden del contenido.
- Imágenes y videos usan `object-fit: cover`, mantienen su aspect ratio y nunca se estiran.
- Hover puede elevar o escalar como máximo `1–2%`; en mobile se elimina el movimiento.

## 9. Botones y controles

### Primario dorado

- Gradiente: `#AC7031 → #E7A551 42% → #FFE499 76% → #E7AA51`.
- Texto: `#17110B`.
- Borde: dorado claro al `88%`.
- Radio: `0.72rem`.
- Min-height: `2.75rem` / `44px`.
- Puede tener shimmer lento y highlight al hover/focus.

### Secundario oscuro

- Fondo `rgba(23,17,11,.72–.94)`.
- Texto `#FFE499` o `#FFF8E5`.
- Borde dorado entre `28%` y `38%`.
- Mismo radio y mínimo de `44px`.

### Rojo

- Usarlo solo para estados Live, Royal Pass o acciones críticas.
- No mezclar un botón rojo dentro de una card roja sin borde, sombra o contraste suficiente.

### Estados y accesibilidad

- Todo botón, enlace o control táctil debe medir mínimo `44 × 44px`.
- Focus visible de `2px` en `#FFE499`, con offset suficiente.
- Disabled conserva label legible y usa opacidad aproximada `0.46–0.68`; nunca aparenta estar activo.
- El icono no sustituye un nombre accesible. En mobile, labels ocultos deben permanecer como `.sr-only`.

## 10. Royal Pass

### Desktop

- Reutilizar el componente compartido `<x-royal-pass-banner>`; no crear versiones nuevas por página.
- Debe verse exactamente como Home: ancho máximo `52rem`, centrado, grid de copy más CTA, padding `0.75rem 1.5rem`, borde dorado de `2px` y radio `1.2rem`.
- Fondo rojo Royal Pass, texto crema, palabras “Royal Pass” en `#FFE499` y CTA dorado.
- El shimmer diagonal dura `7s` y no debe interferir con el texto.
- Las fotos permanecen ocultas en desktop.

### Mobile

- Para páginas interiores, usar la adaptación compacta sin fotos: copy centrado, título, descripción y CTA de ancho completo.
- El CTA mide mínimo `44px` de alto.
- El banner se centra y no supera `24.375rem`.
- Home puede conservar su composición móvil aprobada con imágenes; Royals y las nuevas páginas interiores deben usar `:show-images="false"` salvo nueva aprobación explícita.
- Nunca repetir el banner más de una vez por página.
- Usuarios con Royal Pass activo no deben ver el upsell.

## 11. Media y movimiento

- Video de Home puede iniciar automáticamente solo con `autoplay=1`, `mute=1` y `playsinline=1`.
- Siempre mostrar controles para pausar y permitir que el usuario active el sonido.
- No iniciar audio automáticamente.
- Animaciones decorativas deben ser lentas y no mover contenido interactivo.
- No usar parallax fuerte, flashes rápidos ni loops que distraigan del contenido.

## 12. Comportamiento responsive

- Diseñar y validar desde `320px` hasta `1440px` como mínimo.
- Viewports obligatorios de UAT: `320`, `375`, `430`, `768`, `1024` y `1440px`.
- No permitir horizontal overflow, clipping de texto, imágenes estiradas ni botones fuera del viewport.
- La navegación fija debe dejar visible el final de cada página.
- Formularios y Live Chat deben mantener el composer completo por encima del menú fijo, incluso con viewport de `500px` de alto para simular el teclado.
- En cards equivalentes, conservar dimensiones equivalentes. Ejemplo aprobado: Concert y Album deben verse del mismo tamaño en Home móvil.
- Los layouts de desktop se apilan en mobile; no se elimina contenido para resolver espacio salvo elementos puramente decorativos.

## 13. Aplicación por página

### Videos

- Preservar videos CMS, filtros, detalles, navegación y permisos.
- Fondo dorado y spotlights globales; reproductores sobre superficies oscuras.
- Thumbnails con radio `0.9rem`, borde dorado tenue y títulos crema.
- Royal Pass interior sin fotos en mobile.

### Music y detalles de álbum

- Preservar albums, playlists, tracks, player persistente, estados de acceso y links.
- Usar superficies oscuras para listas y player; superficies claras solo como contraste controlado.
- Artwork cuadrado con radio `0.62–0.9rem`; no modificar aspect ratio.
- El player debe despejar la navegación móvil y safe areas.

### Shows

- Preservar eventos CMS, fechas, venue, RSVP, compra y estados.
- Reutilizar el formato aprobado de la card Concert de Home.
- Botones RSVP/compra dorados, mínimo `44px`; estados Live/sold out pueden usar rojo.

### Store y checkout

- Preservar catálogo, monedas, Royal Pass, carrito/checkout, PayPal y confirmaciones.
- Productos en cards oscuras con bordes dorados; precio y CTA con jerarquía clara.
- Checkout mantiene formularios legibles y no usa movimiento detrás de campos sensibles.

### Photos

- Preservar albums, orden CMS, lightbox, permisos y descarga/compra cuando aplique.
- Grid existente sobre fondo dorado; fotos sin overlays dorados que alteren el color de la imagen.
- Controles de lightbox oscuros con iconos dorados y targets de `44px`.

### Royals y clubs

- Preservar feed, posts, polls, comentarios, shares, gating, Live Chat y clubs.
- Cards editoriales oscuras, texto crema y acentos rojos/dorados.
- Tabs mobile con navegación por teclado, `aria-selected`, flechas, Home/End y focus correcto.
- Composer, comentarios y botones deben ser legibles y medir mínimo `44px`.

### Account, login y estados de acceso

- Mantener formularios y seguridad sin alterar su flujo.
- Usar panel oscuro centrado, logo blanco, inputs claros y focus dorado.
- Mensajes de error usan rojo con texto y contraste suficientes; éxito puede usar crema/dorado sin introducir otro color principal.

## 14. CMS, contenido y rutas

- El CMS sigue controlando títulos, textos, media, orden, publicaciones, precios, eventos y estados.
- No hardcodear contenido que ya proviene del CMS.
- Conservar canonical URLs, compatibilidad de rutas y deep links existentes.
- Royals usa `/royals` como canonical; `/community` se mantiene solo como compatibilidad.
- El reskin público no cambia el diseño del admin/CMS salvo requerimiento separado.

## 15. Checklist de aceptación por página

- Layout, contenido y orden comparados contra la página actual.
- CMS y estados guest/free/Royal comprobados.
- Desktop sidebar y mobile bottom nav correctos.
- Logo blanco, fondo dorado y tres spotlights presentes.
- Colores y gradientes usan exclusivamente los tokens aprobados.
- Títulos, body y labels cumplen contraste y tamaño.
- Botones y controles miden mínimo `44 × 44px`.
- Focus, teclado, reduced motion y nombres accesibles comprobados.
- Sin overflow en `320`, `375`, `430`, `768`, `1024` y `1440px`.
- Contenido final y composers despejan la navegación fija y safe areas.
- Interacciones reales pasan: reproducción, CTA, share/clipboard, comentarios, polls, chat, compra o RSVP según la página.
- Pruebas PHP, JS, lint, build y CI en verde.
- Screenshots desktop y mobile adjuntos antes de merge.
- Cualquier cambio de estructura o contenido queda fuera del reskin y requiere aprobación separada.
