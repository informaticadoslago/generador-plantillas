<?php

namespace InformaticadosLago\GeneradorPlantillas;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Lo que el programa le dice al paquete. Se llama en el boot() de un
 * ServiceProvider del programa:
 *
 *   GeneradorPlantillas::plantillableUsando(
 *       fn (Request $request) => EmpresaFacturacion::find(session('empresa_facturacion_actual_id'))
 *   );
 *
 * El paquete no sabe nada de empresas ni de comunidades: solo guarda y filtra
 * por el modelo que le devuelva esa función.
 */
final class GeneradorPlantillas
{
    private static ?Closure $plantillable = null;

    /**
     * Quién es el dueño de las plantillas en cada petición (la empresa activa,
     * la comunidad…). Si devuelve null, se trabaja con las plantillas comunes
     * del programa. Sin función, siempre las comunes.
     */
    public static function plantillableUsando(?callable $resolver): void
    {
        self::$plantillable = $resolver === null ? null : Closure::fromCallable($resolver);
    }

    public static function plantillable(Request $request): ?Model
    {
        return self::$plantillable ? (self::$plantillable)($request) : null;
    }

    /** Carpeta donde se escriben las Blades de las plantillas (config blade); null = no se escriben. */
    public static function carpetaBlade(): ?string
    {
        $carpeta = config('generador-plantillas.blade');

        return $carpeta ? rtrim($carpeta, '/') : null;
    }

    /**
     * Nombre de la Blade de la plantilla $nombre de ese dueño, sin .blade.php:
     * el nombre en minúsculas y con guiones, y el id del dueño (recibo-alquiler-5).
     * Las comunes, sin id.
     */
    public static function nombreBlade(string $nombre, int|string|null $plantillableId): string
    {
        return Str::slug($nombre) . ($plantillableId !== null && $plantillableId !== '' ? '-' . $plantillableId : '');
    }

    /**
     * La vista de la plantilla $nombre de ese dueño, para view() o
     * Pdf::loadView(); se le pasan los datos en 'datos'. null si las Blades no
     * se escriben. Puede no existir todavía (view()->exists()).
     */
    public static function vista(string $nombre, ?Model $plantillable): ?string
    {
        $carpeta = self::carpetaBlade();
        if ($carpeta === null) {
            return null;
        }
        app('view')->replaceNamespace('generador-plantillas', $carpeta);

        return 'generador-plantillas::' . self::nombreBlade($nombre, $plantillable?->getKey());
    }
}
