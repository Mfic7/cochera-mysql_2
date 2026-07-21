<?php
require __DIR__ . '/../src/Autoload.php';
use App\Auth\AdminAuth;

$config = require __DIR__ . '/../config/config.php';

AdminAuth::logout();
header('Location: ' . $config['app_base_path'] . '/admin/login.php');
exit;
