<?php

namespace App\Controllers;

use App\Controller;
use App\Models\Configuracion;
use App\Models\MetodoPago;
use App\Support\Validator;

class ConfigController extends Controller
{
    private const CAMPOS_PERMITIDOS = [
        'nombre_negocio', 'direccion', 'horario', 'telefono',
        'tarifa_hora', 'hold_minutes', 'adelanto_porcentaje',
    ];

    public function index(): void
    {
        $config = Configuracion::all();
        $this->json([
            'nombre_negocio' => $config['nombre_negocio'] ?? 'Mi Cochera',
            'logo_path' => $config['logo_path'] ?? null,
            'direccion' => $config['direccion'] ?? '',
            'horario' => $config['horario'] ?? '',
            'telefono' => $config['telefono'] ?? '',
            'tarifa_hora' => (float) ($config['tarifa_hora'] ?? 20),
            'hold_minutes' => (int) ($config['hold_minutes'] ?? 5),
            'adelanto_porcentaje' => (int) ($config['adelanto_porcentaje'] ?? 50),
        ]);
    }

    /**
     * Guarda los cambios del formulario "Configuración" del panel admin.
     * Debe registrarse en routes.php como PUT/PATCH/POST hacia /admin/config
     */
    public function actualizar(): void
    {
        $input = $this->input();

        $data = $this->handleValidation(function () use ($input) {
            return (new Validator($input))
                ->required('nombre_negocio', 'Nombre del negocio')
                ->required('direccion', 'Dirección')
                ->required('horario', 'Horario')
                ->required('telefono', 'Teléfono')
                ->required('tarifa_hora', 'Tarifa por hora')
                ->numeric('tarifa_hora', 'Tarifa por hora')
                ->required('hold_minutes', 'Minutos de bloqueo')
                ->numeric('hold_minutes', 'Minutos de bloqueo')
                ->required('adelanto_porcentaje', 'Porcentaje de adelanto')
                ->numeric('adelanto_porcentaje', 'Porcentaje de adelanto')
                ->validate();
        });

        foreach (self::CAMPOS_PERMITIDOS as $campo) {
            if (array_key_exists($campo, $data)) {
                Configuracion::set($campo, (string) $data[$campo]);
            }
        }

        $this->json(['ok' => true, 'config' => Configuracion::all()]);
    }

    public function metodosPago(): void
    {
        $this->json(MetodoPago::activos());
    }
}