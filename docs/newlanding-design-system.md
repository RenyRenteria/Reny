# Reny Renteria · Sistema de diseño del nuevo website

Versión 2 · 17 de noviembre de 2026 · Referencia: https://renyrenteria.com/newlanding

Esta es la referencia del nuevo diseño público de Reny. Consolida el sistema aprobado el 15 de noviembre y las decisiones aplicadas a `/newlanding` hasta el 17 de noviembre: fondo integrado, alturas compactas, colección, Royal Pass, navegación y redes con iconos. La referencia de implementación es el commit `7c03de2`.

Sustituye a Golden Stage como dirección para el nuevo website. Golden Stage documenta las páginas existentes que todavía no se han migrado. La actualización de esta guía no cambia esas páginas ni reemplaza `/`: el nuevo inicio continúa en `/newlanding` hasta aprobar su publicación como homepage.

- Guía visual imprimible: [design-system/newlanding/guide.html](design-system/newlanding/guide.html).
- Valores reutilizables: [tokens.json](design-system/newlanding/tokens.json) y [tokens.css](design-system/newlanding/tokens.css).
- Historial de implementación: [newlanding.md](newlanding.md).
- CSS efectivo del prototipo: [styles.css](../public/newlanding-assets/styles.css).

## 1. Dirección visual

Una experiencia de música, moda y cercanía. La fotografía integrada aporta la emoción; el texto, los controles y el espacio mantienen la claridad. El resultado es cálido, sobrio y expresivo, con pocos colores y sin decoración que compita con Reny.

- Alternar escenario oscuro, colección marfil y cierre íntimo oscuro.
- Usar fondos continuos con texto HTML superpuesto. Preparar una composición específica para móvil cuando lo requiera la fotografía.
- Conservar la firma original como identidad principal. El dragón sigue siendo un sello puntual, no un patrón obligatorio ni un logo nuevo.
- Mantener tarjetas abiertas y rectas, botones discretamente redondeados y separadores finos.
- Concierto y Royal Pass crecen con su contenido. No imponer `100vh` ni alturas que oculten el siguiente bloque; no recortar contenido para lograr una medida.
- El fondo dorado, el gradiente animado y la navegación flotante de Golden Stage no forman parte de este nuevo lenguaje.

## 2. Color

| Nombre | Valor | Uso |
| --- | --- | --- |
| Marfil | `#F5F2EC` | Fondo de colección, texto sobre escenario oscuro |
| Tinta | `#201D1E` | Texto sobre marfil, superficies y paneles |
| Rubí | `#8C242B` | Acción principal, números y acentos puntuales |
| Rubí al pasar el cursor | `#6F1B22` | Estado hover de acciones principales |

Los neutros dominan. El rubí reemplaza al vino `#632E3A` de las primeras propuestas; no se convierte en fondo general de todas las secciones.

Neutros de apoyo extraídos del prototipo: `#191516` (Royal Pass y pie), `#161213` (respaldo del banner), `#E8E4DD` (respaldo de producto), `#68615E` (texto secundario sobre marfil) y `#D4C9C3` (texto secundario sobre oscuro). No son nuevos colores de marca. Bordes: tinta al 15% sobre claro y marfil al 15% sobre oscuro.

Contrastes de colores sólidos: tinta/marfil 14.96:1, marfil/rubí 7.82:1 y gris `#68615E`/marfil 5.43:1. El texto sobre fotografías requiere comprobar el recorte y la capa de oscurecimiento en cada pantalla; estas relaciones no certifican el contraste de toda la página.

## 3. Tipografía e identidad

DM Sans local en pesos 400, 500 y 600. La firma es una imagen original, no una fuente manuscrita sustitutiva. En superficies oscuras se usa su versión clara; el prototipo invierte el PNG negro con CSS. No deformar la proporción.

| Nivel | Escritorio | Móvil | Tratamiento |
| --- | --- | --- | --- |
| TAKEOVER | `clamp(66px, 6.4vw, 104px)` | `clamp(45px, 12.8vw, 78px)` | 600; interlineado 1.05 / 1; tracking −.065em / −.06em |
| Nombre del artista | `clamp(24px, 2.1vw, 35px)` | 24px | 500; tracking −.045em |
| Banner de colección | `clamp(38px, 3.3vw, 52px)` | `clamp(30px, 8vw, 40px)` | 500; interlineado 1.05 / 1.07 |
| Título de colección | 34px | 26px | 500; interlineado 1.08 / 1.1 |
| Título Royal Pass | `clamp(42px, 4.1vw, 64px)` | `clamp(36px, 9.8vw, 52px)` | 500; interlineado 1.04 |
| Nombre de producto | 17px | 14px | 500; interlineado 1.3 |
| Texto Royal Pass | 15px | 14px | 400; interlineado 1.7 / 1.65 |
| Etiquetas y metadatos | 8–12px según componente | 8–11px según componente | Mayúsculas y espaciado solo en rótulos cortos |

Los tamaños son del prototipo, no una regla para reducir todo el texto a microtipografía. Para formularios y páginas de contenido, preservar la lectura y la ampliación de texto. Excepciones existentes: TAKEOVER usa 75px entre 901–1100px; el banner 38px entre 701–1000px y Royal Pass 46px en ese mismo rango.

## 4. Composición y ritmo

Orden del inicio: concierto o último lanzamiento → banner de colección → nueve piezas → enlace a toda la tienda → Royal Pass → pie de página.

El modo de último lanzamiento fue aceptado como alternativa cuando no haya concierto. Aún no está implementado como alternancia en `/newlanding`; requiere contenido y enlaces reales.

| Regla | Valor actual |
| --- | --- |
| Margen horizontal escritorio | `clamp(28px, 5vw, 96px)` |
| Margen horizontal hasta 900px | 24px; 20px hasta 359px |
| Ancho máximo colección y pie | 1456px |
| Ancho máximo banner y Royal Pass | 1600px |
| Banner desde 1001px | Mínimo 280px |
| Banner entre 701–1000px | Mínimo 230px |
| Banner hasta 700px | Mínimo 300px |
| Separación de productos | 40px vertical / 24px horizontal; 32 / 20 hasta 900px; 28 / 12 hasta 700px |

Medidas observadas con DM Sans cargada: a 1440 × 900, concierto 675px, banner 280px y Royal Pass 558px; a 390 × 844, concierto 683px, banner 300px y Royal Pass 705px. Son referencias de ritmo, no alturas fijas. Cambian con la tipografía, el contenido, el ancho y el zoom.

## 5. Componentes

### Cabecera y navegación

Firma a la izquierda. Orden exacto: **Shop → Shows → Royal Pass → Music → My Account**. My Account tiene borde fino y radio de 8px. Escritorio desde 901px; hasta 900px, control «Menú» de 48px y panel con los mismos destinos, filas de 52px y desplazamiento interno si hace falta. Evitar una segunda navegación flotante.

| Etiqueta | Destino actual |
| --- | --- |
| Shop | `/store` |
| Shows | `/shows` |
| Royal Pass | `#royal-pass` |
| Music | `/music` |
| My Account | `/account`, con autenticación existente |

### Escenario de concierto

Arte horizontal en escritorio y arte vertical hasta 900px. Mantener cara, silueta y texto legibles mediante posición de imagen y gradientes. Título, fecha, lugar, cuenta regresiva y acciones son HTML seleccionable, nunca texto incrustado en la imagen.

«Comprar entradas» es la acción principal y «Guardar fecha» descarga un calendario. El contador usa números tabulares, cuatro columnas y separadores de 1px. Actualmente: 2 de octubre de 2027, Rock & Folk, show 20:30, America/Panama. Son datos de evento, no valores de diseño. Si cambian, actualizar vista, JavaScript y `.ics` juntos. Al llegar la hora, el contador se mantiene en cero y cambia su nota.

### Acciones, foco y superficies

- Primaria: marfil sobre rubí, peso 500, radio 8px, sin sombra; altura 52px en escritorio y 50px en móvil. El botón de colección mide 48px. Espacio lateral habitual 24px, 20px en móvil, 14px en pantallas muy estrechas.
- Secundaria: enlace de texto con flecha, área de 48px y subrayado al pasar el cursor. El enlace de cuenta usa borde en vez de relleno.
- Foco visible: contorno de 2px y separación de 6px; marfil sobre oscuro, rubí sobre claro. No eliminarlo.
- Iconos: trazos simples, sin fondos decorativos. Flechas `↗`, `↓`, `↑` acompañan texto; las redes tienen nombre accesible aunque se vean solo iconos.
- Contenedores de producto: esquinas rectas, sin borde de tarjeta ni sombra. Paneles y diálogos: 8px. Campos: conservar los 8px del sistema aprobado; `/newlanding` no incluye formularios y no define sus estados de error/carga.
- El rótulo «Ver pieza» mide 30–36px, pero pertenece al enlace completo de la tarjeta; no replicarlo como control táctil independiente de ese tamaño. El cierre del diálogo es 44 × 44px.

### Colección

Banner corto con «Lleva el Takeover contigo.» y «Explorar la colección» hacia `#coleccion`. Conservar el texto sobre la zona oscura y el modelo a la derecha. Sin rótulos o párrafos adicionales que recuperen la altura eliminada.

Nueve piezas, imágenes cuadradas, tres columnas desde 701px y dos hasta 700px. La novena queda centrada en móvil con el ancho de una columna. Nombre y variante debajo de la imagen, número discreto y acceso a vista ampliada. «Ver toda la tienda» enlaza a `/store`.

La tarjeta abre un `<dialog>` nativo con imagen, nombre y aclaración provisional. Escape, cierre o clic en el fondo cierran y devuelven foco. Sin JavaScript el enlace abre la imagen. No presentar los conceptos como productos comprables: nombres, fotos y piezas siguen provisionales, sin precios o stock inventados.

### Royal Pass

Cierre íntimo sobre `#191516`, retrato real de Reny grabando integrado al fondo y dos columnas desde 701px. En móvil, fotografía arriba e invitación debajo; la franja fotográfica mide `clamp(220px, 29svh, 260px)`.

Título «Hay más de Reny para ti.», descripción breve y dos beneficios: contenido exclusivo y comunidad Royals. «Descubrir Royal Pass» → `/store/checkout/royal`; «Ya soy Royal» → `/royals`. El adelanto sigue siendo una fotografía marcada «Adelanto próximamente». No mostrar reproducción ficticia ni prometer contenido, precios o beneficios no confirmados.

### Pie de página

Firma y, en orden: Contacto → iconos Instagram / YouTube → Preguntas frecuentes → Biography → Políticas. «Redes» no aparece como texto. Cierre con copyright, «La música nos conecta.» y «Volver arriba».

Contacto → `/contacto`; Biography → `/bio`. Redes: `https://www.instagram.com/renyrenteria/` y `https://www.youtube.com/renyrenteriam`. FAQ y Políticas son paneles desplegables, no rutas nuevas. Políticas sigue marcado «Próximamente» hasta contar con el texto aprobado. En móvil el pie se apila; hasta 600px sus enlaces usan dos columnas y se oculta el lema decorativo.

## 6. Movimiento y accesibilidad

Hover principal: 180ms. Vista de producto: 200ms. Zoom de imagen de producto: escala 1.035 durante 350ms, solo con dispositivo que admita hover. Desplazamiento suave para anclas; con `prefers-reduced-motion: reduce`, desactivar transiciones, zoom y desplazamiento suave.

Preservar salto al contenido, orden de encabezados, texto alternativo útil, foco visible y nombres accesibles. Menú y paneles deben cerrar con Escape, devolver foco y funcionar con teclado; mantener navegación básica sin JavaScript. No anunciar los segundos del contador continuamente con `aria-live`. Al migrar formularios, checkout o cuenta, documentar estados reales de error, carga, éxito y deshabilitado sin depender solo del color.

## 7. Aplicación a otras páginas

Usar marfil para catálogo, lectura, formularios y compras; tinta para texto, rubí para la acción principal. Reservar fotografía integrada para una campaña o contenido que la justifique. Aplicar DM Sans, radios, espaciado, navegación e iconos coherentes. Conservar rutas, datos, autenticación, pagos y permisos existentes.

Esta guía define la dirección visual; no implica que Shop, Shows, Music, cuenta, checkout o Royals ya estén migrados. Tampoco cambia el diseño del CMS. La sustitución del inicio, los estados del comercio y el contenido pendiente necesitan su propia implementación y verificación.

## 8. Fuentes y verificación de esta edición

- Fuentes: sistema aprobado el 15 de noviembre y decisiones del hilo de homepage del 16–17 de noviembre en `#reny-app`; vista pública y fuentes del commit `7c03de2`.
- Capturas reales de `/newlanding`, 17 de noviembre de 2026, hora de Panamá. Imágenes diferidas y DM Sans cargadas antes de capturar. Las capturas ilustran el diseño; el contador es una instantánea.
- Playwright: 320, 390, 700, 701, 900, 901, 1000, 1001 y 1440px. Sin desbordamiento horizontal, imágenes cargadas y acciones principales de al menos 48px.
- Menú móvil y primera vista de producto: apertura, Escape y retorno de foco correctos. Preferencia de movimiento reducido: desplazamiento `auto`. Sin errores JavaScript durante la comprobación.
- La revisión no incluye compras, acceso autenticado, una auditoría completa de accesibilidad ni la publicación del nuevo inicio.

Los archivos CSS/JSON del kit son una extracción documentada para reutilizar; no se cargan automáticamente en la aplicación. Cambiar un token del kit no modifica por sí solo el CSS del prototipo.
