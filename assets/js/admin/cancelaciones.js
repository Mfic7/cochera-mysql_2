const AdminCancelaciones = (() => {
    function render(rows) {
        const table = document.getElementById('tabla-cancelaciones');
        if (!table) return;
        table.querySelector('tbody').innerHTML = rows.map((c) => `
            <tr>
                <td>${esc(c.reserva_codigo)}</td>
                <td>${esc(c.espacio_codigo)}</td>
                <td>${esc(c.cliente_nombre)}</td>
                <td>${esc(c.cliente_celular)}</td>
                <td>${esc(c.motivo)}</td>
                <td>${esc(c.numero_operacion || '—')}</td>
                <td>${c.revisado ? 'Sí' : 'No'}</td>
                <td>${c.created_at}</td>
                <td>${c.comprobante_path ? `<button class="btn-sm" data-ver-comprobante="${c.id}">Ver</button>` : '—'}</td>
                <td>${c.revisado ? '—' : `<button class="btn-sm approve" data-revisar="${c.id}">Marcar revisado</button>`}</td>
            </tr>`).join('');

        document.querySelectorAll('[data-ver-comprobante]').forEach((b) => b.addEventListener('click', () => verComprobante(b.dataset.verComprobante)));
        document.querySelectorAll('[data-revisar]').forEach((b) => b.addEventListener('click', () => marcarRevisado(b.dataset.revisar)));
    }

    function verComprobante(id) {
        openModal(`
            <h3>Comprobante de cancelación <button class="modal-close" data-close>×</button></h3>
            <img src="${window.APP_BASE}/storage/cancelaciones/${id}.jpg" alt="Comprobante de cancelación">`);
    }

    async function marcarRevisado(id) {
        await AdminApi.revisarCancelacion(id);
        loadCancelaciones();
    }

    async function loadCancelaciones() {
        const rows = await AdminApi.cancelaciones();
        render(rows);
    }

    return { loadCancelaciones };
})();
