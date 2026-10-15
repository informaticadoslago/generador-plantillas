<?php

namespace InformaticadosLago\GeneradorPlantillas\Tests\Modelos;

use Illuminate\Database\Eloquent\Model;

/** Otro tipo de dueño, para comprobar que no se mezclan con las empresas del mismo id. */
class Comunidad extends Model
{
    public static function conId(int $id): static
    {
        return (new static())->forceFill(['id' => $id]);
    }
}
