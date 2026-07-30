/**
 * VehicleAnimation
 * Dibuja un auto que avanza por una pista según cuánto falta para la hora
 * de la reserva. El "viaje" va desde que se creó la reserva (created_at)
 * hasta la hora de ingreso (fecha_hora_inicio). No agrega polling propio:
 * se re-renderiza cada vez que ReservationForm actualiza el estado (cada
 * ~5s mientras el polling existente esté activo).
 */
const VehicleAnimation = (() => {
    const TRACK_START = 0;
    const TRACK_END = 270; // debe calzar con el viewBox del SVG en index.php

    // Mismo criterio que reservationForm.js: interpretar la fecha como hora
    // local, ignorando cualquier sufijo de zona horaria del backend.
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

    function calcularProgreso(reserva) {
        const inicio = parseFechaLocal(reserva.created_at);
        const llegada = parseFechaLocal(reserva.fecha_hora_inicio);
        if (!inicio || !llegada) return null;

        const total = llegada.getTime() - inicio.getTime();
        if (total <= 0) return 1;

        const transcurrido = Date.now() - inicio.getTime();
        return Math.min(1, Math.max(0, transcurrido / total));
    }

    /**
     * @param {HTMLElement} containerEl - el div .vehicle-progress (se muestra/oculta)
     * @param {SVGGElement} iconGroupEl - el <g> que se desplaza (transform translate)
     * @param {HTMLElement} textEl - texto de estado ("en camino" / "llegó")
     * @param {HTMLElement} etaEl - texto de minutos restantes
     * @param {object|null} reserva - state.reserva actual
     */
    function render(containerEl, iconGroupEl, textEl, etaEl, reserva) {
        if (!reserva || ['cancelada', 'vencida'].includes(reserva.estado)) {
            containerEl.hidden = true;
            return;
        }

        const progreso = calcularProgreso(reserva);
        if (progreso === null) {
            containerEl.hidden = true;
            return;
        }

        containerEl.hidden = false;
        const x = TRACK_START + (TRACK_END - TRACK_START) * progreso;
        iconGroupEl.setAttribute('transform', `translate(${x.toFixed(1)},0)`);

        const yaLlego = progreso >= 1 || reserva.estado === 'pago_completo';
        if (yaLlego) {
            textEl.textContent = '✅ Vehículo en el establecimiento';
            etaEl.textContent = '';
        } else {
            textEl.textContent = '🚗 Tu vehículo está en camino…';
            const llegada = parseFechaLocal(reserva.fecha_hora_inicio);
            const minutosRestantes = Math.max(0, Math.round((llegada.getTime() - Date.now()) / 60000));
            etaEl.textContent = minutosRestantes > 0 ? `Llega en ~${minutosRestantes} min` : 'Llegando…';
        }
    }

    /**
     * Indica si, según el progreso calculado, el vehículo ya llegó al destino.
     * Se reutiliza tanto para pintar el texto de la animación como para que
     * reservationForm.js decida cuándo disparar la notificación grande.
     */
    function haLlegado(reserva) {
        if (!reserva) return false;
        if (reserva.estado === 'pago_completo') return true;
        const progreso = calcularProgreso(reserva);
        return progreso !== null && progreso >= 1;
    }

    return { render, haLlegado };
})();