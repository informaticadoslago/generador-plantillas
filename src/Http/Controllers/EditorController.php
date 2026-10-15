<?php

namespace InformaticadosLago\GeneradorPlantillas\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use InformaticadosLago\GeneradorPlantillas\GeneradorPlantillas;

/**
 * Sirve el mismo public/index.html del modo suelto, con la configuración del
 * programa en window.GeneradorPlantillas: URL de la API, token CSRF, clave del
 * borrador, el botón «Volver» y, si el programa los da, campos, listas y datos
 * de prueba. No es una vista Blade porque el editor lleva {{campo}} en sus textos.
 *
 * ?plantilla=nombre abre esa plantilla al arrancar; ?nueva, una vacía.
 */
class EditorController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $plantillable = GeneradorPlantillas::plantillable($request);
        $abrir        = trim((string) $request->query('plantilla', ''));
        $volver       = config('generador-plantillas.volver');

        $config = array_filter([
            'api'         => rtrim(url(config('generador-plantillas.rutas.prefijo') . '/api'), '/') . '/',
            'csrf'        => csrf_token(),
            'claveLocal'  => 'editor-plantillas:' . ($plantillable ? $plantillable->getMorphClass() . ':' . $plantillable->getKey() : 'comunes'),
            'abrir'       => $abrir !== '' ? $abrir : null,
            'nueva'       => $abrir === '' && $request->query->has('nueva') ? true : null,
            'volver'      => $volver ? url($volver) : null,
            'campos'      => config('generador-plantillas.campos'),
            'listas'      => config('generador-plantillas.listas'),
            'datosPrueba' => config('generador-plantillas.datos_prueba'),
        ], fn ($valor) => $valor !== null);

        $script = '<script>window.GeneradorPlantillas = '
            . json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . ";</script>\n";

        $html = file_get_contents(dirname(__DIR__, 3) . '/public/index.html');
        $html = preg_replace_callback('#</head>#i', fn ($m) => $script . $m[0], $html, 1);

        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
