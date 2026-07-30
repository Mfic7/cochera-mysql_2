<?php

namespace App;

class Config
{
    private static ?array $data = null;

    public static function all(): array
    {
        if (self::$data === null) {
            $config = require __DIR__ . '/../config/config.php';
            self::$data = is_array($config) ? $config : [];
        }
        return self::$data;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::all();
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}