<?php

namespace App\Models;

use App\Database;

class UsuarioAdmin
{
    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM usuarios_admin WHERE email = :email AND activo = 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function marcarLogin(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE usuarios_admin SET ultimo_login = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
