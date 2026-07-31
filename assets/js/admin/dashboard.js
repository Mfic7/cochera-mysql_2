const AdminDashboard = (() => {
    const ESTADO_LABEL = {
        pendiente_pago: 'Pendiente pago',
        en_validacion: 'En validación',
        adelanto_pagado: '50% Pagado',
        pago_completo: '100% Pagado',
        cancelada: 'Cancelada',
        vencida: 'Vencida',
    };

    let reportesPeriodoActual = 'dia';

    function money(n) { return 'S/ ' + Number(n).toFixed(2); }
    function esc(s) { return String(s ?? '').replaceAll(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
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
            reportes: () => loadReportes(reportesPeriodoActual),
            usuarios: loadUsuarios,
            clientes: loadClientes,
            calendario: loadCalendario,
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
        } catch (e) {
            console.warn('Sin datos de ingresos aún:', e);
        }

        try {
            const metodos = await AdminApi.reporteMetodosPago(new Date().toISOString().slice(0, 8) + '01', new Date().toISOString().slice(0, 10));
            renderMetodosChart(document.getElementById('chart-metodos'), document.getElementById('metodos-leyenda'), metodos);
        } catch (e) {
            console.warn('Sin datos de métodos de pago aún:', e);
        }
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

    function registrarSaldo(id) {
        openModal(`
            <h3>Registrar saldo <button class="modal-close" data-close>×</button></h3>
            <p class="muted">Confirma que el cliente pagó el saldo restante (50%) y selecciona el método con el que canceló.</p>
            <div class="form-field">
                <label>Método de pago</label>
                <select id="modal-metodo-saldo">
                    <option value="yape">Yape</option>
                    <option value="plin">Plin</option>
                    <option value="transferencia">Transferencia</option>
                </select>
            </div>
            <button class="btn-primary" type="button" id="btn-confirmar-saldo">Confirmar pago</button>
            <button class="btn-secondary" type="button" id="btn-cancelar-saldo">Cancelar</button>
        `);

        document.getElementById('btn-cancelar-saldo').addEventListener('click', closeModal);

        document.getElementById('btn-confirmar-saldo').addEventListener('click', async () => {
            const metodo = document.getElementById('modal-metodo-saldo').value;
            closeModal();
            try {
                await AdminApi.pagoSaldo(id, null, metodo);
                toast('Saldo registrado, reserva completada.');
                loadReservas();
            } catch (e) { toast(e.data?.error || 'No se pudo registrar el saldo.'); }
        });
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

    // ---------- Reportes ----------
    async function loadReportes(periodo = 'dia') {
        reportesPeriodoActual = periodo;

        try {
            const resumen = await AdminApi.reporteResumen(periodo);
            renderResumenReportes(resumen);
            renderMetodosChart(
                document.getElementById('chart-reportes-metodos'),
                document.getElementById('reportes-metodos-leyenda'),
                resumen.metodos_pago || []
            );
            window._reportesResumen = resumen;
        } catch (e) {
            console.warn('No se pudo cargar el resumen de reportes:', e);
        }

        try {
            const ingresos = await AdminApi.reporteIngresos(periodo);
            renderIngresosChart(
                document.getElementById('chart-reportes-ingresos'),
                ingresos.map((r) => r.etiqueta),
                ingresos.map((r) => Number(r.total))
            );
            window._reportesIngresosRows = ingresos;
        } catch (e) {
            console.warn('Sin datos de ingresos para reportes:', e);
        }

        await loadReportesReservas();
    }

    function renderResumenReportes(k) {
        const cards = [
            { icon: '🚗', label: 'Total de reservas', value: k.total_reservas },
            { icon: '❌', label: 'Reservas canceladas', value: k.reservas_canceladas },
            { icon: '🅿️', label: 'Espacios ocupados', value: k.espacios_ocupados },
            { icon: '🟢', label: 'Espacios disponibles', value: k.espacios_disponibles },
            { icon: '💰', label: 'Ingresos generados', value: money(k.ingresos) },
            { icon: '🧾', label: 'Adelantos recibidos', value: money(k.adelantos) },
            { icon: '⏳', label: 'Pagos pendientes', value: k.pagos_pendientes },
            { icon: '✅', label: 'Pagos completados', value: k.pagos_completados },
        ];
        document.getElementById('reportes-kpi-grid').innerHTML = cards.map((c) => `
            <div class="kpi-card">
                <div class="kpi-top"><span class="kpi-icon">${c.icon}</span>${c.label}</div>
                <div class="kpi-value">${c.value}</div>
            </div>`).join('');
    }

    const METODO_LABEL = { yape: 'Yape', plin: 'Plin', transferencia: 'Transferencia' };
    const PERIODO_LABEL = { dia: 'Día', semana: 'Semana', mes: 'Mes', anio: 'Año' };

    // ---------- Exportación PDF ----------
    function exportarReportesPdf() {
        if (window.jspdf === undefined) {
            toast('No se pudo cargar la librería de PDF.');
            return;
        }
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        const resumen = window._reportesResumen || {};
        const generado = new Date().toLocaleDateString('es-PE');
        const periodoLabel = PERIODO_LABEL[reportesPeriodoActual] || reportesPeriodoActual;

        doc.setFontSize(16);
        doc.text('Reporte — Mi Cochera', 14, 18);
        doc.setFontSize(10);
        doc.text(`Período: ${periodoLabel}  ·  Generado el ${generado}`, 14, 24);

        let y = 32;
        doc.setFontSize(12);
        doc.text('Resumen', 14, y);
        doc.autoTable({
            startY: y + 4,
            head: [['Indicador', 'Valor']],
            body: [
                ['Total de reservas', resumen.total_reservas ?? '—'],
                ['Reservas canceladas', resumen.reservas_canceladas ?? '—'],
                ['Espacios ocupados', resumen.espacios_ocupados ?? '—'],
                ['Espacios disponibles', resumen.espacios_disponibles ?? '—'],
                ['Ingresos generados', money(resumen.ingresos ?? 0)],
                ['Adelantos recibidos', money(resumen.adelantos ?? 0)],
                ['Pagos pendientes', resumen.pagos_pendientes ?? '—'],
                ['Pagos completados', resumen.pagos_completados ?? '—'],
            ],
            theme: 'grid',
            styles: { fontSize: 9 },
        });
        y = doc.lastAutoTable.finalY + 10;

        const metodos = resumen.metodos_pago || [];
        doc.text('Métodos de pago', 14, y);
        doc.autoTable({
            startY: y + 4,
            head: [['Método', 'Total (S/)']],
            body: metodos.map((r) => [METODO_LABEL[r.metodo] || r.metodo, Number(r.total).toFixed(2)]),
            theme: 'grid',
            styles: { fontSize: 9 },
        });
        y = doc.lastAutoTable.finalY + 10;

        const reservas = window._reportesReservasRows || [];
        doc.text('Historial de reservas', 14, y);
        doc.autoTable({
            startY: y + 4,
            head: [['Código', 'Cliente', 'Espacio', 'Ingreso', 'Total (S/)', 'Estado']],
            body: reservas.map((r) => [
                r.codigo,
                r.cliente_nombre,
                r.espacio_codigo,
                r.fecha_hora_inicio,
                Number(r.monto_total ?? 0).toFixed(2),
                ESTADO_LABEL[r.estado] || r.estado,
            ]),
            theme: 'grid',
            styles: { fontSize: 8 },
        });

        doc.save(`reporte_mi_cochera_${reportesPeriodoActual}_${new Date().toISOString().slice(0, 10)}.pdf`);
    }

    async function loadReportesReservas() {
        const fechaFiltro = document.getElementById('reportes-filtro-fecha').value;
        const estado = document.getElementById('reportes-filtro-estado').value;
        const resp = await AdminApi.reservas({
            ...(fechaFiltro ? { fecha: fechaFiltro } : {}),
            ...(estado ? { estado } : {}),
        });
        const rows = resp.data || [];

        document.querySelector('#tabla-reportes-reservas tbody').innerHTML = rows.map((r) => `
            <tr>
                <td>${esc(r.codigo)}</td>
                <td>${esc(r.cliente_nombre)}</td>
                <td>${esc(r.espacio_codigo)}</td>
                <td>${fecha(r.fecha_hora_inicio)}</td>
                <td>${money(r.monto_total)}</td>
                <td>${badge(r.estado)}</td>
            </tr>`).join('');

        // Guardado para que el botón de exportar (CSV y PDF) use exactamente las filas visibles
        window._reportesReservasRows = rows;
    }

    function exportarReportesReservasCsv() {
        const rows = window._reportesReservasRows || [];
        if (!rows.length) {
            toast('No hay datos para exportar con este filtro.');
            return;
        }
        const headers = ['Código', 'Cliente', 'Espacio', 'Ingreso', 'Total', 'Estado'];
        const lineas = [headers.join(',')];
        rows.forEach((r) => {
            lineas.push([
                r.codigo,
                String(r.cliente_nombre ?? '').replaceAll(',', ' '),
                r.espacio_codigo,
                r.fecha_hora_inicio ?? '',
                Number(r.monto_total ?? 0).toFixed(2),
                ESTADO_LABEL[r.estado] || r.estado,
            ].join(','));
        });
        const blob = new Blob(['\uFEFF' + lineas.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `reservas_reporte_${new Date().toISOString().slice(0, 10)}.csv`;
        a.click();
        URL.revokeObjectURL(url);
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
            } catch (e) {
                console.warn('No se pudo actualizar el espacio:', e);
                toast('No se pudo actualizar.');
            }
        }));
    }

    // ---------- Métodos de pago ----------
    async function loadMetodosPago() {
        const rows = await AdminApi.metodosPago();
        const label = { yape: 'Yape', plin: 'Plin', transferencia: 'Transferencia' };
        const subtitulo = { yape: 'Pago móvil con Yape', plin: 'Pago móvil con Plin', transferencia: 'Transferencia bancaria' };
        const monograma = { yape: 'Y', plin: 'P', transferencia: '🏦' };

        document.getElementById('metodos-pago-cards').innerHTML = rows.map((m) => {
            const qrSrc = m.qr_image_path ? `${window.APP_BASE}/storage/${m.qr_image_path}?v=${Date.now()}` : '';
            return `
            <div class="metodo-pago-card metodo-pago-${m.tipo}">
                <div class="metodo-pago-header">
                    <span class="metodo-pago-badge">${monograma[m.tipo]}</span>
                    <div><h3>${label[m.tipo]}</h3><span class="metodo-pago-sub">${subtitulo[m.tipo]}</span></div>
                </div>
                <form data-metodo-form="${m.tipo}">
                    <div class="form-field"><label>Titular</label><input name="titular" value="${esc(m.titular)}"></div>
                    <div class="form-field"><label>Número de cuenta</label><input name="numero_cuenta" value="${esc(m.numero_cuenta)}"></div>
                    <div class="form-field"><label>Banco (opcional)</label><input name="banco" value="${esc(m.banco || '')}"></div>

                    <label class="metodo-pago-qr-label">Código QR</label>
                    <div class="metodo-pago-qr-drop ${qrSrc ? 'has-image' : ''}" data-qr-drop>
                        <img class="metodo-pago-qr-preview" src="${qrSrc}" alt="QR de ${label[m.tipo]}">
                        <div class="metodo-pago-qr-placeholder-icon">📷</div>
                        <span class="metodo-pago-qr-text">Haz clic para subir el código QR</span>
                        <div class="metodo-pago-qr-overlay">Cambiar QR</div>
                        <input type="file" name="qr" accept="image/*" class="metodo-pago-qr-input">
                    </div>

                    <button class="btn-metodo-guardar" type="submit">Guardar cambios</button>
                </form>
            </div>`;
        }).join('');

        // La zona de QR completa es clicable y muestra vista previa instantánea al elegir archivo
        document.querySelectorAll('[data-qr-drop]').forEach((drop) => {
            const input = drop.querySelector('.metodo-pago-qr-input');
            const preview = drop.querySelector('.metodo-pago-qr-preview');
            drop.addEventListener('click', () => input.click());
            input.addEventListener('change', () => {
                if (input.files[0]) {
                    preview.src = URL.createObjectURL(input.files[0]);
                    drop.classList.add('has-image');
                }
            });
        });

        document.querySelectorAll('[data-metodo-form]').forEach((form) => form.addEventListener('submit', async (ev) => {
            ev.preventDefault();
            const tipo = form.dataset.metodoForm;
            const qrInput = form.querySelector('[name="qr"]');
            const datos = Object.fromEntries(new FormData(form).entries());
            delete datos.qr;
            try {
                await AdminApi.actualizarMetodoPago(tipo, datos);
                if (qrInput.files[0]) {
                    const fd = new FormData();
                    fd.append('qr', qrInput.files[0]);
                    await AdminApi.subirQrMetodoPago(tipo, fd);
                }
                toast('Método de pago actualizado.');
                loadMetodosPago();
            } catch (e) {
                console.warn('No se pudo actualizar el método de pago:', e);
                toast('No se pudo actualizar.');
            }
        }));
    }

    // ---------- Cancelaciones ----------
    const CANCELACION_LABEL = { pendiente: 'Pendiente', aprobada: 'Aprobada', fuera_plazo: 'Fuera de plazo', rechazada: 'Rechazada' };

    function celdaAccionCancelacion(c) {
        if (c.estado === 'pendiente') {
            return `
                <button class="btn-sm approve" data-aprobar-cancelacion="${c.id}">Aceptar</button>
                <button class="btn-sm reject" data-rechazar-cancelacion="${c.id}">Rechazar</button>`;
        }
        return c.nota_admin ? esc(c.nota_admin) : '—';
    }

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
                <td>${celdaAccionCancelacion(c)}</td>
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
                if (!r.ok) {
                    const err = new Error(resp?.error || 'No se pudo guardar.');
                    err.data = resp;
                    throw err;
                }
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
    // ---------- Alertas de llegada (independiente de la vista activa) ----------
    const alertasNotificadas = new Set(JSON.parse(sessionStorage.getItem('alertas_llegada_notificadas') || '[]'));

    function marcarAlertaNotificada(key) {
        alertasNotificadas.add(key);
        sessionStorage.setItem('alertas_llegada_notificadas', JSON.stringify([...alertasNotificadas]));
    }

    function reproducirSonidoAlerta() {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            const ctx = new AudioCtx();
            [0, 0.28, 0.56].forEach((delay, i) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.type = 'sine';
                osc.frequency.setValueAtTime(i % 2 === 0 ? 920 : 700, ctx.currentTime + delay);
                gain.gain.setValueAtTime(0.22, ctx.currentTime + delay);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + delay + 0.3);
                osc.start(ctx.currentTime + delay);
                osc.stop(ctx.currentTime + delay + 0.3);
            });
        } catch (e) {
            console.warn('No se pudo reproducir el sonido de alerta:', e);
        }
    }

    function mostrarAlertaLlegada(icono, titulo, mensaje) {
        reproducirSonidoAlerta();
        const overlay = document.createElement('div');
        overlay.className = 'alerta-llegada-overlay';
        overlay.innerHTML = `
            <div class="alerta-llegada-box">
                <button class="alerta-llegada-close" type="button" aria-label="Cerrar">✕</button>
                <div class="alerta-llegada-icon">${icono}</div>
                <h2>${titulo}</h2>
                <p>${mensaje}</p>
            </div>`;
        document.body.appendChild(overlay);
        overlay.querySelector('.alerta-llegada-close').addEventListener('click', () => overlay.remove());
        requestAnimationFrame(() => overlay.classList.add('visible'));
    }

    async function chequearAlertasLlegada() {
        let alertas;
        try {
            alertas = await AdminApi.alertasLlegada();
        } catch (e) {
            console.warn('No se pudo verificar alertas de llegada:', e);
            return;
        }
        alertas.forEach((r) => {
            const key = `${r.tipo}:${r.id}`;
            if (alertasNotificadas.has(key)) return;
            marcarAlertaNotificada(key);
            if (r.tipo === 'por_llegar') {
                mostrarAlertaLlegada(
                    '🚗',
                    '¡Cliente por llegar!',
                    `<strong>${esc(r.cliente_nombre)}</strong> llega pronto al espacio <strong>${esc(r.espacio_codigo)}</strong> (reserva ${esc(r.codigo)}).`
                );
            } else {
                mostrarAlertaLlegada(
                    '✅',
                    '¡Cliente llegó!',
                    `<strong>${esc(r.cliente_nombre)}</strong> llegó al establecimiento — espacio <strong>${esc(r.espacio_codigo)}</strong> (reserva ${esc(r.codigo)}).`
                );
            }
        });
    }

    function iniciarAlertasLlegada() {
        chequearAlertasLlegada();
        setInterval(chequearAlertasLlegada, 20000);
    }

    // ---------- Usuarios ----------
    const ROL_LABEL = { administrador: 'Administrador', operador: 'Operador' };

    async function loadUsuarios() {
        const rows = await AdminApi.usuarios();
        document.querySelector('#tabla-usuarios tbody').innerHTML = rows.map((u) => `
            <tr>
                <td>${esc(u.nombre)}</td>
                <td>${esc(u.email)}</td>
                <td>${ROL_LABEL[u.rol] || u.rol}</td>
                <td>${u.activo ? '<span class="status-badge pago_completo">Activo</span>' : '<span class="status-badge cancelada">Inactivo</span>'}</td>
                <td>${u.ultimo_login ? fecha(u.ultimo_login) : 'Nunca'}</td>
                <td><button class="btn-sm" data-editar-usuario="${u.id}">Editar</button></td>
            </tr>`).join('');

        document.querySelectorAll('[data-editar-usuario]').forEach((b) => {
            const usuario = rows.find((u) => String(u.id) === b.dataset.editarUsuario);
            b.addEventListener('click', () => abrirModalUsuario(usuario));
        });
    }

    function camposUsuarioModal(usuario) {
        const esNuevo = !usuario;
        const campoActivo = esNuevo ? '' : `
            <div class="form-field">
                <label><input type="checkbox" id="modal-usuario-activo" ${usuario.activo ? 'checked' : ''}> Cuenta activa</label>
            </div>`;
        return `
            <div class="form-field"><label>Nombre completo</label><input id="modal-usuario-nombre" value="${esNuevo ? '' : esc(usuario.nombre)}"></div>
            <div class="form-field"><label>Correo</label><input id="modal-usuario-email" type="email" value="${esNuevo ? '' : esc(usuario.email)}" ${esNuevo ? '' : 'disabled'}></div>
            <div class="form-field">
                <label>Rol</label>
                <select id="modal-usuario-rol">
                    <option value="administrador" ${!esNuevo && usuario.rol === 'administrador' ? 'selected' : ''}>Administrador</option>
                    <option value="operador" ${!esNuevo && usuario.rol === 'operador' ? 'selected' : ''}>Operador</option>
                </select>
            </div>
            <div class="form-field">
                <label>${esNuevo ? 'Contraseña' : 'Nueva contraseña (opcional)'}</label>
                <input id="modal-usuario-password" type="password" placeholder="${esNuevo ? '' : 'Dejar en blanco para no cambiar'}">
            </div>
            ${campoActivo}`;
    }

    async function guardarUsuarioModal(usuario) {
        const esNuevo = !usuario;
        const nombre = document.getElementById('modal-usuario-nombre').value.trim();
        const email = document.getElementById('modal-usuario-email').value.trim();
        const rol = document.getElementById('modal-usuario-rol').value;
        const password = document.getElementById('modal-usuario-password').value;

        try {
            if (esNuevo) {
                await AdminApi.crearUsuario({ nombre, email, rol, password });
                toast('Usuario creado.');
            } else {
                const activo = document.getElementById('modal-usuario-activo').checked;
                const datos = { nombre, rol, activo };
                if (password) datos.password = password;
                await AdminApi.actualizarUsuario(usuario.id, datos);
                toast('Usuario actualizado.');
            }
            closeModal();
            loadUsuarios();
        } catch (e) {
            toast(e.data?.error || 'No se pudo guardar.');
        }
    }

    function abrirModalUsuario(usuario) {
        openModal(`
            <h3>${usuario ? 'Editar usuario' : 'Nuevo usuario'} <button class="modal-close" data-close>×</button></h3>
            ${camposUsuarioModal(usuario)}
            <button class="btn-primary" type="button" id="btn-guardar-usuario">Guardar</button>
        `);
        document.getElementById('btn-guardar-usuario').addEventListener('click', () => guardarUsuarioModal(usuario));
    }

    // ---------- Calendario ----------
    const MESES_LABEL = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
    ];
    let calendarioAnio = null;
    let calendarioMes = null;

    async function loadCalendario() {
        if (calendarioAnio === null) {
            const hoy = new Date();
            calendarioAnio = hoy.getFullYear();
            calendarioMes = hoy.getMonth() + 1;
        }
        await renderCalendario();
    }

    function claseBadgeCalendario(total) {
        if (total === 0) return 'sin';
        return total <= 3 ? 'baja' : 'alta';
    }

    function celdaCalendario(dia, fechaStr, totalesPorFecha, hoyStr) {
        const total = totalesPorFecha[fechaStr] || 0;
        const nivel = claseBadgeCalendario(total);
        const esHoy = fechaStr === hoyStr;
        const diaSemana = new Date(fechaStr + 'T00:00:00').getDay();
        const esFinde = diaSemana === 0 || diaSemana === 6;

        const clases = ['calendario-dia', `nivel-${nivel}`];
        if (esHoy) clases.push('calendario-dia-hoy');
        if (esFinde) clases.push('calendario-dia-finde');

        const badge = total > 0 ? `<span class="calendario-dia-badge">${total}</span>` : '';
        const tagHoy = esHoy ? '<span class="calendario-dia-hoy-tag">HOY</span>' : '';

        return `
            <div class="${clases.join(' ')}" data-fecha="${fechaStr}">
                <div class="calendario-dia-top">
                    <span class="calendario-dia-numero">${dia}</span>
                    ${tagHoy}
                </div>
                ${badge}
            </div>`;
    }

    async function renderCalendario() {
        document.getElementById('calendario-mes-label').textContent = `${MESES_LABEL[calendarioMes - 1]} ${calendarioAnio}`;

        let resumen = [];
        try {
            resumen = await AdminApi.calendarioResumen(calendarioAnio, calendarioMes);
        } catch (e) {
            console.warn('No se pudo cargar el resumen del calendario:', e);
        }
        const totalesPorFecha = {};
        resumen.forEach((r) => { totalesPorFecha[r.fecha] = Number(r.total); });

        const primerDia = new Date(calendarioAnio, calendarioMes - 1, 1);
        const diasEnMes = new Date(calendarioAnio, calendarioMes, 0).getDate();
        const diaSemanaInicio = (primerDia.getDay() + 6) % 7;
        const hoyStr = new Date().toISOString().slice(0, 10);

        const encabezados = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom']
            .map((d) => `<div class="calendario-dia-header">${d}</div>`).join('');

        const celdasVacias = Array.from({ length: diaSemanaInicio }, () => '<div class="calendario-dia vacio"></div>').join('');
        const celdasDias = Array.from({ length: diasEnMes }, (_, i) => {
            const dia = i + 1;
            const fechaStr = `${calendarioAnio}-${String(calendarioMes).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
            return celdaCalendario(dia, fechaStr, totalesPorFecha, hoyStr);
        }).join('');

        document.getElementById('calendario-grid').innerHTML = encabezados + celdasVacias + celdasDias;

        document.querySelectorAll('.calendario-dia[data-fecha]').forEach((celda) => {
            celda.addEventListener('click', () => abrirDiaCalendario(celda.dataset.fecha));
        });
    }

    function cambiarMesCalendario(delta) {
        calendarioMes += delta;
        if (calendarioMes < 1) { calendarioMes = 12; calendarioAnio -= 1; }
        if (calendarioMes > 12) { calendarioMes = 1; calendarioAnio += 1; }
        renderCalendario();
    }

    async function abrirDiaCalendario(fechaSeleccionada) {
        const resp = await AdminApi.reservas({ fecha: fechaSeleccionada });
        const filas = (resp.data || []).map((r) => `
            <tr>
                <td>${esc(r.codigo)}</td>
                <td>${esc(r.cliente_nombre)}</td>
                <td>${esc(r.espacio_codigo)}</td>
                <td>${fecha(r.fecha_hora_inicio)}</td>
                <td>${money(r.monto_total)}</td>
                <td>${badge(r.estado)}</td>
            </tr>`).join('');

        openModal(`
            <h3>Reservas del ${fechaSeleccionada} <button class="modal-close" data-close>×</button></h3>
            <div class="table-wrap"><table>
                <thead><tr><th>Código</th><th>Cliente</th><th>Espacio</th><th>Ingreso</th><th>Total</th><th>Estado</th></tr></thead>
                <tbody>${filas || '<tr><td colspan="6">Sin reservas ese día.</td></tr>'}</tbody>
            </table></div>
        `);
    }

    // ---------- Clientes ----------
    const CLIENTE_AVATAR_COLORS = ['#f59e0b', '#9333ea', '#06b6ad', '#2563eb', '#e11d48', '#16a34a'];

    function inicialesCliente(nombre) {
        const partes = String(nombre ?? '').trim().split(/\s+/).filter(Boolean);
        if (!partes.length) return '?';
        const primera = partes[0][0] ?? '';
        const segunda = partes.length > 1 ? partes[1][0] ?? '' : '';
        return (primera + segunda).toUpperCase();
    }

    function colorCliente(texto) {
        let hash = 0;
        for (let i = 0; i < texto.length; i++) {
            hash = texto.charCodeAt(i) + ((hash << 5) - hash);
        }
        return CLIENTE_AVATAR_COLORS[Math.abs(hash) % CLIENTE_AVATAR_COLORS.length];
    }

    function filaCliente(c) {
        const badgeReservas = c.total_reservas >= 5
            ? `<span class="cliente-reservas-badge frecuente">${c.total_reservas}</span>`
            : `<span class="cliente-reservas-badge">${c.total_reservas}</span>`;
        const badgeCanceladas = c.reservas_canceladas > 0
            ? `<span class="cliente-canceladas-badge">${c.reservas_canceladas}</span>`
            : '<span class="muted">0</span>';

        return `
            <tr>
                <td>
                    <div class="cliente-cell">
                        <span class="cliente-avatar" style="background:${colorCliente(c.cliente_celular)}">${inicialesCliente(c.cliente_nombre)}</span>
                        <span class="cliente-nombre">${esc(c.cliente_nombre)}</span>
                    </div>
                </td>
                <td class="cliente-celular">${esc(c.cliente_celular)}</td>
                <td>${badgeReservas}</td>
                <td>${badgeCanceladas}</td>
                <td class="cliente-gastado">${money(c.total_gastado)}</td>
                <td>${c.ultima_reserva ? fecha(c.ultima_reserva) : '—'}</td>
                <td><button class="btn-sm" data-ver-historial="${esc(c.cliente_celular)}">Ver historial</button></td>
            </tr>`;
    }

    async function loadClientes() {
        const busqueda = document.getElementById('filtro-busqueda-clientes').value.trim();
        const rows = await AdminApi.clientes(busqueda || null);
        document.querySelector('#tabla-clientes tbody').innerHTML = rows.map(filaCliente).join('');

        document.querySelectorAll('[data-ver-historial]').forEach((b) => {
            b.addEventListener('click', () => abrirHistorialCliente(b.dataset.verHistorial));
        });
    }

    function filaHistorialCliente(r) {
        return `
            <tr>
                <td>${esc(r.codigo)}</td>
                <td>${esc(r.espacio_codigo)}</td>
                <td>${fecha(r.fecha_hora_inicio)}</td>
                <td>${money(r.monto_total)}</td>
                <td>${badge(r.estado)}</td>
            </tr>`;
    }

    async function abrirHistorialCliente(celular) {
        const reservas = await AdminApi.historialCliente(celular);
        const filas = reservas.map(filaHistorialCliente).join('');

        openModal(`
            <h3>Historial de ${esc(celular)} <button class="modal-close" data-close>×</button></h3>
            <div class="table-wrap"><table>
                <thead><tr><th>Código</th><th>Espacio</th><th>Ingreso</th><th>Total</th><th>Estado</th></tr></thead>
                <tbody>${filas || '<tr><td colspan="5">Sin reservas registradas.</td></tr>'}</tbody>
            </table></div>
        `);
    }

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
        } catch (e) {
            console.warn('No se pudo verificar la sesión de administrador:', e);
            return;
        }

        initNav();
        iniciarAlertasLlegada();
        document.getElementById('btn-refrescar-dashboard').addEventListener('click', loadDashboard);
        document.getElementById('btn-filtrar-reservas').addEventListener('click', loadReservas);
        document.getElementById('btn-filtrar-pagos').addEventListener('click', loadPagos);
        document.getElementById('btn-filtrar-cancelaciones').addEventListener('click', loadCancelaciones);
        document.getElementById('btn-nuevo-usuario').addEventListener('click', () => abrirModalUsuario(null));
        document.getElementById('btn-filtrar-clientes').addEventListener('click', loadClientes);
        document.getElementById('btn-mes-anterior').addEventListener('click', () => cambiarMesCalendario(-1));
        document.getElementById('btn-mes-siguiente').addEventListener('click', () => cambiarMesCalendario(1));

        document.getElementById('btn-filtrar-reportes-reservas').addEventListener('click', loadReportesReservas);
        document.getElementById('btn-exportar-reportes-reservas').addEventListener('click', exportarReportesReservasCsv);
        document.getElementById('btn-exportar-reportes-pdf').addEventListener('click', exportarReportesPdf);
        function activarTabPeriodo(btn) {
            document.querySelectorAll('#reportes-periodo-tabs [data-periodo]').forEach((b) => b.classList.remove('tab-active'));
            btn.classList.add('tab-active');
            loadReportes(btn.dataset.periodo);
        }
        document.querySelectorAll('#reportes-periodo-tabs [data-periodo]').forEach((btn) => {
            btn.addEventListener('click', () => activarTabPeriodo(btn));
        });
        loadDashboard();
    }

    return { init };
})();

document.addEventListener('DOMContentLoaded', AdminDashboard.init);