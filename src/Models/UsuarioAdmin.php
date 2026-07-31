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

    /** Lista para el panel admin, sin exponer password_hash nunca. */
    public static function listar(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, nombre, email, rol, activo, ultimo_login, created_at
             FROM usuarios_admin ORDER BY nombre ASC'
        );
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, nombre, email, rol, activo, ultimo_login, created_at
             FROM usuarios_admin WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function existeEmail(string $email, ?int $excluirId = null): bool
    {
        $pdo = Database::connection();
        $sql = 'SELECT COUNT(*) AS n FROM usuarios_admin WHERE email = :email';
        $params = ['email' => $email];
        if ($excluirId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $excluirId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetch()['n'] > 0;
    }

    public static function crear(array $datos): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO usuarios_admin (nombre, email, password_hash, rol, activo)
             VALUES (:nombre, :email, :password_hash, :rol, 1)'
        );
        $stmt->execute([
            'nombre' => $datos['nombre'],
            'email' => $datos['email'],
            'password_hash' => password_hash($datos['password'], PASSWORD_DEFAULT),
            'rol' => $datos['rol'],
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function actualizar(int $id, array $datos): void
    {
        $pdo = Database::connection();
        $campos = ['nombre = :nombre', 'rol = :rol', 'activo = :activo'];
        $params = [
            'id' => $id,
            'nombre' => $datos['nombre'],
            'rol' => $datos['rol'],
            'activo' => $datos['activo'] ? 1 : 0,
        ];
        if (!empty($datos['password'])) {
            $campos[] = 'password_hash = :password_hash';
            $params['password_hash'] = password_hash($datos['password'], PASSWORD_DEFAULT);
        }
        $sql = 'UPDATE usuarios_admin SET ' . implode(', ', $campos) . ' WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
}