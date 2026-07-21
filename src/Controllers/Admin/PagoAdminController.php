<?php

namespace App\Controllers\Admin;

use App\Controller;
use App\Database;
use App\Models\Configuracion;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\ReservaEstadoHistorial;
use App\Support\Validator;

class PagoAdminController extends Controller
{
    public function listar(): void
    {
        $this->requireAdmin();
        $estado = $_GET['estado'] ?? null;
        $this->json(Pago::listar($estado ?: null));
    }

    public function comprobante(string $id): void
    {
        $this->requireAdmin();
        $pago = Pago::find((int) $id);
        if (!$pago || !$pago['comprobante_path']) {
            $this->error('Comprobante no encontrado', 404);
        }

        $path = realpath(__DIR__ . '/../../../storage/' . $pago['comprobante_path']);
        $storageRoot = realpath(__DIR__ . '/../../../storage');
        if (!$path || !str_starts_with($path, $storageRoot)) {
            $this->error('Comprobante no encontrado', 404);
        }

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            default => 'image/jpeg',
        };
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function revisar(string $id): void
    {
        $admin = $this->requireAdmin();
        $this->requireCsrf();
        $input = $this->input();

        $this->handleValidation(fn () => (new Validator($input))
            ->required('accion', 'Acción')
            ->in('accion', ['aprobar', 'rechazar'], 'Acción')
            ->validate());

        $pago = Pago::find((int) $id);
        if (!$pago) {
            $this->error('Pago no encontrado', 404);
        }
        if ($pago['estado'] !== 'en_validacion') {
            $this->error('Este pago ya fue revisado.', 409);
        }

        $reserva = Reserva::find((int) $pago['reserva_id']);
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            if ($input['accion'] === 'aprobar') {
                // Evita aprobar un segundo 'saldo' si ya existe uno aprobado
                if ($pago['tipo'] === 'saldo' && \App\Models\Pago::existeSaldoAprobadoParaReserva((int) $pago['reserva_id'])) {
                    $this->error('Ya existe un pago de tipo saldo aprobado para esta reserva.', 409);
                }

                Pago::actualizarRevision((int) $id, 'aprobado', $admin['id']);
                $nuevoEstado = $pago['tipo'] === 'adelanto' ? 'adelanto_pagado' : 'pago_completo';
                Reserva::actualizarEstado((int) $reserva['id'], $nuevoEstado);
                // Si es pago completo, marcar el espacio como ocupado
                if ($nuevoEstado === 'pago_completo') {
                    $pdo->prepare('UPDATE espacios SET estado = "ocupado" WHERE id = :id')
                        ->execute(['id' => $reserva['espacio_id']]);
                }
                ReservaEstadoHistorial::registrar($pdo, (int) $reserva['id'], $reserva['estado'], $nuevoEstado, 'admin', $admin['id'], 'Pago aprobado por admin');
            } else {
                $motivo = trim($input['motivo'] ?? 'Comprobante rechazado');
                Pago::actualizarRevision((int) $id, 'rechazado', $admin['id'], $motivo);

                $holdMinutes = (int) Configuracion::get('hold_minutes', 5);
                $holdExpiraEn = date('Y-m-d H:i:s', time() + $holdMinutes * 60);
                $pdo->prepare('UPDATE reservas SET estado = :estado, hold_expira_en = :hold WHERE id = :id')
                    ->execute(['estado' => 'pendiente_pago', 'hold' => $holdExpiraEn, 'id' => $reserva['id']]);
                ReservaEstadoHistorial::registrar($pdo, (int) $reserva['id'], $reserva['estado'], 'pendiente_pago', 'admin', $admin['id'], 'Comprobante rechazado: ' . $motivo);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->json(Pago::find((int) $id));
    }
}
