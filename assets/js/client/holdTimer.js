const HoldTimer = (() => {
    let intervalId = null;

    function start(expiraEnIso, elapsedEl, onExpire) {
        stop();
        elapsedEl.hidden = false;
        const tick = () => {
            const remainingMs = new Date(expiraEnIso).getTime() - Date.now();
            if (remainingMs <= 0) {
                elapsedEl.textContent = 'El bloqueo del espacio expiró.';
                stop();
                if (typeof onExpire === 'function') onExpire();
                return;
            }
            const m = Math.floor(remainingMs / 60000);
            const s = Math.floor((remainingMs % 60000) / 1000);
            elapsedEl.textContent = `⏳ Espacio bloqueado — expira en ${m}:${String(s).padStart(2, '0')}`;
        };
        tick();
        intervalId = setInterval(tick, 1000);
    }

    function stop() {
        if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
        }
    }

    return { start, stop };
})();
