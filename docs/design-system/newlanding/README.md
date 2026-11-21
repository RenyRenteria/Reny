# Sistema de diseño · Reny Renteria · Versión 2

Actualizado el 17 de noviembre de 2026 desde `/newlanding`, commit `7c03de2`.

- Abrir `guide.html` en un navegador para ver la guía visual editable. Sus recursos son locales y las muestras de controles son ilustrativas.
- Imprimir con tamaño **A4 horizontal**, fondos activados, sin márgenes ni encabezado/pie del navegador. El CSS genera ocho páginas.
- Consultar `../../newlanding-design-system.md` para medidas, componentes, destinos y alcance de la implementación.
- `tokens.json` y `tokens.css` contienen valores reutilizables extraídos del prototipo. No se cargan en el website. Las variables CSS usan el prefijo `--rr-`; su adopción en la aplicación requiere enlazarlas explícitamente.
- `reference/` contiene capturas reales del sitio. El contador y los datos de evento son una instantánea, no valores reutilizables de identidad.
- Las imágenes y fuentes se referencian en `../../../public/newlanding-assets/`. Conservar esa estructura al compartir la guía. DM Sans incluye su licencia en ese directorio.

La guía visual sustituye la entrega inicial del 15 de noviembre para el nuevo website. No reemplaza el homepage publicado ni migra otras páginas. El catálogo ilustrado y el adelanto de Royal Pass siguen siendo provisionales.

## Verificación

Véase [verification.md](verification.md). No se ejecutó la suite Laravel porque esta entrega solo modifica documentación y añade una guía estática y archivos de referencia; no cambia rutas, plantillas, CSS ni JavaScript cargados por la aplicación.
