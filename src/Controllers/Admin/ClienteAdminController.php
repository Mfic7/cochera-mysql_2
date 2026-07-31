<?php

namespace App\Controllers\Admin;

use App\Controller;
use App\Models\Cliente;

class ClienteAdminController extends Controller
{
    public function listar(): void
    {
        $this->requireAdmin();
        $busqueda = $_GET['busqueda'] ?? null;
        $this->json(Cliente::listar($busqueda));
    }

    public function historial(string $celular): void
    {
        $this->requireAdmin();
        $this->json(Cliente::historial($celular));
    }
}