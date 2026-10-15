<?php

namespace InformaticadosLago\GeneradorPlantillas\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InformaticadosLago\GeneradorPlantillas\GeneradorPlantillas;
use InformaticadosLago\GeneradorPlantillas\GeneradorPlantillasServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [GeneradorPlantillasServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // Sin login en las pruebas: el programa pondrá 'auth' y sus permisos.
        $app['config']->set('generador-plantillas.rutas.middleware', ['web']);
    }

    protected function tearDown(): void
    {
        GeneradorPlantillas::plantillableUsando(null);

        parent::tearDown();
    }

    /** Hace que las peticiones sean de ese dueño (null = comunes). */
    protected function como(?Model $plantillable): static
    {
        GeneradorPlantillas::plantillableUsando(fn () => $plantillable);

        return $this;
    }

    protected function xml(string $nombre, string $contenido = ''): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<plantilla nombre="' . htmlspecialchars($nombre, ENT_XML1) . '" version="1">'
            . '<pagina formato="A4" orientacion="vertical" unidad="mm"/>' . $contenido . '</plantilla>';
    }

    protected function url(string $nombre = '', string $accion = ''): string
    {
        return '/generador-plantillas/api/plantillas'
            . ($nombre !== '' ? '/' . rawurlencode($nombre) : '')
            . ($accion !== '' ? '/' . $accion : '');
    }
}
