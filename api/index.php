<?php

declare(strict_types=1);

require __DIR__ . '/../src/Autoload.php';

use App\Router;
use App\Support\Response;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
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

$path = $_SERVER['PATH_INFO'] ?? '/';

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], $path);
} catch (\Throwable $e) {
    error_log($e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    Response::error('Error interno del servidor', 500);
}
