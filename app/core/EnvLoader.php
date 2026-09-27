<?php

class EnvLoader {
    private static $loaded = false;

    public static function load($path = null) {
        if (self::$loaded) return;

        if ($path === null) {
            $path = dirname(__DIR__, 2) . '/.env';
        }

        if (!file_exists($path)) return;

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            } elseif (strpos($value, '\'') === 0 && strrpos($value, '\'') === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            }

            if (!getenv($name)) {
                putenv("$name=$value");
                $_ENV[$name] = $value;
            }
        }

        self::$loaded = true;
    }

    public static function get($key, $default = null) {
        $value = getenv($key);
        if ($value === false) return $default;
        return $value;
    }

    public static function getRequired($key) {
        $value = self::get($key);
        if ($value === null) {
            throw new RuntimeException("Variable de entorno requerida: $key");
        }
        return $value;
    }
}
