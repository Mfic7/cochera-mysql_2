<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Autoload.php';

use App\Router;
use App\Support\Response;

// CORS restringido: solo se permiten los orÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­genes conocidos de esta app (no '*').
// Ajusta ALLOWED_ORIGINS si sirves el frontend desde otro dominio (ej. otro tÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âºnel de ngrok).
const ALLOWED_ORIGINS = [
    'http://localhost',
    'http://127.0.0.1',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$originPermitido = false;
foreach (ALLOWED_ORIGINS as $permitido) {
    if (str_starts_with($origin, $permitido)) {
        $originPermitido = true;
        break;
    }
}
// TambiÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©n se permite cualquier subdominio *.ngrok-free.dev o *.ngrok.io (tÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âºneles de desarrollo).
if (!$originPermitido && preg_match('#^https://[a-z0-9-]+\.ngrok(-free)?\.(dev|io|app)$#i', $origin)) {
    $originPermitido = true;
}

if ($originPermitido) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$router = new Router();

$router->get('ping', function () {
    Response::json(['ok' => true, 'time' => date('c')]);
});

require_once __DIR__ . '/routes.php';

$path = $_SERVER['PATH_INFO'] ?? null;
if ($path === null) {
    $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '/';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    if (str_starts_with($requestUri, $scriptName)) {
        $path = substr($requestUri, strlen($scriptName));
    } else {
        $scriptDir = dirname($scriptName);
        if ($scriptDir !== '/' && str_starts_with($requestUri, $scriptDir)) {
            $path = substr($requestUri, strlen($scriptDir));
        } else {
            $path = '/';
        }
    }
}
$path = '/' . trim((string) $path, '/');

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], $path);
} catch (\Throwable $e) {
    error_log($e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    Response::error('Error interno del servidor', 500);
}
