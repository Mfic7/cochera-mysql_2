<?php

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Controller;
use App\Models\UsuarioAdmin;

class AuthController extends Controller
{
    public function login(): void
    {
        $input = $this->input();
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if ($email === '' || $password === '') {
            $this->error('Ingresa tu correo y contraseña', 422);
        }

        $admin = UsuarioAdmin::findByEmail($email);
        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            $this->error('Credenciales inválidas', 401);
        }

        AdminAuth::login($admin);
        UsuarioAdmin::marcarLogin($admin['id']);

        $this->json([
            'id' => $admin['id'],
            'nombre' => $admin['nombre'],
            'email' => $admin['email'],
            'rol' => $admin['rol'],
            'csrf_token' => AdminAuth::csrfToken(),
        ]);
    }

    public function logout(): void
    {
        AdminAuth::logout();
        $this->json(['ok' => true]);
    }

    public function me(): void
    {
        $admin = $this->requireAdmin();
        $this->json(array_merge($admin, ['csrf_token' => AdminAuth::csrfToken()]));
    }
}
