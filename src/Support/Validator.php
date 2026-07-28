<?php

namespace App\Support;

class ValidationException extends \RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Datos inválidos');
    }
}

class Validator
{
    private array $errors = [];

    public function __construct(private readonly array $data)
    {
    }

    public function required(string $field, string $label): static
    {
        if (!isset($this->data[$field]) || trim((string) $this->data[$field]) === '') {
            $this->errors[$field] = "$label es obligatorio";
        }
        return $this;
    }

    public function numeric(string $field, string $label): static
    {
        if (isset($this->data[$field]) && trim((string) $this->data[$field]) !== '' && !is_numeric($this->data[$field])) {
            $this->errors[$field] = "$label debe ser numérico";
        }
        return $this;
    }

    public function in(string $field, array $allowed, string $label): static
    {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $allowed, true)) {
            $this->errors[$field] = "$label inválido";
        }
        return $this;
    }

    /**
     * Nombre completo: solo letras/espacios/tildes, mínimo 3 caracteres,
     * y al menos 2 palabras (nombre + apellido).
     */
    public function nombreCompleto(string $field, string $label): static
    {
        if (isset($this->errors[$field])) {
            return $this;
        }

        $valor = trim((string) ($this->data[$field] ?? ''));
        $error = $this->validarNombreCompleto($valor, $label);
        if ($error !== null) {
            $this->errors[$field] = $error;
        }

        return $this;
    }

    /** Devuelve el mensaje de error del nombre completo, o null si es válido (o vacío, ya cubierto por required()). */
    private function validarNombreCompleto(string $valor, string $label): ?string
    {
        if ($valor === '') {
            return null; // ya cubierto por required()
        }

        return match (true) {
            mb_strlen($valor) < 3 => "$label debe tener al menos 3 caracteres",
            !preg_match('/^[\p{L}\s]+$/u', $valor) => "$label solo puede contener letras",
            count(array_filter(preg_split('/\s+/', $valor))) < 2 => "Ingresa nombre y apellido en $label",
            default => null,
        };
    }

    /**
     * Celular peruano: 9 dígitos, debe empezar en 9.
     * Acepta espacios/guiones en la entrada (la limpieza final se hace en el controller,
     * ya que $data es readonly y no se puede normalizar aquí).
     */
    public function celularPeru(string $field, string $label): static
    {
        if (isset($this->errors[$field])) {
            return $this;
        }

        $valor = (string) ($this->data[$field] ?? '');
        $limpio = preg_replace('/[\s\-]/', '', $valor);

        if ($limpio === '') {
            return $this; // ya cubierto por required()
        }

        if (!preg_match('/^9\d{8}$/', $limpio)) {
            $this->errors[$field] = "$label debe tener 9 dígitos y empezar con 9 (ej. 987654321)";
        }

        return $this;
    }

    public function validate(): array
    {
        if (!empty($this->errors)) {
            throw new ValidationException($this->errors);
        }
        return $this->data;
    }
}

