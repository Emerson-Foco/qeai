<?php

declare(strict_types=1);

/**
 * Caminho base da aplicação ('' na raiz do domínio, '/qeai' em subpasta local).
 * Preferimos calcular pela diferença entre o document root e a pasta do projeto,
 * o que funciona igualmente para scripts na raiz e dentro de workspace/.
 */
function base_path(): string
{
    static $base = null;
    if ($base !== null) return $base;

    $normalize = static fn(string $path): string => str_replace('\\', '/', rtrim($path, '/'));
    $documentRoot = $normalize((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $root = $normalize(defined('ROOT_PATH') ? (string)ROOT_PATH : '');

    if ($documentRoot !== '' && $root !== '' && $root !== $documentRoot && str_starts_with($root, $documentRoot . '/')) {
        return $base = substr($root, strlen($documentRoot));
    }

    // Reserva: usa o diretório do script em execução (scripts na raiz da aplicação).
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if ($dir === '' || $dir === '.' || $dir === '/') return $base = '';
    return $base = $dir;
}

/** URL relativa à raiz da aplicação, respeitando subpasta. */
function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/**
 * URL absoluta calculada a partir da requisição atual.
 * Usada quando storage/config.json ainda não definiu o domínio canônico.
 */
function request_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');

    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') $host = trim((string)($_SERVER['SERVER_NAME'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9.\-]+(?::\d+)?$/', $host)) $host = 'localhost';

    $base = base_path();
    if ($base !== '' && !str_starts_with($base, '/')) $base = '';

    return ($https ? 'https' : 'http') . '://' . $host . $base;
}

/**
 * URL absoluta do site. Prioriza site.url em storage/config.json; se estiver
 * vazio, deriva do host da requisição. Nunca grava o domínio no código.
 */
function site_url(string $path = ''): string
{
    global $config;

    $configured = is_array($config)
        ? rtrim(trim((string)($config['site']['url'] ?? '')), '/')
        : '';
    $base = $configured !== '' ? $configured : request_base_url();

    return $path === '' ? $base . '/' : $base . '/' . ltrim($path, '/');
}

/**
 * URL do Q One. Usa site.app_url quando configurado; caso contrário deriva
 * "one." + host atual. Retorna vazio em localhost/IP para não gerar link falso.
 */
function site_app_url(): string
{
    global $config;

    $configured = is_array($config)
        ? rtrim(trim((string)($config['site']['app_url'] ?? '')), '/')
        : '';
    if ($configured !== '') return $configured;

    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?? '';
    $host = preg_replace('/^www\./i', '', $host) ?? '';
    if ($host === '' || $host === 'localhost' || substr_count($host, '.') > 2 || !str_contains($host, '.')) {
        return '';
    }

    return 'https://one.' . $host;
}
