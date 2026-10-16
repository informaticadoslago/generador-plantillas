<?php

namespace InformaticadosLago\GeneradorPlantillas\Almacenes;

use DomainException;
use DOMDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Las plantillas globales del programa: un XML por plantilla en una carpeta
 * que va al repositorio (config globales.carpeta). El fichero de una nueva se
 * llama como ella, en minúsculas y con guiones (recibo-alquiler.xml). La
 * versión va en el propio XML, en <plantilla revision="N">; no tienen Blade.
 */
class AlmacenFicheros implements Almacen
{
    public function __construct(private string $carpeta) {}

    public function lista(): array
    {
        $plantillas = [];
        foreach (glob($this->carpeta . '/*.xml') ?: [] as $fichero) {
            if ($plantilla = $this->leer($fichero)) {
                $plantillas[] = $plantilla;
            }
        }
        usort($plantillas, fn ($a, $b) => strcasecmp($a['nombre'], $b['nombre']));

        return $plantillas;
    }

    public function buscar(string $nombre): ?array
    {
        foreach ($this->lista() as $plantilla) {
            if (mb_strtolower($plantilla['nombre']) === mb_strtolower($nombre)) {
                return $plantilla;
            }
        }

        return null;
    }

    public function crear(string $nombre, string $xml): array
    {
        $fichero = $this->carpeta . '/' . Str::slug($nombre) . '.xml';
        if (is_file($fichero)) {
            throw new DomainException('Ya hay otra plantilla en ' . basename($fichero) . '.');
        }
        $this->escribir($fichero, $this->conRevision($xml, 1));

        return $this->leer($fichero);
    }

    public function actualizar(string $actual, string $nombre, string $xml): array
    {
        $anterior = $this->buscar($actual);
        $this->escribir($anterior['fichero'], $this->conRevision($xml, $anterior['version'] + 1));

        return $this->leer($anterior['fichero']);
    }

    public function borrar(string $nombre): void
    {
        if ($plantilla = $this->buscar($nombre)) {
            unlink($plantilla['fichero']);
        }
    }

    public function bloqueado(callable $hacer): mixed
    {
        // El cerrojo, fuera de la carpeta, que va al repositorio.
        $cerrojo = fopen(sys_get_temp_dir() . '/generador-plantillas-' . md5($this->carpeta) . '.lock', 'c');
        flock($cerrojo, LOCK_EX);
        try {
            return $hacer();
        } finally {
            flock($cerrojo, LOCK_UN);
            fclose($cerrojo);
        }
    }

    /** La plantilla de un fichero; null si no es una plantilla. */
    private function leer(string $fichero): ?array
    {
        $xml       = (string) @file_get_contents($fichero);
        $documento = $this->cargar($xml);
        $raiz      = $documento?->documentElement;
        if ($raiz === null || $raiz->nodeName !== 'plantilla' || $raiz->getAttribute('nombre') === '') {
            return null;
        }
        clearstatcache(true, $fichero);

        return [
            'nombre'      => $raiz->getAttribute('nombre'),
            'xml'         => $xml,
            'version'     => max(1, (int) $raiz->getAttribute('revision')),
            'creada'      => null,
            'actualizada' => Carbon::createFromTimestamp(filemtime($fichero)),
            'fichero'     => $fichero,
        ];
    }

    /** El XML con su versión en <plantilla revision="N">. */
    private function conRevision(string $xml, int $revision): string
    {
        $documento = $this->cargar($xml);
        $documento->documentElement->setAttribute('revision', (string) $revision);

        return $documento->saveXML();
    }

    private function escribir(string $fichero, string $xml): void
    {
        if (! is_dir($this->carpeta) && ! mkdir($this->carpeta, 0775, true) && ! is_dir($this->carpeta)) {
            throw new RuntimeException("No se puede crear la carpeta de las plantillas globales: {$this->carpeta}");
        }
        if (file_put_contents($fichero, $xml, LOCK_EX) === false) {
            throw new RuntimeException("No se puede escribir la plantilla global: {$fichero}");
        }
    }

    private function cargar(string $xml): ?DOMDocument
    {
        $documento = new DOMDocument();

        return $xml !== '' && @$documento->loadXML($xml, LIBXML_NONET) ? $documento : null;
    }
}
