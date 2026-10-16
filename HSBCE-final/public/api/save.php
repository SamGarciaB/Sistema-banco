<?php

declare(strict_types=1);

/**
 * =============================================================================
 * POST /api/save.php
 *
 * Versión SIN cifrado de PII. Los datos se guardan en plano para permitir
 * queries SQL directos. La protección de PII vive en la capa de infra
 * (firewall, WAF, accesos controlados, backups cifrados).
 *
 * Capas de seguridad de TRANSPORTE (anti-bot, anti-replay, anti-tampering):
 *   1. Rate limit por IP (3/min, 10/hora)
 *   2. Validación Content-Type y tamaño del payload (max 10KB)
 *   3. Honeypot
 *   4. Tiempo en página mínimo (>5s)
 *   5. Comportamiento humano (mouse/teclado/focus)
 *   6. Idempotencia UUID v4 (anti doble-click)
 *   7. Validación HMAC + timestamp (token expira 10 min)
 *   8. Validación Nonce contra DB (anti replay)
 *   9. Verificación Cloudflare Turnstile
 *  10. Validación de campos (whitelist regex)
 *  11. Detección de duplicados (3+ envíos rechaza)
 *  12. Risk scoring (0-100)
 *  13. INSERT con prepared statement
 * =============================================================================
 */

require_once __DIR__ . '/../../lib/security.php';
require_once __DIR__ . '/../../lib/env.php';

loadEnv(__DIR__ . '/../../.env');
$traceId = getTraceId();

require_once __DIR__ . '/../../lib/rate_limit.php';
require_once __DIR__ . '/../../lib/logger.php';
require_once __DIR__ . '/../../lib/db.php';

// 1. Rate limiting (doble ventana)
rateLimit('save_api_min',  3,    60);
rateLimit('save_api_hour', 10,   3600);

// 2. Método y Content-Type
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método no permitido', 405);
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (!str_contains($contentType, 'application/json')) jsonError('Content-Type inválido', 415);

$rawInput = file_get_contents('php://input');
if (strlen($rawInput) > 10000) {
    appLog('warning', 'Payload demasiado grande', ['size' => strlen($rawInput)]);
    jsonError('Payload demasiado grande', 413);
}

$data = json_decode($rawInput, true);
if (!is_array($data)) jsonError('Payload inválido', 400);

// 3. Honeypot
$honeypotName = $data['_hpn'] ?? '';
if ($honeypotName && !empty($data[$honeypotName])) {
    appLog('warning', 'Honeypot activado (bot)');
    jsonResponse(['status' => 'ok']);
}

// 4. Tiempo en página
$landedAt = (int) ($data['_landed_at'] ?? 0);
if ($landedAt > 0) {
    $elapsedMs = (time() * 1000) - $landedAt;
    if ($elapsedMs < 5000) {
        appLog('warning', 'Submit demasiado rápido', ['elapsed_ms' => $elapsedMs]);
        jsonError('Solicitud inválida', 403);
    }
}

// 5. Comportamiento humano
$behavior = is_array($data['_behavior'] ?? null) ? $data['_behavior'] : [];
$humanSignals = (int)($behavior['m'] ?? 0) + (int)($behavior['k'] ?? 0) + (int)($behavior['f'] ?? 0);
if ($humanSignals < 3) appLog('notice', 'Comportamiento no-humano', ['signals' => $humanSignals]);

// 6. Idempotencia
$idempKey = $data['_idempotency_key'] ?? '';
if (!preg_match('/^[a-f0-9-]{36}$/i', $idempKey)) jsonError('Idempotency key inválida', 400);

try {
    $stmt = $pdo->prepare("SELECT id FROM solicitudes WHERE idempotency_key = ?");
    $stmt->execute([$idempKey]);
    if ($stmt->fetch()) {
        appLog('info', 'Submit duplicado por idempotency key');
        jsonResponse(['status' => 'ok', 'duplicate' => true]);
    }
} catch (PDOException $e) {
    appLog('error', 'Fallo check idempotencia', ['code' => $e->getCode()]);
}

// 7. HMAC + timestamp
$timestamp = $data['_t']  ?? '';
$token     = $data['_ts'] ?? '';
$nonce     = $data['_nonce'] ?? '';

if (!ctype_digit((string) $timestamp) || empty($token) || empty($nonce)) {
    appLog('warning', 'Token/timestamp/nonce inválido');
    jsonError('Firma de seguridad inválida', 403);
}

$hmacKey       = getenv('HMAC_KEY') ?: '';
$expectedToken = hash_hmac('sha256', $timestamp . '|' . $nonce, $hmacKey);
if (!hash_equals($expectedToken, $token)) {
    appLog('critical', 'Violación de integridad HMAC');
    jsonError('Firma de seguridad inválida', 403);
}

if (abs(time() - (int) $timestamp) > 600) {
    appLog('warning', 'Token expirado');
    jsonError('Sesión expirada. Recarga la página.', 403);
}

// 8. Nonce
if (!validateNonce($pdo, $nonce)) {
    appLog('warning', 'Nonce reutilizado o inválido');
    jsonError('Solicitud duplicada o inválida', 403);
}

// 9. Cloudflare Turnstile
$turnstileToken  = $data['turnstileToken'] ?? '';
$turnstileSecret = getenv('TURNSTILE_SECRET') ?: '';

if (empty($turnstileToken) || empty($turnstileSecret)) {
    appLog('error', 'Turnstile token/secret faltante');
    jsonError('Verificación anti-bot requerida', 403);
}

$ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'secret'   => $turnstileSecret,
        'response' => $turnstileToken,
        'remoteip' => getClientIp(),
    ]),
    CURLOPT_TIMEOUT        => 5,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$tsRaw = curl_exec($ch);
$tsHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($tsHttpCode !== 200 || !$tsRaw) {
    appLog('error', 'Turnstile API error', ['http_code' => $tsHttpCode]);
    jsonError('Verificación anti-bot fallida', 403);
}

$tsResponse = json_decode($tsRaw, true);
if (empty($tsResponse['success'])) {
    appLog('warning', 'Turnstile rechazado', ['errors' => $tsResponse['error-codes'] ?? []]);
    jsonError('Verificación anti-bot fallida', 403);
}

// 10. Validación de campos
$firstName = trim($data['firstName'] ?? '');
$lastName  = trim($data['lastName']  ?? '');
$email     = strtolower(trim($data['email'] ?? ''));
$phone     = trim($data['phone']     ?? '');
$ocupacion = trim($data['ocupacion'] ?? '');
$company   = trim($data['company']   ?? '');
$ingreso   = trim($data['ingreso']   ?? '');
$tarjeta   = trim($data['tarjeta']   ?? 'Platinum');
$marketing = !empty($data['notificaciones']);

$errors = [];
if (!validateName($firstName)) $errors[] = 'Nombre inválido';
if (!validateName($lastName))  $errors[] = 'Apellidos inválidos';
if (!validateEmail($email))    $errors[] = 'Email inválido';
if (!validatePhoneMX($phone))  $errors[] = 'Teléfono debe ser 10 dígitos';
if (!validateInList($ocupacion, ['Empleado', 'Independiente', 'Empresario', 'Jubilado'])) $errors[] = 'Ocupación inválida';
if (!validateInList($ingreso, ['15k-30k', '30k-50k', '50k-80k', '80k+'])) $errors[] = 'Ingreso inválido';
if (!validateInList($tarjeta, ['Platinum', 'Gold'])) $errors[] = 'Tarjeta inválida';
if ($company !== '' && mb_strlen($company) > 120) $errors[] = 'Empresa demasiado larga';

if (!empty($errors)) {
    appLog('info', 'Validación de campos falló', ['errors' => $errors]);
    jsonResponse(['status' => 'error', 'message' => 'Datos inválidos', 'errors' => $errors], 400);
}

// 11. Detección de duplicados (3+ envíos rechaza)
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM solicitudes WHERE email = ? OR telefono = ?");
    $stmt->execute([$email, $phone]);
    $count = (int) ($stmt->fetch()['c'] ?? 0);
    if ($count >= 3) {
        appLog('warning', 'Email/Phone duplicado múltiple');
        sleep(2);
        jsonError('Esta información ya fue registrada anteriormente.', 429);
    }
} catch (PDOException $e) {
    appLog('error', 'Falló check duplicados', ['code' => $e->getCode()]);
}

// 12. Risk score
$riskScore = 0;
if (isset($elapsedMs)) {
    if ($elapsedMs < 10000)      $riskScore += 40;
    elseif ($elapsedMs < 30000)  $riskScore += 20;
}
if (($behavior['m'] ?? 0) < 5)  $riskScore += 30;
if (($behavior['k'] ?? 0) < 10) $riskScore += 20;
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (preg_match('/bot|crawler|spider|curl|wget|python|headless/i', $ua)) $riskScore += 50;
$riskScore = min(100, $riskScore);

// 13. INSERT
try {
    $stmt = $pdo->prepare("
        INSERT INTO solicitudes (
            nombres, apellidos, email, telefono,
            ocupacion, empresa_negocio, ingreso_mensual,
            tipo_tarjeta, acepta_notificaciones,
            ip_origen, user_agent,
            trace_id, idempotency_key, risk_score
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        mb_substr($firstName, 0, 100),
        mb_substr($lastName,  0, 100),
        $email,
        $phone,
        $ocupacion,
        mb_substr($company, 0, 120),
        $ingreso,
        $tarjeta,
        $marketing ? 1 : 0,
        getClientIp(),
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        $traceId,
        $idempKey,
        $riskScore,
    ]);
    appLog('info', 'Solicitud guardada', ['tarjeta' => $tarjeta, 'risk_score' => $riskScore]);
    jsonResponse(['status' => 'ok']);
} catch (PDOException $e) {
    appLog('critical', 'INSERT falló', ['code' => $e->getCode(), 'error' => $e->getMessage()]);
    jsonError('Error interno del servidor', 500);
}