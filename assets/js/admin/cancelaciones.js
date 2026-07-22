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
                <td>${esc(c.numero_operacion || '—')}</td>
                <td>${c.revisado ? 'Sí' : 'No'}</td>
                <td>${esc(c.created_at)}</td>
                <td>${c.comprobante_path ? `<a class="btn-sm" href="${window.APP_BASE}/storage/${encodeURIComponent(c.comprobante_path)}" target="_blank">Ver</a>` : '—'}</td>
                <td>${c.revisado ? '—' : `<button class="btn-sm approve" data-revisar="${c.id}">Marcar revisado</button>`}</td>
            </tr>`).join('');

        document.querySelectorAll('[data-revisar]').forEach((b) => b.addEventListener('click', () => marcarRevisado(b.dataset.revisar)));
    }

    async function marcarRevisado(id) {
        await AdminApi.revisarCancelacion(id);
        loadCancelaciones();
    }

    function verComprobante(path) {
        const url = encodeURI(`${window.APP_BASE}/storage/${path}`);
        openModal(`
            <h3>Comprobante de cancelación <button class="modal-close" data-close>×</button></h3>
            <div class="modal-comprobante">
                ${path.toLowerCase().endsWith('.pdf')
                    ? `<a href="${url}" target="_blank">Abrir PDF de comprobante</a>`
                    : `<img src="${url}" alt="Comprobante de cancelación">`}
            </div>`);
    }

    function openModal(html) {
        const root = document.getElementById('modal-root');
        if (!root) return;
        root.innerHTML = `<div class="modal-overlay" data-overlay><div class="modal">${html}</div></div>`;
        const overlay = root.querySelector('[data-overlay]');
        if (overlay) {
            overlay.addEventListener('click', (e) => { if (e.target === overlay) closeModal(); });
        }
        const closeBtn = root.querySelector('[data-close]');
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
    }

    function closeModal() {
        const root = document.getElementById('modal-root');
        if (root) root.innerHTML = '';
    }

    async function loadCancelaciones() {
        const rows = await AdminApi.cancelaciones();
        render(rows);
    }

    return { loadCancelaciones };
})();
