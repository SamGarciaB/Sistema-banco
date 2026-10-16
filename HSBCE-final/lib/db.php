<?php

declare(strict_types=1);

require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/env.php';

/**
 * =============================================================================
 * DB CONNECTION — PDO con configuración estricta.
 * - ERRMODE_EXCEPTION para que las queries malas lancen PDOException
 * - EMULATE_PREPARES = false para usar prepared statements REALES de MySQL
 * - utf8mb4 con collation unicode (soporta emojis, acentos, etc.)
 * =============================================================================
 */

loadEnv(__DIR__ . '/../.env');

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_NAME') ?: 'hsbc_worldcup';
$user = getenv('DB_USER') ?: 'hsbc_app';
$pass = getenv('DB_PASS') ?: '';

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE              => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE   => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES     => false,
    PDO::ATTR_PERSISTENT           => false,
    PDO::MYSQL_ATTR_INIT_COMMAND   => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    appLog('critical', 'Database connection failed', [
        'error' => $e->getMessage(),
    ]);

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status'  => 'error',
        'message' => 'Error interno del servidor',
    ]);

    exit;
}