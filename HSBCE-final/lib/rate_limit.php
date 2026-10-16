<?php

declare(strict_types=1);

require_once __DIR__ . '/security.php';

/**
 * =============================================================================
 * RATE LIMITER por archivo + flock (apto para single server).
 * Para multi-server, migrar a Redis.
 * =============================================================================
 */

function rateLimit(string $key, int $limit = 10, int $window = 60): void
{
    $ip  = getClientIp();
    $dir = sys_get_temp_dir() . '/hsbc_rate_limit';

    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    $file = $dir . '/' . sha1($key . $ip);
    $now  = time();

    $data = ['count' => 0, 'start' => $now];

    $fp = fopen($file, 'c+');
    if (!$fp) return;

    flock($fp, LOCK_EX);

    $existing = stream_get_contents($fp);
    if ($existing) {
        $decoded = json_decode($existing, true);
        if (is_array($decoded) && isset($decoded['count'], $decoded['start'])) {
            $data = $decoded;
        }
    }

    if (($now - $data['start']) > $window) {
        $data = ['count' => 0, 'start' => $now];
    }

    $data['count']++;

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data));
    fflush($fp);

    flock($fp, LOCK_UN);
    fclose($fp);

    if ($data['count'] > $limit) {
        $retryAfter = max(1, $window - ($now - $data['start']));
        header('Retry-After: ' . $retryAfter);
        jsonError('Demasiadas solicitudes. Intenta más tarde.', 429);
    }
}

function cleanupRateLimitFiles(int $maxAgeSeconds = 3600): int
{
    $dir = sys_get_temp_dir() . '/hsbc_rate_limit';
    if (!is_dir($dir)) return 0;

    $deleted = 0;
    $now     = time();

    foreach (glob($dir . '/*') ?: [] as $file) {
        if (is_file($file) && ($now - filemtime($file)) > $maxAgeSeconds) {
            if (@unlink($file)) $deleted++;
        }
    }

    return $deleted;
}