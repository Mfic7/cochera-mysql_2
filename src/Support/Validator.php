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

    public function validate(): array
    {
        if (!empty($this->errors)) {
            throw new ValidationException($this->errors);
        }
        return $this->data;
    }
}
