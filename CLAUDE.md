# Generador de plantillas de informes

Sistema propio para diseñar plantillas de informes (empezando por facturas) y generar PDF/Excel a partir de ellas.
Idioma del proyecto: **español** (nombres de elementos XML, atributos, comentarios y mensajes).

## Arquitectura prevista

1. **Editor visual** (JavaScript puro, un solo HTML en `public/index.html`): lienzo con la página a tamaño real, fondo blanco con puntos cada 5 mm. Lee y escribe el XML de la plantilla.
2. **Almacenamiento**: el XML es la fuente de verdad y se guarda **en el servidor, en SQLite** (`datos/plantillas.sqlite`, fuera de Git). Tabla `plantillas`: `nombre` único (sin distinguir mayúsculas), `xml`, `version`, `creada`, `actualizada`. El mismo esquema se llevará a la migración de Laravel. Validación con un XSD antes de guardar (pendiente).
3. **API actual (PHP + SQLite, sin dependencias)** en `api/index.php`; en el entorno de la sesión no se podía instalar Laravel (Packagist bloqueado), así que se hizo así para poder probarla. Cuando se pase a Laravel, el contrato se mantiene:
   - `GET /api/plantillas` lista (nombre, versión, fecha)
   - `GET /api/plantillas/{nombre}` plantilla con su XML
   - `GET /api/plantillas/{nombre}/xml` descarga el XML
   - `PUT /api/plantillas/{nombre}` guarda `{xml, version}`; `version` es la que tenía el cliente (0 si es nueva). Si no coincide con la del servidor responde 409 (control de versiones, para no pisar cambios entre equipos). El nombre de la URL debe coincidir con `<plantilla nombre="...">`.
   - `POST /api/plantillas/{nombre}/duplicar` copia con otro nombre `{nombre}`
   - `DELETE /api/plantillas/{nombre}`
4. **Backend Laravel** (pendiente): mismas rutas, ejecución de la consulta de datos, agrupación y totales, y plantilla Blade que traduce el XML a HTML/CSS.
5. **Renderizado** (pendiente): HTML + CSS Paged Media → PDF con **Gotenberg** (contenedor Docker con Chromium). Alternativa sin Docker: Dompdf (peor CSS). Excel/CSV salen directamente de los datos con PhpSpreadsheet, sin pasar por HTML.

## Cómo ejecutarlo

```bash
php -S localhost:8000 -t public router.php
```

Abrir http://localhost:8000. La base de datos y la carpeta `datos/` se crean solas. `router.php` solo sirve para el servidor integrado de PHP (desarrollo).

Si se abre `public/index.html` directamente en el navegador (sin servidor), el editor funciona en **modo local**: sin Guardar ni Plantillas, con Exportar/Importar XML y un borrador en `localStorage`.

## Editor: guardado y lista

- **Guardar** (Ctrl+S) envía el XML al servidor. El indicador de la barra inferior muestra `nombre · vN · guardada`, `cambios sin guardar` o `sin guardar`.
- **Plantillas** abre la lista guardada en el servidor: abrir, descargar XML, duplicar y borrar, con filtro por nombre.
- Cambiar el nombre de la plantilla y guardar crea una plantilla nueva (guardar como). Si el nombre ya existe en el servidor, se rechaza.
- **Exportar XML** descarga lo que hay en pantalla; el botón XML de la lista descarga lo guardado en el servidor.
- **Logo**: «Cargar imagen…» en el panel del elemento guarda la imagen en base64 dentro del XML (y se guarda con la plantilla en el servidor).
- **Columnas del cuerpo**: se reordenan arrastrando el asa de dos líneas de cada columna en el panel (clic en el cuerpo para verlas).

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
    <logo nombre="logo.png" tipo="image/png" x="15" y="10" ancho="40" alto="20">iVBORw0KGgoAAAANSUhEUg...</logo>
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
- **La imagen del logo va dentro del XML, en base64**, como contenido del elemento `<logo>`; `tipo` es el tipo MIME (PNG, JPEG, GIF, WebP o SVG) y `nombre` el nombre del archivo original. Sin imagen, `<logo>` va vacío y solo lleva `nombre`. El editor admite hasta 500 KB por imagen y la API hasta 5 MB de XML; ambos validan el tipo y el base64. Los XML antiguos con `src` se leen como `nombre`.
- `columna/@ancho` es un porcentaje del ancho útil; deben sumar 100. El orden de los elementos `<columna>` es el orden de las columnas en la tabla.
- Elementos admitidos en cabecera y pie: `logo`, `texto`, `linea`, `rectangulo`. El editor decide la banda de cada elemento según su posición vertical.

## Decisiones abiertas

- ¿Origen de coordenadas en la página (actual) o en cada banda?
- ¿Los totales van solo al final de la última página o hay pie repetido con «suma y sigue»?
- ¿La cabecera completa se repite en cada página o solo en la primera?
- Subinformes, agrupaciones con subtotales y totales calculados (`suma(importe)`): sintaxis por definir.
- XSD de la plantilla y estructura Laravel (migración, controlador, servicio de renderizado). Al pasar a Laravel, SQLite encaja con su configuración por defecto.
- Autenticación de la API: ahora no hay (herramienta local). Hace falta antes de exponerla en el servidor.
- Despliegue en Plesk (Apache): `router.php` es solo para desarrollo; habrá que enrutar `/api/` a `api/index.php` y proteger `datos/`.
- Despliegue de Gotenberg: el servidor de producción es un Plesk y puede limitar Docker; plan B Dompdf.

## Convenciones

- Rama de trabajo: `desarrollo`. No tocar `main` directamente.
- Todo el editor en un único HTML (`public/index.html`) mientras sea un prototipo; sin dependencias externas ni CDN.
- La API PHP no usa Composer: solo PDO SQLite y DOM, ambos incluidos en PHP 8.
- Mensajes de commit en español.
