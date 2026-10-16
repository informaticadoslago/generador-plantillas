<?php

return [

    /*
     * Tabla de las plantillas. Se crea con `php artisan migrate` en la conexión
     * por defecto (la del .env).
     */
    'tabla' => 'plantillas',

    /*
     * Rutas del editor y de su API:
     *
     *   GET    {prefijo}                                el editor
     *   GET    {prefijo}/api/plantillas                 lista
     *   GET    {prefijo}/api/plantillas/{nombre}        plantilla con su XML
     *   GET    {prefijo}/api/plantillas/{nombre}/xml    descarga el XML
     *   PUT    {prefijo}/api/plantillas/{nombre}        guarda {xml, version}
     *   POST   {prefijo}/api/plantillas/{nombre}/duplicar
     *   DELETE {prefijo}/api/plantillas/{nombre}
     *
     * En middleware van también los permisos, por ejemplo 'can:plantilla-edit'.
     */
    'rutas' => [
        'prefijo'    => 'generador-plantillas',
        'middleware' => ['web', 'auth'],
    ],

    /*
     * Adónde lleva el botón «Volver» del editor (ruta del programa, p. ej. la
     * lista de plantillas). null = sin botón.
     */
    'volver' => null,

    /*
     * Si en las plantillas del dueño se pueden crear, duplicar y borrar. false
     * = solo se modifican las que ya tiene (las copias de las globales): la API
     * responde 403 y el editor esconde esos botones.
     */
    'crear' => true,

    /*
     * Plantillas globales del programa: un XML por plantilla en una carpeta
     * que va al repositorio (sin Blade). Se crean y se modifican con su propio
     * editor, en {prefijo}, con las mismas rutas de arriba, y
     * GeneradorPlantillas::copiarGlobales() las copia a un dueño. null o sin
     * carpeta = no hay globales.
     *
     *   'globales' => [
     *       'carpeta'    => resource_path('plantillas'),
     *       'prefijo'    => 'generador-plantillas/globales',
     *       'middleware' => ['web', 'auth', 'can:menu-maestros'],
     *       'volver'     => 'plantillas',
     *   ],
     */
    'globales' => null,

    /*
     * Carpeta donde se escribe la Blade de cada plantilla al guardarla (pisa la
     * anterior; al borrar la plantilla, se borra). Se llama como la plantilla y
     * el id de su dueño: recibo-alquiler-5.blade.php. El programa la usa con
     * GeneradorPlantillas::vista(). null = no se escriben.
     */
    'blade' => null,

    /*
     * Tamaño máximo del XML en bytes (incluye las imágenes en base64).
     */
    'max_xml' => 5_000_000,

    /*
     * Lo que el editor ofrece en los desplegables y usa en la vista previa.
     * null = los de factura que trae el editor.
     *
     *   campos:       ['grupo' => ['campo', ...]]   se usan como {{grupo.campo}}
     *   listas:       ['lista' => ['campo', ...]]   listas que se repiten (cuerpo o tabla)
     *   datos_prueba: array con un ejemplo de los datos para la vista previa
     */
    'campos'       => null,
    'listas'       => null,
    'datos_prueba' => null,

];
