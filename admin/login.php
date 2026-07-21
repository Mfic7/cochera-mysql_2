<?php
require __DIR__ . '/../src/Autoload.php';
use App\Auth\AdminAuth;

$config = require __DIR__ . '/../config/config.php';
$basePath = $config['app_base_path'];

if (AdminAuth::check()) {
    header('Location: ' . $basePath . '/admin/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi Cochera — Panel de administración</title>
<link rel="stylesheet" href="<?= $basePath ?>/assets/css/admin.css">
</head>
<body class="login-body">
<div class="login-card">
    <div class="brand-badge">P</div>
    <h1>Mi Cochera</h1>
    <p class="muted">Panel de administración</p>
    <form id="login-form">
        <label>Correo electrónico<input type="email" id="email" required autocomplete="username"></label>
        <label>Contraseña<input type="password" id="password" required autocomplete="current-password"></label>
        <p class="form-error" id="form-error" hidden></p>
        <button type="submit" class="btn-primary">Ingresar</button>
    </form>
</div>
<script>window.APP_BASE = <?= json_encode($basePath) ?>;</script>
<script src="<?= $basePath ?>/assets/js/admin/api.js"></script>
<script src="<?= $basePath ?>/assets/js/admin/login.js"></script>
</body>
</html>
