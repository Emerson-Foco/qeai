<?php

declare(strict_types=1);

function db_config_path(): string
{
    return ROOT_PATH . '/config/db.php';
}

/**
 * Conexão PDO única e preguiçosa com o banco do blog.
 * Lança exceção clara (para o log) quando o banco não está configurado.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $path = db_config_path();
    if (!is_file($path)) {
        throw new RuntimeException('Banco do blog não configurado. Crie config/db.php a partir de config/db.example.php.');
    }
    $config = require $path;
    if (!is_array($config)) throw new RuntimeException('config/db.php inválido.');

    foreach (['host', 'database', 'username'] as $key) {
        if (!isset($config[$key]) || (string)$config[$key] === '') {
            throw new RuntimeException('config/db.php incompleto: informe "' . $key . '".');
        }
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        (string)$config['host'],
        (int)($config['port'] ?? 3306),
        (string)$config['database'],
        (string)($config['charset'] ?? 'utf8mb4')
    );

    $pdo = new PDO($dsn, (string)$config['username'], (string)($config['password'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
