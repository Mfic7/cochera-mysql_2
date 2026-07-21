<?php

namespace App\Controllers;

use App\Controller;
use App\Models\Configuracion;
use App\Models\MetodoPago;

class ConfigController extends Controller
{
    public function index(): void
    {
        $config = Configuracion::all();
        $this->json([
            'nombre_negocio' => $config['nombre_negocio'] ?? 'Mi Cochera',
            'direccion' => $config['direccion'] ?? '',
            'horario' => $config['horario'] ?? '',
            'telefono' => $config['telefono'] ?? '',
            'tarifa_hora' => (float) ($config['tarifa_hora'] ?? 20),
            'hold_minutes' => (int) ($config['hold_minutes'] ?? 5),
            'adelanto_porcentaje' => (int) ($config['adelanto_porcentaje'] ?? 50),
        ]);
    }

    public function metodosPago(): void
    {
        $this->json(MetodoPago::activos());
    }
}
