/**
 * Lógica de la sección "Solicitar cancelación" del perfil del cliente.
 * Requiere que exista una variable global con los datos de la reserva activa:
 *   window.reservaActual = { id, token, codigo, fecha_hora_inicio, estado }
 * Ajusta el nombre "window.reservaActual" según cómo la guardes en reservationForm.js/main.js.
 */
(function () {
  const MINUTOS_LIMITE = 20;

  const panel = document.getElementById('panel-cancelacion');
  const codigoEl = document.getElementById('cancelacion-codigo');
  const motivoEl = document.getElementById('cancelacion-motivo');
  const numOperacionEl = document.getElementById('cancelacion-numero-operacion');
  const comprobanteEl = document.getElementById('cancelacion-comprobante');
  const btnEnviar = document.getElementById('btn-solicitar-cancelacion');
  const bannerError = document.getElementById('cancelacion-banner-error');
  const btnAbrirCancelacion = document.getElementById('btn-abrir-cancelacion');

  function mostrarError(msg) {
    bannerError.textContent = msg;
    bannerError.hidden = false;
  }

  function ocultarError() {
    bannerError.hidden = true;
    bannerError.textContent = '';
  }

  function minutosRestantes(reserva) {
    const inicioTs = new Date(reserva.fecha_hora_inicio.replace(' ', 'T')).getTime();
    return (inicioTs - Date.now()) / 60000;
  }

  function actualizarBloqueo() {
    const reserva = window.reservaActual;
    if (!reserva) return;

    const mins = minutosRestantes(reserva);
    const bloqueado = mins < MINUTOS_LIMITE;

    btnEnviar.disabled = bloqueado;
    [motivoEl, numOperacionEl, comprobanteEl].forEach((el) => (el.disabled = bloqueado));

    if (bloqueado) {
      mostrarError(
        `⛔ El plazo para cancelar venció. Solo se permite cancelar hasta ${MINUTOS_LIMITE} minutos antes de tu hora de reserva.`
      );
    } else {
      ocultarError();
    }
  }

  async function enviarSolicitud() {
    const reserva = window.reservaActual;
    if (!reserva) return;

    ocultarError();

    if (minutosRestantes(reserva) < MINUTOS_LIMITE) {
      actualizarBloqueo();
      return;
    }

    const motivo = motivoEl.value.trim();
    if (motivo === '') {
      mostrarError('Debes indicar el motivo de la cancelación.');
      return;
    }
    if (!comprobanteEl.files.length) {
      mostrarError('Debes adjuntar tu comprobante (imagen o PDF).');
      return;
    }

    btnEnviar.disabled = true;
    btnEnviar.textContent = 'Enviando...';

    const formData = new FormData();
    formData.append('token', reserva.token);
    formData.append('motivo', motivo);
    formData.append('numero_operacion', numOperacionEl.value.trim());
    formData.append('comprobante', comprobanteEl.files[0]);

    try {
      const resp = await fetch(`${window.APP_BASE}/api/reservas/${reserva.id}/cancelacion`, {
        method: 'POST',
        body: formData,
      });
      const data = await resp.json();

      if (!resp.ok) {
        throw new Error(data.error || data.message || 'No se pudo enviar la solicitud.');
      }

      btnEnviar.textContent = 'Solicitud enviada ✓';
      [motivoEl, numOperacionEl, comprobanteEl].forEach((el) => (el.disabled = true));
    } catch (err) {
      mostrarError(err.message);
      btnEnviar.disabled = false;
      btnEnviar.textContent = 'Enviar solicitud de cancelación';
    }
  }

  /**
   * Llamar esta función cuando la reserva del cliente ya esté cargada
   * (por ejemplo, al final de reservationForm.js, después de obtener la reserva del backend).
   */
  window.initPanelCancelacion = function (reserva) {
    window.reservaActual = reserva;
    codigoEl.textContent = reserva.codigo;
    panel.hidden = false;
    if (btnAbrirCancelacion) btnAbrirCancelacion.hidden = false;

    actualizarBloqueo();
    setInterval(actualizarBloqueo, 30000); // revisa cada 30s por si se acaba el plazo
  };

  btnEnviar.addEventListener('click', enviarSolicitud);
  if (btnAbrirCancelacion) {
    btnAbrirCancelacion.addEventListener('click', () => {
      panel.hidden = false;
      panel.scrollIntoView({ behavior: 'smooth' });
    });
  }
})();