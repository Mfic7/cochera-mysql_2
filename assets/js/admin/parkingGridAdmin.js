function renderAdminParkingGrid(container, espacios) {
    container.innerHTML = '';
    espacios.forEach((espacio) => {
        const box = document.createElement('div');
        box.className = 'space ' + espacio.estado_ventana;
        box.textContent = espacio.codigo;
        box.title = espacio.estado_ventana;
        container.appendChild(box);
    });
}
