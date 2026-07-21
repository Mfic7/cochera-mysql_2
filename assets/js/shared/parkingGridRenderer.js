/**
 * Renderiza un grid de espacios en `container` a partir de espacios[] con {codigo, estado_ventana}.
 * `selectedId` marca visualmente el espacio elegido (estado 'seleccionado', puramente de UI).
 * `onSelect(espacio)` se invoca solo si el espacio está en estado 'disponible'.
 */
function renderParkingGrid(container, espacios, { selectedId = null, onSelect = null } = {}) {
    container.innerHTML = '';
    if (!espacios || espacios.length === 0) {
        container.innerHTML = '<p class="loading">No hay espacios configurados.</p>';
        return;
    }
    espacios.forEach((espacio) => {
        const box = document.createElement('div');
        const estado = espacio.id === selectedId ? 'seleccionado' : espacio.estado_ventana;
        box.className = 'space ' + estado;
        box.textContent = espacio.codigo;
        box.dataset.id = espacio.id;
        if (espacio.estado_ventana === 'disponible' && typeof onSelect === 'function') {
            box.addEventListener('click', () => onSelect(espacio));
        }
        container.appendChild(box);
    });
}
