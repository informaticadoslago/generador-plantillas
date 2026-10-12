<?php
declare(strict_types=1);

/*
 * API de plantillas (PHP + SQLite, sin dependencias).
 *
 *   GET    /api/plantillas                      lista (nombre, versión, fecha)
 *   GET    /api/plantillas/{nombre}             plantilla con su XML
 *   GET    /api/plantillas/{nombre}/xml         descarga el XML
 *   PUT    /api/plantillas/{nombre}             guarda {xml, version}; version = la que tenía el cliente (0 si es nueva)
 *   POST   /api/plantillas/{nombre}/duplicar    copia con otro nombre {nombre}
 *   DELETE /api/plantillas/{nombre}             borra
 *
 * Mismo esquema que la futura migración de Laravel: nombre único, xml, version y fechas.
 */

const MAX_XML = 5000000;   // incluye las imágenes en base64

function responder(int $codigo, array $datos): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fallo(int $codigo, string $mensaje, array $extra = []): never
{
    responder($codigo, ['error' => $mensaje] + $extra);
}

function bd(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $carpeta = dirname(__DIR__) . '/datos';
    if (!is_dir($carpeta) && !mkdir($carpeta, 0775, true) && !is_dir($carpeta)) {
        fallo(500, 'No se puede crear la carpeta de datos.');
    }
    $pdo = new PDO('sqlite:' . $carpeta . '/plantillas.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS plantillas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL UNIQUE COLLATE NOCASE,
            xml TEXT NOT NULL,
            version INTEGER NOT NULL DEFAULT 1,
            creada TEXT NOT NULL,
            actualizada TEXT NOT NULL
        )'
    );
    return $pdo;
}

function ahora(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}

function validarNombre(string $nombre): void
{
    if (!preg_match('/^[\p{L}\p{N}][\p{L}\p{N} _.\-]{0,79}$/u', $nombre)) {
        fallo(422, 'Nombre no válido: usa letras, números, espacios, guion, guion bajo o punto (máximo 80 caracteres).');
    }
}

function validarXml(string $xml, string $nombre): void
{
    if ($xml === '' || strlen($xml) > MAX_XML) {
        fallo(422, 'El XML está vacío o supera 5 MB.');
    }
    if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
        fallo(422, 'El XML no puede llevar DOCTYPE ni entidades.');
    }
    $anterior = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $correcto = $dom->loadXML($xml, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($anterior);
    if (!$correcto || $dom->documentElement === null) {
        fallo(422, 'El XML está mal formado.');
    }
    $raiz = $dom->documentElement;
    if ($raiz->nodeName !== 'plantilla') {
        fallo(422, 'El elemento raíz debe ser <plantilla>.');
    }
    if ($raiz->getAttribute('nombre') !== $nombre) {
        fallo(422, 'El atributo nombre de <plantilla> debe coincidir con el nombre de la plantilla.');
    }
    // Las imágenes de los logos van dentro del XML en base64.
    $tiposImagen = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'];
    $alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
    foreach ($dom->getElementsByTagName('logo') as $logo) {
        $datos = preg_replace('/\s+/', '', $logo->textContent);
        if ($datos === '') {
            continue;
        }
        $resto = substr($datos, strspn($datos, $alfabeto));
        if (!in_array($logo->getAttribute('tipo'), $tiposImagen, true) || !in_array($resto, ['', '=', '=='], true)) {
            fallo(422, 'La imagen de un logo no es válida (tipo o base64 incorrecto).');
        }
    }
}

function cuerpoJson(): array
{
    $tipo = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($tipo, 'application/json') === false) {
        fallo(415, 'El cuerpo debe enviarse como application/json.');
    }
    $datos = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($datos)) {
        fallo(400, 'JSON no válido.');
    }
    return $datos;
}

function metodoNoPermitido(string ...$permitidos): never
{
    header('Allow: ' . implode(', ', $permitidos));
    fallo(405, 'Método no permitido.');
}

function buscar(string $nombre): ?array
{
    $st = bd()->prepare('SELECT nombre, xml, version, creada, actualizada FROM plantillas WHERE nombre = ?');
    $st->execute([$nombre]);
    $fila = $st->fetch();
    return $fila === false ? null : $fila;
}

/* ----------------------------- enrutado ----------------------------- */

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$ruta = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

if (!preg_match('#(?:^|/)api/plantillas(?:/([^/]+))?(?:/(xml|duplicar))?$#', $ruta, $m)) {
    fallo(404, 'Ruta no encontrada.');
}
$nombre = isset($m[1]) && $m[1] !== '' ? rawurldecode($m[1]) : null;
$accion = $m[2] ?? null;

try {
    // Lista
    if ($nombre === null) {
        if ($metodo !== 'GET') {
            metodoNoPermitido('GET');
        }
        $filas = bd()->query('SELECT nombre, version, actualizada FROM plantillas ORDER BY nombre COLLATE NOCASE')->fetchAll();
        foreach ($filas as &$f) {
            $f['version'] = (int) $f['version'];
        }
        unset($f);
        responder(200, ['plantillas' => $filas]);
    }

    validarNombre($nombre);

    // Descargar XML
    if ($accion === 'xml') {
        if ($metodo !== 'GET') {
            metodoNoPermitido('GET');
        }
        $fila = buscar($nombre) ?? fallo(404, 'La plantilla no existe.');
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^\w.\-]+/u', '_', $fila['nombre']) . '.xml"');
        echo $fila['xml'];
        exit;
    }

    // Duplicar
    if ($accion === 'duplicar') {
        if ($metodo !== 'POST') {
            metodoNoPermitido('POST');
        }
        $nuevo = trim((string) (cuerpoJson()['nombre'] ?? ''));
        validarNombre($nuevo);
        $fila = buscar($nombre) ?? fallo(404, 'La plantilla no existe.');
        if (buscar($nuevo) !== null) {
            fallo(409, 'Ya existe una plantilla con ese nombre.', ['codigo' => 'existe']);
        }
        $dom = new DOMDocument();
        $dom->loadXML($fila['xml'], LIBXML_NONET);
        $dom->documentElement->setAttribute('nombre', $nuevo);
        $xml = $dom->saveXML();
        $t = ahora();
        $st = bd()->prepare('INSERT INTO plantillas (nombre, xml, version, creada, actualizada) VALUES (?, ?, 1, ?, ?)');
        $st->execute([$nuevo, $xml, $t, $t]);
        responder(201, ['nombre' => $nuevo, 'version' => 1, 'actualizada' => $t]);
    }

    // Leer, guardar, borrar
    switch ($metodo) {
        case 'GET':
            $fila = buscar($nombre) ?? fallo(404, 'La plantilla no existe.');
            $fila['version'] = (int) $fila['version'];
            responder(200, $fila);

        case 'PUT':
            $datos = cuerpoJson();
            $xml = (string) ($datos['xml'] ?? '');
            $version = (int) ($datos['version'] ?? 0);
            validarXml($xml, $nombre);

            $pdo = bd();
            $pdo->exec('BEGIN IMMEDIATE');
            try {
                $fila = buscar($nombre);
                $t = ahora();
                if ($fila === null) {
                    $st = $pdo->prepare('INSERT INTO plantillas (nombre, xml, version, creada, actualizada) VALUES (?, ?, 1, ?, ?)');
                    $st->execute([$nombre, $xml, $t, $t]);
                    $pdo->exec('COMMIT');
                    responder(201, ['nombre' => $nombre, 'version' => 1, 'actualizada' => $t]);
                }
                $actual = (int) $fila['version'];
                if ($version !== $actual) {
                    $pdo->exec('ROLLBACK');
                    if ($version === 0) {
                        fallo(409, 'Ya existe una plantilla con ese nombre.', ['codigo' => 'existe', 'version_actual' => $actual]);
                    }
                    fallo(409, "La plantilla ha cambiado en el servidor (versión $actual). Ábrela de nuevo antes de guardar.", ['codigo' => 'version', 'version_actual' => $actual]);
                }
                $st = $pdo->prepare('UPDATE plantillas SET nombre = ?, xml = ?, version = version + 1, actualizada = ? WHERE nombre = ?');
                $st->execute([$nombre, $xml, $t, $nombre]);
                $pdo->exec('COMMIT');
                responder(200, ['nombre' => $nombre, 'version' => $actual + 1, 'actualizada' => $t]);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->exec('ROLLBACK');
                }
                throw $e;
            }

        case 'DELETE':
            $st = bd()->prepare('DELETE FROM plantillas WHERE nombre = ?');
            $st->execute([$nombre]);
            if ($st->rowCount() === 0) {
                fallo(404, 'La plantilla no existe.');
            }
            responder(200, ['borrada' => $nombre]);

        default:
            metodoNoPermitido('GET', 'PUT', 'DELETE');
    }
} catch (PDOException $e) {
    error_log('plantillas: ' . $e->getMessage());
    fallo(500, 'Error de base de datos.');
}
