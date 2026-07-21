<?php

namespace App\Services;

class FileUploadException extends \RuntimeException
{
}

class FileUploadService
{
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    private const MAX_BYTES = 5 * 1024 * 1024;

    /** Valida y mueve un archivo subido a storage/comprobantes/{reservaId}/, devuelve la ruta relativa. */
    public static function guardarComprobante(array $file, int $reservaId): string
    {
        return self::guardar($file, 'comprobantes/' . $reservaId);
    }

    /** Valida y mueve un archivo subido a storage/qr/, nombrado por tipo de método de pago. */
    public static function guardarQr(array $file, string $tipo): string
    {
        return self::guardar($file, 'qr', $tipo);
    }

    private static function guardar(array $file, string $subdir, ?string $nombreFijo = null): string
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new FileUploadException('No se pudo subir el archivo.');
        }
        if ($file['size'] > self::MAX_BYTES) {
            throw new FileUploadException('El archivo supera el tamaño máximo de 5MB.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            throw new FileUploadException('Formato de archivo no permitido. Usa JPG, PNG, WEBP o PDF.');
        }

        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        };

        $dir = __DIR__ . '/../../storage/' . $subdir;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new FileUploadException('No se pudo preparar el almacenamiento.');
        }

        $filename = ($nombreFijo ?? bin2hex(random_bytes(8))) . '.' . $ext;
        $destino = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destino)) {
            throw new FileUploadException('No se pudo guardar el archivo.');
        }

        return $subdir . '/' . $filename;
    }
}
