<?php

namespace App\Services;

class FileUploadException extends \RuntimeException
{
}

class FileUploadService
{
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    private const ALLOWED_MIME_LOGO = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_BYTES = 5 * 1024 * 1024;

    /** Valida y mueve un archivo subido a storage/comprobantes/{reservaId}/, devuelve la ruta relativa. */
    public static function guardarComprobante(array $file, int $reservaId): string
    {
        return self::guardar($file, 'comprobantes/' . $reservaId);
    }

    public static function guardarComprobanteCancelacion(array $file, int $reservaId): string
    {
        return self::guardar($file, 'cancelaciones/' . $reservaId);
    }

    /** Valida y mueve un archivo subido a storage/qr/, nombrado por tipo de método de pago. */
    public static function guardarQr(array $file, string $tipo): string
    {
        return self::guardar($file, 'qr', $tipo);
    }

    /**
     * Valida y mueve el logo del negocio a storage/logo/.
     * No acepta PDF (a diferencia de comprobantes) porque el logo se muestra como <img>.
     * Usa nombre fijo 'negocio' para sobrescribir el logo anterior en cada actualización.
     */
    public static function guardarLogo(array $file): string
    {
        return self::guardar($file, 'logo', 'negocio', self::ALLOWED_MIME_LOGO);
    }

    private static function guardar(array $file, string $subdir, ?string $nombreFijo = null, ?array $mimesPermitidos = null): string
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

        $permitidos = $mimesPermitidos ?? self::ALLOWED_MIME;
        if (!in_array($mime, $permitidos, true)) {
            $formatos = in_array('application/pdf', $permitidos, true) ? 'JPG, PNG, WEBP o PDF' : 'JPG, PNG o WEBP';
            throw new FileUploadException("Formato de archivo no permitido. Usa $formatos.");
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

        // Si hay nombre fijo, borra versiones anteriores con otra extensión (ej. logo cambiado de .png a .jpg)
        if ($nombreFijo !== null) {
            foreach (glob($dir . '/' . $nombreFijo . '.*') ?: [] as $anterior) {
                @unlink($anterior);
            }
        }

        $filename = ($nombreFijo ?? bin2hex(random_bytes(8))) . '.' . $ext;
        $destino = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destino)) {
            throw new FileUploadException('No se pudo guardar el archivo.');
        }

        return $subdir . '/' . $filename;
    }
}