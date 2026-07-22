<?php

declare(strict_types=1);

require __DIR__ . '/../src/Autoload.php';

use App\Router;
use App\Support\Response;

header('Access-Control-Allow-Origin: *');
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

require __DIR__ . '/routes.php';

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
