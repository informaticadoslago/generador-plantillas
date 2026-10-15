<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plantillas de informes. El XML es la fuente de verdad; nombre es el de
     * <plantilla nombre="...">, único (sin distinguir mayúsculas) dentro de su
     * plantillable, y version sube con cada guardado.
     *
     * plantillable es el dueño (empresa, comunidad…), polimórfico como
     * documentable; vacío = plantilla común a todo el programa.
     */
    public function up(): void
    {
        Schema::create(config('generador-plantillas.tabla', 'plantillas'), function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('plantillable');
            $table->string('nombre', 80);
            $table->longText('xml');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('creada')->nullable();
            $table->timestamp('actualizada')->nullable();

            $table->unique(['plantillable_type', 'plantillable_id', 'nombre'], 'plantillas_plantillable_nombre_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('generador-plantillas.tabla', 'plantillas'));
    }
};
