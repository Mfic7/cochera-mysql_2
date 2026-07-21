<?php

namespace App\Controllers;

use App\Controller;
use App\Database;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\ReservaEstadoHistorial;
use App\Services\FileUploadException;
use App\Services\FileUploadService;
use App\Support\Dates;

class PagoController extends Controller
{
    private const METODOS_VALIDOS = ['yape', 'plin', 'transferencia'];

    public function comprobante(string $id): void
    {
        $reservaId = (int) $id;
        $reserva = Reserva::find($reservaId);
        $token = $_POST['token'] ?? null;

        if (!$reserva || $token === null || !hash_equals($reserva['token'], $token)) {
            $this->error('Reserva no encontrada', 404);
        }

        if ($reserva['estado'] !== 'pendiente_pago') {
            $this->error('Esta reserva ya no admite un nuevo comprobante en su estado actual.', 409);
        }

        $input = $this->input();
        $metodo = $input['metodo'] ?? '';
        $numeroOperacion = trim((string) ($input['numero_operacion'] ?? ''));
        if (!in_array($metodo, self::METODOS_VALIDOS, true)) {
            $this->error('Método de pago inválido', 422);
        }
        if ($numeroOperacion === '') {
            $this->error('El número de operación es obligatorio', 422);
        }
        if (!isset($_FILES['comprobante'])) {
            $this->error('Debes adjuntar el comprobante de pago', 422);
        }

        try {
            $path = FileUploadService::guardarComprobante($_FILES['comprobante'], $reservaId);
        } catch (FileUploadException $e) {
            $this->error($e->getMessage(), 422);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            Pago::crear([
                'reserva_id' => $reservaId,
                'tipo' => 'adelanto',
                'metodo' => $metodo,
                'monto' => $reserva['monto_adelanto'],
                'numero_operacion' => $numeroOperacion,
                'comprobante_path' => $path,
            ]);

            Reserva::actualizarEstado($reservaId, 'en_validacion');
            ReservaEstadoHistorial::registrar($pdo, $reservaId, 'pendiente_pago', 'en_validacion', 'cliente', null, 'Comprobante de adelanto enviado');

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $actualizada = Reserva::find($reservaId);
        $this->json([
            'id' => (int) $actualizada['id'],
            'codigo' => $actualizada['codigo'],
            'token' => $actualizada['token'],
            'estado' => $actualizada['estado'],
            'hold_expira_en' => Dates::iso($actualizada['hold_expira_en']),
            'monto_adelanto' => (float) $actualizada['monto_adelanto'],
        ]);
    }
}
