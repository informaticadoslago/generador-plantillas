# generador-plantillas

Generador visual de informes: plantillas en XML, editor con lienzo A4 en el navegador y, más adelante, generación de PDF/Excel desde Laravel.

## Editor visual (prototipo)

Abre `editor/index.html` en el navegador (no necesita servidor ni dependencias).

- Página A4 a tamaño real, en milímetros, con puntos cada 5 mm.
- Elementos: logo, texto (con `{{campo}}`), línea y rectángulo; tabla del cuerpo con columnas editables.
- Arrastrar, redimensionar con el asa, imán a la rejilla (Alt = ajuste fino), flechas, duplicar, eliminar, deshacer/rehacer.
- **Exportar XML** / **Importar XML**, y un cuadro con el XML en vivo que se puede editar y aplicar.
- Guarda un borrador en el navegador (`localStorage`).

Ejemplo de plantilla: `ejemplos/factura.xml`.

## Documentación del proyecto

Decisiones de diseño, formato XML y puntos abiertos: [`CLAUDE.md`](CLAUDE.md).

## Licencia

MIT.
