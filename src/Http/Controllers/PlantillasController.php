<?php

namespace InformaticadosLago\GeneradorPlantillas\Http\Controllers;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use InformaticadosLago\GeneradorPlantillas\Almacenes\Almacen;
use InformaticadosLago\GeneradorPlantillas\GeneradorPlantillas;
use InformaticadosLago\GeneradorPlantillas\Support\ValidadorPlantilla;

/**
 * API del editor, con el mismo contrato que api/index.php (modo suelto): mismas
 * respuestas y mismos códigos (409 con codigo «existe» o «version»). Trabaja
 * con las plantillas del plantillable de la petición (GeneradorPlantillas) o,
 * en las rutas de las globales, con las de su carpeta.
 *
 * Con crear a false, en las del plantillable solo se modifican las que ya hay:
 * crear, duplicar y borrar responden 403. Las globales, siempre.
 */
class PlantillasController extends Controller
{
    public function __construct(private ValidadorPlantilla $validador) {}

    public function lista(Request $request): JsonResponse
    {
        $plantillas = array_map(fn (array $p) => [
            'nombre'      => $p['nombre'],
            'version'     => $p['version'],
            'actualizada' => $this->fecha($p['actualizada']),
        ], $this->almacen($request)->lista());

        return response()->json(['plantillas' => $plantillas]);
    }

    public function ver(Request $request, string $nombre): JsonResponse
    {
        $plantilla = $this->buscar($request, $nombre);
        if (! is_array($plantilla)) {
            return $plantilla;
        }

        return response()->json([
            'nombre'      => $plantilla['nombre'],
            'xml'         => $plantilla['xml'],
            'version'     => $plantilla['version'],
            'creada'      => $this->fecha($plantilla['creada']),
            'actualizada' => $this->fecha($plantilla['actualizada']),
        ]);
    }

    public function xml(Request $request, string $nombre): Response|JsonResponse
    {
        $plantilla = $this->buscar($request, $nombre);
        if (! is_array($plantilla)) {
            return $plantilla;
        }
        $fichero = preg_replace('/[^\w.\-]+/u', '_', $plantilla['nombre']) . '.xml';

        return response($plantilla['xml'], 200, [
            'Content-Type'        => 'application/xml; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $fichero . '"',
        ]);
    }

    /**
     * Guarda {xml, version}: version es la que tenía el cliente (0 si es nueva).
     * Si no coincide con la guardada, 409 y no se toca nada.
     */
    public function guardar(Request $request, string $nombre): JsonResponse
    {
        if ($error = $this->validador->nombre($nombre)) {
            return $this->fallo(422, $error);
        }
        if (! $request->isJson()) {
            return $this->fallo(415, 'El cuerpo debe enviarse como application/json.');
        }
        $xml     = (string) $request->json('xml', '');
        $version = (int) $request->json('version', 0);
        if ($error = $this->validador->xml($xml, $nombre)) {
            return $this->fallo(422, $error);
        }
        $almacen = $this->almacen($request);
        $crear   = $this->puedeCrear($request);

        return $almacen->bloqueado(function () use ($almacen, $crear, $nombre, $xml, $version) {
            $plantilla = $almacen->buscar($nombre);

            if ($plantilla === null) {
                if (! $crear) {
                    return $this->fallo(403, 'Aquí solo se modifican las plantillas que ya hay: no se crean nuevas.');
                }
                try {
                    return $this->respuestaGuardada($almacen->crear($nombre, $xml), 201);
                } catch (DomainException $e) {
                    return $this->fallo(409, $e->getMessage(), ['codigo' => 'existe']);
                }
            }

            if ($version !== $plantilla['version']) {
                return $version === 0
                    ? $this->fallo(409, 'Ya existe una plantilla con ese nombre.', ['codigo' => 'existe', 'version_actual' => $plantilla['version']])
                    : $this->fallo(409, "La plantilla ha cambiado en el servidor (versión {$plantilla['version']}). Ábrela de nuevo antes de guardar.", ['codigo' => 'version', 'version_actual' => $plantilla['version']]);
            }

            return $this->respuestaGuardada($almacen->actualizar($plantilla['nombre'], $nombre, $xml), 200);
        });
    }

    /** Copia lo guardado con otro nombre {nombre}, en el mismo sitio. */
    public function duplicar(Request $request, string $nombre): JsonResponse
    {
        if (! $this->puedeCrear($request)) {
            return $this->fallo(403, 'Aquí solo se modifican las plantillas que ya hay: no se duplican.');
        }
        if ($error = $this->validador->nombre($nombre)) {
            return $this->fallo(422, $error);
        }
        if (! $request->isJson()) {
            return $this->fallo(415, 'El cuerpo debe enviarse como application/json.');
        }
        $nuevo = trim((string) $request->json('nombre', ''));
        if ($error = $this->validador->nombre($nuevo)) {
            return $this->fallo(422, $error);
        }
        $almacen = $this->almacen($request);

        return $almacen->bloqueado(function () use ($almacen, $nombre, $nuevo) {
            $original = $almacen->buscar($nombre);
            if ($original === null) {
                return $this->fallo(404, 'La plantilla no existe.');
            }
            if ($almacen->buscar($nuevo) !== null) {
                return $this->fallo(409, 'Ya existe una plantilla con ese nombre.', ['codigo' => 'existe']);
            }
            try {
                return $this->respuestaGuardada($almacen->crear($nuevo, $this->validador->renombrar($original['xml'], $nuevo)), 201);
            } catch (DomainException $e) {
                return $this->fallo(409, $e->getMessage(), ['codigo' => 'existe']);
            }
        });
    }

    public function borrar(Request $request, string $nombre): JsonResponse
    {
        if (! $this->puedeCrear($request)) {
            return $this->fallo(403, 'Aquí solo se modifican las plantillas que ya hay: no se borran.');
        }
        $plantilla = $this->buscar($request, $nombre);
        if (! is_array($plantilla)) {
            return $plantilla;
        }
        $this->almacen($request)->borrar($plantilla['nombre']);

        return response()->json(['borrada' => $nombre]);
    }

    private function almacen(Request $request): Almacen
    {
        return GeneradorPlantillas::almacen($request);
    }

    private function puedeCrear(Request $request): bool
    {
        return GeneradorPlantillas::sonGlobales($request) || config('generador-plantillas.crear', true);
    }

    /** La plantilla de la petición, o la respuesta de error (422 o 404). */
    private function buscar(Request $request, string $nombre): array|JsonResponse
    {
        if ($error = $this->validador->nombre($nombre)) {
            return $this->fallo(422, $error);
        }

        return $this->almacen($request)->buscar($nombre) ?? $this->fallo(404, 'La plantilla no existe.');
    }

    private function respuestaGuardada(array $plantilla, int $codigo): JsonResponse
    {
        return response()->json([
            'nombre'      => $plantilla['nombre'],
            'version'     => $plantilla['version'],
            'actualizada' => $this->fecha($plantilla['actualizada']),
        ], $codigo);
    }

    private function fallo(int $codigo, string $mensaje, array $extra = []): JsonResponse
    {
        return response()->json(['error' => $mensaje] + $extra, $codigo);
    }

    private function fecha(?Carbon $fecha): ?string
    {
        return $fecha?->toIso8601String();
    }
}
