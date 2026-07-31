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
    private const FORMATO_FECHA_HORA = 'Y-m-d H:i:s';

    public function kpis(): void
    {
        $this->requireAdmin();
        $pdo = Database::connection();

        $totalReservasHoy = (int) $pdo->query(
            "SELECT COUNT(*) AS n FROM reservas WHERE DATE(created_at) = CURDATE()"
        )->fetch()['n'];

        $ahora = date(self::FORMATO_FECHA_HORA);
        $disponibilidad = EspacioAvailabilityService::disponibilidad($ahora, $ahora);

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
        $ahora = date(self::FORMATO_FECHA_HORA);
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

    /**
     * Resumen completo de un período (día/semana/mes/año) con los indicadores
     * pedidos en el requerimiento: total de reservas, ocupación, ingresos,
     * adelantos, pagos pendientes/completados, canceladas y métodos de pago.
     */
    public function reporteResumen(): void
    {
        $this->requireAdmin();
        $periodo = $_GET['periodo'] ?? 'dia';
        [$desde, $hasta] = self::rangoPeriodo($periodo);
        $pdo = Database::connection();

        $totalReservas = self::contarReservas($pdo, $desde, $hasta);
        $canceladas = self::contarReservas($pdo, $desde, $hasta, 'cancelada');

        $ahora = date(self::FORMATO_FECHA_HORA);
        $disponibilidad = EspacioAvailabilityService::disponibilidad($ahora, $ahora);

        $stmtIngresos = $pdo->prepare(
            "SELECT COALESCE(SUM(monto),0) AS s FROM pagos
             WHERE estado='aprobado' AND DATE(revisado_en) BETWEEN :desde AND :hasta"
        );
        $stmtIngresos->execute(['desde' => $desde, 'hasta' => $hasta]);
        $ingresos = (float) $stmtIngresos->fetch()['s'];

        $stmtAdelantos = $pdo->prepare(
            "SELECT COALESCE(SUM(monto),0) AS s FROM pagos
             WHERE tipo='adelanto' AND estado='aprobado' AND DATE(revisado_en) BETWEEN :desde AND :hasta"
        );
        $stmtAdelantos->execute(['desde' => $desde, 'hasta' => $hasta]);
        $adelantos = (float) $stmtAdelantos->fetch()['s'];

        $stmtPendientes = $pdo->prepare(
            "SELECT COUNT(*) AS n FROM pagos
             WHERE estado='en_validacion' AND DATE(created_at) BETWEEN :desde AND :hasta"
        );
        $stmtPendientes->execute(['desde' => $desde, 'hasta' => $hasta]);
        $pagosPendientes = (int) $stmtPendientes->fetch()['n'];

        $stmtCompletados = $pdo->prepare(
            "SELECT COUNT(*) AS n FROM pagos
             WHERE estado='aprobado' AND DATE(created_at) BETWEEN :desde AND :hasta"
        );
        $stmtCompletados->execute(['desde' => $desde, 'hasta' => $hasta]);
        $pagosCompletados = (int) $stmtCompletados->fetch()['n'];

        $metodos = Pago::ingresosPorMetodo($desde, $hasta);

        $this->json([
            'periodo' => $periodo,
            'desde' => $desde,
            'hasta' => $hasta,
            'total_reservas' => $totalReservas,
            'reservas_canceladas' => $canceladas,
            'espacios_ocupados' => $disponibilidad['ocupados'] + $disponibilidad['reservados'],
            'espacios_disponibles' => $disponibilidad['disponibles'],
            'total_espacios' => count($disponibilidad['espacios']),
            'ingresos' => $ingresos,
            'adelantos' => $adelantos,
            'pagos_pendientes' => $pagosPendientes,
            'pagos_completados' => $pagosCompletados,
            'metodos_pago' => $metodos,
        ]);
    }

    /**
     * Calcula el rango de fechas (desde/hasta, formato Y-m-d) para cada período.
     * semana = lunes a domingo de la semana actual; mes = mes calendario actual;
     * año = año calendario actual.
     */
    private static function rangoPeriodo(string $periodo): array
    {
        $hoy = new \DateTime();
        switch ($periodo) {
            case 'semana':
                $desde = (clone $hoy)->modify('monday this week');
                $hasta = (clone $hoy)->modify('sunday this week');
                break;
            case 'mes':
                $desde = new \DateTime($hoy->format('Y-m-01'));
                $hasta = new \DateTime($hoy->format('Y-m-t'));
                break;
            case 'anio':
                $desde = new \DateTime($hoy->format('Y-01-01'));
                $hasta = new \DateTime($hoy->format('Y-12-31'));
                break;
            default: // 'dia'
                $desde = clone $hoy;
                $hasta = clone $hoy;
        }
        return [$desde->format('Y-m-d'), $hasta->format('Y-m-d')];
    }

    private static function contarReservas(\PDO $pdo, string $desde, string $hasta, ?string $estado = null): int
    {
        $sql = "SELECT COUNT(*) AS n FROM reservas WHERE DATE(created_at) BETWEEN :desde AND :hasta";
        $params = ['desde' => $desde, 'hasta' => $hasta];
        if ($estado) {
            $sql .= " AND estado = :estado";
            $params['estado'] = $estado;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetch()['n'];
    }

    /**
     * Reservas confirmadas cuya hora de llegada está cerca (para avisar al admin
     * "cliente por llegar") o ya pasó hace poco (para avisar "cliente llegó").
     * El frontend decide con qué texto mostrar cada una según el campo 'tipo'.
     */
    public function alertasLlegada(): void
    {
        $this->requireAdmin();
        $minutosAnticipacion = 10;
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            "SELECT r.id, r.codigo, r.cliente_nombre, e.codigo AS espacio_codigo, r.fecha_hora_inicio, r.estado
             FROM reservas r
             JOIN espacios e ON e.id = r.espacio_id
             WHERE r.estado IN ('adelanto_pagado', 'pago_completo')
               AND r.fecha_hora_inicio BETWEEN (NOW() - INTERVAL 5 MINUTE) AND (NOW() + INTERVAL :minutos MINUTE)
             ORDER BY r.fecha_hora_inicio ASC"
        );
        $stmt->bindValue(':minutos', $minutosAnticipacion, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $ahora = new \DateTime();
        $resultado = array_map(function ($r) use ($ahora) {
            $llegada = new \DateTime($r['fecha_hora_inicio']);
            $r['tipo'] = $llegada <= $ahora ? 'llego' : 'por_llegar';
            return $r;
        }, $rows);

        $this->json($resultado);
    }
}

