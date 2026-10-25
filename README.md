# generador-plantillas

Generador visual de informes: plantillas en XML y editor con lienzo A4 en el navegador. Es un paquete de Laravel (`informaticadoslago/generador-plantillas`) que guarda las plantillas en la base de datos del programa, cada una con su dueño (empresa, comunidad…), y escribe una Blade por plantilla para generar el PDF con Dompdf. También funciona suelto, sin Laravel, para probar el editor.

## Como paquete de Laravel

Necesita Laravel 13 y PHP 8.3 o superior.

```bash
composer require informaticadoslago/generador-plantillas
php artisan vendor:publish --tag=generador-plantillas-config
php artisan migrate
```

Mientras no esté publicado, se instala desde la carpeta con un repositorio `path` de Composer (ver [`CLAUDE.md`](CLAUDE.md)).

En el `boot()` de un ServiceProvider se indica de quién son las plantillas en cada petición:

```php
use InformaticadosLago\GeneradorPlantillas\GeneradorPlantillas;

GeneradorPlantillas::plantillableUsando(fn ($request) => Empresa::find(session('empresa_id')));
```

El editor queda en `/generador-plantillas`. Rutas, permisos, plantillas globales, Blades y campos del editor se configuran en `config/generador-plantillas.php`. Con `blade` configurado, para generar un PDF:

```php
Pdf::loadView(GeneradorPlantillas::vista('recibo-alquiler', $empresa), ['datos' => $datos]);
```

Pruebas: `composer install && vendor/bin/phpunit`.

## Suelto (sin Laravel)

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
- Barra de botones en una columna a la izquierda y, desde un programa, botón «← Volver».
- **Exportar XML** / **Importar XML**, y un cuadro con el XML en vivo que se puede editar y aplicar.
- Sin servidor (abriendo `public/index.html` directamente) funciona en modo local, con borrador en el navegador.

Ejemplo de plantilla: `ejemplos/factura.xml`.

## Documentación del proyecto

Decisiones de diseño, formato XML, API y puntos abiertos: [`CLAUDE.md`](CLAUDE.md).

## Licencia

MIT.
