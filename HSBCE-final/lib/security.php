<?php

declare(strict_types=1);

/**
 * =============================================================================
 * SECURITY UTILITIES
 * Trace IDs, IP detection, validación de inputs, respuestas JSON
 * estandarizadas, y validación de nonces contra DB.
 * =============================================================================
 */

function getTraceId(): string
{
    static $traceId = null;
    if ($traceId === null) {
        $traceId = bin2hex(random_bytes(8));
    }
    return $traceId;
}

function validateNonce(PDO $pdo, string $nonce): bool
{
    if (!ctype_xdigit($nonce) || strlen($nonce) < 32 || strlen($nonce) > 128) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO nonces (nonce) VALUES (?)");
        $stmt->execute([$nonce]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function getClientIp(): string
{
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function jsonResponse(array $data, int $status = 200): never
{
    $traceId = getTraceId();

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header("X-Trace-ID: " . $traceId);
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; form-action 'none';");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    echo json_encode(
        array_merge($data, ['_trace' => $traceId]),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

function jsonError(string $message, int $status = 400): never
{
    jsonResponse(['status' => 'error', 'message' => $message], $status);
}

function validateName(string $name, int $minLen = 2, int $maxLen = 100): bool
{
    $name = trim($name);
    if (mb_strlen($name) < $minLen || mb_strlen($name) > $maxLen) {
        return false;
    }
    return (bool) preg_match('/^[\p{L}\p{M}\s\-\'\.]+$/u', $name);
}

function validateEmail(string $email): bool
{
    if (mb_strlen($email) > 120) return false;
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhoneMX(string $phone): bool
{
    return (bool) preg_match('/^\d{10}$/', $phone);
}

function validateInList(string $value, array $allowed): bool
{
    return in_array($value, $allowed, true);
}