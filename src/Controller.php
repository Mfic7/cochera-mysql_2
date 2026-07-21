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

    /**
     * Lee el cuerpo de la petición de forma robusta para JSON, x-www-form-urlencoded
     * y multipart/form-data (incluyendo PATCH/POST desde el panel admin y del usuario).
     */
    protected function input(): array
    {
        $data = [];

        if (!empty($_POST)) {
            $data = array_merge($data, $_POST);
        }

        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return $data;
        }

        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = array_merge($data, $decoded);
            }
            return $data;
        }

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            parse_str($raw, $parsed);
            if (is_array($parsed)) {
                $data = array_merge($data, $parsed);
            }
            return $data;
        }

        if (str_contains($contentType, 'multipart/form-data')) {
            $data = array_merge($data, $this->parseMultipartFormData($raw, $contentType));
        }

        return $data;
    }

    private function parseMultipartFormData(string $raw, string $contentType): array
    {
        if (!preg_match('/boundary=(?:"([^"]+)"|([^;]+))/', $contentType, $matches)) {
            return [];
        }

        $boundary = trim($matches[1] ?? $matches[2] ?? '');
        if ($boundary === '') {
            return [];
        }

        $parts = array_filter(explode('--' . $boundary, $raw), static fn ($part) => trim($part) !== '' && trim($part) !== '--');
        $values = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $segments = explode("\r\n\r\n", $part, 2);
            if (count($segments) !== 2) {
                continue;
            }

            [$headersRaw, $body] = $segments;
            $headers = [];
            foreach (explode("\r\n", $headersRaw) as $line) {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $headers[strtolower(trim($name))] = trim($value);
                }
            }

            $contentDisposition = $headers['content-disposition'] ?? '';
            if (!preg_match('/name="([^"]+)"/', $contentDisposition, $nameMatch)) {
                continue;
            }

            $fieldName = $nameMatch[1];
            if (preg_match('/filename="([^"]*)"/', $contentDisposition)) {
                continue;
            }

            $values[$fieldName] = trim(rtrim($body, "\r\n"));
        }

        return $values;
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
