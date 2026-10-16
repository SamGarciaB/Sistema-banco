<?php

declare(strict_types=1);

require_once __DIR__ . '/security.php';

/**
 * =============================================================================
 * LOGGER — Genera logs estructurados (JSON Lines) compatibles con SIEMs
 * corporativos como Splunk, ELK, Sumo Logic.
 * =============================================================================
 */

function appLog(string $level, string $message, array $context = []): void
{
    $levels = [
        'debug'    => 100,
        'info'     => 200,
        'notice'   => 250,
        'warning'  => 300,
        'error'    => 400,
        'critical' => 500,
    ];

    $envLevelStr = strtolower(getenv('LOG_LEVEL') ?: 'info');
    $envLevelInt = $levels[$envLevelStr] ?? 200;

    $msgLevelStr = strtolower($level);
    $msgLevelInt = $levels[$msgLevelStr] ?? 200;

    if ($msgLevelInt < $envLevelInt) {
        return;
    }

    $dir = __DIR__ . '/../storage/logs';

    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    if (!is_writable($dir)) {
        error_log("[appLog] No se puede escribir en: $dir");
        return;
    }

    $file = $dir . '/app-' . date('Y-m-d') . '.log';

    $payload = [
        '@timestamp' => date('c'),
        'level'      => strtoupper($level),
        'message'    => $message,
        'context'    => $context,
        'service'    => 'hsbc-worldcup-form',
        'env'        => getenv('APP_ENV') ?: 'unknown',
        'host'       => gethostname() ?: '-',
        'http' => [
            'method'  => $_SERVER['REQUEST_METHOD']      ?? '-',
            'uri'     => $_SERVER['REQUEST_URI']          ?? '-',
            'referer' => substr($_SERVER['HTTP_REFERER'] ?? '-', 0, 255),
            'ua'      => substr($_SERVER['HTTP_USER_AGENT'] ?? '-', 0, 512),
        ],
        'client' => [
            'ip'         => getClientIp(),
            'cf_ray'     => $_SERVER['HTTP_CF_RAY']       ?? null,
            'cf_country' => $_SERVER['HTTP_CF_IPCOUNTRY'] ?? null,
        ],
        'trace_id' => getTraceId(),
    ];

    @file_put_contents(
        $file,
        json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}