<?php

namespace App\Controllers;

use App\Controller;
use App\Services\EspacioAvailabilityService;

class EspacioController extends Controller
{
    public function disponibilidad(): void
    {
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        $horaInicio = $_GET['hora_inicio'] ?? '00:00';
        $horas = isset($_GET['horas']) ? (float) $_GET['horas'] : 2.0;

        $inicio = $fecha . ' ' . $horaInicio . ':00';
        $inicioTs = strtotime($inicio);
        if ($inicioTs === false) {
            $this->error('Fecha/hora inválida', 422);
        }
        $fin = date('Y-m-d H:i:s', (int) ($inicioTs + $horas * 3600));

        $this->json(EspacioAvailabilityService::disponibilidad($inicio, $fin));
    }
}
