<?php

namespace App\Controllers\Admin;

use App\Controller;
use App\Database;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\ReservaEstadoHistorial;
use App\Services\EspacioAvailabilityService;

class DashboardController extends Controller
{
    public function kpis(): void
    {
        $this->requireAdmin();
        $pdo = Database::connection();
        $hoy = date('Y-m-d');

        $totalReservasHoy = (int) $pdo->query(
            "SELECT COUNT(*) AS n FROM reservas WHERE DATE(created_at) = CURDATE()"
        )->fetch()['n'];

        $disponibilidad = EspacioAvailabilityService::disponibilidad(date('Y-m-d H:i:s'), date('Y-m-d H:i:s'));

        $ingresosHoy = (float) $pdo->query(
            "SELECT COALESCE(SUM(monto),0) AS s FROM pagos WHERE estado='aprobado' AND DATE(revisado_en) = CURDATE()"
        )->fetch()['s'];

        $adelantosHoy = (float) $pdo->query(
            "SELECT COALESCE(SUM(monto),0) AS s FROM pagos WHERE tipo='adelanto' AND estado='aprobado' AND DATE(revisado_en) = CURDATE()"
        )->fetch()['s'];

        $ayer = (int) $pdo->query(
            "SELECT COUNT(*) AS n FROM reservas WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY"
        )->fetch()['n'];
        $variacion = $ayer > 0 ? round((($totalReservasHoy - $ayer) / $ayer) * 100) : 0;

        $reservasSemana = (int) $pdo->query(
            "SELECT COUNT(*) AS n FROM reservas WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)"
        )->fetch()['n'];
        $reservasMes = (int) $pdo->query(
            "SELECT COUNT(*) AS n FROM reservas WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())"
        )->fetch()['n'];
        $reservasAnio = (int) $pdo->query(
            "SELECT COUNT(*) AS n FROM reservas WHERE YEAR(created_at) = YEAR(CURDATE())"
        )->fetch()['n'];

        $this->json([
            'total_reservas' => $totalReservasHoy,
            'reservas_variacion_pct' => $variacion,
            'espacios_ocupados' => $disponibilidad['ocupados'] + $disponibilidad['reservados'],
            'espacios_disponibles' => $disponibilidad['disponibles'],
            'total_espacios' => count($disponibilidad['espacios']),
            'ingresos_hoy' => $ingresosHoy,
            'adelantos_hoy' => $adelantosHoy,
            'reservas_dia' => $totalReservasHoy,
            'reservas_semana' => $reservasSemana,
            'reservas_mes' => $reservasMes,
            'reservas_anio' => $reservasAnio,
        ]);
    }

    public function ocupacion(): void
    {
        $this->requireAdmin();
        $ahora = date('Y-m-d H:i:s');
        $this->json(EspacioAvailabilityService::disponibilidad($ahora, $ahora));
    }

    public function actividad(): void
    {
        $this->requireAdmin();
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 15;
        $this->json(ReservaEstadoHistorial::recientes($limit));
    }

    public function reservasDelDia(): void
    {
        $this->requireAdmin();
        $this->json(Reserva::delDia());
    }

    public function reservasRecientes(): void
    {
        $this->requireAdmin();
        $this->json(Reserva::recientes(10));
    }

    public function reporteIngresos(): void
    {
        $this->requireAdmin();
        $agrupacion = $_GET['agrupacion'] ?? 'dia';
        $pdo = Database::connection();

        [$formato, $intervalo, $n] = match ($agrupacion) {
            'semana' => ['%Y-%m-%d', 'DAY', 7],
            'mes' => ['%Y-%m-%d', 'DAY', 30],
            'anio' => ['%Y-%m', 'MONTH', 12],
            default => ['%H:00', 'HOUR', 24],
        };

        if ($agrupacion === 'dia') {
            $stmt = $pdo->query(
                "SELECT DATE_FORMAT(revisado_en, '$formato') AS etiqueta, SUM(monto) AS total
                 FROM pagos WHERE estado='aprobado' AND DATE(revisado_en) = CURDATE()
                 GROUP BY etiqueta ORDER BY etiqueta"
            );
        } else {
            $stmt = $pdo->query(
                "SELECT DATE_FORMAT(revisado_en, '$formato') AS etiqueta, SUM(monto) AS total
                 FROM pagos WHERE estado='aprobado' AND revisado_en >= NOW() - INTERVAL $n $intervalo
                 GROUP BY etiqueta ORDER BY etiqueta"
            );
        }

        $this->json($stmt->fetchAll());
    }

    public function reporteMetodosPago(): void
    {
        $this->requireAdmin();
        $desde = $_GET['desde'] ?? date('Y-m-01');
        $hasta = $_GET['hasta'] ?? date('Y-m-d');
        $this->json(Pago::ingresosPorMetodo($desde, $hasta));
    }
}
