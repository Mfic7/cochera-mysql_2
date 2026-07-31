<?php

namespace App\Controllers\Admin;

use App\Controller;
use App\Database;
use App\Models\Cancelacion;
use App\Models\Reserva;
use App\Models\ReservaEstadoHistorial;

class CancelacionAdminController extends Controller
{
    /** El cliente puede solicitar hasta 20 min antes de la hora de ingreso; el admin re-valida esto al aprobar. */
    private const LIMITE_MINUTOS = 20;

    public function listar(): void
    {
        $this->requireAdmin();
        $estado = $_GET['estado'] ?? null;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $this->json(Cancelacion::listar($estado ?: null, $page, 20));
    }

    /** Sirve la imagen del comprobante de forma protegida (requiere sesiÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n admin). */
    public function comprobante(string $id): void
    {
        $this->requireAdmin();
        $cancelacion = Cancelacion::find((int) $id);
        if (!$cancelacion || !$cancelacion['comprobante_path']) {
            $this->error('Comprobante no encontrado', 404);
            return;
        }

        $ruta = __DIR__ . '/../../../storage/' . $cancelacion['comprobante_path'];
        if (!is_file($ruta)) {
            $this->error('Archivo no encontrado', 404);
            return;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $ruta);
        finfo_close($finfo);

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }

    /**
     * El admin decide sobre la solicitud.
     * accion = 'aprobar': si aÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âºn faltan >= 20 min para la hora de ingreso, cancela la reserva y marca "aprobada"
     *          (a devolver el adelanto manualmente); si ya no cumple el plazo, NO cancela y responde 'fuera_plazo'.
     * accion = 'rechazar': marca la solicitud como rechazada sin tocar la reserva (ej. datos de pago no vÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lidos).
     */
    public function decidir(string $id): void
    {
        $admin = $this->requireAdmin();
        $this->requireCsrf();
        $input = $this->input();
        $accion = $input['accion'] ?? null;

        $datos = $this->cargarSolicitudValida($id);
        if ($datos === null) {
            return;
        }
        [, $reserva] = $datos;

        if ($accion === 'rechazar') {
            $this->rechazarSolicitud((int) $id, $admin, $input['nota'] ?? null);
            return;
        }

        if ($accion !== 'aprobar') {
            $this->error('AcciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n invÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lida.', 422);
            return;
        }

        $this->aprobarSolicitud((int) $id, $reserva, $admin, $input['nota'] ?? null);
    }

    /** Valida que la solicitud exista, estÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â© pendiente, y que su reserva exista. Devuelve [cancelacion, reserva] o null. */
    private function cargarSolicitudValida(string $id): ?array
    {
        $cancelacion = Cancelacion::find((int) $id);
        if (!$cancelacion || $cancelacion['estado'] !== 'pendiente') {
            $this->error(
                $cancelacion ? 'Esta solicitud ya fue revisada.' : 'Solicitud no encontrada',
                $cancelacion ? 409 : 404
            );
            return null;
        }

        $reserva = Reserva::find((int) $cancelacion['reserva_id']);
        if (!$reserva) {
            $this->error('Reserva no encontrada', 404);
            return null;
        }

        return [$cancelacion, $reserva];
    }

    private function rechazarSolicitud(int $id, array $admin, ?string $nota): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            Cancelacion::marcar($pdo, $id, 'rechazada', $admin['id'], $nota);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        $this->json(['ok' => true, 'estado' => 'rechazada']);
    }

    private function aprobarSolicitud(int $id, array $reserva, array $admin, ?string $nota): void
    {
        $inicioTs = strtotime($reserva['fecha_hora_inicio']);
        $minutosRestantes = ($inicioTs - time()) / 60;

        if ($minutosRestantes < self::LIMITE_MINUTOS) {
            $this->marcarFueraDePlazo($id, $admin);
            return;
        }

        $this->aprobarDentroDePlazo($id, $reserva, $admin, $nota);
    }

    /** Fuera de plazo: NO se cancela la reserva ni se devuelve el dinero. */
    private function marcarFueraDePlazo(int $id, array $admin): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            Cancelacion::marcar(
                $pdo,
                $id,
                'fuera_plazo',
                $admin['id'],
                'Fuera del plazo de 20 minutos antes de la hora de ingreso. No procede devoluciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n.'
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        $this->json([
            'ok' => false,
            'estado' => 'fuera_plazo',
            'mensaje' => 'Esta solicitud estÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡ fuera del plazo de cancelaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n (20 minutos antes de la hora de ingreso). No corresponde devoluciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n del dinero.',
        ]);
    }

    /** Dentro del plazo: cancela la reserva y marca la solicitud como aprobada (a devolver el adelanto). */
    private function aprobarDentroDePlazo(int $id, array $reserva, array $admin, ?string $nota): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            Reserva::actualizarEstado((int) $reserva['id'], 'cancelada');
            ReservaEstadoHistorial::registrar(
                $pdo,
                (int) $reserva['id'],
                $reserva['estado'],
                'cancelada',
                'admin',
                $admin['id'],
                'CancelaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n aprobada dentro del plazo. Adelanto de S/ ' . $reserva['monto_adelanto'] . ' pendiente de devoluciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n.'
            );
            Cancelacion::marcar($pdo, $id, 'aprobada', $admin['id'], $nota);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->json([
            'ok' => true,
            'estado' => 'aprobada',
            'mensaje' => 'Reserva cancelada. Recuerda devolver el adelanto de S/ ' . $reserva['monto_adelanto'] . ' al cliente.',
        ]);
    }
}
