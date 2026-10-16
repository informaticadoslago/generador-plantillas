<?php

namespace InformaticadosLago\GeneradorPlantillas\Almacenes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InformaticadosLago\GeneradorPlantillas\Models\Plantilla;

/**
 * Las plantillas de un dueño en la tabla (sin dueño, las comunes). Al
 * guardarlas, el modelo escribe su Blade.
 */
class AlmacenBaseDatos implements Almacen
{
    public function __construct(private ?Model $plantillable) {}

    public function lista(): array
    {
        return Plantilla::de($this->plantillable)
            ->orderBy('nombre')
            ->get(['nombre', 'version', 'creada', 'actualizada'])
            ->map(fn (Plantilla $p) => $this->datos($p))
            ->all();
    }

    public function buscar(string $nombre): ?array
    {
        $plantilla = $this->plantilla($nombre);

        return $plantilla ? $this->datos($plantilla) : null;
    }

    public function crear(string $nombre, string $xml): array
    {
        $plantilla = new Plantilla(['nombre' => $nombre, 'xml' => $xml, 'version' => 1]);
        if ($this->plantillable !== null) {
            $plantilla->plantillable()->associate($this->plantillable);
        }
        $plantilla->save();

        return $this->datos($plantilla);
    }

    public function actualizar(string $actual, string $nombre, string $xml): array
    {
        $plantilla          = $this->plantilla($actual);
        $plantilla->nombre  = $nombre;
        $plantilla->xml     = $xml;
        $plantilla->version = $plantilla->version + 1;
        $plantilla->save();

        return $this->datos($plantilla);
    }

    public function borrar(string $nombre): void
    {
        $this->plantilla($nombre)?->delete();
    }

    public function bloqueado(callable $hacer): mixed
    {
        return DB::transaction($hacer);
    }

    private function plantilla(string $nombre): ?Plantilla
    {
        return Plantilla::de($this->plantillable)->llamada($nombre)->lockForUpdate()->first();
    }

    private function datos(Plantilla $plantilla): array
    {
        return [
            'nombre'      => $plantilla->nombre,
            'xml'         => $plantilla->xml,
            'version'     => $plantilla->version,
            'creada'      => $plantilla->creada,
            'actualizada' => $plantilla->actualizada,
        ];
    }
}
