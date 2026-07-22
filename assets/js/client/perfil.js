const Perfil = (() => {
    const state = { reserva: null, pollHandle: null };
    const el = {};

    function cacheEls() {
        [
            'buscar-codigo', 'buscar-celular', 'btn-buscar', 'buscar-banner-error',
            'panel-buscar', 'panel-reserva', 'panel-cancelacion',
            'v-codigo', 'v-espacio', 'v-nombre', 'v-fecha', 'v-horas', 'v-total', 'v-adelanto', 'v-estado',
            'aviso-plazo', 'btn-abrir-cancelacion',
            'cancelacion-tipo', 'cancelacion-motivo', 'cancelacion-numero-operacion', 'cancelacion-comprobante',
            'btn-solicitar-cancelacion', 'cancelacion-banner-error',
        ].forEach((id) => { el[id] = document.getElementById(id); });
    }

    function init() {
        cacheEls();
        el['btn-buscar'].addEventListener('click', buscarReserva);
        el['btn-abrir-cancelacion'].addEventListener('click', mostrarPanelCancelacion);
        el['btn-solicitar-cancelacion'].addEventListener('click', solicitarCancelacion);

        el['buscar-celular'].addEventListener('input', () => formatearCelular(el['buscar-celular']));
        el['buscar-celular'].addEventListener('keypress', (e) => {
            if (!/[0-9]/.test(e.key)) e.preventDefault();
        });
    }

    function formatearCelular(input) {
        const limpio = input.value.replace(/\D/g, '').slice(0, 9);
        input.value = limpio.replace(/(\d{3})(\d{3})(\d{0,3})/, (m, a, b, c) =>
            c ? `${a} ${b} ${c}` : b ? `${a} ${b}` : a
        );
    }

    async function buscarReserva() {
        hideError('buscar-banner-error');
        const codigo = el['buscar-codigo'].value.trim();
        const celular = el['buscar-celular'].value.replace(/[\s-]/g, '');

        if (!codigo) return showError('buscar-banner-error', 'Ingresa el código de tu reserva.');
        if (!/^9\d{8}$/.test(celular)) return showError('buscar-banner-error', 'Ingresa un celular válido de 9 dígitos.');

        el['btn-buscar'].disabled = true;
        el['btn-buscar'].textContent = 'Buscando…';

        try {
            const reserva = await Api.buscarReserva(codigo, celular);
            state.reserva = reserva;
            el['panel-buscar'].hidden = true;
            renderReserva();
            startPolling();
        } catch (e) {
            showError('buscar-banner-error', (e.data && e.data.error) || 'No se pudo encontrar tu reserva.');
        } finally {
            el['btn-buscar'].disabled = false;
            el['btn-buscar'].textContent = 'Buscar mi reserva';
        }
    }

    function renderReserva() {
        const r = state.reserva;
        el['v-codigo'].textContent = r.codigo;
        el['v-espacio'].textContent = r.espacio_codigo;
        el['v-nombre'].textContent = r.cliente_nombre;
        el['v-fecha'].textContent = new Date(r.fecha_hora_inicio).toLocaleString('es-PE');
        el['v-horas'].textContent = r.horas_estimadas + ' h';
        el['v-total'].textContent = 'S/ ' + Number(r.monto_total).toFixed(2);
        el['v-adelanto'].textContent = 'S/ ' + Number(r.monto_adelanto).toFixed(2);
        el['v-estado'].textContent = etiquetaEstado(r.estado);

        el['panel-reserva'].hidden = false;
        actualizarAvisoYBoton();
    }

    function etiquetaEstado(estado) {
        return {
            pendiente_pago: 'Pendiente de pago',
            en_validacion: 'Pago en validación',
            adelanto_pagado: 'Adelanto confirmado',
            pago_completo: 'Pago completo',
            cancelada: 'Cancelada',
            vencida: 'Vencida',
        }[estado] || estado;
    }

    function actualizarAvisoYBoton() {
        const r = state.reserva;
        if (['cancelada', 'vencida'].includes(r.estado)) {
            el['aviso-plazo'].hidden = true;
            el['btn-abrir-cancelacion'].hidden = true;
            el['panel-cancelacion'].hidden = true;
            return;
        }

        const inicio = new Date(r.fecha_hora_inicio).getTime();
        const minutosRestantes = (inicio - Date.now()) / 60000;
        const puedeCancelar = minutosRestantes >= 20;

        el['aviso-plazo'].hidden = false;
        el['aviso-plazo'].textContent = puedeCancelar
            ? 'Tienes hasta 20 minutos antes de tu hora de reserva para cancelarla. Pasado ese tiempo no hay devolución de dinero.'
            : 'El plazo para cancelar sin costo venció. Ya no es posible solicitar la devolución del adelanto.';

        // Mostrar siempre el botón para que el usuario pueda detallar la solicitud.
        // Si no está en plazo, se permitirá ver el formulario pero el botón de envío quedará deshabilitado.
        el['btn-abrir-cancelacion'].hidden = false;
        el['btn-solicitar-cancelacion'].disabled = !puedeCancelar;
        if (!puedeCancelar) {
            showError('cancelacion-banner-error', 'El plazo para solicitar la devolución venció. No podrás enviar la solicitud, pero puedes detallar la información.');
        } else {
            hideError('cancelacion-banner-error');
        }
        // Ocultamos el panel si ya no puede cancelar (evita abrirlo automáticamente)
        if (!puedeCancelar) el['panel-cancelacion'].hidden = true;
    }

    function mostrarPanelCancelacion() {
        el['cancelacion-motivo'].value = '';
        el['cancelacion-numero-operacion'].value = '';
        el['cancelacion-comprobante'].value = '';
        // Mostrar el panel incluso si el plazo venció; el envío quedará bloqueado con aviso
        const inicio = new Date(state.reserva.fecha_hora_inicio).getTime();
        const minutosRestantes = (inicio - Date.now()) / 60000;
        const puedeCancelar = minutosRestantes >= 20;
        if (!puedeCancelar) {
            showError('cancelacion-banner-error', 'El plazo para solicitar la devolución venció. No podrás enviar la solicitud, pero puedes dejar los detalles para el admin.');
            el['btn-solicitar-cancelacion'].disabled = true;
        } else {
            hideError('cancelacion-banner-error');
            el['btn-solicitar-cancelacion'].disabled = false;
        }
        el['panel-cancelacion'].hidden = false;
        el['panel-cancelacion'].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    async function solicitarCancelacion() {
        hideError('cancelacion-banner-error');
        const tipo = el['cancelacion-tipo'].value.trim();
        const motivo = el['cancelacion-motivo'].value.trim();
        const numeroOperacion = el['cancelacion-numero-operacion'].value.trim();
        const comprobante = el['cancelacion-comprobante'].files[0];

        if (!tipo) return showError('cancelacion-banner-error', 'Selecciona el tipo de cancelación.');
        if (!motivo) return showError('cancelacion-banner-error', 'Ingresa la descripción de la cancelación.');
        if (!numeroOperacion) return showError('cancelacion-banner-error', 'Ingresa el número de operación.');
        if (!comprobante) return showError('cancelacion-banner-error', 'Adjunta el comprobante de pago.');

        el['btn-solicitar-cancelacion'].disabled = true;
        el['btn-solicitar-cancelacion'].textContent = 'Enviando…';

        try {
            const formData = new FormData();
            formData.append('token', state.reserva.token);
            formData.append('tipo', tipo);
            formData.append('motivo', motivo);
            formData.append('numero_operacion', numeroOperacion);
            formData.append('comprobante', comprobante);

            await Api.solicitarCancelacion(state.reserva.id, formData);
            el['panel-cancelacion'].innerHTML =
                '<div class="panel-message"><strong>Solicitud enviada.</strong><p>Hemos recibido tu solicitud de cancelación y el equipo la revisará.</p></div>';
            stopPolling();
        } catch (e) {
            showError('cancelacion-banner-error', (e.data && e.data.error) || 'No se pudo enviar la solicitud.');
            el['btn-solicitar-cancelacion'].disabled = false;
            el['btn-solicitar-cancelacion'].textContent = 'Enviar solicitud de cancelación';
        }
    }

    function startPolling() {
        stopPolling();
        state.pollHandle = setInterval(async () => {
            try {
                const reserva = await Api.obtenerReserva(state.reserva.id, state.reserva.token);
                state.reserva = { ...state.reserva, ...reserva };
                renderReserva();
                if (['cancelada', 'vencida'].includes(reserva.estado)) stopPolling();
            } catch (e) { /* reintenta en el próximo tick */ }
        }, 15000);
    }

    function stopPolling() {
        if (state.pollHandle) {
            clearInterval(state.pollHandle);
            state.pollHandle = null;
        }
    }

    function showError(id, msg) {
        el[id].textContent = msg;
        el[id].hidden = false;
    }

    function hideError(id) {
        el[id].hidden = true;
    }

    return { init };
})();
