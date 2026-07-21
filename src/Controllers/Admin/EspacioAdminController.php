<?php

namespace App\Controllers\Admin;

use App\Controller;
use App\Database;
use App\Support\Validator;

class EspacioAdminController extends Controller
{
    public function listar(): void
    {
        $this->requireAdmin();
        $stmt = Database::connection()->query('SELECT * FROM espacios ORDER BY numero');
        $this->json($stmt->fetchAll());
    }

    public function crear(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $input = $this->input();

        $this->handleValidation(fn () => (new Validator($input))
            ->required('codigo', 'Código')
            ->required('numero', 'Número')
            ->numeric('numero', 'Número')
            ->validate());

        $stmt = Database::connection()->prepare(
            'INSERT INTO espacios (codigo, numero, zona, estado) VALUES (:codigo, :numero, :zona, :estado)'
        );
        $stmt->execute([
            'codigo' => $input['codigo'],
            'numero' => $input['numero'],
            'zona' => $input['zona'] ?? null,
            'estado' => $input['estado'] ?? 'disponible',
        ]);

        $this->json(['id' => (int) Database::connection()->lastInsertId()], 201);
    }

    public function actualizar(string $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $input = $this->input();

        $this->handleValidation(fn () => (new Validator($input))
            ->in('estado', ['disponible', 'ocupado', 'mantenimiento'], 'Estado')
            ->validate());

        $campos = [];
        $params = ['id' => (int) $id];
        foreach (['codigo', 'numero', 'zona', 'estado', 'activo'] as $campo) {
            if (array_key_exists($campo, $input)) {
                $campos[] = "$campo = :$campo";
                $params[$campo] = $input[$campo];
            }
        }
        if (empty($campos)) {
            $this->error('Nada que actualizar', 422);
        }

        $stmt = Database::connection()->prepare('UPDATE espacios SET ' . implode(', ', $campos) . ' WHERE id = :id');
        $stmt->execute($params);

        $this->json(['ok' => true]);
    }
}
