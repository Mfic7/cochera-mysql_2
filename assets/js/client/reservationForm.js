const ReservationForm = (() => {
    const state = {
        config: null,
        metodosPago: [],
        espacios: [],
        selectedEspacio: null,
        reserva: null,
        metodoActivo: null,
        pollHandle: null,
    };

    const el = {};

    function cacheEls() {
        [
            'fecha', 'hora', 'horas', 'parking-grid', 'stat-disponibles', 'stat-ocupados', 'stat-tarifa',
            'cliente-nombre', 'cliente-celular', 'r-espacio', 'r-fecha', 'r-hora', 'r-tarifa', 'r-total',
            'r-adelanto', 'hold-minutes', 'btn-reservar', 'hold-timer', 'panel-pago', 'metodo-tabs',
            'metodo-detalle', 'numero-operacion', 'comprobante', 'btn-enviar-comprobante', 'stepper',
            'banner-error', 'info-direccion', 'info-horario', 'info-telefono',
        ].forEach((id) => { el[id] = document.getElementById(id); });
    }

    function defaultDateTime() {
        const now = new Date();
        el.fecha.value = now.toISOString().slice(0, 10);
        const h = String(now.getHours()).padStart(2, '0');
        const m = String(now.getMinutes()).padStart(2, '0');
        el.hora.value = `${h}:${m}`;
    }

    async function init() {
        cacheEls();
        defaultDateTime();

        state.config = await Api.config();
        el['info-direccion'].textContent = state.config.direccion;
        el['info-horario'].textContent = state.config.horario;
        el['info-telefono'].textContent = state.config.telefono;
        el['hold-minutes'].textContent = state.config.hold_minutes;

        state.metodosPago = await Api.metodosPago();

        await reloadGrid();

        ['fecha', 'hora', 'horas'].forEach((id) => el[id].addEventListener('change', () => {
            if (!state.reserva) reloadGrid();
            actualizarResumen();
        }));
        el['btn-reservar'].addEventListener('click', crearReserva);
        el['btn-enviar-comprobante'].addEventListener('click', enviarComprobante);
    }

    async function reloadGrid() {
        try {
            const data = await Api.disponibilidad(el.fecha.value, el.hora.value, el.horas.value);
            state.espacios = data.espacios;
            el['stat-disponibles'].textContent = data.disponibles;
            el['stat-ocupados'].textContent = data.ocupados;
            el['stat-tarifa'].textContent = 'S/ ' + Number(data.tarifa_hora).toFixed(2);
            renderParkingGrid(el['parking-grid'], state.espacios, {
                selectedId: state.selectedEspacio ? state.selectedEspacio.id : null,
                onSelect: seleccionarEspacio,
            });
        } catch (e) {
            el['parking-grid'].innerHTML = '<p class="loading">No se pudo cargar el mapa de espacios.</p>';
        }
    }

    function seleccionarEspacio(espacio) {
        state.selectedEspacio = espacio;
        renderParkingGrid(el['parking-grid'], state.espacios, { selectedId: espacio.id, onSelect: seleccionarEspacio });
        actualizarResumen();
    }

    function actualizarResumen() {
        const tarifa = Number(state.config.tarifa_hora);
        const horas = Number(el.horas.value);
        const total = tarifa * horas;
        const adelanto = total * (state.config.adelanto_porcentaje / 100);

        el['r-espacio'].textContent = state.selectedEspacio ? state.selectedEspacio.codigo : '—';
        el['r-fecha'].textContent = el.fecha.value || '—';
        el['r-hora'].textContent = el.hora.value || '—';
        el['r-tarifa'].textContent = 'S/ ' + tarifa.toFixed(2);
        el['r-total'].textContent = 'S/ ' + total.toFixed(2);
        el['r-adelanto'].textContent = 'S/ ' + adelanto.toFixed(2);
    }

    function setFormDisabled(disabled) {
        ['fecha', 'hora', 'horas', 'cliente-nombre', 'cliente-celular'].forEach((id) => { el[id].disabled = disabled; });
        el['btn-reservar'].disabled = disabled;
    }

    async function crearReserva() {
        hideError();
        if (!state.selectedEspacio) return showError('Selecciona un espacio disponible.');
        const nombre = el['cliente-nombre'].value.trim();
        const celular = el['cliente-celular'].value.trim();
        if (!nombre) return showError('Ingresa tu nombre completo.');
        if (!celular) return showError('Ingresa tu número de celular.');

        el['btn-reservar'].disabled = true;
        el['btn-reservar'].textContent = 'Reservando…';

        try {
            const payload = {
                espacio_id: state.selectedEspacio.id,
                fecha_hora_inicio: `${el.fecha.value} ${el.hora.value}:00`,
                horas_estimadas: Number(el.horas.value),
                cliente_nombre: nombre,
                cliente_celular: celular,
            };
            const reserva = await Api.crearReserva(payload);
            state.reserva = reserva;
            setFormDisabled(true);
            el['btn-reservar'].hidden = true;
            HoldTimer.start(reserva.hold_expira_en, el['hold-timer'], onHoldExpired);
            mostrarPanelPago();
            actualizarStepper(reserva.estado);
            startPolling();
        } catch (e) {
            if (e.status === 409) {
                showError('Este espacio ya no está disponible, elige otro.');
                state.selectedEspacio = null;
                await reloadGrid();
            } else {
                showError('No se pudo crear la reserva. Intenta nuevamente.');
            }
            el['btn-reservar'].disabled = false;
            el['btn-reservar'].textContent = 'Reservar espacio';
        }
    }

    function onHoldExpired() {
        showError('El tiempo para completar el pago expiró. El espacio fue liberado.');
        actualizarStepper('vencida');
        stopPolling();
    }

    function mostrarPanelPago() {
        el['panel-pago'].hidden = false;
        el['metodo-tabs'].innerHTML = '';
        state.metodosPago.forEach((m, i) => {
            const tab = document.createElement('div');
            tab.className = 'tab' + (i === 0 ? ' active' : '');
            tab.textContent = etiquetaMetodo(m.tipo);
            tab.addEventListener('click', () => seleccionarMetodo(m.tipo));
            el['metodo-tabs'].appendChild(tab);
        });
        seleccionarMetodo(state.metodosPago[0].tipo);
        el['panel-pago'].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function etiquetaMetodo(tipo) {
        return { yape: 'Yape', plin: 'Plin', transferencia: 'Transferencia' }[tipo] || tipo;
    }

    function seleccionarMetodo(tipo) {
        state.metodoActivo = tipo;
        [...el['metodo-tabs'].children].forEach((tab) => {
            tab.classList.toggle('active', tab.textContent === etiquetaMetodo(tipo));
        });
        const metodo = state.metodosPago.find((m) => m.tipo === tipo);
        const monto = state.reserva ? Number(state.reserva.monto_adelanto).toFixed(2) : '0.00';

        let qrHtml = '';
        if (metodo.qr_image_path) {
            qrHtml = `<img class="qr-img" src="${window.APP_BASE}/storage/${metodo.qr_image_path}?v=${Date.now()}" alt="QR de ${etiquetaMetodo(tipo)}">`;
        } else if (tipo !== 'transferencia') {
            qrHtml = '<div class="qr-placeholder" title="El administrador aún no subió el código QR"></div>';
        }

        el['metodo-detalle'].innerHTML = `
            <div class="metodo-card">
                <p><strong>Paga con ${etiquetaMetodo(tipo)}</strong></p>
                <p class="muted">${tipo === 'transferencia' ? 'Realiza la transferencia a la cuenta indicada' : 'Escanea el código QR con tu app de ' + etiquetaMetodo(tipo)}</p>
                <div class="qr-row">
                    ${qrHtml}
                    <div class="cuenta">
                        <div>Número<strong>${metodo.numero_cuenta}</strong></div>
                        <div>Titular<strong>${metodo.titular}</strong></div>
                        <div>Monto a pagar<strong>S/ ${monto}</strong></div>
                    </div>
                </div>
                <div class="aviso-comprobante">Luego de realizar el pago, registra el comprobante para confirmar tu reserva.</div>
            </div>`;
    }

    async function enviarComprobante() {
        hideError();
        if (!state.reserva) return;
        const numeroOperacion = el['numero-operacion'].value.trim();
        const file = el['comprobante'].files[0];
        if (!numeroOperacion) return showError('Ingresa el número de operación.');
        if (!file) return showError('Sube tu comprobante de pago.');

        el['btn-enviar-comprobante'].disabled = true;
        el['btn-enviar-comprobante'].textContent = 'Enviando…';

        const formData = new FormData();
        formData.append('token', state.reserva.token);
        formData.append('metodo', state.metodoActivo);
        formData.append('numero_operacion', numeroOperacion);
        formData.append('comprobante', file);

        try {
            const reserva = await Api.subirComprobante(state.reserva.id, formData);
            state.reserva = reserva;
            actualizarStepper(reserva.estado);
            el['panel-pago'].querySelectorAll('input, .tab').forEach((n) => n.setAttribute('disabled', 'disabled'));
            el['btn-enviar-comprobante'].textContent = 'Comprobante enviado ✓';
            HoldTimer.stop();
            el['hold-timer'].hidden = true;
        } catch (e) {
            showError('No se pudo enviar el comprobante. Intenta nuevamente.');
            el['btn-enviar-comprobante'].disabled = false;
            el['btn-enviar-comprobante'].textContent = '🔒 Enviar comprobante y reservar';
        }
    }

    function actualizarStepper(estado) {
        const orden = ['pendiente_pago', 'en_validacion', 'adelanto_pagado', 'pago_completo'];
        const idx = orden.indexOf(estado);
        el.stepper.querySelectorAll('.step').forEach((step) => {
            const stepIdx = orden.indexOf(step.dataset.estado);
            step.classList.toggle('active', stepIdx === idx);
            step.classList.toggle('done', idx > -1 && stepIdx < idx);
        });
        if (estado === 'cancelada' || estado === 'vencida') {
            showError(estado === 'cancelada' ? 'Esta reserva fue cancelada.' : 'La reserva venció por falta de pago.');
        }
    }

    function startPolling() {
        stopPolling();
        state.pollHandle = setInterval(async () => {
            try {
                const reserva = await Api.obtenerReserva(state.reserva.id, state.reserva.token);
                state.reserva = { ...state.reserva, ...reserva };
                actualizarStepper(reserva.estado);
                if (['pago_completo', 'cancelada', 'vencida'].includes(reserva.estado)) {
                    stopPolling();
                }
            } catch (e) { /* silencioso: se reintenta en el próximo tick */ }
        }, 5000);
    }

    function stopPolling() {
        if (state.pollHandle) {
            clearInterval(state.pollHandle);
            state.pollHandle = null;
        }
    }

    function showError(msg) {
        el['banner-error'].textContent = msg;
        el['banner-error'].hidden = false;
    }

    function hideError() {
        el['banner-error'].hidden = true;
    }

    return { init };
})();
