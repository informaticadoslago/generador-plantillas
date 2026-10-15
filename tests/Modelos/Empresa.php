<?php

namespace InformaticadosLago\GeneradorPlantillas\Tests\Modelos;

use Illuminate\Database\Eloquent\Model;

/** Un dueño de plantillas para las pruebas (en los programas: empresa, comunidad…). */
class Empresa extends Model
{
    public static function conId(int $id): static
    {
        return (new static())->forceFill(['id' => $id]);
    }
}
