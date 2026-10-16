<?php

namespace InformaticadosLago\GeneradorPlantillas\Almacenes;

/**
 * Dónde guarda la API las plantillas: en la tabla, las de un dueño
 * (AlmacenBaseDatos), o en una carpeta de XML, las globales (AlmacenFicheros).
 *
 * Cada plantilla va como array: nombre, xml, version, creada y actualizada
 * (Carbon o null). Los nombres no distinguen mayúsculas.
 */
interface Almacen
{
    /** Todas, por nombre (sin el xml). */
    public function lista(): array;

    /** La que se llama así, o null. */
    public function buscar(string $nombre): ?array;

    /** Crea una nueva, versión 1. Lanza DomainException si no se puede porque ya hay otra en su sitio. */
    public function crear(string $nombre, string $xml): array;

    /** Sobrescribe la que se llama $actual con $nombre y $xml, y sube su versión. */
    public function actualizar(string $actual, string $nombre, string $xml): array;

    public function borrar(string $nombre): void;

    /** Hace $hacer sin que otra petición toque el almacén a la vez (transacción o cerrojo). */
    public function bloqueado(callable $hacer): mixed;
}
