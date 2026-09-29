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
