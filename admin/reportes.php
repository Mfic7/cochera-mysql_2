<?php
/**
 * admin/reportes.php
 *
 * Ajusta los includes de layout (sidebar/header) a como los tengas
 * en dashboard.php — aquí dejo el bloque de contenido asumiendo que
 * ya existe la misma estructura de <head> con admin.css cargado.
 */
require_once __DIR__ . '/../src/Auth/AdminAuth.php'; // ajusta si tu guard vive en otro lado
\App\Auth\AdminAuth::check();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes — Mi Cochera Admin</title>
    <link rel="stylesheet" href="<?= $_ENV['APP_BASE'] ?? '' ?>/assets/css/admin.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
</head>
<body>

<!-- TODO: incluir aquí tu partial de sidebar/header si lo tienes, ej: -->
<!-- <?php /* require __DIR__ . '/partials/sidebar.php'; */ ?> -->

<main class="admin-content">
    <h1>Reportes</h1>

    <!-- ===== Bloque: Ingresos ===== -->
    <section class="card">
        <div class="card-header">
            <h2>Ingresos</h2>
            <div class="tabs" id="ingresos-tabs">
                <button class="tab-btn active" data-agrupacion="semana">Semanal</button>
                <button class="tab-btn" data-agrupacion="mes">Mensual</button>
                <button class="tab-btn" data-agrupacion="anio">Anual</button>
            </div>
        </div>
        <canvas id="chart-ingresos" height="90"></canvas>
    </section>

    <!-- ===== Bloque: Métodos de pago ===== -->
    <section class="card">
        <div class="card-header">
            <h2>Métodos de pago</h2>
            <div class="date-range">
                <label>Desde <input type="date" id="metodos-desde"></label>
                <label>Hasta <input type="date" id="metodos-hasta"></label>
                <button id="metodos-filtrar" class="btn btn-sm">Filtrar</button>
            </div>
        </div>
        <div class="metodos-layout">
            <canvas id="chart-metodos" width="220" height="220"></canvas>
            <div id="metodos-legend"></div>
        </div>
    </section>

    <!-- ===== Bloque: Reservas ===== -->
    <section class="card">
        <div class="card-header">
            <h2>Reservas</h2>
            <div class="filtros">
                <label>Fecha <input type="date" id="reservas-fecha"></label>
                <label>Estado
                    <select id="reservas-estado">
                        <option value="">Todos</option>
                        <option value="pendiente_pago">Pendiente de pago</option>
                        <option value="en_validacion">En validación</option>
                        <option value="adelanto_pagado">Adelanto pagado</option>
                        <option value="pago_completo">Pago completo</option>
                        <option value="cancelada">Cancelada</option>
                        <option value="vencida">Vencida</option>
                    </select>
                </label>
                <button id="reservas-filtrar" class="btn btn-sm">Filtrar</button>
                <button id="reservas-exportar" class="btn btn-sm btn-outline">Exportar CSV</button>
            </div>
        </div>

        <table class="table" id="tabla-reservas">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Espacio</th>
                    <th>Estado</th>
                    <th>Monto</th>
                </tr>
            </thead>
            <tbody id="tabla-reservas-body">
                <tr><td colspan="6">Cargando…</td></tr>
            </tbody>
        </table>

        <div class="pagination" id="reservas-paginacion"></div>
    </section>
</main>

<script src="<?= $_ENV['APP_BASE'] ?? '' ?>/assets/js/admin/api.js"></script>
<script src="<?= $_ENV['APP_BASE'] ?? '' ?>/assets/js/admin/charts.js"></script>
<script src="<?= $_ENV['APP_BASE'] ?? '' ?>/assets/js/admin/reportes.js"></script>
</body>
</html>