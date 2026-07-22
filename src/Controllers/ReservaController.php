<?php

namespace App\Controllers;

use App\Controller;
use App\Database;
use App\Models\Reserva;
use App\Services\EspacioAvailabilityService;
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
     * Búsqueda para el "perfil del cliente": localizar su reserva usando
     * código + celular (sin exponer el token, que solo vive en el navegador
     * donde se creó la reserva).
     */
    public function buscar(): void
    {
        EspacioAvailabilityService::expirarHoldsVencidos();

        $codigo = trim((string) ($_GET['codigo'] ?? ''));
        $celular = preg_replace('/[\s\-]/', '', (string) ($_GET['celular'] ?? ''));

        if ($codigo === '' || $celular === '') {
            $this->error('Ingresa el código de tu reserva y tu número de celular.', 422);
        }

        $reserva = Reserva::findByCodigoAndCelular($codigo, $celular);
        if (!$reserva) {
            $this->error('No encontramos una reserva con esos datos. Verifica el código y el celular.', 404);
        }

        $this->json($this->presentar($reserva));
    }

    /**
     * Devuelve el último pago rechazado de la reserva (si existe), para que
     * el cliente vea el motivo del rechazo en su pantalla.
     */
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