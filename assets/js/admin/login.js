document.getElementById('login-form').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const errorEl = document.getElementById('form-error');
    errorEl.hidden = true;

    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;

    try {
        const admin = await AdminApi.login(email, password);
        AdminApi.setCsrfToken(admin.csrf_token);
        window.location.href = window.APP_BASE + '/admin/dashboard.php';
    } catch (e) {
        errorEl.textContent = e.status === 401 ? 'Correo o contraseña incorrectos.' : 'No se pudo iniciar sesión.';
        errorEl.hidden = false;
    }
});
