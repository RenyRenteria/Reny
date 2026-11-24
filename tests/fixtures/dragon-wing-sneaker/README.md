# Dragon Wing Sneaker: ensayo local del nuevo landing

Ficha solicitada por Reny el 24 de noviembre de 2026: **Dragon Wing Sneaker, USD 179, tallas US3–US13**. Las cinco imágenes son las originales aportadas por Reny; no se generaron ni retocaron. No son nueve productos distintos.

## Qué entrega

Una vista HTML autónoma del landing existente, con su colección sustituida **solo durante la exportación local** por este producto. Incluye galería, modal, once opciones enteras de talla y aviso de pago pendiente. No modifica las plantillas del website, rutas, catálogo ni configuración. No se publica con el deploy habitual.

Los enlaces del resto del landing quedan dentro de la vista. Imágenes, tipografía, estilos y JavaScript están incluidos en el archivo; se puede descargar y abrir en un navegador sin servidor. El botón de pago está desactivado y la política de contenido impide conexiones y envío de formularios.

## Generar

En un worktree limpio, sin `.env` ni configuración cacheada:

```sh
composer install --no-interaction --prefer-dist --no-scripts
php tests/fixtures/dragon-wing-sneaker/export.php /tmp/Dragon-Wing-Sneaker-ensayo.html
```

Abrir el HTML descargado y pulsar «Ver Dragon Wing Sneaker», después «Elegir talla». El render usa Blade del proyecto con un reemplazo de vista limitado al proceso CLI. El exportador no se debe registrar como ruta web. No necesita claves de PayPal o Yoycol, base de datos ni compras de prueba.

## Información pendiente

- Enlace de producto/diseño Yoycol: Reny indicó que lo enviaría. `catalog.json` conserva URL, design code y mapa de variantes en `null` hasta verificarlo; las fotos no identifican esos datos.
- La lista US3–US13 expresa lo pedido por Reny. Confirmar con Yoycol las equivalencias, medias tallas si existen, variantes disponibles y costos; no se inventaron SKU ni se afirmó stock.
- Envío e impuestos están pendientes y no se muestran como gratuitos. USD 179 es el precio del producto, no el total confirmado del checkout.
- Confirmar un host HTTPS de ensayo separado, con base de datos y correo de prueba. `/newlanding` es una ruta del sitio actual, no un entorno de pago aislado.
- PR [291](https://github.com/RenyRenteria/Reny/pull/291) sigue abierto en `c5018129491288442e0996a24af596ea9904ae9a` según consulta del 24/11. Contiene el gate y las protecciones para PayPal sandbox; este fixture no lo fusiona ni reemplaza.
- Solo se observó el entorno GitHub `staging`, y la identidad disponible no tiene permiso `admin`/`maintain`. El 403 previo al crear `paypal-sandbox-e2e` sigue sin resolverse; no se volvió a intentar ni se cambió de identidad.
- Tras habilitar el host, conectar la ficha a variantes verificadas, configurar webhook y realizar las dos corridas sandbox previstas en `docs/qa/paypal-sandbox-e2e.md` del PR 291.

## Evidencia y límites

La generación Blade terminó correctamente. Se comprobaron USD 179.00, once tallas, cinco vistas del producto, foto principal idéntica al archivo original, activos incluidos, ausencia de enlaces externos/formularios y botón de pago desactivado. Sintaxis PHP/JS y Pint correctos.

La regresión existente `tests/Feature/HomePageTest.php` pasó: **20 pruebas, 185 aserciones**. Es evidencia del homepage actual; no valida visualmente el nuevo modal del fixture.

Playwright MCP rechazó la navegación por requerir aprobación con política `never`. No se intentó eludir esa restricción con otro navegador. Apertura/cierre del modal, foco, interacción con galería/tallas, desbordes y aspecto móvil/escritorio quedan pendientes de validación visual. Las comprobaciones de HTML no certifican esas interacciones.

No se hizo un deploy ni se accedió al servidor: SSH está deshabilitado. No se conectó PayPal, no se configuró un webhook, no se cobró ni se creó un pedido Yoycol. Esta entrega es una maqueta local revisable, **no una tienda sandbox operativa**.
