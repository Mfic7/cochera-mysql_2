<?php

namespace App\Services;

use App\Database;
use App\Models\Configuracion;
use App\Models\Espacio;
use App\Models\Reserva;
use App\Models\ReservaEstadoHistorial;
use App\Support\Dates;

class ReservaConflictException extends \RuntimeException
{
}

class ReservaService
{
    /**
     * Crea una reserva con bloqueo temporal (hold), garantizando primero-en-llegar
     * mediante SELECT ... FOR UPDATE sobre la fila del espacio dentro de una transacción.
     */
    public static function crear(array $datos): array
    {
        $pdo = Database::connection();
        $holdMinutes = (int) Configuracion::get('hold_minutes', 5);
        $adelantoPct = (float) Configuracion::get('adelanto_porcentaje', 50);

        $inicio = $datos['fecha_hora_inicio'];
        $horas = (float) $datos['horas_estimadas'];
        $inicioTs = strtotime($inicio);
        if ($inicioTs === false) {
            throw new \InvalidArgumentException('Fecha/hora de inicio inválida');
        }
        $fin = date('Y-m-d H:i:s', (int) ($inicioTs + $horas * 3600));

        $pdo->beginTransaction();
        try {
            $espacioId = (int) $datos['espacio_id'];

            // Serializa intentos concurrentes SOLO para este espacio; no bloquea otros espacios.
            $lockStmt = $pdo->prepare('SELECT id, activo FROM espacios WHERE id = :id FOR UPDATE');
            $lockStmt->execute(['id' => $espacioId]);
            $espacio = $lockStmt->fetch();
            if (!$espacio || !$espacio['activo']) {
                throw new ReservaConflictException('El espacio no existe o no está disponible.');
            }

            // Expira holds vencidos de ESTE espacio dentro del lock, antes del chequeo de solapamiento.
            $pdo->prepare(
                "UPDATE reservas SET estado = 'vencida'
                 WHERE espacio_id = :id AND estado = 'pendiente_pago' AND hold_expira_en < NOW()"
            )->execute(['id' => $espacioId]);

            $overlapStmt = $pdo->prepare(
                "SELECT COUNT(*) AS n FROM reservas
                 WHERE espacio_id = :id
                   AND estado IN ('pendiente_pago','en_validacion','adelanto_pagado','pago_completo')
                   AND fecha_hora_inicio < :fin AND fecha_hora_fin > :inicio"
            );
            $overlapStmt->execute(['id' => $espacioId, 'inicio' => $inicio, 'fin' => $fin]);
            if ((int) $overlapStmt->fetch()['n'] > 0) {
                throw new ReservaConflictException('Este espacio ya no está disponible, elige otro.');
            }

            $tarifaHora = Espacio::tarifaHora();
            $montoTotal = round($tarifaHora * $horas, 2);
            $montoAdelanto = round($montoTotal * $adelantoPct / 100, 2);
            $montoRestante = round($montoTotal - $montoAdelanto, 2);
            $token = bin2hex(random_bytes(16));
            $codigo = Reserva::generarCodigo($pdo);
            $holdExpiraEn = date('Y-m-d H:i:s', time() + $holdMinutes * 60);

            $insert = $pdo->prepare(
                'INSERT INTO reservas
                    (codigo, token, espacio_id, cliente_nombre, cliente_celular, fecha_hora_inicio, horas_estimadas,
                     fecha_hora_fin, tarifa_hora, monto_total, monto_adelanto, monto_restante, estado, hold_expira_en, ip_origen)
                 VALUES
                    (:codigo, :token, :espacio_id, :cliente_nombre, :cliente_celular, :inicio, :horas,
                     :fin, :tarifa, :total, :adelanto, :restante, \'pendiente_pago\', :hold_expira_en, :ip)'
            );
            $insert->execute([
                'codigo' => $codigo,
                'token' => $token,
                'espacio_id' => $espacioId,
                'cliente_nombre' => $datos['cliente_nombre'],
                'cliente_celular' => $datos['cliente_celular'],
                'inicio' => $inicio,
                'horas' => $horas,
                'fin' => $fin,
                'tarifa' => $tarifaHora,
                'total' => $montoTotal,
                'adelanto' => $montoAdelanto,
                'restante' => $montoRestante,
                'hold_expira_en' => $holdExpiraEn,
                'ip' => $datos['ip_origen'] ?? null,
            ]);
            $reservaId = (int) $pdo->lastInsertId();

            ReservaEstadoHistorial::registrar($pdo, $reservaId, null, 'pendiente_pago', 'cliente', null, 'Reserva creada, espacio bloqueado por ' . $holdMinutes . ' min');

            $pdo->commit();

            return [
                'id' => $reservaId,
                'codigo' => $codigo,
                'token' => $token,
                'espacio_id' => $espacioId,
                'estado' => 'pendiente_pago',
                'fecha_hora_inicio' => Dates::iso($inicio),
                'fecha_hora_fin' => Dates::iso($fin),
                'horas_estimadas' => $horas,
                'tarifa_hora' => $tarifaHora,
                'monto_total' => $montoTotal,
                'monto_adelanto' => $montoAdelanto,
                'monto_restante' => $montoRestante,
                'hold_expira_en' => Dates::iso($holdExpiraEn),
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
