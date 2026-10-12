# generador-plantillas

Generador visual de informes: plantillas en XML, editor con lienzo A4 en el navegador, guardado en SQLite en el servidor y, más adelante, generación de PDF/Excel desde Laravel.

## Ejecutar

Necesita PHP 8 con `pdo_sqlite` (sin Composer ni dependencias). Desde la carpeta del proyecto:

```bash
php -S localhost:8000 -t public router.php
```

Abre http://localhost:8000. La base de datos se crea sola en `datos/plantillas.sqlite` (no se sube a Git).

## Editor visual

- Página A4 a tamaño real, en milímetros, con puntos cada 5 mm.
- Elementos: logo, texto (con `{{campo}}`), línea y rectángulo; tabla del cuerpo con columnas editables.
- La imagen del logo se guarda dentro del XML en base64 (máx. 500 KB).
- Las columnas del cuerpo se reordenan arrastrando el asa de dos líneas.
- Giro de cualquier elemento (botones, campo en grados o el círculo que sale encima): líneas verticales, texto en vertical, recuadros con borde y relleno.
- **Previsualizar**: la plantilla rellena con datos de prueba (JSON editable que se guarda con la plantilla), con paginación del cuerpo y aviso de campos que faltan. En los textos, `{{campo|moneda}}` da formato.
- **Tablas libres** en cabecera o pie (por ejemplo el desglose de IVA), distintas de la del cuerpo: lista que se repite, columnas reordenables y altura automática. Desplegables con los campos de factura, emisor, cliente, líneas, impuestos y programa.
- **Rectángulo contenedor**: puede llevar textos, logos y líneas dentro; al girarlo gira todo. Cada elemento se puede desvincular (o vincular) desde su panel sin moverlo de sitio.
- **Marca de agua** a página completa, detrás de todo: texto o imagen (base64) girados y con opacidad casi total.
- Arrastrar, redimensionar con el asa, imán a la rejilla (Alt = ajuste fino), flechas, duplicar, eliminar, deshacer/rehacer.
- **Guardar** (Ctrl+S) sobrescribe la plantilla del servidor o la crea si no existe (si cambió en el servidor, pregunta antes de pisarla). **Guardar como…** (Ctrl+Mayús+S) la guarda con otro nombre.
- **Plantillas** con la lista guardada: abrir, descargar XML, duplicar y borrar.
- **Exportar XML** / **Importar XML**, y un cuadro con el XML en vivo que se puede editar y aplicar.
- Sin servidor (abriendo `public/index.html` directamente) funciona en modo local, con borrador en el navegador.

Ejemplo de plantilla: `ejemplos/factura.xml`.

## Documentación del proyecto

Decisiones de diseño, formato XML, API y puntos abiertos: [`CLAUDE.md`](CLAUDE.md).

## Licencia

MIT.
