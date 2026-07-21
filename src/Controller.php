<?php

namespace App;

use App\Auth\AdminAuth;
use App\Support\Response;
use App\Support\ValidationException;

abstract class Controller
{
    protected function json(mixed $data, int $status = 200): never
    {
        Response::json($data, $status);
        exit;
    }

    protected function error(string $message, int $status = 400, array $extra = []): never
    {
        Response::error($message, $status, $extra);
        exit;
    }

    /** Cuerpo JSON (o form-urlencoded/multipart vía $_POST) fusionado, como arreglo asociativo. */
    protected function input(): array
    {
        $raw = file_get_contents('php://input');
        $json = [];
        if ($raw !== false && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $json = $decoded;
            }
        }
        return array_merge($_POST, $json);
    }

    protected function requireAdmin(): array
    {
        $admin = AdminAuth::user();
        if ($admin === null) {
            $this->error('No autenticado', 401);
        }
        return $admin;
    }

    protected function requireCsrf(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!AdminAuth::verifyCsrf($token)) {
            $this->error('Token CSRF inválido', 403);
        }
    }

    protected function handleValidation(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (ValidationException $e) {
            $this->error('Datos inválidos', 422, ['errores' => $e->errors]);
        }
    }
}
