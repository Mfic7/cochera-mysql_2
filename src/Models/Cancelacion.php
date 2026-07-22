<?php

namespace App\Models;

use App\Database;

class Cancelacion
{
    public static function crear(\PDO $pdo, array $datos): int
    {
        $stmt = $pdo->prepare(
            'INSERT INTO cancelaciones (reserva_id, motivo, numero_operacion, comprobante_path, estado)
             VALUES (:reserva_id, :motivo, :numero_operacion, :comprobante_path, \'pendiente\')'
        );
        $stmt->execute([
            'reserva_id' => $datos['reserva_id'],
            'motivo' => $datos['motivo'],
            'numero_operacion' => $datos['numero_operacion'] ?? null,
            'comprobante_path' => $datos['comprobante_path'] ?? null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function pendienteParaReserva(int $reservaId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM cancelaciones WHERE reserva_id = :id AND estado = 'pendiente' LIMIT 1"
        );
        $stmt->execute(['id' => $reservaId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM cancelaciones WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function marcar(\PDO $pdo, int $id, string $estado, ?int $adminId, ?string $nota): void
    {
        $stmt = $pdo->prepare(
            'UPDATE cancelaciones SET estado = :estado, admin_id = :admin_id, nota_admin = :nota, revisado_en = NOW()
             WHERE id = :id'
        );
        $stmt->execute(['estado' => $estado, 'admin_id' => $adminId, 'nota' => $nota, 'id' => $id]);
    }

    /** Lista para el panel admin, con datos de la reserva y el espacio ya unidos. */
    public static function listar(?string $estado = null, int $page = 1, int $perPage = 20): array
    {
        $pdo = Database::connection();
        $offset = max(0, ($page - 1) * $perPage);
        $where = $estado ? 'WHERE c.estado = :estado' : '';

        $stmt = $pdo->prepare(
            "SELECT c.*, r.codigo AS reserva_codigo, r.cliente_nombre, r.cliente_celular,
                    r.monto_total, r.monto_adelanto, r.fecha_hora_inicio, r.estado AS reserva_estado,
                    e.codigo AS espacio_codigo
             FROM cancelaciones c
             JOIN reservas r ON r.id = c.reserva_id
             JOIN espacios e ON e.id = r.espacio_id
             $where
             ORDER BY c.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        if ($estado) {
            $stmt->bindValue(':estado', $estado);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $countSql = 'SELECT COUNT(*) AS n FROM cancelaciones c ' . $where;
        $countStmt = $pdo->prepare($countSql);
        if ($estado) {
            $countStmt->bindValue(':estado', $estado);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetch()['n'];

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }
}