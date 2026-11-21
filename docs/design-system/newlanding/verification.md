# Verificación · Sistema de diseño v2

Fecha local: 17 de noviembre de 2026, America/Panama. Navegador: Chromium mediante Playwright. Referencia pública: `https://renyrenteria.com/newlanding`; código de referencia: `7c03de2`.

## Página de referencia

En cada tamaño se cargaron DM Sans y las imágenes diferidas antes de medir. No se encontró desbordamiento horizontal. Las acciones principales visibles midieron al menos 48px.

| Ventana | Concierto | Banner | Royal Pass | Columnas de productos |
| --- | --- | --- | --- | --- |
| 320 × 740 | 656px | 300px | 675px | 2 |
| 390 × 844 | 683px | 300px | 705px | 2 |
| 700 × 900 | 706px | 300px | 725px | 2 |
| 701 × 900 | 706px | 230px | 565px | 3 |
| 900 × 900 | 706px | 230px | 493px | 3 |
| 901 × 900 | 649px | 230px | 494px | 3 |
| 1000 × 900 | 649px | 230px | 494px | 3 |
| 1001 × 900 | 649px | 280px | 523px | 3 |
| 1440 × 900 | 675px | 280px | 558px | 3 |

Medidas redondeadas del contenido renderizado; no son restricciones de altura.

- Menú móvil: abre, cierra con Escape y devuelve el foco a su control.
- Primera pieza: abre el diálogo, cierra con Escape y devuelve el foco a la tarjeta.
- Preferencia de movimiento reducido: `scroll-behavior: auto`.
- Sin errores JavaScript durante la comprobación.
- Capturas de cabecera, banner, Royal Pass y pie guardadas en `reference/` después de cargar las imágenes. Captura móvil a 390 × 844.

## Entregables

- Guía HTML revisada a 320, 390, 768 y 1440px: sin desbordamiento horizontal, imágenes y fuentes cargadas, sin errores JavaScript.
- Ocho páginas de impresión A4 horizontal; comprobación de límites del contenido respecto al pie y revisión visual de las ocho láminas.
- Contraste calculado según luminancia relativa sRGB: tinta/marfil 14.96:1, marfil/rubí 7.82:1 y gris secundario/marfil 5.43:1.

## Alcance

Comprobación de diseño y documentación. No se probaron pagos, creación de pedidos ni acceso autenticado; tampoco se realizó una auditoría completa de accesibilidad. No hubo cambios funcionales al website ni publicación del nuevo homepage. La suite Laravel no es un requisito de esta actualización documental.
