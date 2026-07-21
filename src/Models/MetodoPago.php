<?php

namespace App\Models;

use App\Database;

class MetodoPago
{
    public static function activos(): array
    {
        $stmt = Database::connection()->query(
            'SELECT tipo, titular, numero_cuenta, banco, qr_image_path FROM metodos_pago WHERE activo = 1'
        );
        return $stmt->fetchAll();
    }

    public static function todos(): array
    {
        $stmt = Database::connection()->query('SELECT * FROM metodos_pago ORDER BY tipo');
        return $stmt->fetchAll();
    }

    public static function actualizar(string $tipo, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE metodos_pago SET titular = :titular, numero_cuenta = :numero_cuenta,
             banco = :banco, activo = :activo WHERE tipo = :tipo'
        );
        $stmt->execute([
            'titular' => $data['titular'],
            'numero_cuenta' => $data['numero_cuenta'],
            'banco' => $data['banco'] ?? null,
            'activo' => isset($data['activo']) ? (int) $data['activo'] : 1,
            'tipo' => $tipo,
        ]);
    }

    public static function actualizarQr(string $tipo, string $path): void
    {
        $stmt = Database::connection()->prepare('UPDATE metodos_pago SET qr_image_path = :path WHERE tipo = :tipo');
        $stmt->execute(['path' => $path, 'tipo' => $tipo]);
    }
}
