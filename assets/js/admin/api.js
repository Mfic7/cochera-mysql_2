const AdminApi = (() => {
    const base = window.APP_BASE + '/api/index.php';
    let csrfToken = sessionStorage.getItem('csrf_token') || null;

    function setCsrfToken(token) {
        csrfToken = token;
        sessionStorage.setItem('csrf_token', token);
    }

    async function request(path, options = {}) {
        const method = (options.method || 'GET').toUpperCase();
        const headers = { ...(options.headers || {}) };
        if (!(options.body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
        }
        if (['POST', 'PATCH', 'DELETE'].includes(method) && csrfToken) {
            headers['X-CSRF-Token'] = csrfToken;
        }
        const res = await fetch(base + path, { ...options, method, headers, credentials: 'same-origin' });
        let data = null;
        try { data = await res.json(); } catch (e) { /* sin cuerpo */ }
        if (res.status === 401) {
            window.location.href = window.APP_BASE + '/admin/login.php';
            return null;
        }
        if (!res.ok) {
            const err = new Error((data && data.error) || 'Error de red');
            err.status = res.status;
            err.data = data;
            throw err;
        }
        return data;
    }

    return {
        setCsrfToken,
        login: (email, password) => request('/admin/auth/login', { method: 'POST', body: JSON.stringify({ email, password }) }),
        logout: () => request('/admin/auth/logout', { method: 'POST' }),
        me: () => request('/admin/auth/me'),

        kpis: (desde, hasta) => request(`/admin/dashboard/kpis?desde=${desde}&hasta=${hasta}`),
        ocupacion: () => request('/admin/espacios/ocupacion'),
        actividad: (limit = 15) => request(`/admin/actividad?limit=${limit}`),

        reservas: (params = {}) => request('/admin/reservas?' + new URLSearchParams(params).toString()),
        reserva: (id) => request(`/admin/reservas/${id}`),
        actualizarEstadoReserva: (id, estado, nota) => request(`/admin/reservas/${id}/estado`, { method: 'PATCH', body: JSON.stringify({ estado, nota }) }),
        pagoSaldo: (id, monto, metodo) => request(`/admin/reservas/${id}/pago-saldo`, { method: 'POST', body: JSON.stringify({ monto, metodo }) }),

        pagos: (estado) => request('/admin/pagos' + (estado ? `?estado=${estado}` : '')),
        revisarPago: (id, accion, motivo) => request(`/admin/pagos/${id}`, { method: 'PATCH', body: JSON.stringify({ accion, motivo }) }),
        comprobanteUrl: (id) => `${base}/admin/pagos/${id}/comprobante`,
        cancelaciones: () => request('/admin/cancelaciones'),
        revisarCancelacion: (id) => request(`/admin/cancelaciones/${id}/revisado`, { method: 'PATCH' }),

        espacios: () => request('/admin/espacios'),
        crearEspacio: (data) => request('/admin/espacios', { method: 'POST', body: JSON.stringify(data) }),
        actualizarEspacio: (id, data) => request(`/admin/espacios/${id}`, { method: 'PATCH', body: JSON.stringify(data) }),
        eliminarEspacio: (id) => request(`/admin/espacios/${id}`, { method: 'DELETE' }),

        metodosPago: () => request('/admin/metodos-pago'),
        actualizarMetodoPago: (tipo, data) => request(`/admin/metodos-pago/${tipo}`, { method: 'PATCH', body: JSON.stringify(data) }),

        configuracion: () => request('/admin/configuracion'),
        actualizarConfiguracion: (data) => request('/admin/configuracion', { method: 'PATCH', body: JSON.stringify(data) }),

        reporteIngresos: (agrupacion) => request(`/admin/reportes/ingresos?agrupacion=${agrupacion}`),
        reporteMetodosPago: (desde, hasta) => request(`/admin/reportes/metodos-pago?desde=${desde}&hasta=${hasta}`),
    };
})();
