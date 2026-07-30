const ReservationForm = (() => {
    const state = {
        config: null,
        metodosPago: [],
        espacios: [],
        selectedEspacio: null,
        reserva: null,
        metodoActivo: null,
        pollHandle: null,
        lastEstado: null,
        vehicleArrivedNotifiedFor: null,
    };

    const ESTADO_LABEL_CLIENTE = {
        pendiente_pago: 'Pendiente de pago',
        en_validacion: 'En validación',
        adelanto_pagado: 'Confirmada (50% pagado)',
        pago_completo: 'Pago completo',
        cancelada: 'Cancelada',
        vencida: 'Vencida',
    };

    const el = {};

    function cacheEls() {
        [
            'fecha', 'hora', 'horas', 'parking-grid', 'stat-disponibles', 'stat-ocupados', 'stat-tarifa',
            'cliente-nombre', 'cliente-celular', 'r-espacio', 'r-fecha', 'r-hora', 'r-tarifa', 'r-total',
            'r-adelanto', 'hold-minutes', 'btn-reservar', 'hold-timer', 'panel-pago', 'metodo-tabs',
            'metodo-detalle', 'numero-operacion', 'comprobante', 'btn-enviar-comprobante', 'btn-abrir-cancelacion', 'panel-cancelacion',
            'cancelacion-codigo', 'cancelacion-motivo', 'cancelacion-numero-operacion', 'cancelacion-comprobante',
            'btn-solicitar-cancelacion', 'cancelacion-banner-error', 'stepper', 'banner-error', 'info-direccion', 'info-horario', 'info-telefono',
            // Buscar / ver reserva existente
            'buscar-celular', 'buscar-espacio', 'btn-buscar-reserva', 'buscar-banner-error',
            'buscar-resultado', 'buscar-r-codigo', 'buscar-r-espacio', 'buscar-r-fecha', 'buscar-r-estado', 'btn-buscar-cancelar',
            // Animación del vehículo en camino
            'vehicle-progress', 'vehicle-icon-group', 'vehicle-progress-text', 'vehicle-progress-eta',
        ].forEach((id) => { el[id] = document.getElementById(id); });
    }

    function defaultDateTime() {
        const now = new Date();
        el.fecha.value = now.toISOString().slice(0, 10);
        const h = String(now.getHours()).padStart(2, '0');
        const m = String(now.getMinutes()).padStart(2, '0');
        el.hora.value = `${h}:${m}`;
    }

    // Interpreta la fecha/hora de la reserva como HORA LOCAL, ignorando cualquier
    // sufijo de zona horaria que venga del backend (evita desfases tipo UTC vs Perú).
    function parseFechaLocal(isoString) {
        if (!isoString) return null;
        const match = /^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2}):(\d{2})/.exec(String(isoString));
        if (!match) return new Date(isoString);
        const y = Number(match[1]);
        const mo = Number(match[2]);
        const d = Number(match[3]);
        const h = Number(match[4]);
        const mi = Number(match[5]);
        const s = Number(match[6]);
        return new Date(y, mo - 1, d, h, mi, s);
    }

    function formatearFechaHora(isoString) {
        const f = parseFechaLocal(isoString);
        return f ? f.toLocaleString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';
    }

    async function init() {
        cacheEls();
        defaultDateTime();

        state.config = await Api.config();
        el['info-direccion'].textContent = state.config.direccion;
        el['info-horario'].textContent = state.config.horario;
        el['info-telefono'].textContent = state.config.telefono;
        el['hold-minutes'].textContent = state.config.hold_minutes;

        const brandName = document.getElementById('brand-name');
        const brandLogo = document.getElementById('brand-logo');
        const brandBadge = document.getElementById('brand-badge');
        if (brandName) {
            brandName.textContent = state.config.nombre_negocio || brandName.textContent;
        }
        if (brandLogo && state.config.logo_path) {
            brandLogo.src = `${window.APP_BASE}/storage/${state.config.logo_path}`;
            brandLogo.style.display = '';
        }
        if (brandBadge && state.config.logo_path) {
            brandBadge.style.display = 'none';
        }
        document.title = (state.config.nombre_negocio || 'Mi Cochera') + ' — Reserva tu espacio';

        state.metodosPago = await Api.metodosPago();

        await reloadGrid();

        // Si hay una reserva guardada en localStorage, cargarla para mantener acceso a la cancelación
        await tryRestoreReservationFromStorage();

        ['fecha', 'hora', 'horas'].forEach((id) => el[id].addEventListener('change', () => {
            if (!state.reserva) reloadGrid();
            actualizarResumen();
        }));

        // Bloquea caracteres inválidos mientras el usuario escribe
        el['cliente-nombre'].addEventListener('input', () => {
            filtrarSoloLetras(el['cliente-nombre']);
            const err = validarNombre(el['cliente-nombre'].value);
            marcarCampo(el['cliente-nombre'], err);
        });
        el['cliente-celular'].addEventListener('input', () => {
            formatearCelular(el['cliente-celular']);
            const err = validarCelular(el['cliente-celular'].value);
            marcarCampo(el['cliente-celular'], err);
        });
        // Bloqueo adicional a nivel de tecla (evita pegar/teclear símbolos raros)
        el['cliente-nombre'].addEventListener('keypress', (e) => {
            if (!/[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/.test(e.key)) e.preventDefault();
        });
        el['cliente-celular'].addEventListener('keypress', (e) => {
            if (!/\d/.test(e.key)) e.preventDefault();
        });

        el['btn-reservar'].addEventListener('click', crearReserva);
        el['btn-enviar-comprobante'].addEventListener('click', enviarComprobante);
        el['btn-abrir-cancelacion'].addEventListener('click', mostrarPanelCancelacion);
        el['btn-solicitar-cancelacion'].addEventListener('click', solicitarCancelacion);

        // Buscar / ver reserva existente
        el['btn-buscar-reserva'].addEventListener('click', buscarReserva);
        el['btn-buscar-cancelar'].addEventListener('click', mostrarPanelCancelacion);
    }

    // --- Persistencia en localStorage para mantener la reserva visible al recargar ---
    function saveReservationToStorage(reserva) {
        try {
            localStorage.setItem('reserva_id', String(reserva.id));
            localStorage.setItem('reserva_token', String(reserva.token));
        } catch (e) {
            console.warn('No se pudo guardar la reserva en localStorage:', e);
        }
    }

    function clearReservationStorage() {
        try {
            localStorage.removeItem('reserva_id');
            localStorage.removeItem('reserva_token');
        } catch (e) {
            console.warn('No se pudo limpiar la reserva de localStorage:', e);
        }
    }

    async function tryRestoreReservationFromStorage() {
        const id = localStorage.getItem('reserva_id');
        const token = localStorage.getItem('reserva_token');
        if (!id || !token) return;
        try {
            const reserva = await Api.obtenerReserva(id, token);
            // Si la reserva ya terminó (cancelada/vencida), no bloquear el formulario: limpiar y seguir normal
            if (['cancelada', 'vencida'].includes(reserva.estado)) {
                clearReservationStorage();
                return;
            }
            // Si existe y sigue activa, asignar al estado y actualizar UI
            state.reserva = reserva;
            state.lastEstado = reserva.estado;
            setFormDisabled(true);
            el['btn-reservar'].hidden = true;
            mostrarAvisoReservaEnCurso(reserva);
            if (reserva.estado === 'pendiente_pago') {
                mostrarPanelPago();
                HoldTimer.start(reserva.hold_expira_en, el['hold-timer'], onHoldExpired);
            }
            actualizarStepper(reserva.estado);
            actualizarCancelacionPanel();
            startPolling();
        } catch (e) {
            // token inválido o reserva no encontrada -> limpiar
            console.warn('No se pudo restaurar la reserva desde localStorage:', e);
            clearReservationStorage();
        }
    }

    // Muestra un aviso claro explicando por qué el formulario está bloqueado
    function mostrarAvisoReservaEnCurso(reserva) {
        showError(
            `Ya tienes una reserva en curso (código ${reserva.codigo}) para el espacio ${reserva.espacio_codigo}. ` +
            'Completa el pago o espera a que se cancele/venza para poder crear una nueva.'
        );
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
            console.warn('No se pudo cargar el mapa de espacios:', e);
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

    // --- Validaciones ---

    function validarNombre(nombre) {
        const valor = nombre.trim();
        if (!valor) return 'Ingresa tu nombre completo.';
        if (valor.length < 3) return 'El nombre debe tener al menos 3 caracteres.';
        if (!/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/.test(valor)) return 'El nombre solo puede contener letras.';
        if (valor.split(/\s+/).filter(Boolean).length < 2) return 'Ingresa nombre y apellido.';
        return null;
    }

    function validarCelular(celular) {
        const limpio = celular.replace(/[\s-]/g, '');
        if (!limpio) return 'Ingresa tu número de celular.';
        if (!/^9\d{8}$/.test(limpio)) return 'Ingresa un celular válido de 9 dígitos (ej. 987654321).';
        return null;
    }

    function formatearCelular(input) {
        const limpio = input.value.replace(/\D/g, '').slice(0, 9);
        input.value = limpio.replace(/(\d{3})(\d{3})(\d{0,3})/, (m, a, b, c) => {
            if (c) return `${a} ${b} ${c}`;
            if (b) return `${a} ${b}`;
            return a;
        });
    }

    function filtrarSoloLetras(input) {
        const cursor = input.selectionStart;
        const limpio = input.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '');
        if (limpio !== input.value) {
            input.value = limpio;
            input.setSelectionRange(cursor - 1, cursor - 1);
        }
    }

    function marcarCampo(input, error) {
        input.style.borderColor = error ? '#e53e3e' : '';
    }

    // --- Crear reserva ---

    async function crearReserva() {
        hideError();
        if (!state.selectedEspacio) return showError('Selecciona un espacio disponible.');

        const nombre = el['cliente-nombre'].value.trim();
        const celular = el['cliente-celular'].value.trim();

        const errNombre = validarNombre(nombre);
        marcarCampo(el['cliente-nombre'], errNombre);
        if (errNombre) return showError(errNombre);

        const errCelular = validarCelular(celular);
        marcarCampo(el['cliente-celular'], errCelular);
        if (errCelular) return showError(errCelular);

        const celularLimpio = celular.replace(/[\s-]/g, '');

        el['btn-reservar'].disabled = true;
        el['btn-reservar'].textContent = 'Reservando…';

        try {
            const payload = {
                espacio_id: state.selectedEspacio.id,
                fecha_hora_inicio: `${el.fecha.value} ${el.hora.value}:00`,
                horas_estimadas: Number(el.horas.value),
                cliente_nombre: nombre,
                cliente_celular: celularLimpio,
            };
            const reserva = await Api.crearReserva(payload);
            state.reserva = reserva;
            saveReservationToStorage(reserva);
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
            } else if (e.status === 422 && e.message) {
                showError(e.message);
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
            tab.className = 'tab tab-' + m.tipo + (i === 0 ? ' active' : '');
            tab.textContent = etiquetaMetodo(m.tipo);
            tab.addEventListener('click', () => seleccionarMetodo(m.tipo));
            el['metodo-tabs'].appendChild(tab);
        });
        seleccionarMetodo(state.metodosPago[0].tipo);
        el['panel-pago'].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        actualizarCancelacionPanel();
    }

    function etiquetaMetodo(tipo) {
        return { yape: 'Yape', plin: 'Plin', transferencia: 'Transferencia' }[tipo] || tipo;
    }

    function seleccionarMetodo(tipo) {
        state.metodoActivo = tipo;
        [...el['metodo-tabs'].children].forEach((tab) => {
            tab.classList.toggle('active', tab.classList.contains('tab-' + tipo));
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
            <div class="metodo-card metodo-${tipo}">
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
            // CORRECCIÓN: combinar en vez de reemplazar, para no perder campos
            // como fecha_hora_inicio/token que usa el resto del formulario.
            state.reserva = { ...state.reserva, ...reserva };
            saveReservationToStorage(state.reserva);
            actualizarStepper(state.reserva.estado);
            el['panel-pago'].querySelectorAll('input, .tab').forEach((n) => n.setAttribute('disabled', 'disabled'));
            el['btn-enviar-comprobante'].textContent = 'Comprobante enviado ✓';
            HoldTimer.stop();
            el['hold-timer'].hidden = true;
        } catch (e) {
            // CORRECCIÓN: mostrar el mensaje real que devuelve el servidor
            // (ej. "Debes adjuntar..." o "Formato no permitido...") en vez de
            // un mensaje genérico, para poder diagnosticar el fallo en pantalla.
            showError(e.data?.error || e.message || 'No se pudo enviar el comprobante. Intenta nuevamente.');
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
        // Si la reserva acaba de pasar a 'adelanto_pagado', notificar al usuario
        if (state.lastEstado !== 'adelanto_pagado' && estado === 'adelanto_pagado') {
            showToast('Tu reserva ha sido confirmada. Cualquier cancelación debe realizarse hasta 20 minutos antes de la hora de ingreso.');
        }
        state.lastEstado = estado;
        actualizarCancelacionPanel();

        // Animación del vehículo en camino: se re-renderiza en cada refresco de estado
        // (cada ~5s mientras el polling esté activo), reusando ese mismo ciclo.
        VehicleAnimation.render(
            el['vehicle-progress'],
            el['vehicle-icon-group'],
            el['vehicle-progress-text'],
            el['vehicle-progress-eta'],
            state.reserva
        );

        // Aviso grande de "vehículo llegó", una sola vez por reserva
        if (state.reserva && VehicleAnimation.haLlegado(state.reserva) && state.vehicleArrivedNotifiedFor !== state.reserva.id) {
            state.vehicleArrivedNotifiedFor = state.reserva.id;
            showToast('🚗✅ ¡Tu vehículo llegó al establecimiento! Presenta tu código de reserva al ingresar.');
        }
    }

    // Genera un pequeño sonido de notificación sin necesidad de archivos externos
    function playNotificationSound() {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            const ctx = new AudioCtx();
            const oscillator = ctx.createOscillator();
            const gain = ctx.createGain();
            oscillator.connect(gain);
            gain.connect(ctx.destination);
            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(880, ctx.currentTime);
            gain.gain.setValueAtTime(0.15, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
            oscillator.start();
            oscillator.stop(ctx.currentTime + 0.5);
        } catch (e) {
            console.warn('No se pudo reproducir el sonido de notificación:', e);
        }
    }

    // Muestra una notificación centrada, con overlay oscuro, ícono y sonido
    function showToast(msg, timeout = 6000) {
        playNotificationSound();

        const overlay = document.createElement('div');
        overlay.className = 'app-toast-overlay';
        overlay.innerHTML = `
            <div class="app-toast">
                <button class="app-toast-close" type="button" aria-label="Cerrar">✕</button>
                <div class="app-toast-icon">🎉</div>
                <div class="app-toast-msg">${msg}</div>
            </div>`;
        document.body.appendChild(overlay);

        function cerrar() {
            overlay.classList.remove('visible');
            setTimeout(() => overlay.remove(), 400);
        }

        overlay.querySelector('.app-toast-close').addEventListener('click', cerrar);
        overlay.addEventListener('click', (e) => { if (e.target === overlay) cerrar(); });

        requestAnimationFrame(() => overlay.classList.add('visible'));
        setTimeout(cerrar, timeout);
    }

    function startPolling() {
        stopPolling();
        state.pollHandle = setInterval(async () => {
            try {
                const reserva = await Api.obtenerReserva(state.reserva.id, state.reserva.token);
                state.reserva = { ...state.reserva, ...reserva };
                actualizarStepper(reserva.estado);
                // Actualizar persistencia local cuando cambie
                saveReservationToStorage(state.reserva);
                if (['cancelada', 'vencida'].includes(reserva.estado)) {
                    // limpiar almacenamiento para que no vuelva a aparecer la opción de cancelar
                    clearReservationStorage();
                    stopPolling();
                } else if (reserva.estado === 'pago_completo') {
                    // Si ya se completó el pago, no necesitamos seguir poller
                    stopPolling();
                }
            } catch (e) {
                // silencioso: se reintenta en el próximo tick
                console.warn('Error al refrescar el estado de la reserva:', e);
            }
        }, 5000);
    }

    // --- Buscar reserva existente (celular + espacio) ---

    async function buscarReserva() {
        hideBuscarError();

        const celular = el['buscar-celular'].value.trim().replace(/[\s-]/g, '');
        const espacioCodigo = el['buscar-espacio'].value.trim();

        if (!celular) return showBuscarError('Ingresa tu número de celular.');
        if (!espacioCodigo) return showBuscarError('Ingresa el número de espacio.');

        el['btn-buscar-reserva'].disabled = true;
        el['btn-buscar-reserva'].textContent = 'Buscando…';

        try {
            const reserva = await Api.buscarReserva(celular, espacioCodigo);

            // Pinta el resultado en el panel de la derecha
            el['buscar-r-codigo'].textContent = reserva.codigo;
            el['buscar-r-espacio'].textContent = reserva.espacio_codigo;
            el['buscar-r-fecha'].textContent = formatearFechaHora(reserva.fecha_hora_inicio);
            el['buscar-r-estado'].textContent = ESTADO_LABEL_CLIENTE[reserva.estado] || reserva.estado;
            el['buscar-resultado'].hidden = false;

            const puedeCancelar = !['cancelada', 'vencida', 'pago_completo'].includes(reserva.estado);
            el['btn-buscar-cancelar'].hidden = !puedeCancelar;

            // Reutiliza el mismo flujo de pago/cancelación/polling que ya existe
            // para una reserva recién creada, para no duplicar lógica.
            state.reserva = reserva;
            state.lastEstado = reserva.estado;
            saveReservationToStorage(reserva);

            if (!['cancelada', 'vencida'].includes(reserva.estado)) {
                setFormDisabled(true);
                el['btn-reservar'].hidden = true;
                mostrarAvisoReservaEnCurso(reserva);
                if (reserva.estado === 'pendiente_pago' && reserva.hold_expira_en) {
                    mostrarPanelPago();
                    HoldTimer.start(reserva.hold_expira_en, el['hold-timer'], onHoldExpired);
                }
                actualizarStepper(reserva.estado);
                actualizarCancelacionPanel();
                startPolling();
            } else {
                // aunque esté cancelada/vencida, refrescamos la animación para que se oculte
                VehicleAnimation.render(
                    el['vehicle-progress'],
                    el['vehicle-icon-group'],
                    el['vehicle-progress-text'],
                    el['vehicle-progress-eta'],
                    state.reserva
                );
            }
        } catch (e) {
            el['buscar-resultado'].hidden = true;
            if (e.status === 404) {
                showBuscarError('No encontramos ninguna reserva con esos datos. Verifica el celular y el número de espacio.');
            } else {
                showBuscarError(e.data?.error || 'No se pudo buscar la reserva. Intenta nuevamente.');
            }
        } finally {
            el['btn-buscar-reserva'].disabled = false;
            el['btn-buscar-reserva'].textContent = 'Buscar reserva';
        }
    }

    function showBuscarError(msg) {
        el['buscar-banner-error'].textContent = msg;
        el['buscar-banner-error'].hidden = false;
    }

    function hideBuscarError() {
        el['buscar-banner-error'].hidden = true;
    }

    function mostrarPanelCancelacion() {
        if (!state.reserva) return;
        const estado = state.reserva.estado;
        if (['cancelada', 'vencida'].includes(estado)) {
            el['panel-cancelacion'].hidden = true;
            return;
        }
        const inicio = parseFechaLocal(state.reserva.fecha_hora_inicio).getTime();
        const minutosRestantes = (inicio - Date.now()) / 60000;
        if (minutosRestantes < 20) {
            showCancelacionError('El plazo para solicitar la cancelación venció (debe ser con 20 minutos de anticipación).');
            return;
        }
        el['cancelacion-codigo'].textContent = state.reserva.codigo;
        el['cancelacion-motivo'].value = '';
        el['cancelacion-numero-operacion'].value = '';
        el['cancelacion-comprobante'].value = '';
        hideCancelacionError();
        el['panel-cancelacion'].hidden = false;
        el['panel-cancelacion'].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function actualizarCancelacionPanel() {
        if (!state.reserva) {
            el['panel-cancelacion'].hidden = true;
            el['btn-abrir-cancelacion'].hidden = true;
            return;
        }
        // Solo permitir cancelar si la reserva no está cancelada/vencida
        if (['cancelada', 'vencida'].includes(state.reserva.estado)) {
            el['btn-abrir-cancelacion'].hidden = true;
            el['panel-cancelacion'].hidden = true;
            return;
        }

        // Verifica la ventana de cancelación: se permite hasta 20 minutos antes del inicio
        const inicio = parseFechaLocal(state.reserva.fecha_hora_inicio).getTime();
        const minutosRestantes = (inicio - Date.now()) / 60000;
        const canCancel = minutosRestantes >= 20;

        // El botón siempre se muestra mientras la reserva esté activa; solo se deshabilita
        // si ya no está dentro del plazo permitido.
        el['btn-abrir-cancelacion'].hidden = false;
        el['btn-abrir-cancelacion'].disabled = !canCancel;
        el['btn-abrir-cancelacion'].textContent = canCancel
            ? 'Solicitar cancelación'
            : 'Plazo de cancelación vencido';

        // siempre actualizamos el código por si cambió
        el['cancelacion-codigo'].textContent = state.reserva.codigo;

        // También refleja la posibilidad de cancelar en el botón del panel de búsqueda
        if (el['btn-buscar-cancelar'] && !el['buscar-resultado'].hidden) {
            el['btn-buscar-cancelar'].hidden = false;
            el['btn-buscar-cancelar'].disabled = !canCancel;
            el['btn-buscar-cancelar'].textContent = canCancel
                ? 'Solicitar cancelación'
                : 'Plazo de cancelación vencido';
        }
    }

    async function solicitarCancelacion() {
        hideCancelacionError();
        if (!state.reserva) return;

        const motivo = el['cancelacion-motivo'].value.trim();
        const numeroOperacion = el['cancelacion-numero-operacion'].value.trim();
        const comprobante = el['cancelacion-comprobante'].files[0];

        if (!motivo) {
            return showCancelacionError('Ingresa el motivo de la cancelación.');
        }
        if (!numeroOperacion) {
            return showCancelacionError('Ingresa el número de operación o código del pago.');
        }
        if (!comprobante) {
            return showCancelacionError('Adjunta el comprobante de pago.');
        }

        el['btn-solicitar-cancelacion'].disabled = true;
        el['btn-solicitar-cancelacion'].textContent = 'Enviando…';

        try {
            const formData = new FormData();
            formData.append('token', state.reserva.token);
            formData.append('motivo', motivo);
            formData.append('numero_operacion', numeroOperacion);
            formData.append('comprobante', comprobante);

            await Api.solicitarCancelacion(state.reserva.id, formData);
            el['panel-cancelacion'].innerHTML = `<div class="panel-message"><strong>Solicitud enviada.</strong><p>Hemos recibido tu solicitud de cancelación y el equipo la revisará.</p></div>`;
        } catch (e) {
            if (e.status === 409) {
                showCancelacionError(e.data?.error || 'Ya existe una solicitud de cancelación para esta reserva.');
            } else {
                showCancelacionError(e.data?.error || 'No se pudo enviar la solicitud. Intenta nuevamente.');
            }
            el['btn-solicitar-cancelacion'].disabled = false;
            el['btn-solicitar-cancelacion'].textContent = 'Enviar solicitud de cancelación';
        }
    }

    function showCancelacionError(msg) {
        el['cancelacion-banner-error'].textContent = msg;
        el['cancelacion-banner-error'].hidden = false;
    }

    function hideCancelacionError() {
        el['cancelacion-banner-error'].hidden = true;
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