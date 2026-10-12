# Generador de plantillas de informes

Sistema propio para diseñar plantillas de informes (empezando por facturas) y generar PDF/Excel a partir de ellas.
Idioma del proyecto: **español** (nombres de elementos XML, atributos, comentarios y mensajes).

## Arquitectura prevista

1. **Editor visual** (JavaScript puro, un solo HTML en `editor/index.html`): lienzo con la página a tamaño real, fondo blanco con puntos cada 5 mm. Lee y escribe el XML de la plantilla.
2. **Almacenamiento**: el XML es la fuente de verdad. En Laravel irá en una columna `longText` de la tabla `plantillas` (`nombre` único, `xml`, `version`). Validación con un XSD antes de guardar.
3. **Backend Laravel**: rutas `GET/PUT /plantillas/{nombre}`, ejecución de la consulta de datos, agrupación y totales, y plantilla Blade que traduce el XML a HTML/CSS.
4. **Renderizado**: HTML + CSS Paged Media → PDF con **Gotenberg** (contenedor Docker con Chromium). Alternativa sin Docker: Dompdf (peor CSS). Excel/CSV salen directamente de los datos con PhpSpreadsheet, sin pasar por HTML.

El editor se puede usar sin backend: abrir `editor/index.html` en el navegador. Guarda un borrador en `localStorage`.

## Decisiones tomadas

- Página definida primero: `formato` (A4 por defecto), `orientacion` (vertical/horizontal), márgenes. Equivale a `@page { size: A4 portrait; margin: ... }`.
- **Una sola unidad: milímetros** para todo (posiciones, tamaños, márgenes). Los píxeles de pantalla del editor son solo visualización (1 mm = 96/25,4 px).
- Tres bandas: `cabecera` (texto y gráficos), `cuerpo` (tabla con una fila por elemento de `datos`, en columnas) y `pie` (más texto; totales del cuerpo).
- Datos del informe con `{{campo}}`, también con ruta (`{{cliente.nombre}}`).
- La cabecera de la tabla del cuerpo se repite en cada página (`thead`).
- El editor se rige por un imán a la rejilla (5 mm por defecto; 1 mm, 0,5 mm o ninguno).
- La plantilla se llama con `nombre` (ejemplo: `factura`).

## Formato XML (versión 1)

```xml
<plantilla nombre="factura" version="1">
  <pagina formato="A4" orientacion="vertical" unidad="mm">
    <margen superior="25" inferior="20" izquierda="15" derecha="15"/>
  </pagina>
  <cabecera altura="70">
    <logo src="logo.png" x="15" y="10" ancho="40" alto="20"/>
    <texto x="65" y="10" ancho="70" alto="20" tam="10" negrita="false" alinear="izquierda">{{emisor.nombre}}</texto>
    <linea x="15" y="66" largo="180" grosor="0.3"/>
    <rectangulo x="15" y="30" ancho="50" alto="10" grosor="0.3" relleno="#eeeeee"/>
  </cabecera>
  <cuerpo datos="lineas">
    <columna campo="concepto" titulo="Concepto" ancho="46" alinear="izquierda" formato=""/>
    <columna campo="importe" titulo="Importe" ancho="18" alinear="derecha" formato="moneda"/>
  </cuerpo>
  <pie altura="55">
    <texto x="15" y="268" ancho="180" alto="9" tam="8">{{textoLegal}}</texto>
  </pie>
</plantilla>
```

- `x`, `y` van en mm **desde la esquina superior izquierda de la página** (provisional, ver decisiones abiertas).
- `cabecera/@altura`: mm desde el borde superior. `pie/@altura`: mm desde el borde inferior. El cuerpo ocupa el espacio entre ambos.
- `columna/@ancho` es un porcentaje del ancho útil; deben sumar 100.
- Elementos admitidos en cabecera y pie: `logo`, `texto`, `linea`, `rectangulo`. El editor decide la banda de cada elemento según su posición vertical.

## Decisiones abiertas

- ¿Origen de coordenadas en la página (actual) o en cada banda?
- ¿Los totales van solo al final de la última página o hay pie repetido con «suma y sigue»?
- ¿La cabecera completa se repite en cada página o solo en la primera?
- Subinformes, agrupaciones con subtotales y totales calculados (`suma(importe)`): sintaxis por definir.
- XSD de la plantilla y estructura Laravel (migración, controlador, servicio de renderizado).
- Despliegue de Gotenberg: el servidor de producción es un Plesk y puede limitar Docker; plan B Dompdf.

## Convenciones

- Rama de trabajo: `desarrollo`. No tocar `main` directamente.
- Todo en un único HTML mientras el editor sea un prototipo; sin dependencias externas ni CDN.
- Mensajes de commit en español.
