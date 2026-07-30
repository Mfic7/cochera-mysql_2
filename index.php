<?php
$config = require_once __DIR__ . '/config/config.php';
$basePath = $config['app_base_path'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi Cochera — Reserva tu espacio</title>
<link rel="stylesheet" href="<?= $basePath ?>/assets/css/client.css">
</head>
<body>
<header class="topbar">
    <div class="brand">
        <img id="brand-logo" src="" alt="Logo" style="display:none;width:40px;height:40px;object-fit:cover;border-radius:10px;margin-right:0.75rem;">
        <span class="brand-badge" id="brand-badge">P</span>
        <div>
            <h1 id="brand-name">Mi Cochera</h1>
            <p id="brand-tagline">Reserva tu espacio seguro</p>
        </div>
    </div>
    <div class="topbar-info" id="topbar-info">
        <div class="info-item"><span class="icon">📍</span><div><strong>Ubicación</strong><br><span id="info-direccion">—</span></div></div>
        <div class="info-item"><span class="icon">🕒</span><div><strong>Horario</strong><br><span id="info-horario">—</span></div></div>
        <div class="info-item"><span class="icon">📞</span><div><strong>Contacto</strong><br><span id="info-telefono">—</span></div></div>
        <button class="btn-ayuda" type="button">? Ayuda</button>
    </div>
</header>

<div class="app-shell">
    <aside class="sidebar">
        <h3>Reservar</h3>
        <button class="sidebar-item active" type="button" data-tab="tab-fecha-espacio">1-2. Fecha y espacio</button>
        <button class="sidebar-item" type="button" data-tab="tab-datos-resumen">3-4. Tus datos y resumen</button>
        <button class="sidebar-item" type="button" data-tab="tab-buscar">¿Ya tienes reserva?</button>
    </aside>

    <main class="content-area">

        <!-- ===== TAB 1: pasos 1 y 2 ===== -->
        <div class="tab-content active" id="tab-fecha-espacio">
            <section class="panel">
                <h2>1. Selecciona tu fecha y hora</h2>
                <div class="field-row">
                    <label>Fecha<input type="date" id="fecha"></label>
                    <label>Hora de ingreso<input type="time" id="hora"></label>
                </div>
                <div class="field-row">
                    <label>Horas estimadas
                        <select id="horas">
                            <option value="1">1 hora</option>
                            <option value="2" selected>2 horas</option>
                            <option value="3">3 horas</option>
                            <option value="4">4 horas</option>
                            <option value="6">6 horas</option>
                            <option value="8">8 horas</option>
                            <option value="12">12 horas</option>
                            <option value="24">24 horas</option>
                        </select>
                    </label>
                </div>

                <h2>2. Elige tu espacio</h2>
                <div class="legend">
                    <span><i class="dot disponible"></i>Disponible</span>
                    <span><i class="dot reservado"></i>Reservado</span>
                    <span><i class="dot ocupado"></i>Ocupado</span>
                    <span><i class="dot seleccionado"></i>Seleccionado</span>
                </div>
                <div class="parking-lot" id="parking-grid">
                    <p class="loading">Cargando espacios…</p>
                </div>

                <div class="stat-cards">
                    <div class="stat-card"><span id="stat-disponibles">—</span><span class="stat-label">Espacios disponibles</span></div>
                    <div class="stat-card"><span id="stat-ocupados">—</span><span class="stat-label">Espacios ocupados</span></div>
                    <div class="stat-card"><span id="stat-tarifa">—</span><span class="stat-label">Tarifa por hora</span></div>
                </div>
            </section>
        </div>

        <!-- ===== TAB 2: pasos 3 y 4 ===== -->
        <div class="tab-content" id="tab-datos-resumen">
            <section class="panel">
                <h2>3. Tus datos</h2>
                <label>Nombre completo<input type="text" id="cliente-nombre" placeholder="Juan Pérez"></label>
                <label>Número de celular<input type="tel" id="cliente-celular" placeholder="987 654 321"></label>

                <h2>4. Resumen de tu reserva</h2>
                <div class="resumen">
                    <div class="resumen-row"><span>Espacio seleccionado</span><strong id="r-espacio">—</strong></div>
                    <div class="resumen-row"><span>Fecha</span><strong id="r-fecha">—</strong></div>
                    <div class="resumen-row"><span>Hora de ingreso</span><strong id="r-hora">—</strong></div>
                    <div class="resumen-row"><span>Tarifa por hora</span><strong id="r-tarifa">—</strong></div>
                    <div class="resumen-row total"><span>Total a pagar</span><strong id="r-total">—</strong></div>
                </div>

                <div class="adelanto-box">
                    <span>Adelanto requerido (50%)</span>
                    <strong id="r-adelanto">S/ 0.00</strong>
                    <p>✅ El 50% restante lo pagas al llegar</p>
                </div>

                <div class="hold-note">
                    <span class="icon">🕒</span>
                    <p>El espacio quedará bloqueado por <span id="hold-minutes">5</span> minutos para que puedas completar tu reserva.</p>
                </div>

                <button class="btn-primary" id="btn-reservar" type="button">Reservar espacio</button>
                <p class="hold-timer" id="hold-timer" hidden></p>
            </section>
        </div>

        <!-- ===== TAB 3: buscar / cancelar reserva ===== -->
        <div class="tab-content" id="tab-buscar">
            <div class="search-grid">
                <section class="panel" id="panel-buscar-reserva">
                    <h2>¿Ya tienes una reserva?</h2>
                    <p class="muted">Ingresa tu celular y el número de espacio que reservaste para verla o cancelarla.</p>

                    <label>Número de celular<input type="tel" id="buscar-celular" placeholder="987 654 321"></label>
                    <label>Número de espacio<input type="text" id="buscar-espacio" placeholder="Ej. 04"></label>

                    <p class="banner-error" id="buscar-banner-error" hidden></p>

                    <button class="btn-primary" id="btn-buscar-reserva" type="button">Buscar reserva</button>
                </section>

                <section class="panel" id="panel-buscar-resultado">
                    <h2>Datos de tu reserva</h2>

                    <div class="resumen" id="buscar-resultado" hidden>
                        <div class="resumen-row"><span>Código</span><strong id="buscar-r-codigo">—</strong></div>
                        <div class="resumen-row"><span>Espacio</span><strong id="buscar-r-espacio">—</strong></div>
                        <div class="resumen-row"><span>Fecha y hora</span><strong id="buscar-r-fecha">—</strong></div>
                        <div class="resumen-row"><span>Estado</span><strong id="buscar-r-estado">—</strong></div>
                        <button class="btn-secondary" id="btn-buscar-cancelar" type="button" hidden style="margin-top:12px;">Solicitar cancelación</button>
                    </div>

                    <p class="muted" id="buscar-resultado-vacio">Aquí aparecerán los datos de tu reserva una vez que la encuentres.</p>
                </section>
            </div>
        </div>

        <!-- ===== Paneles dinámicos (los muestra/oculta el JS existente, no dependen de la pestaña activa) ===== -->
        <section class="panel" id="panel-pago" hidden>
            <h2>5. Realiza el pago del 50%</h2>
            <p class="muted">Elige tu método de pago</p>
            <div class="tabs" id="metodo-tabs"></div>
            <div id="metodo-detalle"></div>

            <label>Número de operación / Código de transacción<input type="text" id="numero-operacion"></label>
            <label>Sube tu comprobante de pago<input type="file" id="comprobante" accept="image/*,application/pdf"></label>

            <button class="btn-primary" id="btn-enviar-comprobante" type="button">🔒 Enviar comprobante y reservar</button>
            <button class="btn-secondary" id="btn-abrir-cancelacion" type="button" hidden>Solicitar cancelación</button>
        </section>

        <section class="panel" id="panel-cancelacion" hidden>
            <h2>6. Solicitar cancelación</h2>
            <p class="muted">Envía tu solicitud cuando necesites cancelar la reserva.</p>
            <p>Reserva: <strong id="cancelacion-codigo">—</strong></p>
            <label>Motivo de la cancelación<textarea id="cancelacion-motivo" rows="3" placeholder="Describe brevemente por qué deseas cancelar"></textarea></label>
            <label>Número de operación / código de transacción<input type="text" id="cancelacion-numero-operacion"></label>
            <label>Comprobante de pago<input type="file" id="cancelacion-comprobante" accept="image/*,application/pdf"></label>
            <p class="banner-error" id="cancelacion-banner-error" hidden></p>
            <button class="btn-primary" id="btn-solicitar-cancelacion" type="button">Enviar solicitud de cancelación</button>
        </section>

    </main>
</div>

<section class="bottom-grid">
    <div class="panel">
        <h3>Estado de tu reserva</h3>
        <div class="vehicle-progress" id="vehicle-progress" hidden>
            <div class="vehicle-progress-label">
                <span id="vehicle-progress-text">🚗 Tu vehículo está en camino…</span>
                <strong id="vehicle-progress-eta">—</strong>
            </div>
            <svg viewBox="0 0 300 46" class="vehicle-track" preserveAspectRatio="none">
                <line x1="12" y1="34" x2="288" y2="34" class="vehicle-road"></line>
                <text x="2" y="16" class="vehicle-flag" title="Origen">📍</text>
                <text x="146" y="13" class="vehicle-direction-arrow">➡</text>
                <text x="272" y="16" class="vehicle-flag" title="Destino">🅿️</text>
                <g id="vehicle-icon-group" class="vehicle-icon-group">
                    <text x="8" y="32" class="vehicle-car-icon">🚗</text>
                </g>
            </svg>
        </div>
        <div class="stepper" id="stepper">
            <div class="step active" data-estado="pendiente_pago"><span class="step-icon">⏳</span><strong>Pendiente de pago</strong><p>Completa el pago del 50%</p></div>
            <div class="step" data-estado="en_validacion"><span class="step-icon">📄</span><strong>En validación</strong><p>Validaremos tu comprobante</p></div>
            <div class="step" data-estado="adelanto_pagado"><span class="step-icon">✔️</span><strong>Confirmada</strong><p>Recibirás tu código</p></div>
            <div class="step" data-estado="pago_completo"><span class="step-icon">✅</span><strong>Pago completo</strong><p>Pagas el 50% restante</p></div>
        </div>
        <p class="banner-error" id="banner-error" hidden></p>
    </div>
    <div class="panel">
        <h3>Información importante</h3>
        <ul class="info-list">
            <li>⚠️ Presenta tu código de reserva al llegar</li>
            <li>✔️ El 50% restante se paga en el establecimiento</li>
            <li>✔️ Cancelaciones hasta 1 hora antes sin penalidad</li>
        </ul>
    </div>
    <div class="panel">
        <h3>Métodos de pago aceptados</h3>
        <div class="badges">
            <div class="badge yape">yape</div>
            <div class="badge plin">plin</div>
            <div class="badge transferencia">🏦<br>Transferencia</div>
        </div>
    </div>
</section>

<script>window.APP_BASE = <?= json_encode($basePath) ?>;</script>
<script src="<?= $basePath ?>/assets/js/shared/parkingGridRenderer.js"></script>
<script src="<?= $basePath ?>/assets/js/client/api.js"></script>
<script src="<?= $basePath ?>/assets/js/client/holdTimer.js"></script>
<script src="<?= $basePath ?>/assets/js/client/vehicleAnimation.js"></script>
<script src="<?= $basePath ?>/assets/js/client/reservationForm.js"></script>
<script src="<?= $basePath ?>/assets/js/client/main.js"></script>

<!-- ===== Navegación del sidebar: SOLO cambia qué pestaña se ve, no toca datos ni JS existente ===== -->
<script>
(function () {
    var sidebarItems = document.querySelectorAll('.sidebar-item');
    var tabContents = document.querySelectorAll('.tab-content');

    function activarTab(tabId) {
        tabContents.forEach(function (tab) { tab.classList.remove('active'); });
        sidebarItems.forEach(function (item) { item.classList.remove('active'); });

        var tab = document.getElementById(tabId);
        if (tab) tab.classList.add('active');

        var item = document.querySelector('.sidebar-item[data-tab="' + tabId + '"]');
        if (item) item.classList.add('active');
    }

    sidebarItems.forEach(function (item) {
        item.addEventListener('click', function () {
            activarTab(item.getAttribute('data-tab'));
        });
    });

    // Si alguien entra con #panel-buscar-reserva en la URL, abre esa pestaña automáticamente
    if (window.location.hash === '#panel-buscar-reserva') {
        activarTab('tab-buscar');
    }
})();
</script>
</body>
</html>