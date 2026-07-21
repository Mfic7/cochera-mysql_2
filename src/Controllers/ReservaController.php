<?php

namespace App\Controllers;

use App\Controller;
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

        $this->handleValidation(function () use ($input) {
            return (new Validator($input))
                ->required('espacio_id', 'Espacio')
                ->required('fecha_hora_inicio', 'Fecha y hora')
                ->required('horas_estimadas', 'Horas estimadas')
                ->numeric('horas_estimadas', 'Horas estimadas')
                ->required('cliente_nombre', 'Nombre')
                ->required('cliente_celular', 'Celular')
                ->validate();
        });

        $input['ip_origen'] = $_SERVER['REMOTE_ADDR'] ?? null;

        try {
            $reserva = ReservaService::crear($input);
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

    private function presentar(array $reserva): array
    {
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
        ];
    }
}
