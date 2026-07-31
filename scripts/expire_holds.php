<?php
// Backstop opcional: expira holds vencidos aunque no haya trÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡fico entrante.
// La app ya se autocorrige en cada lectura de disponibilidad (ver EspacioAvailabilityService),
// asÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­ que ESTE SCRIPT NO ES NECESARIO PARA EL FUNCIONAMIENTO CORRECTO.
// Solo es ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âºtil como limpieza periÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³dica si se desea programar en Windows Task Scheduler:
//   Programa: C:\xampp\php\php.exe
//   Argumentos: C:\xampp\htdocs\cochera-mysql_2\scripts\expire_holds.php
//   Frecuencia sugerida: cada 1 minuto

require_once __DIR__ . '/../src/Autoload.php';

use App\Services\EspacioAvailabilityService;

EspacioAvailabilityService::expirarHoldsVencidos();
echo "Holds vencidos expirados: " . date('Y-m-d H:i:s') . "\n";
