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

        $this->json(['ok' => true]);
    }

    /**
     * Sube el QR por separado, siempre vía POST. PHP solo puebla $_FILES
     * automáticamente en peticiones POST — en PATCH el archivo nunca llega
     * de forma confiable entre distintos entornos (Apache/mod_php, XAMPP,
     * PHP-FPM, etc.), así que este endpoint evita depender de parsear el
     * archivo a mano dentro de un multipart de PATCH.
     */
    public function subirQr(string $tipo): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        if (!isset($_FILES['qr']) || $_FILES['qr']['error'] !== UPLOAD_ERR_OK) {
            $this->error('Adjunta una imagen de código QR.', 422);
        }

        try {
            $path = FileUploadService::guardarQr($_FILES['qr'], $tipo);
        } catch (FileUploadException $e) {
            $this->error($e->getMessage(), 422);
        }

        MetodoPago::actualizarQr($tipo, $path);

        $this->json(['ok' => true, 'qr_image_path' => $path]);
    }
}

