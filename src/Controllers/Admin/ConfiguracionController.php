<?php

namespace App\Controllers\Admin;

use App\Controller;
use App\Models\Configuracion;
use App\Services\FileUploadException;
use App\Services\FileUploadService;

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
        $input = array_map(static fn ($value) => is_array($value) ? $value[0] ?? '' : $value, $input);

        foreach (self::CLAVES_PERMITIDAS as $clave) {
            if (array_key_exists($clave, $input)) {
                Configuracion::set($clave, (string) $input[$clave]);
            }
        }

        // El logo llega como multipart/form-data (input type="file"), no dentro de $input.
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $logoPath = FileUploadService::guardarLogo($_FILES['logo']);
                Configuracion::set('logo_path', $logoPath);
            } catch (FileUploadException $e) {
                $this->error($e->getMessage(), 422);
                return;
            }
        }

        $this->json(Configuracion::all());
    }
}