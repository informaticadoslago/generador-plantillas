<?php
/*
 * Router del servidor integrado de PHP (solo desarrollo):
 *
 *   php -S localhost:8000 -t public router.php
 *
 * Las rutas /api/... van a api/index.php; el resto son archivos estáticos de public/.
 */
$ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (str_starts_with($ruta, '/api/')) {
    require __DIR__ . '/api/index.php';
    return true;
}

return false;
