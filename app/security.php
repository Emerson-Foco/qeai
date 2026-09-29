<?php

declare(strict_types=1);

function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function verify_csrf($token): bool { return is_string($token) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token); }
function post_string(string $key): string { return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : ''; }
function text_length(string $value): int { return preg_match_all('/./us', $value, $matches) === false ? PHP_INT_MAX : count($matches[0]); }
function is_logged_in(): bool { return !empty($_SESSION['qeai_auth']) && $_SESSION['qeai_auth'] === true; }
function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }

function rate_limit(string $bucket, int $max, int $seconds): bool
{
    ensure_storage_directory('rate-limit');
    $file = ROOT_PATH . '/storage/rate-limit/' . hash('sha256', $bucket . '|' . client_ip()) . '.json';
    $handle = fopen($file, 'c+');
    if ($handle === false) throw new RuntimeException('Falha ao abrir o controle de tentativas.');
    try {
        if (!flock($handle, LOCK_EX)) throw new RuntimeException('Falha ao bloquear o controle de tentativas.');
        $now = time();
        $parsed = json_decode((string)stream_get_contents($handle), true);
        $data = is_array($parsed) ? $parsed : ['start' => $now, 'count' => 0];
        if (($now - (int)($data['start'] ?? 0)) >= $seconds) $data = ['start' => $now, 'count' => 0];
        if ((int)($data['count'] ?? 0) >= $max) return false;
        $data['count'] = (int)($data['count'] ?? 0) + 1;
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, json_encode($data)) === false || !fflush($handle)) throw new RuntimeException('Falha ao gravar o controle de tentativas.');
        return true;
    } finally { flock($handle, LOCK_UN); fclose($handle); }
}

function safe_hex(string $value, string $fallback): string { return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? strtoupper($value) : $fallback; }
