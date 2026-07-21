<?php

namespace App\Services;

use App\Database;
use App\Models\Espacio;

class EspacioAvailabilityService
{
    private const ESTADOS_ACTIVOS = ['pendiente_pago', 'en_validacion', 'adelanto_pagado', 'pago_completo'];

    /** Expira en caliente (sin cron) las reservas cuyo hold de 5 min ya venció. */
    public static function expirarHoldsVencidos(): void
    {
        Database::connection()->exec(
            "UPDATE reservas SET estado = 'vencida'
             WHERE estado = 'pendiente_pago' AND hold_expira_en IS NOT NULL AND hold_expira_en < NOW()"
        );
    }

    /**
     * Estado de cada espacio para la ventana [inicio, fin). Devuelve también
     * conteos agregados para las tarjetas de stats del mockup.
     */
    public static function disponibilidad(string $inicio, string $fin): array
    {
        self::expirarHoldsVencidos();

        $espacios = Espacio::todosActivos();

        $stmt = Database::connection()->prepare(
            "SELECT DISTINCT espacio_id FROM reservas
             WHERE estado IN ('" . implode("','", self::ESTADOS_ACTIVOS) . "')
               AND fecha_hora_inicio < :fin AND fecha_hora_fin > :inicio"
        );
        $stmt->execute(['inicio' => $inicio, 'fin' => $fin]);
        $reservados = array_column($stmt->fetchAll(), 'espacio_id');
        $reservados = array_flip($reservados);

        $disponibles = 0;
        $ocupados = 0;
        foreach ($espacios as &$espacio) {
            if ($espacio['estado'] === 'ocupado' || $espacio['estado'] === 'mantenimiento') {
                $espacio['estado_ventana'] = 'ocupado';
                $ocupados++;
            } elseif (isset($reservados[(int) $espacio['id']])) {
                $espacio['estado_ventana'] = 'reservado';
            } else {
                $espacio['estado_ventana'] = 'disponible';
                $disponibles++;
            }
        }
        unset($espacio);

        return [
            'espacios' => $espacios,
            'disponibles' => $disponibles,
            'ocupados' => $ocupados,
            'reservados' => count($espacios) - $disponibles - $ocupados,
            'tarifa_hora' => Espacio::tarifaHora(),
        ];
    }

    public static function espacioDisponibleEnVentana(int $espacioId, string $inicio, string $fin): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) AS n FROM reservas
             WHERE espacio_id = :espacio_id
               AND estado IN ('" . implode("','", self::ESTADOS_ACTIVOS) . "')
               AND fecha_hora_inicio < :fin AND fecha_hora_fin > :inicio"
        );
        $stmt->execute(['espacio_id' => $espacioId, 'inicio' => $inicio, 'fin' => $fin]);
        return (int) $stmt->fetch()['n'] === 0;
    }
}
