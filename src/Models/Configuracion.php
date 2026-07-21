<?php

namespace App\Models;

use App\Database;

class Configuracion
{
    public static function all(): array
    {
        $stmt = Database::connection()->query('SELECT clave, valor FROM configuracion');
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['clave']] = $row['valor'];
        }
        return $out;
    }

    public static function get(string $clave, mixed $default = null): mixed
    {
        $stmt = Database::connection()->prepare('SELECT valor FROM configuracion WHERE clave = :clave');
        $stmt->execute(['clave' => $clave]);
        $row = $stmt->fetch();
        return $row ? $row['valor'] : $default;
    }

    public static function set(string $clave, string $valor): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO configuracion (clave, valor) VALUES (:clave, :valor)
             ON DUPLICATE KEY UPDATE valor = :valor2'
        );
        $stmt->execute(['clave' => $clave, 'valor' => $valor, 'valor2' => $valor]);
    }
}
