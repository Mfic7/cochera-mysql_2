<?php

namespace App\Controllers;

use App\Controller;
use App\Models\Cancelacion;
use App\Models\Reserva;
use App\Models\ReservaEstadoHistorial;
use App\Services\FileUploadException;
use App\Services\FileUploadService;

class CancelacionController extends Controller
{
    public function solicitar(string $id): void
    {
        $reservaId = (int) $id;
        $reserva = Reserva::find($reservaId);
        $token = $_POST['token'] ?? null;

        if (!$reserva || $token === null || !hash_equals($reserva['token'], $token)) {
            $this->error('Reserva no encontrada', 404);
        }
        if (in_array($reserva['estado'], ['cancelada', 'vencida'], true)) {
            $this->error('Esta reserva ya fue cancelada o vencida.', 409);
        }
        if (Cancelacion::existsForReserva($reservaId)) {
            $this->error('Ya existe una solicitud de cancelación para esta reserva.', 409);
        }

        $motivo = trim((string) ($_POST['motivo'] ?? ''));
        $numeroOperacion = trim((string) ($_POST['numero_operacion'] ?? ''));
        $comprobantePath = null;

        if ($motivo === '') {
            $this->error('Debes indicar el motivo de la cancelación.', 422);
        }
        if (!isset($_FILES['comprobante'])) {
            $this->error('Debes adjuntar una imagen o PDF de tu pago.', 422);
        }

        try {
            $comprobantePath = FileUploadService::guardarComprobante($_FILES['comprobante'], $reservaId);
        } catch (FileUploadException $e) {
            $this->error($e->getMessage(), 422);
        }

        $pdo = \App\Database::connection();
        $pdo->beginTransaction();
        try {
            Cancelacion::crear($reservaId, $motivo, $numeroOperacion ?: null, $comprobantePath);
            Reserva::actualizarEstado($reservaId, 'cancelada');
            ReservaEstadoHistorial::registrar($pdo, $reservaId, $reserva['estado'], 'cancelada', 'cliente', null, 'Solicitud de cancelación enviada: ' . $motivo);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->json(['ok' => true, 'message' => 'Solicitud de cancelación enviada.']);
    }
}
