const AdminCancelaciones = (() => {
    function esc(s) {
        return String(s ?? '').replace(/[&<>'"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

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
                <td>${esc(c.numero_operacion || 'ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â')}</td>
                <td>${c.revisado ? 'SÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­' : 'No'}</td>
                <td>${esc(c.created_at)}</td>
                <td>${c.comprobante_path ? `<a class="btn-sm" href="${window.APP_BASE}/storage/${encodeURIComponent(c.comprobante_path)}" target="_blank">Ver</a>` : 'ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â'}</td>
                <td>${c.revisado ? 'ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â' : `<button class="btn-sm approve" data-revisar="${c.id}">Marcar revisado</button>`}</td>
            </tr>`).join('');

        document.querySelectorAll('[data-revisar]').forEach((b) => b.addEventListener('click', () => marcarRevisado(b.dataset.revisar)));
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
