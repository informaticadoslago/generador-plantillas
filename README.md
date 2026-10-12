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
- Arrastrar, redimensionar con el asa, imán a la rejilla (Alt = ajuste fino), flechas, duplicar, eliminar, deshacer/rehacer.
- **Guardar** (Ctrl+S) en el servidor y **Plantillas** con la lista guardada: abrir, descargar XML, duplicar y borrar.
- **Exportar XML** / **Importar XML**, y un cuadro con el XML en vivo que se puede editar y aplicar.
- Sin servidor (abriendo `public/index.html` directamente) funciona en modo local, con borrador en el navegador.

Ejemplo de plantilla: `ejemplos/factura.xml`.

## Documentación del proyecto

Decisiones de diseño, formato XML, API y puntos abiertos: [`CLAUDE.md`](CLAUDE.md).

## Licencia

MIT.
