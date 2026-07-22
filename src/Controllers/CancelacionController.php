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
    /** Minutos mínimos de antelación exigidos para poder cancelar sin perder el adelanto. */
    private const MINUTOS_LIMITE_CANCELACION = 20;

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

        // Regla de negocio: solo se puede cancelar hasta 20 minutos antes del ingreso.
        // Se valida en el servidor (no solo en el frontend) para que no pueda evadirse.
        $inicioTs = strtotime($reserva['fecha_hora_inicio']);
        $minutosRestantes = ($inicioTs - time()) / 60;
        if ($minutosRestantes < self::MINUTOS_LIMITE_CANCELACION) {
            $this->error(
                'El plazo para cancelar venció. Solo se permite cancelar hasta '
                . self::MINUTOS_LIMITE_CANCELACION
                . ' minutos antes de tu hora de reserva; pasado ese tiempo no hay devolución de dinero.',
                409
            );
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
            $this->error('Debes adjuntar una imagen o PDF de tu comprobante.', 422);
        }

        try {
            $comprobantePath = FileUploadService::guardarComprobanteCancelacion($_FILES['comprobante'], $reservaId);
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