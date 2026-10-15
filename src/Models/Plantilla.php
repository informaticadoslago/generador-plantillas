<?php

namespace InformaticadosLago\GeneradorPlantillas\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InformaticadosLago\GeneradorPlantillas\GeneradorPlantillas;
use InformaticadosLago\GeneradorPlantillas\Support\GeneradorBlade;
use RuntimeException;

/**
 * Plantilla de informe en XML. nombre es el de <plantilla nombre="...">, único
 * (sin distinguir mayúsculas) dentro de su plantillable; version sube con cada
 * guardado. Sin plantillable, es común a todo el programa.
 *
 * Si el programa da carpeta (config blade), cada vez que se guarda se escribe
 * de nuevo su Blade, pisando la que hubiera; al borrarla, se borra.
 */
class Plantilla extends Model
{
    const CREATED_AT = 'creada';

    const UPDATED_AT = 'actualizada';

    protected static function booted(): void
    {
        static::saved(function (Plantilla $plantilla) {
            $anterior = $plantilla->wasChanged('nombre') ? $plantilla->ficheroBlade($plantilla->getOriginal('nombre')) : null;
            $fichero  = $plantilla->ficheroBlade();
            if ($fichero === null) {
                return;
            }
            if ($anterior !== null && $anterior !== $fichero && is_file($anterior)) {
                unlink($anterior);
            }

            $carpeta = dirname($fichero);
            if (! is_dir($carpeta) && ! mkdir($carpeta, 0775, true) && ! is_dir($carpeta)) {
                throw new RuntimeException("No se puede crear la carpeta de las Blades: {$carpeta}");
            }
            if (file_put_contents($fichero, app(GeneradorBlade::class)->blade($plantilla->xml), LOCK_EX) === false) {
                throw new RuntimeException("No se puede escribir la Blade de la plantilla: {$fichero}");
            }
        });

        static::deleted(function (Plantilla $plantilla) {
            $fichero = $plantilla->ficheroBlade();
            if ($fichero !== null && is_file($fichero)) {
                unlink($fichero);
            }
        });
    }

    /** El fichero de su Blade (con otro nombre, el que tendría); null si no se escriben. */
    public function ficheroBlade(?string $nombre = null): ?string
    {
        $carpeta = GeneradorPlantillas::carpetaBlade();

        return $carpeta === null ? null
            : $carpeta . '/' . GeneradorPlantillas::nombreBlade($nombre ?? $this->nombre, $this->plantillable_id) . '.blade.php';
    }

    protected $fillable = [
        'nombre',
        'xml',
        'version',
    ];

    protected $casts = [
        'version' => 'integer',
    ];

    public function getTable(): string
    {
        return config('generador-plantillas.tabla', 'plantillas');
    }

    public function plantillable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Las plantillas de ese dueño; con null, las comunes. */
    public function scopeDe(Builder $query, ?Model $plantillable): Builder
    {
        if ($plantillable === null) {
            return $query->whereNull('plantillable_type')->whereNull('plantillable_id');
        }

        return $query->where('plantillable_type', $plantillable->getMorphClass())
            ->where('plantillable_id', $plantillable->getKey());
    }

    /** La que tiene ese nombre, sin distinguir mayúsculas. */
    public function scopeLlamada(Builder $query, string $nombre): Builder
    {
        return $query->whereRaw('lower(nombre) = ?', [mb_strtolower($nombre)]);
    }
}
