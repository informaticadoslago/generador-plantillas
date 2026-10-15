<?php

use Illuminate\Support\Facades\Route;
use InformaticadosLago\GeneradorPlantillas\Http\Controllers\EditorController;
use InformaticadosLago\GeneradorPlantillas\Http\Controllers\PlantillasController;

// Prefijo, middleware y nombre (generador-plantillas.) los pone el ServiceProvider.

Route::get('/', EditorController::class)->name('editor');

Route::prefix('api/plantillas')
    ->name('api.')
    ->controller(PlantillasController::class)
    ->group(function () {
        Route::get('/', 'lista')->name('lista');
        Route::get('{nombre}', 'ver')->name('ver');
        Route::get('{nombre}/xml', 'xml')->name('xml');
        Route::put('{nombre}', 'guardar')->name('guardar');
        Route::post('{nombre}/duplicar', 'duplicar')->name('duplicar');
        Route::delete('{nombre}', 'borrar')->name('borrar');
    });
