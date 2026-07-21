const Api = (() => {
    const base = window.APP_BASE + '/api/index.php';

    async function request(path, options = {}) {
        const res = await fetch(base + path, {
            ...options,
            credentials: 'same-origin',
            headers: options.body instanceof FormData
                ? options.headers
                : { 'Content-Type': 'application/json', ...(options.headers || {}) },
        });
        let data = null;
        try { data = await res.json(); } catch (e) { /* respuesta sin cuerpo */ }
        if (!res.ok) {
            const err = new Error((data && data.error) || 'Error de red');
            err.status = res.status;
            err.data = data;
            throw err;
        }
        return data;
    }

    return {
        config: () => request('/config'),
        metodosPago: () => request('/metodos-pago'),
        disponibilidad: (fecha, hora, horas) =>
            request(`/espacios/disponibilidad?fecha=${fecha}&hora_inicio=${hora}&horas=${horas}`),
        crearReserva: (payload) => request('/reservas', { method: 'POST', body: JSON.stringify(payload) }),
        obtenerReserva: (id, token) => request(`/reservas/${id}?token=${token}`),
        subirComprobante: (id, formData) => request(`/reservas/${id}/comprobante`, { method: 'POST', body: formData }),
    };
})();
