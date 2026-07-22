<?php

namespace App\Controllers\Admin;

use App\Controller;
use App\Models\Cancelacion;

class CancelacionAdminController extends Controller
{
    public function listar(): void
    {
        $this->requireAdmin();
        $this->json(Cancelacion::listar());
    }

    public function marcarRevisado(string $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        Cancelacion::marcarRevisado((int) $id, true);
        $this->json(['ok' => true]);
    }
}
