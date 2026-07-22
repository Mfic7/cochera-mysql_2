const AdminDashboard = (() => {
    const ESTADO_LABEL = {
        pendiente_pago: 'Pendiente pago',
        en_validacion: 'En validación',
        adelanto_pagado: '50% Pagado',
        pago_completo: '100% Pagado',
        cancelada: 'Cancelada',
        vencida: 'Vencida',
    };

    function money(n) { return 'S/ ' + Number(n).toFixed(2); }
    function esc(s) { return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
    function fecha(s) { return s ? new Date(s.replace(' ', 'T')).toLocaleString('es-PE', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—'; }
    function hora(s) { return s ? new Date(s.replace(' ', 'T')).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' }) : '—'; }
    function badge(estado) { return `<span class="status-badge ${estado}">${ESTADO_LABEL[estado] || estado}</span>`; }
    function toast(msg) {
        const t = document.createElement('div');
        t.className = 'toast';
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 3000);
    }

    // ---------- Navegación ----------
    function initNav() {
        document.querySelectorAll('.nav-item[data-view]').forEach((item) => {
            item.addEventListener('click', () => switchView(item.dataset.view));
        });
        document.querySelectorAll('[data-goto]').forEach((link) => {
            link.addEventListener('click', (e) => { e.preventDefault(); switchView(link.dataset.goto); });
        });
    }

    function switchView(view) {
        document.querySelectorAll('.nav-item[data-view]').forEach((n) => n.classList.toggle('active', n.dataset.view === view));
        document.querySelectorAll('.view').forEach((v) => v.classList.toggle('active', v.id === 'view-' + view));
        const loaders = {
            dashboard: loadDashboard,
            reservas: () => loadReservas(),
            pagos: () => loadPagos(),
            espacios: loadEspacios,
            'metodos-pago': loadMetodosPago,
            cancelaciones: loadCancelaciones,
            configuracion: loadConfiguracion,
        };
        if (loaders[view]) loaders[view]();
    }

    // ---------- Dashboard ----------
    async function loadDashboard() {
        const [kpis, ocupacion, reservasDia, recientes, actividad] = await Promise.all([
            AdminApi.kpis(), AdminApi.ocupacion(), AdminApi.reservas({ fecha: new Date().toISOString().slice(0, 10) }).catch(() => ({ data: [] })),
            (async () => { const r = await fetch(`${window.APP_BASE}/api/index.php/admin/dashboard/reservas-recientes`, { credentials: 'same-origin' }); return r.json(); })(),
            AdminApi.actividad(8),
        ]);

        renderKpis(kpis);
        renderAdminParkingGrid(document.getElementById('admin-parking-grid'), ocupacion.espacios);
        renderReservasDelDia(reservasDia.data || []);
        renderReservasRecientes(recientes);
        renderActividad(actividad);
        renderBottomStats(kpis);

        try {
            const ingresos = await AdminApi.reporteIngresos('dia');
            renderIngresosChart(document.getElementById('chart-ingresos'), ingresos.map((r) => r.etiqueta), ingresos.map((r) => Number(r.total)));
        } catch (e) { /* sin datos aún */ }

        try {
            const metodos = await AdminApi.reporteMetodosPago(new Date().toISOString().slice(0, 8) + '01', new Date().toISOString().slice(0, 10));
            renderMetodosChart(document.getElementById('chart-metodos'), document.getElementById('metodos-leyenda'), metodos);
        } catch (e) { /* sin datos aún */ }
    }

    function renderKpis(k) {
        const cards = [
            { icon: '🚗', label: 'Total de reservas', value: k.total_reservas, trend: `↑ ${k.reservas_variacion_pct}% vs ayer`, bg: '#dbeafe' },
            { icon: '🅿️', label: 'Espacios ocupados', value: k.espacios_ocupados, trend: `${Math.round((k.espacios_ocupados / k.total_espacios) * 100)}% del total`, bg: '#dcfce7' },
            { icon: '🟡', label: 'Espacios disponibles', value: k.espacios_disponibles, trend: `${Math.round((k.espacios_disponibles / k.total_espacios) * 100)}% del total`, bg: '#fef3c7' },
            { icon: '💰', label: 'Ingresos del día', value: money(k.ingresos_hoy), trend: '', bg: '#ede9fe' },
            { icon: '🧾', label: 'Adelantos recibidos', value: money(k.adelantos_hoy), trend: '', bg: '#cffafe' },
        ];
        document.getElementById('kpi-grid').innerHTML = cards.map((c) => `
            <div class="kpi-card">
                <div class="kpi-top"><span class="kpi-icon" style="background:${c.bg}">${c.icon}</span>${c.label}</div>
                <div class="kpi-value">${c.value}</div>
                ${c.trend ? `<div class="kpi-trend">${c.trend}</div>` : ''}
            </div>`).join('');
    }

    function renderReservasDelDia(rows) {
        const el = document.getElementById('reservas-del-dia');
        if (!rows.length) { el.innerHTML = '<p class="muted">No hay reservas para hoy.</p>'; return; }
        el.innerHTML = rows.slice(0, 6).map((r) => `
            <div class="list-row">
                <span class="time">${hora(r.fecha_hora_inicio)}</span>
                <div class="info"><strong>${esc(r.cliente_nombre)}</strong><span>Espacio ${esc(r.espacio_codigo)}</span></div>
                ${badge(r.estado)}
            </div>`).join('');
    }

    function renderReservasRecientes(rows) {
        document.querySelector('#tabla-reservas-recientes tbody').innerHTML = rows.map((r) => `
            <tr>
                <td>${esc(r.codigo)}</td><td>${esc(r.cliente_nombre)}</td><td>${esc(r.espacio_codigo)}</td>
                <td>${fecha(r.fecha_hora_inicio)}</td><td>${money(r.monto_total)}</td>
                <td>${badge(r.estado)}</td><td>${ESTADO_LABEL[r.estado] || r.estado}</td>
            </tr>`).join('');
    }

    function renderActividad(rows) {
        const iconos = { pendiente_pago: '📝', en_validacion: '📄', adelanto_pagado: '✅', pago_completo: '💰', cancelada: '❌', vencida: '⏰' };
        const el = document.getElementById('actividad-reciente');
        if (!rows.length) { el.innerHTML = '<p class="muted">Sin actividad reciente.</p>'; return; }
        el.innerHTML = rows.map((r) => `
            <div class="activity-item">
                <span class="activity-icon" style="background:#f1f2f5">${iconos[r.estado_nuevo] || '•'}</span>
                <div><strong>${esc(r.reserva_codigo)} — ${ESTADO_LABEL[r.estado_nuevo] || r.estado_nuevo}</strong><span>${esc(r.cliente_nombre)} · Espacio ${esc(r.espacio_codigo)}</span></div>
                <span class="activity-time">${fecha(r.created_at)}</span>
            </div>`).join('');
    }

    function renderBottomStats(k) {
        document.getElementById('bottom-stats').innerHTML = `
            <div class="panel"><label>Reservas por día</label><span>${k.reservas_dia}</span></div>
            <div class="panel"><label>Reservas por semana</label><span>${k.reservas_semana}</span></div>
            <div class="panel"><label>Reservas por mes</label><span>${k.reservas_mes}</span></div>
            <div class="panel"><label>Reservas por año</label><span>${k.reservas_anio}</span></div>
            <div class="panel subscription-card">
                <div class="icon">👑</div>
                <div><strong>Plan Activo</strong><small>Ver sección Suscripción</small></div>
                <button class="btn-secondary" type="button" data-goto="suscripcion">Administrar</button>
            </div>`;
        document.querySelectorAll('#bottom-stats [data-goto]').forEach((b) => b.addEventListener('click', () => switchView('suscripcion')));
    }

    // ---------- Reservas ----------
    async function loadReservas() {
        const fechaFiltro = document.getElementById('filtro-fecha-reservas').value;
        const estado = document.getElementById('filtro-estado-reservas').value;
        const resp = await AdminApi.reservas({ ...(fechaFiltro ? { fecha: fechaFiltro } : {}), ...(estado ? { estado } : {}) });
        document.querySelector('#tabla-reservas tbody').innerHTML = resp.data.map((r) => `
            <tr>
                <td>${esc(r.codigo)}</td><td>${esc(r.cliente_nombre)}</td><td>${esc(r.cliente_celular)}</td>
                <td>${esc(r.espacio_codigo)}</td><td>${fecha(r.fecha_hora_inicio)}</td>
                <td>${money(r.monto_total)}</td><td>${money(r.monto_adelanto)}</td>
                <td>${badge(r.estado)}</td>
                <td>
                    ${r.estado === 'adelanto_pagado' ? `<button class="btn-sm approve" data-saldo="${r.id}">Registrar saldo</button>` : ''}
                    ${!['cancelada', 'pago_completo', 'vencida'].includes(r.estado) ? `<button class="btn-sm reject" data-cancelar="${r.id}">Cancelar</button>` : ''}
                </td>
            </tr>`).join('');

        document.querySelectorAll('[data-saldo]').forEach((b) => b.addEventListener('click', () => registrarSaldo(b.dataset.saldo)));
        document.querySelectorAll('[data-cancelar]').forEach((b) => b.addEventListener('click', () => cancelarReserva(b.dataset.cancelar)));
    }

    async function registrarSaldo(id) {
        if (!confirm('¿Confirmar que el cliente pagó el saldo restante en efectivo/otro método presencial?')) return;
        try {
            await AdminApi.pagoSaldo(id, null, 'efectivo');
            toast('Saldo registrado, reserva completada.');
            loadReservas();
        } catch (e) { toast(e.data?.error || 'No se pudo registrar el saldo.'); }
    }

    async function cancelarReserva(id) {
        if (!confirm('¿Cancelar esta reserva?')) return;
        try {
            await AdminApi.actualizarEstadoReserva(id, 'cancelada', 'Cancelada manualmente por admin');
            toast('Reserva cancelada.');
            loadReservas();
        } catch (e) { toast(e.data?.error || 'No se pudo cancelar.'); }
    }

    // ---------- Pagos ----------
    async function loadPagos() {
        const estado = document.getElementById('filtro-estado-pagos').value;
        const rows = await AdminApi.pagos(estado);
        document.querySelector('#tabla-pagos tbody').innerHTML = rows.map((p) => `
            <tr>
                <td>${esc(p.reserva_codigo)}</td><td>${esc(p.cliente_nombre)}</td><td>${esc(p.espacio_codigo)}</td>
                <td>${esc(p.metodo)}</td><td>${money(p.monto)}</td><td>${esc(p.numero_operacion || '—')}</td>
                <td>${badge(p.estado)}</td>
                <td>${p.comprobante_path ? `<button class="btn-sm" data-ver-comprobante="${p.id}">Ver</button>` : '—'}</td>
                <td>
                    ${p.estado === 'en_validacion' ? `
                        <button class="btn-sm approve" data-aprobar="${p.id}">Aprobar</button>
                        <button class="btn-sm reject" data-rechazar="${p.id}">Rechazar</button>` : ''}
                </td>
            </tr>`).join('');

        document.querySelectorAll('[data-ver-comprobante]').forEach((b) => b.addEventListener('click', () => verComprobante(b.dataset.verComprobante)));
        document.querySelectorAll('[data-aprobar]').forEach((b) => b.addEventListener('click', () => revisarPago(b.dataset.aprobar, 'aprobar')));
        document.querySelectorAll('[data-rechazar]').forEach((b) => b.addEventListener('click', () => {
            const motivo = prompt('Motivo del rechazo:');
            if (motivo !== null) revisarPago(b.dataset.rechazar, 'rechazar', motivo);
        }));
    }

    function verComprobante(id) {
        openModal(`
            <h3>Comprobante de pago <button class="modal-close" data-close>×</button></h3>
            <img src="${AdminApi.comprobanteUrl(id)}" alt="Comprobante">`);
    }

    async function revisarPago(id, accion, motivo) {
        try {
            await AdminApi.revisarPago(id, accion, motivo);
            toast(accion === 'aprobar' ? 'Pago aprobado.' : 'Pago rechazado.');
            loadPagos();
        } catch (e) { toast(e.data?.error || 'No se pudo procesar.'); }
    }

    // ---------- Espacios ----------
    async function loadEspacios() {
        const rows = await AdminApi.espacios();
        document.querySelector('#tabla-espacios tbody').innerHTML = rows.map((e) => `
            <tr>
                <td>${esc(e.codigo)}</td><td>${esc(e.zona || '—')}</td>
                <td>
                    <select data-estado-espacio="${e.id}">
                        <option value="disponible" ${e.estado === 'disponible' ? 'selected' : ''}>Disponible</option>
                        <option value="ocupado" ${e.estado === 'ocupado' ? 'selected' : ''}>Ocupado</option>
                        <option value="mantenimiento" ${e.estado === 'mantenimiento' ? 'selected' : ''}>Mantenimiento</option>
                    </select>
                </td>
                <td><button class="btn-sm" data-guardar-espacio="${e.id}">Guardar</button></td>
            </tr>`).join('');

        document.querySelectorAll('[data-guardar-espacio]').forEach((b) => b.addEventListener('click', async () => {
            const id = b.dataset.guardarEspacio;
            const estado = document.querySelector(`[data-estado-espacio="${id}"]`).value;
            try {
                await AdminApi.actualizarEspacio(id, { estado });
                toast('Espacio actualizado.');
            } catch (e) { toast('No se pudo actualizar.'); }
        }));
    }

    // ---------- Métodos de pago ----------
    async function loadMetodosPago() {
        const rows = await AdminApi.metodosPago();
        const label = { yape: 'Yape', plin: 'Plin', transferencia: 'Transferencia' };
        document.getElementById('metodos-pago-cards').innerHTML = rows.map((m) => `
            <div class="panel">
                <h3>${label[m.tipo]}</h3>
                <form data-metodo-form="${m.tipo}">
                    <div class="form-field"><label>Titular</label><input name="titular" value="${esc(m.titular)}"></div>
                    <div class="form-field"><label>Número de cuenta</label><input name="numero_cuenta" value="${esc(m.numero_cuenta)}"></div>
                    <div class="form-field"><label>Banco (opcional)</label><input name="banco" value="${esc(m.banco || '')}"></div>
                    <div class="form-field"><label>Código QR</label><input type="file" name="qr" accept="image/*"></div>
                    <button class="btn-sm" type="submit">Guardar</button>
                </form>
            </div>`).join('');

        document.querySelectorAll('[data-metodo-form]').forEach((form) => form.addEventListener('submit', async (ev) => {
            ev.preventDefault();
            const tipo = form.dataset.metodoForm;
            const qrInput = form.querySelector('[name="qr"]');
            try {
                if (qrInput.files[0]) {
                    const fd = new FormData(form);
                    await fetch(`${window.APP_BASE}/api/index.php/admin/metodos-pago/${tipo}`, {
                        method: 'PATCH', body: fd, credentials: 'same-origin',
                        headers: { 'X-CSRF-Token': sessionStorage.getItem('csrf_token') || '' },
                    });
                } else {
                    await AdminApi.actualizarMetodoPago(tipo, Object.fromEntries(new FormData(form).entries()));
                }
                toast('Método de pago actualizado.');
            } catch (e) { toast('No se pudo actualizar.'); }
        }));
    }

    // ---------- Cancelaciones ----------
    const CANCELACION_LABEL = { pendiente: 'Pendiente', aprobada: 'Aprobada', fuera_plazo: 'Fuera de plazo', rechazada: 'Rechazada' };

    async function loadCancelaciones() {
        const estado = document.getElementById('filtro-estado-cancelaciones').value;
        const resp = await fetch(`${window.APP_BASE}/api/index.php/admin/cancelaciones` + (estado ? `?estado=${estado}` : ''), { credentials: 'same-origin' });
        const data = await resp.json();
        const rows = data.data || [];

        document.querySelector('#tabla-cancelaciones tbody').innerHTML = rows.map((c) => `
            <tr>
                <td>${esc(c.reserva_codigo)}</td><td>${esc(c.espacio_codigo)}</td>
                <td>${esc(c.cliente_nombre)}</td><td>${esc(c.cliente_celular)}</td>
                <td class="celda-motivo" title="${esc(c.motivo)}">${esc(c.motivo)}</td>
                <td>${esc(c.numero_operacion || '—')}</td>
                <td><span class="status-badge cancelacion-${c.estado}">${CANCELACION_LABEL[c.estado] || c.estado}</span></td>
                <td>${fecha(c.created_at)}</td>
                <td>${c.comprobante_path ? `<button class="btn-sm" data-ver-comprobante-cancelacion="${c.id}">Ver</button>` : '—'}</td>
                <td>
                    ${c.estado === 'pendiente' ? `
                        <button class="btn-sm approve" data-aprobar-cancelacion="${c.id}">Aceptar</button>
                        <button class="btn-sm reject" data-rechazar-cancelacion="${c.id}">Rechazar</button>` : (c.nota_admin ? esc(c.nota_admin) : '—')}
                </td>
            </tr>`).join('');

        document.querySelectorAll('[data-ver-comprobante-cancelacion]').forEach((b) => b.addEventListener('click', () => {
            openModal(`
                <h3>Comprobante de pago <button class="modal-close" data-close>×</button></h3>
                <img src="${window.APP_BASE}/api/index.php/admin/cancelaciones/${b.dataset.verComprobanteCancelacion}/comprobante" alt="Comprobante">`);
        }));

        document.querySelectorAll('[data-aprobar-cancelacion]').forEach((b) => b.addEventListener('click', () => decidirCancelacion(b.dataset.aprobarCancelacion, 'aprobar')));
        document.querySelectorAll('[data-rechazar-cancelacion]').forEach((b) => b.addEventListener('click', () => {
            const nota = prompt('Motivo del rechazo (opcional):') || null;
            decidirCancelacion(b.dataset.rechazarCancelacion, 'rechazar', nota);
        }));

        // Badge con el número de pendientes, visible en el menú lateral
        const pendientes = rows.filter((c) => c.estado === 'pendiente').length;
        const badge = document.getElementById('badge-cancelaciones-pendientes');
        if (badge) {
            if (estado === 'pendiente' || estado === '') {
                badge.textContent = pendientes;
                badge.style.display = pendientes > 0 ? '' : 'none';
            }
        }
    }

    async function decidirCancelacion(id, accion, nota) {
        try {
            const r = await fetch(`${window.APP_BASE}/api/index.php/admin/cancelaciones/${id}`, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': sessionStorage.getItem('csrf_token') || '' },
                body: JSON.stringify({ accion, nota }),
            });
            const data = await r.json();
            if (!r.ok) throw data;

            if (data.estado === 'fuera_plazo') {
                toast('⚠️ ' + (data.mensaje || 'Fuera del plazo de cancelación. No procede devolución.'));
            } else if (data.estado === 'aprobada') {
                toast('✅ ' + (data.mensaje || 'Reserva cancelada.'));
            } else {
                toast('Solicitud rechazada.');
            }
            loadCancelaciones();
        } catch (e) {
            toast(e?.error || 'No se pudo procesar la solicitud.');
        }
    }

    // ---------- Configuración ----------
    async function loadConfiguracion() {
        const cfg = await AdminApi.configuracion();
        const form = document.getElementById('form-configuracion');
        Object.entries(cfg).forEach(([k, v]) => { if (form[k]) form[k].value = v; });
    }

    document.getElementById('input-logo').addEventListener('change', (ev) => {
        const file = ev.target.files[0];
        if (!file) return;
        const preview = document.getElementById('logo-preview');
        preview.src = URL.createObjectURL(file);
        preview.style.display = '';
    });

    document.getElementById('form-configuracion').addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const form = ev.target;
        const logoInput = document.getElementById('input-logo');

        try {
            let resp;
            if (logoInput.files[0]) {
                // Con archivo: se envía como multipart/form-data (igual que el QR de métodos de pago)
                const fd = new FormData(form);
                const r = await fetch(`${window.APP_BASE}/api/index.php/admin/configuracion`, {
                    method: 'PATCH', body: fd, credentials: 'same-origin',
                    headers: { 'X-CSRF-Token': sessionStorage.getItem('csrf_token') || '' },
                });
                resp = await r.json();
                if (!r.ok) throw { data: resp };
            } else {
                const data = Object.fromEntries(new FormData(form).entries());
                delete data.logo;
                resp = await AdminApi.actualizarConfiguracion(data);
            }

            const nombreActualizado = resp?.nombre_negocio || form.nombre_negocio.value;
            const logoActualizado = resp?.logo_path;

            // Refleja los cambios al instante en el sidebar y el título de la pestaña,
            // sin necesidad de recargar la página.
            const brandNombre = document.getElementById('brand-nombre');
            if (brandNombre) brandNombre.textContent = nombreActualizado;
            document.title = nombreActualizado + ' — Panel de administración';

            if (logoActualizado) {
                const logoImg = document.getElementById('brand-logo');
                if (logoImg) logoImg.src = `${window.APP_BASE}/storage/${logoActualizado}?v=${Date.now()}`;
            }

            toast('Configuración guardada.');
        } catch (e) {
            toast(e.data?.error || 'No se pudo guardar.');
        }
    });

    // ---------- Modal ----------
    function openModal(html) {
        const root = document.getElementById('modal-root');
        root.innerHTML = `<div class="modal-overlay" data-overlay><div class="modal">${html}</div></div>`;
        root.querySelector('[data-overlay]').addEventListener('click', (e) => { if (e.target.dataset.overlay !== undefined && (e.target === e.currentTarget)) closeModal(); });
        const closeBtn = root.querySelector('[data-close]');
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
    }
    function closeModal() { document.getElementById('modal-root').innerHTML = ''; }

    async function init() {
        try {
            const me = await AdminApi.me();
            if (me) AdminApi.setCsrfToken(me.csrf_token);
        } catch (e) { return; }

        initNav();
        document.getElementById('btn-refrescar-dashboard').addEventListener('click', loadDashboard);
        document.getElementById('btn-filtrar-reservas').addEventListener('click', loadReservas);
        document.getElementById('btn-filtrar-pagos').addEventListener('click', loadPagos);
        document.getElementById('btn-filtrar-cancelaciones').addEventListener('click', loadCancelaciones);
        loadDashboard();
    }

    return { init };
})();

document.addEventListener('DOMContentLoaded', AdminDashboard.init);