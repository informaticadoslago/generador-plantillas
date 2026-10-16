<?php

namespace InformaticadosLago\GeneradorPlantillas;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class GeneradorPlantillasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/generador-plantillas.php', 'generador-plantillas');
    }

    public function boot(): void
    {
        // La tabla se crea con el `php artisan migrate` del programa.
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        Route::prefix(config('generador-plantillas.rutas.prefijo'))
            ->middleware(config('generador-plantillas.rutas.middleware'))
            ->name('generador-plantillas.')
            ->group(__DIR__ . '/../routes/generador-plantillas.php');

        // Las mismas rutas para las plantillas globales (su carpeta), si el programa las tiene.
        if (config('generador-plantillas.globales.carpeta')) {
            Route::prefix(config('generador-plantillas.globales.prefijo'))
                ->middleware(config('generador-plantillas.globales.middleware'))
                ->name('generador-plantillas.globales.')
                ->group(__DIR__ . '/../routes/generador-plantillas.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/generador-plantillas.php' => config_path('generador-plantillas.php'),
            ], 'generador-plantillas-config');
        }
    }
}
