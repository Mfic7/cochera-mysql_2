<?php
require __DIR__ . '/../src/Autoload.php';
use App\Auth\AdminAuth;

$config = require __DIR__ . '/../config/config.php';
$basePath = $config['app_base_path'];

if (!AdminAuth::check()) {
    header('Location: ' . $basePath . '/admin/login.php');
    exit;
}
$admin = AdminAuth::user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi Cochera — Panel de administración</title>
<link rel="stylesheet" href="<?= $basePath ?>/assets/css/admin.css">
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-badge">P</div>
            <div><h1>Mi Cochera</h1><p>Panel de Administración</p></div>
        </div>
        <nav id="nav">
            <div class="nav-item active" data-view="dashboard">🏠 Dashboard</div>
            <div class="nav-item" data-view="reservas">📅 Reservas</div>
            <div class="nav-item" data-view="calendario">🗓️ Calendario<span class="badge-soon">Pronto</span></div>
            <div class="nav-item" data-view="clientes">👥 Clientes<span class="badge-soon">Pronto</span></div>
            <div class="nav-item" data-view="pagos">💳 Pagos</div>
            <div class="nav-item" data-view="reportes">📊 Reportes<span class="badge-soon">Pronto</span></div>
            <div class="nav-item" data-view="vehiculos">🚗 Vehículos<span class="badge-soon">Pronto</span></div>
            <div class="nav-item" data-view="espacios">🅿️ Espacios</div>
            <div class="nav-item" data-view="metodos-pago">🏦 Métodos de pago</div>
            <div class="nav-item" data-view="usuarios">🧑‍💼 Usuarios<span class="badge-soon">Pronto</span></div>
            <div class="nav-item" data-view="configuracion">⚙️ Configuración</div>
            <div class="nav-item" data-view="suscripcion">👑 Suscripción</div>
        </nav>
        <div class="sidebar-footer">
            <div class="avatar">🧑</div>
            <div><span id="admin-nombre"><?= htmlspecialchars($admin['nombre']) ?></span><small><?= htmlspecialchars($admin['email']) ?></small></div>
            <a class="logout-link" href="<?= $basePath ?>/admin/logout.php" title="Cerrar sesión">⏻</a>
        </div>
    </aside>

    <main class="content">
        <!-- Dashboard -->
        <section class="view active" id="view-dashboard">
            <div class="content-header">
                <div><h2>Dashboard</h2><p>Resumen general de tu cochera</p></div>
                <button class="btn-sm" id="btn-refrescar-dashboard">↻ Actualizar</button>
            </div>
            <div class="kpi-grid" id="kpi-grid"></div>
            <div class="grid-2">
                <div class="panel">
                    <h3>Ocupación de espacios</h3>
                    <div class="legend">
                        <span><i class="dot disponible"></i>Disponible</span>
                        <span><i class="dot ocupado"></i>Ocupado</span>
                        <span><i class="dot reservado"></i>Reservado</span>
                    </div>
                    <div class="parking-lot" id="admin-parking-grid"></div>
                </div>
                <div class="panel">
                    <h3>Reservas del día <a data-goto="reservas">Ver todas</a></h3>
                    <div id="reservas-del-dia"></div>
                </div>
            </div>
            <div class="grid-2">
                <div class="panel">
                    <h3>Ingresos</h3>
                    <canvas id="chart-ingresos" height="140"></canvas>
                </div>
                <div class="panel">
                    <h3>Métodos de pago <a data-goto="reportes">Ver detalle</a></h3>
                    <canvas id="chart-metodos" height="140"></canvas>
                    <div id="metodos-leyenda"></div>
                </div>
            </div>
            <div class="grid-2">
                <div class="panel">
                    <h3>Reservas recientes</h3>
                    <div class="table-wrap"><table id="tabla-reservas-recientes">
                        <thead><tr><th>Código</th><th>Cliente</th><th>Espacio</th><th>Ingreso</th><th>Total</th><th>Estado</th><th>Pago</th></tr></thead>
                        <tbody></tbody>
                    </table></div>
                </div>
                <div class="panel">
                    <h3>Actividad reciente</h3>
                    <div id="actividad-reciente"></div>
                </div>
            </div>
            <div class="bottom-stats" id="bottom-stats"></div>
        </section>

        <!-- Reservas -->
        <section class="view" id="view-reservas">
            <div class="content-header"><div><h2>Reservas</h2><p>Gestiona todas las reservas del sistema</p></div></div>
            <div class="toolbar">
                <input type="date" id="filtro-fecha-reservas">
                <select id="filtro-estado-reservas">
                    <option value="">Todos los estados</option>
                    <option value="pendiente_pago">Pendiente de pago</option>
                    <option value="en_validacion">En validación</option>
                    <option value="adelanto_pagado">Adelanto pagado</option>
                    <option value="pago_completo">Pago completo</option>
                    <option value="cancelada">Cancelada</option>
                    <option value="vencida">Vencida</option>
                </select>
                <button class="btn-sm" id="btn-filtrar-reservas">Filtrar</button>
            </div>
            <div class="panel">
                <div class="table-wrap"><table id="tabla-reservas">
                    <thead><tr><th>Código</th><th>Cliente</th><th>Celular</th><th>Espacio</th><th>Ingreso</th><th>Total</th><th>Adelanto</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody></tbody>
                </table></div>
            </div>
        </section>

        <!-- Pagos -->
        <section class="view" id="view-pagos">
            <div class="content-header"><div><h2>Pagos</h2><p>Revisa y valida los comprobantes de los clientes</p></div></div>
            <div class="toolbar">
                <select id="filtro-estado-pagos">
                    <option value="en_validacion">En validación</option>
                    <option value="aprobado">Aprobados</option>
                    <option value="rechazado">Rechazados</option>
                    <option value="">Todos</option>
                </select>
                <button class="btn-sm" id="btn-filtrar-pagos">Filtrar</button>
            </div>
            <div class="panel">
                <div class="table-wrap"><table id="tabla-pagos">
                    <thead><tr><th>Reserva</th><th>Cliente</th><th>Espacio</th><th>Método</th><th>Monto</th><th>N° operación</th><th>Estado</th><th>Comprobante</th><th>Acciones</th></tr></thead>
                    <tbody></tbody>
                </table></div>
            </div>
        </section>

        <!-- Espacios -->
        <section class="view" id="view-espacios">
            <div class="content-header"><div><h2>Espacios</h2><p>Administra el estado de cada espacio de la cochera</p></div></div>
            <div class="panel">
                <div class="table-wrap"><table id="tabla-espacios">
                    <thead><tr><th>Código</th><th>Zona</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody></tbody>
                </table></div>
            </div>
        </section>

        <!-- Métodos de pago -->
        <section class="view" id="view-metodos-pago">
            <div class="content-header"><div><h2>Métodos de pago</h2><p>Configura las cuentas que verán tus clientes al pagar</p></div></div>
            <div class="grid-3" id="metodos-pago-cards"></div>
        </section>

        <!-- Configuración -->
        <section class="view" id="view-configuracion">
            <div class="content-header"><div><h2>Configuración</h2><p>Datos generales del negocio</p></div></div>
            <div class="panel" style="max-width:480px">
                <form id="form-configuracion">
                    <div class="form-field"><label>Nombre del negocio</label><input name="nombre_negocio"></div>
                    <div class="form-field"><label>Dirección</label><input name="direccion"></div>
                    <div class="form-field"><label>Horario</label><input name="horario"></div>
                    <div class="form-field"><label>Teléfono</label><input name="telefono"></div>
                    <div class="form-field"><label>Tarifa por hora (S/)</label><input name="tarifa_hora" type="number" step="0.01"></div>
                    <div class="form-field"><label>Minutos de bloqueo (hold)</label><input name="hold_minutes" type="number"></div>
                    <div class="form-field"><label>% de adelanto requerido</label><input name="adelanto_porcentaje" type="number"></div>
                    <button class="btn-primary" type="submit">Guardar cambios</button>
                </form>
            </div>
        </section>

        <!-- Stubs -->
        <?php foreach (['calendario' => 'Calendario', 'clientes' => 'Clientes', 'reportes' => 'Reportes', 'vehiculos' => 'Vehículos', 'usuarios' => 'Usuarios'] as $slug => $label): ?>
        <section class="view" id="view-<?= $slug ?>">
            <div class="content-header"><div><h2><?= $label ?></h2></div></div>
            <div class="stub-panel">🚧 Módulo de <?= $label ?> — próximamente en una siguiente iteración.</div>
        </section>
        <?php endforeach; ?>

        <!-- Suscripción -->
        <section class="view" id="view-suscripcion">
            <div class="content-header"><div><h2>Suscripción</h2><p>Estado de tu licencia</p></div></div>
            <div class="panel subscription-card" style="max-width:520px">
                <div class="icon">👑</div>
                <div><strong>Plan Activo</strong><small id="suscripcion-detalle">Vence el —</small></div>
                <button class="btn-secondary" type="button">Administrar suscripción</button>
            </div>
        </section>
    </main>
</div>

<div id="modal-root"></div>

<script>window.APP_BASE = <?= json_encode($basePath) ?>;</script>
<script src="<?= $basePath ?>/assets/vendor/chart.umd.min.js"></script>
<script src="<?= $basePath ?>/assets/js/shared/parkingGridRenderer.js"></script>
<script src="<?= $basePath ?>/assets/js/admin/api.js"></script>
<script src="<?= $basePath ?>/assets/js/admin/charts.js"></script>
<script src="<?= $basePath ?>/assets/js/admin/parkingGridAdmin.js"></script>
<script src="<?= $basePath ?>/assets/js/admin/dashboard.js"></script>
</body>
</html>
