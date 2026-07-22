<?php

namespace App\Controllers;

use App\Controller;
use App\Database;
use App\Models\Cancelacion;
use App\Models\Reserva;
use App\Services\EspacioAvailabilityService;
use App\Services\FileUploadException;
use App\Services\FileUploadService;
use App\Services\ReservaConflictException;
use App\Services\ReservaService;
use App\Support\Dates;
use App\Support\Validator;

class ReservaController extends Controller
{
    public function crear(): void
    {
        $input = $this->input();

        $data = $this->handleValidation(function () use ($input) {
            return (new Validator($input))
                ->required('espacio_id', 'Espacio')
                ->required('fecha_hora_inicio', 'Fecha y hora')
                ->required('horas_estimadas', 'Horas estimadas')
                ->numeric('horas_estimadas', 'Horas estimadas')
                ->required('cliente_nombre', 'Nombre')
                ->nombreCompleto('cliente_nombre', 'Nombre')
                ->required('cliente_celular', 'Celular')
                ->celularPeru('cliente_celular', 'Celular')
                ->validate();
        });

        $data['cliente_nombre'] = trim((string) $data['cliente_nombre']);
        $data['cliente_celular'] = preg_replace('/[\s\-]/', '', (string) $data['cliente_celular']);
        $data['ip_origen'] = $_SERVER['REMOTE_ADDR'] ?? null;

        try {
            $reserva = ReservaService::crear($data);
            $this->json($reserva, 201);
        } catch (ReservaConflictException $e) {
            $this->error($e->getMessage(), 409);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function ver(string $id): void
    {
        EspacioAvailabilityService::expirarHoldsVencidos();

        $reserva = Reserva::find((int) $id);
        $token = $_GET['token'] ?? null;

        if (!$reserva || $token === null || !hash_equals($reserva['token'], $token)) {
            $this->error('Reserva no encontrada', 404);
        }

        $this->json($this->presentar($reserva));
    }

    /**
     * Búsqueda de reserva por el CLIENTE, usando su celular y el número de espacio
     * que reservó (más fácil de recordar que el código de reserva).
     */
    public function buscar(): void
    {
        EspacioAvailabilityService::expirarHoldsVencidos();

        $celular = preg_replace('/[\s\-]/', '', (string) ($_GET['celular'] ?? ''));
        $numeroEspacio = trim((string) ($_GET['espacio'] ?? ''));

        if ($celular === '' || $numeroEspacio === '') {
            $this->error('Ingresa tu número de celular y el número de espacio.', 422);
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT r.*, e.codigo AS espacio_codigo
             FROM reservas r
             INNER JOIN espacios e ON e.id = r.espacio_id
             WHERE r.cliente_celular = :celular AND e.numero = :numero
             ORDER BY r.created_at DESC
             LIMIT 1"
        );
        $stmt->execute(['celular' => $celular, 'numero' => $numeroEspacio]);
        $reserva = $stmt->fetch();

        if (!$reserva) {
            $this->error('No encontramos una reserva con ese celular y número de espacio.', 404);
            return;
        }

        $this->json($this->presentar($reserva));
    }

    /**
     * El CLIENTE solicita cancelar su reserva. No la cancela de inmediato:
     * queda 'pendiente' hasta que el admin la revise y decida (dentro/fuera de plazo).
     */
    public function cancelacion(string $id): void
    {
        $reservaId = (int) $id;
        $reserva = Reserva::find($reservaId);
        $token = $_POST['token'] ?? null;

        if (!$reserva || $token === null || !hash_equals($reserva['token'], $token)) {
            $this->error('Reserva no encontrada', 404);
            return;
        }

        if (in_array($reserva['estado'], ['cancelada', 'vencida'], true)) {
            $this->error('Esta reserva ya no se puede cancelar.', 409);
            return;
        }

        if (Cancelacion::pendienteParaReserva($reservaId)) {
            $this->error('Ya existe una solicitud de cancelación pendiente para esta reserva.', 409);
            return;
        }

        $motivo = trim((string) ($_POST['motivo'] ?? ''));
        $numeroOperacion = trim((string) ($_POST['numero_operacion'] ?? ''));
        if ($motivo === '') {
            $this->error('Ingresa el motivo de la cancelación.', 422);
            return;
        }
        if ($numeroOperacion === '') {
            $this->error('Ingresa el número de operación del pago.', 422);
            return;
        }
        if (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] === UPLOAD_ERR_NO_FILE) {
            $this->error('Adjunta el comprobante de pago.', 422);
            return;
        }

        try {
            $comprobantePath = FileUploadService::guardarEvidenciaCancelacion($_FILES['comprobante'], $reservaId);
        } catch (FileUploadException $e) {
            $this->error($e->getMessage(), 422);
            return;
        }

        $pdo = Database::connection();
        Cancelacion::crear($pdo, [
            'reserva_id' => $reservaId,
            'motivo' => $motivo,
            'numero_operacion' => $numeroOperacion,
            'comprobante_path' => $comprobantePath,
        ]);

        $this->json(['ok' => true, 'mensaje' => 'Solicitud de cancelación enviada. El equipo la revisará.']);
    }

    private function ultimoRechazo(int $reservaId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT motivo_rechazo, revisado_en FROM pagos
             WHERE reserva_id = :id AND estado = 'rechazado'
             ORDER BY revisado_en DESC LIMIT 1"
        );
        $stmt->execute(['id' => $reservaId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function presentar(array $reserva): array
    {
        $rechazo = $this->ultimoRechazo((int) $reserva['id']);

        return [
            'id' => (int) $reserva['id'],
            'codigo' => $reserva['codigo'],
            'token' => $reserva['token'],
            'espacio_id' => (int) $reserva['espacio_id'],
            'espacio_codigo' => $reserva['espacio_codigo'],
            'cliente_nombre' => $reserva['cliente_nombre'],
            'cliente_celular' => $reserva['cliente_celular'],
            'fecha_hora_inicio' => Dates::iso($reserva['fecha_hora_inicio']),
            'fecha_hora_fin' => Dates::iso($reserva['fecha_hora_fin']),
            'horas_estimadas' => (float) $reserva['horas_estimadas'],
            'tarifa_hora' => (float) $reserva['tarifa_hora'],
            'monto_total' => (float) $reserva['monto_total'],
            'monto_adelanto' => (float) $reserva['monto_adelanto'],
            'monto_restante' => (float) $reserva['monto_restante'],
            'estado' => $reserva['estado'],
            'hold_expira_en' => Dates::iso($reserva['hold_expira_en']),
            'ultimo_rechazo' => $rechazo ? [
                'motivo' => $rechazo['motivo_rechazo'],
                'fecha' => Dates::iso($rechazo['revisado_en']),
            ] : null,
        ];
    }
}