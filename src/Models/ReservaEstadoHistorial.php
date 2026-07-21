<?php

namespace App\Models;

use App\Database;

class ReservaEstadoHistorial
{
    public static function registrar(
        \PDO $pdo,
        int $reservaId,
        ?string $estadoAnterior,
        string $estadoNuevo,
        string $actorTipo,
        ?int $actorId = null,
        ?string $nota = null
    ): void {
        $stmt = $pdo->prepare(
            'INSERT INTO reserva_estado_historial (reserva_id, estado_anterior, estado_nuevo, actor_tipo, actor_id, nota)
             VALUES (:reserva_id, :estado_anterior, :estado_nuevo, :actor_tipo, :actor_id, :nota)'
        );
        $stmt->execute([
            'reserva_id' => $reservaId,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'actor_tipo' => $actorTipo,
            'actor_id' => $actorId,
            'nota' => $nota,
        ]);
    }

    public static function paraReserva(int $reservaId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM reserva_estado_historial WHERE reserva_id = :id ORDER BY created_at ASC'
        );
        $stmt->execute(['id' => $reservaId]);
        return $stmt->fetchAll();
    }

    public static function recientes(int $limit = 15): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT h.*, r.codigo AS reserva_codigo, r.cliente_nombre, e.codigo AS espacio_codigo
             FROM reserva_estado_historial h
             JOIN reservas r ON r.id = h.reserva_id
             JOIN espacios e ON e.id = r.espacio_id
             ORDER BY h.created_at DESC LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
