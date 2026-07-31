<?php
// Copiar a config.php y ajustar segÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âºn el entorno. config.php estÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡ bloqueado por .htaccess
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
    // Ej: si accedes por http://localhost/cochera-mysql_2/ -> '/cochera-mysql_2'
    // Con ngrok apuntando directo a la raÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­z del vhost, dejar '' (vacÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­o).
    'app_base_path' => '/cochera-mysql_2',
    'session_name' => 'micochera_admin',
];
