<?php
// Copiar a config.php y ajustar según el entorno. config.php está bloqueado por .htaccess
// y no se sube a control de versiones (agregar a .gitignore si se usa git).

return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'mi_cochera',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    // Ruta base de la app tal como se ve desde el navegador, sin slash final.
    // Ej: si accedes por http://localhost/Coherv2/ -> '/Coherv2'
    // Con ngrok apuntando directo a la raíz del vhost, dejar '' (vacío).
    'app_base_path' => '/Coherv2',
    'session_name' => 'micochera_admin',
];
