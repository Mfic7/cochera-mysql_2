<?php
// Backstop opcional: expira holds vencidos aunque no haya tráfico entrante.
// La app ya se autocorrige en cada lectura de disponibilidad (ver EspacioAvailabilityService),
// así que ESTE SCRIPT NO ES NECESARIO PARA EL FUNCIONAMIENTO CORRECTO.
// Solo es útil como limpieza periódica si se desea programar en Windows Task Scheduler:
//   Programa: C:\xampp\php\php.exe
//   Argumentos: C:\xampp\htdocs\Coherv2\scripts\expire_holds.php
//   Frecuencia sugerida: cada 1 minuto

require __DIR__ . '/../src/Autoload.php';

use App\Services\EspacioAvailabilityService;

EspacioAvailabilityService::expirarHoldsVencidos();
echo "Holds vencidos expirados: " . date('Y-m-d H:i:s') . "\n";
