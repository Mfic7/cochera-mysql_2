<?php

namespace App\Controllers\Admin;

use App\Controller;
use App\Models\Configuracion;

class ConfiguracionController extends Controller
{
    private const CLAVES_PERMITIDAS = [
        'nombre_negocio', 'direccion', 'horario', 'telefono',
        'tarifa_hora', 'hold_minutes', 'adelanto_porcentaje',
    ];

    public function ver(): void
    {
        $this->requireAdmin();
        $this->json(Configuracion::all());
    }

    public function actualizar(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $input = $this->input();

        foreach (self::CLAVES_PERMITIDAS as $clave) {
            if (array_key_exists($clave, $input)) {
                Configuracion::set($clave, (string) $input[$clave]);
            }
        }

        $this->json(Configuracion::all());
    }
}
