<?php
// Página pública del cliente: buscar su reserva y gestionarla (ver estado, cancelar).
// No requiere sesión: se identifica con código de reserva + celular.
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi Reserva — Mi Cochera</title>
<link rel="stylesheet" href="assets/css/client.css">
</head>
<body>
<header class="topbar">
    <div class="brand">
        <span id="brand-badge">🚗</span>
        <img id="brand-logo" alt="logo" style="display:none;height:32px;">
        <div>
            <div id="brand-name" class="brand-name">Mi Cochera</div>
            <div class="brand-sub">Consulta y gestiona tu reserva</div>
        </div>
    </div>
</header>

<main class="container">
    <section id="panel-buscar" class="card">
        <h2>1. Ingresa los datos de tu reserva</h2>
        <p class="muted">Usa el código que recibiste al reservar y el celular con el que hiciste la reserva.</p>

        <div class="form-row">
            <label for="buscar-codigo">Código de reserva</label>
            <input id="buscar-codigo" type="text" placeholder="RES-20260722-XXXXXXXX">
        </div>
        <div class="form-row">
            <label for="buscar-celular">Número de celular</label>
            <input id="buscar-celular" type="text" placeholder="987 654 321" maxlength="11">
        </div>

        <div id="buscar-banner-error" class="banner-error" hidden></div>

        <button id="btn-buscar" class="btn-primary">Buscar mi reserva</button>
    </section>

    <section id="panel-reserva" class="card" hidden>
        <h2>Tu reserva</h2>
        <div class="resumen">
            <div>Código<strong id="v-codigo">—</strong></div>
            <div>Espacio<strong id="v-espacio">—</strong></div>
            <div>Cliente<strong id="v-nombre">—</strong></div>
            <div>Fecha de ingreso<strong id="v-fecha">—</strong></div>
            <div>Horas estimadas<strong id="v-horas">—</strong></div>
            <div>Total<strong id="v-total">—</strong></div>
            <div>Adelanto (50%)<strong id="v-adelanto">—</strong></div>
            <div>Estado<strong id="v-estado">—</strong></div>
        </div>

        <div id="aviso-plazo" class="aviso-comprobante" hidden></div>

        <button id="btn-abrir-cancelacion" class="btn-secondary" hidden>Cancelar mi reserva</button>
    </section>

    <section id="panel-cancelacion" class="card" hidden>
        <h2>Solicitar cancelación</h2>
        <p class="muted">
            Puedes cancelar hasta <strong>20 minutos antes</strong> de tu hora de reserva.
            Pasado ese tiempo, <strong>no hay devolución de dinero</strong>.
        </p>

        <div class="form-row">
            <label for="cancelacion-tipo">Tipo de cancelación</label>
            <select id="cancelacion-tipo">
                <option value="">Selecciona un tipo</option>
                <option value="emergencia">Emergencia</option>
                <option value="cambio_de_planes">Cambio de planes</option>
                <option value="problema_personal">Problema personal</option>
                <option value="otro">Otro</option>
            </select>
        </div>
        <div class="form-row">
            <label for="cancelacion-motivo">Descripción de la cancelación</label>
            <textarea id="cancelacion-motivo" rows="3" placeholder="Cuéntanos más sobre por qué deseas cancelar"></textarea>
        </div>
        <div class="form-row">
            <label for="cancelacion-numero-operacion">N° de operación / código de pago</label>
            <input id="cancelacion-numero-operacion" type="text" placeholder="Ej. 000123456">
        </div>
        <div class="form-row">
            <label for="cancelacion-comprobante">Comprobante de pago</label>
            <input id="cancelacion-comprobante" type="file" accept="image/*,.pdf">
        </div>

        <div id="cancelacion-banner-error" class="banner-error" hidden></div>

        <button id="btn-solicitar-cancelacion" class="btn-primary">Enviar solicitud de cancelación</button>
    </section>
</main>

<script>window.APP_BASE = '<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); ?>';</script>
<script src="assets/js/client/api.js"></script>
<script src="assets/js/client/perfil.js"></script>
<script>Perfil.init();</script>
</body>
</html>
