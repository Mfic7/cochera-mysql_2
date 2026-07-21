<?php

namespace App\Models;

use App\Database;

class Pago
{
    public static function crear(array $datos): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO pagos (reserva_id, tipo, metodo, monto, numero_operacion, comprobante_path, estado)
             VALUES (:reserva_id, :tipo, :metodo, :monto, :numero_operacion, :comprobante_path, :estado)'
        );
        $stmt->execute([
            'reserva_id' => $datos['reserva_id'],
            'tipo' => $datos['tipo'],
            'metodo' => $datos['metodo'],
            'monto' => $datos['monto'],
            'numero_operacion' => $datos['numero_operacion'] ?? null,
            'comprobante_path' => $datos['comprobante_path'] ?? null,
            'estado' => $datos['estado'] ?? 'en_validacion',
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.*, r.codigo AS reserva_codigo, r.cliente_nombre, r.espacio_id
             FROM pagos p JOIN reservas r ON r.id = p.reserva_id WHERE p.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function paraReserva(int $reservaId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM pagos WHERE reserva_id = :id ORDER BY created_at DESC');
        $stmt->execute(['id' => $reservaId]);
        return $stmt->fetchAll();
    }

    public static function listar(?string $estado = null): array
    {
        $sql = 'SELECT p.*, r.codigo AS reserva_codigo, r.cliente_nombre, e.codigo AS espacio_codigo
                FROM pagos p
                JOIN reservas r ON r.id = p.reserva_id
                JOIN espacios e ON e.id = r.espacio_id';
        $params = [];
        if ($estado) {
            $sql .= ' WHERE p.estado = :estado';
            $params['estado'] = $estado;
        }
        $sql .= ' ORDER BY p.created_at DESC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function actualizarRevision(int $id, string $estado, ?int $adminId, ?string $motivo = null): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pagos SET estado = :estado, admin_id = :admin_id, motivo_rechazo = :motivo, revisado_en = NOW()
             WHERE id = :id'
        );
        $stmt->execute(['estado' => $estado, 'admin_id' => $adminId, 'motivo' => $motivo, 'id' => $id]);
    }

    public static function ingresosPorMetodo(string $desde, string $hasta): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT metodo, SUM(monto) AS total FROM pagos
             WHERE estado = 'aprobado' AND DATE(revisado_en) BETWEEN :desde AND :hasta
             GROUP BY metodo"
        );
        $stmt->execute(['desde' => $desde, 'hasta' => $hasta]);
        return $stmt->fetchAll();
    }
}
