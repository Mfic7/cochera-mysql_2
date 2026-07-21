<?php

namespace App\Models;

use App\Database;

class Espacio
{
    public static function todosActivos(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, codigo, numero, zona, estado FROM espacios WHERE activo = 1 ORDER BY numero'
        );
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM espacios WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function tarifaHora(): float
    {
        $stmt = Database::connection()->prepare('SELECT valor FROM configuracion WHERE clave = :clave');
        $stmt->execute(['clave' => 'tarifa_hora']);
        $row = $stmt->fetch();
        return $row ? (float) $row['valor'] : 20.0;
    }
}
