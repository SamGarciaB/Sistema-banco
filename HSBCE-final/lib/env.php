<?php

declare(strict_types=1);

/**
 * =============================================================================
 * ENV LOADER — Cargador de variables de entorno desde .env
 * Compatible con formato KEY=VALUE, con soporte para comillas y comentarios.
 * =============================================================================
 */
function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name  = trim($name);
        $value = trim($value);

        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        putenv("$name=$value");
        $_ENV[$name]    = $value;
        $_SERVER[$name] = $value;
    }

    $debug = strtolower(getenv('APP_DEBUG') ?: 'false') === 'true';
    if ($debug) {
        ini_set('display_errors', '1');
        error_reporting(E_ALL);
    } else {
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);
    }

    $tz = getenv('APP_TIMEZONE') ?: 'America/Mexico_City';
    date_default_timezone_set($tz);
}