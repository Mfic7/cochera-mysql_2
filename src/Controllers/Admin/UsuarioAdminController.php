<?php

namespace App\Controllers\Admin;

use App\Controller;
use App\Models\UsuarioAdmin;
use App\Support\Validator;

class UsuarioAdminController extends Controller
{
    private const ROLES_VALIDOS = ['administrador', 'operador'];
    private const USUARIO_NO_ENCONTRADO = 'Usuario no encontrado';

    public function listar(): void
    {
        $this->requireAdmin();
        $this->json(UsuarioAdmin::listar());
    }

    public function crear(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $input = $this->input();

        $data = $this->handleValidation(fn () => (new Validator($input))
            ->required('nombre', 'Nombre')
            ->required('email', 'Correo')
            ->required('password', 'Contraseña')
            ->required('rol', 'Rol')
            ->in('rol', self::ROLES_VALIDOS, 'Rol')
            ->validate());

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->error('Ingresa un correo válido.', 422);
        }
        if (UsuarioAdmin::existeEmail($data['email'])) {
            $this->error('Ya existe un usuario con ese correo.', 409);
        }

        $id = UsuarioAdmin::crear($data);
        $this->json(UsuarioAdmin::find($id), 201);
    }

    public function actualizar(string $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $usuarioId = (int) $id;
        $input = $this->input();

        $usuario = UsuarioAdmin::find($usuarioId);
        if (!$usuario) {
            $this->error(self::USUARIO_NO_ENCONTRADO, 404);
        }

        $data = $this->handleValidation(fn () => (new Validator($input))
            ->required('nombre', 'Nombre')
            ->required('rol', 'Rol')
            ->in('rol', self::ROLES_VALIDOS, 'Rol')
            ->validate());

        $data['activo'] = array_key_exists('activo', $input) ? (bool) $input['activo'] : (bool) $usuario['activo'];
        $data['password'] = $input['password'] ?? null;

        UsuarioAdmin::actualizar($usuarioId, $data);
        $this->json(UsuarioAdmin::find($usuarioId));
    }
}