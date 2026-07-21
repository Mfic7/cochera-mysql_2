<?php

namespace App\Controllers\Admin;

use App\Controller;
use App\Database;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\ReservaEstadoHistorial;
use App\Support\Validator;

class ReservaAdminController extends Controller
{
    private const ESTADOS_VALIDOS = ['pendiente_pago', 'en_validacion', 'adelanto_pagado', 'pago_completo', 'cancelada', 'vencida'];

    public function listar(): void
    {
        $this->requireAdmin();
        $filtros = [
            'fecha' => $_GET['fecha'] ?? null,
            'estado' => $_GET['estado'] ?? null,
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $this->json(Reserva::listar($filtros, $page, 20));
    }

    public function ver(string $id): void
    {
        $this->requireAdmin();
        $reserva = Reserva::find((int) $id);
        if (!$reserva) {
            $this->error('Reserva no encontrada', 404);
        }
        $reserva['pagos'] = Pago::paraReserva((int) $id);
        $reserva['historial'] = ReservaEstadoHistorial::paraReserva((int) $id);
        $this->json($reserva);
    }

    public function actualizarEstado(string $id): void
    {
        $admin = $this->requireAdmin();
        $this->requireCsrf();
        $input = $this->input();

        $this->handleValidation(fn () => (new Validator($input))
            ->required('estado', 'Estado')
            ->in('estado', self::ESTADOS_VALIDOS, 'Estado')
            ->validate());

        $reservaId = (int) $id;
        $reserva = Reserva::find($reservaId);
        if (!$reserva) {
            $this->error('Reserva no encontrada', 404);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            Reserva::actualizarEstado($reservaId, $input['estado']);
            ReservaEstadoHistorial::registrar($pdo, $reservaId, $reserva['estado'], $input['estado'], 'admin', $admin['id'], $input['nota'] ?? null);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->json(Reserva::find($reservaId));
    }

    public function registrarPagoSaldo(string $id): void
    {
        $admin = $this->requireAdmin();
        $this->requireCsrf();
        $input = $this->input();

        $reservaId = (int) $id;
        $reserva = Reserva::find($reservaId);
        if (!$reserva) {
            $this->error('Reserva no encontrada', 404);
        }
        if ($reserva['estado'] !== 'adelanto_pagado') {
            $this->error('Solo se puede registrar el saldo de reservas con el adelanto ya confirmado.', 409);
        }

        // Evita registrar el saldo dos veces para la misma reserva
        if (Pago::existeSaldoAprobadoParaReserva($reservaId)) {
            $this->error('El saldo ya fue registrado previamente para esta reserva.', 409);
        }

        $this->handleValidation(fn () => (new Validator($input))
            ->required('metodo', 'Método')
            ->in('metodo', ['yape', 'plin', 'transferencia', 'efectivo'], 'Método')
            ->validate());

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            Pago::crear([
                'reserva_id' => $reservaId,
                'tipo' => 'saldo',
                'metodo' => $input['metodo'],
                'monto' => $input['monto'] ?? $reserva['monto_restante'],
                'estado' => 'aprobado',
            ]);
            $pdo->prepare("UPDATE pagos SET admin_id = :admin_id, revisado_en = NOW() WHERE id = LAST_INSERT_ID()")
                ->execute(['admin_id' => $admin['id']]);

            Reserva::actualizarEstado($reservaId, 'pago_completo');
            // Marca el espacio como ocupado al confirmar el pago completo
            $pdo->prepare('UPDATE espacios SET estado = "ocupado" WHERE id = :id')->execute(['id' => $reserva['espacio_id']]);
            ReservaEstadoHistorial::registrar($pdo, $reservaId, $reserva['estado'], 'pago_completo', 'admin', $admin['id'], 'Saldo del 50% pagado en el establecimiento');
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->json(Reserva::find($reservaId));
    }
}
