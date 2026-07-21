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
        if ($valor === '') {
            return $this; // ya cubierto por required()
        }

        if (mb_strlen($valor) < 3) {
            $this->errors[$field] = "$label debe tener al menos 3 caracteres";
            return $this;
        }

        if (!preg_match('/^[\p{L}\s]+$/u', $valor)) {
            $this->errors[$field] = "$label solo puede contener letras";
            return $this;
        }

        $palabras = array_filter(preg_split('/\s+/', $valor));
        if (count($palabras) < 2) {
            $this->errors[$field] = "Ingresa nombre y apellido en $label";
        }

        return $this;
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