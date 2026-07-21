<?php

namespace App\Controllers\Admin;

use App\Controller;
use App\Models\MetodoPago;
use App\Services\FileUploadException;
use App\Services\FileUploadService;

class MetodoPagoAdminController extends Controller
{
    public function listar(): void
    {
        $this->requireAdmin();
        $this->json(MetodoPago::todos());
    }

    public function actualizar(string $tipo): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $input = $this->input();
        $input = array_map(static fn ($value) => is_array($value) ? $value[0] ?? '' : $value, $input);

        if (empty($input['titular']) || empty($input['numero_cuenta'])) {
            $this->error('Titular y número de cuenta son obligatorios', 422);
        }

        MetodoPago::actualizar($tipo, $input);

        if (isset($_FILES['qr']) && $_FILES['qr']['error'] === UPLOAD_ERR_OK) {
            try {
                $path = FileUploadService::guardarQr($_FILES['qr'], $tipo);
                MetodoPago::actualizarQr($tipo, $path);
            } catch (FileUploadException $e) {
                $this->error($e->getMessage(), 422);
            }
        }

        $this->json(['ok' => true]);
    }
}
