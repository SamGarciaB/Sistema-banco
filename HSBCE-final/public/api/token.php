<?php

declare(strict_types=1);

/**
 * =============================================================================
 * GET /api/token.php
 * Devuelve { status, _t, _nonce, token, _trace }
 *
 * Este endpoint se llama JUSTO antes del submit (lazy fetch en cliente),
 * NO al cargar la página. Esto evita que escáneres de email (Gmail, Outlook)
 * disparen el rate limit y generen nonces inválidos.
 * =============================================================================
 */

require_once __DIR__ . '/../../lib/security.php';
require_once __DIR__ . '/../../lib/env.php';
require_once __DIR__ . '/../../lib/rate_limit.php';
require_once __DIR__ . '/../../lib/logger.php';

loadEnv(__DIR__ . '/../../.env');

// Rate limit del endpoint (más permisivo que /save porque puede recargarse)
rateLimit('token', 20, 60);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Método no permitido', 405);
}

/**
 * CORS: orígenes permitidos desde .env (CORS_ALLOWED_ORIGINS)
 */
$allowedOrigins = array_filter(array_map('trim', explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: '')));

if (empty($allowedOrigins)) {
    $allowedOrigins = ['http://localhost', 'http://127.0.0.1'];
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

$isLocalDev = (getenv('APP_ENV') === 'development') &&
              preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $origin);

if ($origin && !in_array($origin, $allowedOrigins, true) && !$isLocalDev) {
    appLog('warning', 'Blocked CORS request', ['origin' => $origin]);
    jsonError('CORS bloqueado', 403);
}

if ($origin) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: false');
}

// Verificar HMAC_KEY
$hmacKey = getenv('HMAC_KEY') ?: '';
if (!$hmacKey || strlen($hmacKey) < 32) {
    appLog('critical', 'HMAC_KEY ausente o débil');
    jsonError('Error interno del servidor', 500);
}

// Generar nonce aleatorio (64 chars hex = 32 bytes de entropía)
$nonce     = bin2hex(random_bytes(32));
$timestamp = (string) time();

// Token = HMAC-SHA256(timestamp + nonce, HMAC_KEY)
$token = hash_hmac('sha256', $timestamp . '|' . $nonce, $hmacKey);

jsonResponse([
    'status' => 'ok',
    '_t'     => $timestamp,
    '_nonce' => $nonce,
    'token'  => $token,
]);