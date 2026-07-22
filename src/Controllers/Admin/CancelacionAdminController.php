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

    /** Sirve la imagen del comprobante de forma protegida (requiere sesión admin). */
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
     * accion = 'aprobar': si aún faltan >= 20 min para la hora de ingreso, cancela la reserva y marca "aprobada"
     *          (a devolver el adelanto manualmente); si ya no cumple el plazo, NO cancela y responde 'fuera_plazo'.
     * accion = 'rechazar': marca la solicitud como rechazada sin tocar la reserva (ej. datos de pago no válidos).
     */
    public function decidir(string $id): void
    {
        $admin = $this->requireAdmin();
        $this->requireCsrf();
        $input = $this->input();
        $accion = $input['accion'] ?? null;

        $cancelacion = Cancelacion::find((int) $id);
        if (!$cancelacion) {
            $this->error('Solicitud no encontrada', 404);
            return;
        }
        if ($cancelacion['estado'] !== 'pendiente') {
            $this->error('Esta solicitud ya fue revisada.', 409);
            return;
        }

        $reserva = Reserva::find((int) $cancelacion['reserva_id']);
        if (!$reserva) {
            $this->error('Reserva no encontrada', 404);
            return;
        }

        $pdo = Database::connection();

        if ($accion === 'rechazar') {
            $pdo->beginTransaction();
            try {
                Cancelacion::marcar($pdo, (int) $id, 'rechazada', $admin['id'], $input['nota'] ?? null);
                $pdo->commit();
            } catch (\Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            $this->json(['ok' => true, 'estado' => 'rechazada']);
            return;
        }

        if ($accion !== 'aprobar') {
            $this->error('Acción inválida.', 422);
            return;
        }

        $inicioTs = strtotime($reserva['fecha_hora_inicio']);
        $minutosRestantes = ($inicioTs - time()) / 60;

        if ($minutosRestantes < self::LIMITE_MINUTOS) {
            // Fuera de plazo: NO se cancela la reserva ni se devuelve el dinero.
            $pdo->beginTransaction();
            try {
                Cancelacion::marcar(
                    $pdo,
                    (int) $id,
                    'fuera_plazo',
                    $admin['id'],
                    'Fuera del plazo de 20 minutos antes de la hora de ingreso. No procede devolución.'
                );
                $pdo->commit();
            } catch (\Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            $this->json([
                'ok' => false,
                'estado' => 'fuera_plazo',
                'mensaje' => 'Esta solicitud está fuera del plazo de cancelación (20 minutos antes de la hora de ingreso). No corresponde devolución del dinero.',
            ]);
            return;
        }

        // Dentro del plazo: cancela la reserva y marca la solicitud como aprobada (a devolver el adelanto).
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
                'Cancelación aprobada dentro del plazo. Adelanto de S/ ' . $reserva['monto_adelanto'] . ' pendiente de devolución.'
            );
            Cancelacion::marcar($pdo, (int) $id, 'aprobada', $admin['id'], $input['nota'] ?? null);
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