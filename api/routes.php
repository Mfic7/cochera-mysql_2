<?php

declare(strict_types=1);

// Rutas de la API. $router está disponible desde api/index.php.

use App\Controllers\ConfigController;
use App\Controllers\EspacioController;
use App\Controllers\PagoController;
use App\Controllers\ReservaController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\ReservaAdminController;
use App\Controllers\Admin\PagoAdminController;
use App\Controllers\Admin\EspacioAdminController;
use App\Controllers\Admin\MetodoPagoAdminController;
use App\Controllers\Admin\ConfiguracionController as AdminConfiguracionController;
use App\Controllers\Admin\CancelacionAdminController;

$router->get('config', fn () => (new ConfigController())->index());
$router->get('metodos-pago', fn () => (new ConfigController())->metodosPago());
$router->get('espacios/disponibilidad', fn () => (new EspacioController())->disponibilidad());

$router->post('reservas', fn () => (new ReservaController())->crear());
$router->get('reservas/buscar', fn () => (new ReservaController())->buscar());
$router->get('reservas/{id}', fn ($id) => (new ReservaController())->ver($id));
$router->post('reservas/{id}/comprobante', fn ($id) => (new PagoController())->comprobante($id));
$router->post('reservas/{id}/cancelacion', fn ($id) => (new ReservaController())->cancelacion($id));

$router->post('admin/auth/login', fn () => (new AuthController())->login());
$router->post('admin/auth/logout', fn () => (new AuthController())->logout());
$router->get('admin/auth/me', fn () => (new AuthController())->me());

$router->get('admin/dashboard/kpis', fn () => (new DashboardController())->kpis());
$router->get('admin/espacios/ocupacion', fn () => (new DashboardController())->ocupacion());
$router->get('admin/actividad', fn () => (new DashboardController())->actividad());
$router->get('admin/dashboard/reservas-del-dia', fn () => (new DashboardController())->reservasDelDia());
$router->get('admin/dashboard/reservas-recientes', fn () => (new DashboardController())->reservasRecientes());
$router->get('admin/reportes/ingresos', fn () => (new DashboardController())->reporteIngresos());
$router->get('admin/reportes/metodos-pago', fn () => (new DashboardController())->reporteMetodosPago());

$router->get('admin/reservas', fn () => (new ReservaAdminController())->listar());
$router->get('admin/reservas/{id}', fn ($id) => (new ReservaAdminController())->ver($id));
$router->patch('admin/reservas/{id}/estado', fn ($id) => (new ReservaAdminController())->actualizarEstado($id));
$router->post('admin/reservas/{id}/pago-saldo', fn ($id) => (new ReservaAdminController())->registrarPagoSaldo($id));

$router->get('admin/pagos', fn () => (new PagoAdminController())->listar());
$router->get('admin/pagos/{id}/comprobante', fn ($id) => (new PagoAdminController())->comprobante($id));
$router->patch('admin/pagos/{id}', fn ($id) => (new PagoAdminController())->revisar($id));

$router->get('admin/espacios', fn () => (new EspacioAdminController())->listar());
$router->post('admin/espacios', fn () => (new EspacioAdminController())->crear());
$router->patch('admin/espacios/{id}', fn ($id) => (new EspacioAdminController())->actualizar($id));

$router->get('admin/metodos-pago', fn () => (new MetodoPagoAdminController())->listar());
$router->patch('admin/metodos-pago/{tipo}', fn ($tipo) => (new MetodoPagoAdminController())->actualizar($tipo));

$router->get('admin/configuracion', fn () => (new AdminConfiguracionController())->ver());
$router->patch('admin/configuracion', fn () => (new AdminConfiguracionController())->actualizar());

$router->get('admin/cancelaciones', fn () => (new CancelacionAdminController())->listar());
$router->get('admin/cancelaciones/{id}/comprobante', fn ($id) => (new CancelacionAdminController())->comprobante($id));
$router->patch('admin/cancelaciones/{id}', fn ($id) => (new CancelacionAdminController())->decidir($id));