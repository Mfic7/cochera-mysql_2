<?php

namespace App\Models;

use App\Database;

class Cancelacion
{
    public static function crear(int $reservaId, string $motivo, ?string $numeroOperacion, ?string $comprobantePath): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO cancelaciones (reserva_id, motivo, numero_operacion, comprobante_path)
             VALUES (:reserva_id, :motivo, :numero_operacion, :comprobante_path)'
        );
        $stmt->execute([
            'reserva_id' => $reservaId,
            'motivo' => $motivo,
            'numero_operacion' => $numeroOperacion,
            'comprobante_path' => $comprobantePath,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.*, r.codigo AS reserva_codigo, r.cliente_nombre, r.espacio_id, e.codigo AS espacio_codigo
             FROM cancelaciones c
             JOIN reservas r ON r.id = c.reserva_id
             JOIN espacios e ON e.id = r.espacio_id
             WHERE c.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function existsForReserva(int $reservaId): bool
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) AS n FROM cancelaciones WHERE reserva_id = :id');
        $stmt->execute(['id' => $reservaId]);
        return (int) $stmt->fetch()['n'] > 0;
    }

    public static function listar(): array
    {
        $stmt = Database::connection()->query(
            'SELECT c.*, r.codigo AS reserva_codigo, r.cliente_nombre, r.cliente_celular, e.codigo AS espacio_codigo
             FROM cancelaciones c
             JOIN reservas r ON r.id = c.reserva_id
             JOIN espacios e ON e.id = r.espacio_id
             ORDER BY c.created_at DESC'
        );
        return $stmt->fetchAll();
    }

    public static function marcarRevisado(int $id, bool $revisado): void
    {
        $stmt = Database::connection()->prepare('UPDATE cancelaciones SET revisado = :revisado WHERE id = :id');
        $stmt->execute(['revisado' => $revisado ? 1 : 0, 'id' => $id]);
    }
}
