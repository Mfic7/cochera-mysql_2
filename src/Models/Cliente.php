<?php

namespace App\Models;

use App\Database;

class Cliente
{
    /**
     * Agrupa las reservas por celular (identificador estable del cliente) y calcula
     * estadísticas básicas. El nombre mostrado es el más reciente que escribió,
     * por si lo tipeó distinto entre una reserva y otra.
     */
    public static function listar(?string $busqueda = null): array
    {
        $pdo = Database::connection();
        $where = '';
        $params = [];
        if ($busqueda !== null && $busqueda !== '') {
            $where = 'WHERE cliente_nombre LIKE :busqueda_nombre OR cliente_celular LIKE :busqueda_celular';
            $params['busqueda_nombre'] = '%' . $busqueda . '%';
            $params['busqueda_celular'] = '%' . $busqueda . '%';
        }

        $stmt = $pdo->prepare(
            "SELECT cliente_celular,
                    SUBSTRING_INDEX(GROUP_CONCAT(cliente_nombre ORDER BY created_at DESC), ',', 1) AS cliente_nombre,
                    COUNT(*) AS total_reservas,
                    SUM(CASE WHEN estado = 'cancelada' THEN 1 ELSE 0 END) AS reservas_canceladas,
                    SUM(CASE WHEN estado IN ('adelanto_pagado', 'pago_completo') THEN monto_total ELSE 0 END) AS total_gastado,
                    MAX(fecha_hora_inicio) AS ultima_reserva
             FROM reservas
             $where
             GROUP BY cliente_celular
             ORDER BY ultima_reserva DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Historial completo de reservas de un cliente, identificado por su celular. */
    public static function historial(string $celular): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT r.*, e.codigo AS espacio_codigo
             FROM reservas r
             JOIN espacios e ON e.id = r.espacio_id
             WHERE r.cliente_celular = :celular
             ORDER BY r.fecha_hora_inicio DESC"
        );
        $stmt->execute(['celular' => $celular]);
        return $stmt->fetchAll();
    }
}