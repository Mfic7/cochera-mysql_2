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
    /** Minutos mÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­nimos de antelaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n exigidos para poder cancelar sin perder el adelanto. */
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
                'El plazo para cancelar venciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³. Solo se permite cancelar hasta '
                . self::MINUTOS_LIMITE_CANCELACION
                . ' minutos antes de tu hora de reserva; pasado ese tiempo no hay devoluciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n de dinero.',
                409
            );
        }

        if (Cancelacion::existsForReserva($reservaId)) {
            $this->error('Ya existe una solicitud de cancelaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n para esta reserva.', 409);
        }

        $motivo = trim((string) ($_POST['motivo'] ?? ''));
        $numeroOperacion = trim((string) ($_POST['numero_operacion'] ?? ''));
        $comprobantePath = null;

        if ($motivo === '') {
            $this->error('Debes indicar el motivo de la cancelaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n.', 422);
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
            // CORRECCIÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œN: Cancelacion::crear() espera (\PDO $pdo, array $datos),
            // no una lista de parÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡metros sueltos. La firma anterior no coincidÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­a
            // con el modelo real y provocaba un TypeError -> Error 500.
            Cancelacion::crear($pdo, [
                'reserva_id' => $reservaId,
                'motivo' => $motivo,
                'numero_operacion' => $numeroOperacion ?: null,
                'comprobante_path' => $comprobantePath,
            ]);
            Reserva::actualizarEstado($reservaId, 'cancelada');
            ReservaEstadoHistorial::registrar($pdo, $reservaId, $reserva['estado'], 'cancelada', 'cliente', null, 'Solicitud de cancelaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n enviada: ' . $motivo);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->json(['ok' => true, 'message' => 'Solicitud de cancelaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n enviada.']);
    }
}
