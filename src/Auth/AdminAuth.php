<?php

namespace App\Auth;

use App\Config;

class AdminAuth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name(Config::get('session_name', 'micochera_admin'));
        session_start();
    }

    public static function login(array $admin): void
    {
        self::startSession();
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_nombre'] = $admin['nombre'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['admin_rol'] = $admin['rol'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        session_destroy();
    }

    public static function check(): bool
    {
        self::startSession();
        return isset($_SESSION['admin_id']);
    }

    public static function user(): ?array
    {
        self::startSession();
        if (!self::check()) {
            return null;
        }
        return [
            'id' => $_SESSION['admin_id'],
            'nombre' => $_SESSION['admin_nombre'],
            'email' => $_SESSION['admin_email'],
            'rol' => $_SESSION['admin_rol'],
        ];
    }

    public static function csrfToken(): string
    {
        self::startSession();
        return $_SESSION['csrf_token'] ?? '';
    }

    public static function verifyCsrf(?string $token): bool
    {
        self::startSession();
        return $token !== null && hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }
}
