(function () {
    const ETIQUETAS_ESTADO = {
        pendiente_pago: 'Pendiente de pago',
        en_validacion: 'En validación',
        adelanto_pagado: 'Adelanto pagado',
        pago_completo: 'Pago completo',
        cancelada: 'Cancelada',
        vencida: 'Vencida',
    };

    let paginaActual = 1;

    // ===== Ingresos (semana / mes / año) =====
    async function cargarIngresos(agrupacion) {
        const canvas = document.getElementById('chart-ingresos');
        try {
            const rows = await AdminApi.reporteIngresos(agrupacion);
            const labels = rows.map((r) => r.etiqueta);
            const data = rows.map((r) => Number(r.total));
            renderIngresosChart(canvas, labels, data);
        } catch (e) {
            console.error('Error cargando reporte de ingresos:', e);
        }
    }

    document.getElementById('ingresos-tabs').addEventListener('click', (ev) => {
        const btn = ev.target.closest('.tab-btn');
        if (!btn) return;
        document.querySelectorAll('#ingresos-tabs .tab-btn').forEach((b) => b.classList.remove('active'));
        btn.classList.add('active');
        cargarIngresos(btn.dataset.agrupacion);
    });

    // ===== Métodos de pago =====
    async function cargarMetodosPago() {
        const desde = document.getElementById('metodos-desde').value || primerDiaDelMes();
        const hasta = document.getElementById('metodos-hasta').value || hoy();
        const canvas = document.getElementById('chart-metodos');
        const legend = document.getElementById('metodos-legend');
        try {
            const rows = await AdminApi.reporteMetodosPago(desde, hasta);
            renderMetodosChart(canvas, legend, rows);
        } catch (e) {
            console.error('Error cargando métodos de pago:', e);
        }
    }
    document.getElementById('metodos-filtrar').addEventListener('click', cargarMetodosPago);

    // ===== Reservas =====
    async function cargarReservas(page = 1) {
        paginaActual = page;
        const fecha = document.getElementById('reservas-fecha').value || null;
        const estado = document.getElementById('reservas-estado').value || null;
        const tbody = document.getElementById('tabla-reservas-body');
        tbody.innerHTML = '<tr><td colspan="6">Cargando…</td></tr>';

        try {
            const params = { page };
            if (fecha) params.fecha = fecha;
            if (estado) params.estado = estado;
            const resp = await AdminApi.reservas(params);

            // Ajusta esto según la forma real que devuelva Reserva::listar().
            // Formatos comunes: { data: [...], total, page, per_page } o directamente un array.
            const filas = Array.isArray(resp) ? resp : (resp.data ?? []);
            const total = resp.total ?? filas.length;
            const perPage = resp.per_page ?? 20;

            renderTablaReservas(filas);
            renderPaginacion(total, perPage, page);
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="6">Error al cargar reservas: ${e.message}</td></tr>`;
        }
    }

    function renderTablaReservas(filas) {
        const tbody = document.getElementById('tabla-reservas-body');
        if (!filas.length) {
            tbody.innerHTML = '<tr><td colspan="6">Sin resultados para este filtro.</td></tr>';
            return;
        }
        tbody.innerHTML = filas.map((r) => `
            <tr>
                <td>${r.id}</td>
                <td>${r.created_at ?? ''}</td>
                <td>${r.cliente_nombre ?? r.cliente ?? '—'}</td>
                <td>${r.espacio_codigo ?? r.espacio ?? '—'}</td>
                <td>${ETIQUETAS_ESTADO[r.estado] ?? r.estado}</td>
                <td>S/ ${Number(r.monto ?? 0).toFixed(2)}</td>
            </tr>
        `).join('');
    }

    function renderPaginacion(total, perPage, page) {
        const totalPaginas = Math.max(1, Math.ceil(total / perPage));
        const cont = document.getElementById('reservas-paginacion');
        let html = '';
        for (let p = 1; p <= totalPaginas; p++) {
            html += `<button class="btn-page ${p === page ? 'active' : ''}" data-page="${p}">${p}</button>`;
        }
        cont.innerHTML = html;
        cont.querySelectorAll('.btn-page').forEach((btn) => {
            btn.addEventListener('click', () => cargarReservas(Number(btn.dataset.page)));
        });
    }

    document.getElementById('reservas-filtrar').addEventListener('click', () => cargarReservas(1));

    // ===== Exportar CSV =====
    document.getElementById('reservas-exportar').addEventListener('click', async () => {
        const fecha = document.getElementById('reservas-fecha').value || null;
        const estado = document.getElementById('reservas-estado').value || null;

        // Trae todas las páginas del filtro actual para exportar el set completo.
        let page = 1;
        let todas = [];
        const perPage = 20;
        while (true) {
            const params = { page, per_page: perPage };
            if (fecha) params.fecha = fecha;
            if (estado) params.estado = estado;
            const resp = await AdminApi.reservas(params);
            const filas = Array.isArray(resp) ? resp : (resp.data ?? []);
            if (!filas.length) break;
            todas = todas.concat(filas);
            const total = resp.total ?? filas.length;
            if (page * perPage >= total) break;
            page++;
        }

        descargarCsv(todas);
    });

    function descargarCsv(filas) {
        if (!filas.length) {
            alert('No hay datos para exportar con este filtro.');
            return;
        }
        const headers = ['ID', 'Fecha', 'Cliente', 'Espacio', 'Estado', 'Monto'];
        const lineas = [headers.join(',')];
        filas.forEach((r) => {
            const fila = [
                r.id,
                r.created_at ?? '',
                (r.cliente_nombre ?? r.cliente ?? '').replace(/,/g, ' '),
                r.espacio_codigo ?? r.espacio ?? '',
                ETIQUETAS_ESTADO[r.estado] ?? r.estado,
                Number(r.monto ?? 0).toFixed(2),
            ];
            lineas.push(fila.join(','));
        });
        const blob = new Blob(['\uFEFF' + lineas.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `reservas_${hoy()}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    }

    // ===== Helpers de fecha =====
    function hoy() {
        return new Date().toISOString().slice(0, 10);
    }
    function primerDiaDelMes() {
        const d = new Date();
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`;
    }

    // ===== Init =====
    document.getElementById('metodos-desde').value = primerDiaDelMes();
    document.getElementById('metodos-hasta').value = hoy();
    cargarIngresos('semana');
    cargarMetodosPago();
    cargarReservas(1);
})();