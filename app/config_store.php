<?php

declare(strict_types=1);

function default_config(): array
{
    return [
        'site' => [
            'name' => 'QEAI',
            // Vazio = deriva do host da requisição. O domínio canônico pode
            // ser definido em storage/config.json sem alterar código.
            'url' => '',
            'app_url' => '',
            'contact_email' => 'contato@qeai.com.br',
            'cnpj' => '60357082000158',
        ],
        'features' => [
            'contact_form' => true,
            'whatsapp' => false,
        ],
        'whatsapp' => [
            'number' => '',
            'message' => 'Olá! Vim pelo site da QEAI e gostaria de conversar sobre marketing.',
        ],
        'theme' => [
            'primary' => '#6D3DF5',
            'secondary' => '#18C7D9',
            'accent' => '#FF7048',
            'dark' => '#11132B',
        ],
        'smtp' => [
            'host' => 'smtp.hostinger.com',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'contato@qeai.com.br',
            'password_enc' => '',
            'from_name' => 'QEAI',
            'to_email' => 'contato@qeai.com.br',
        ],
        'admin' => [
            'username' => 'owner',
            'password_hash' => '',
        ],
    ];
}

function config_path(): string { return ROOT_PATH . '/storage/config.json'; }

function normalize_cnpj(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function cnpj_is_valid(string $digits): bool
{
    if (!preg_match('/^\d{14}$/', $digits) || preg_match('/^(\d)\1{13}$/', $digits)) return false;
    $check = static function (string $base, array $weights): int {
        $sum = 0;
        foreach ($weights as $index => $weight) $sum += (int)$base[$index] * $weight;
        $mod = $sum % 11;
        return $mod < 2 ? 0 : 11 - $mod;
    };
    return (int)$digits[12] === $check(substr($digits, 0, 12), [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2])
        && (int)$digits[13] === $check(substr($digits, 0, 13), [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
}

function format_cnpj(string $value): string
{
    $digits = normalize_cnpj($value);
    if (strlen($digits) !== 14) return $digits;
    return substr($digits, 0, 2) . '.' . substr($digits, 2, 3) . '.' . substr($digits, 5, 3) . '/' . substr($digits, 8, 4) . '-' . substr($digits, 12, 2);
}

function ensure_storage_directory(string $directory): void
{
    $path = ROOT_PATH . '/storage' . ($directory !== '' ? '/' . $directory : '');
    if (!is_dir($path) && !@mkdir($path, 0750, true) && !is_dir($path)) {
        throw new RuntimeException('Não foi possível criar storage/' . $directory . '. Verifique permissões na hospedagem.');
    }
    if (!is_writable($path)) throw new RuntimeException('Diretório sem permissão de escrita: storage/' . $directory);
}

function deep_merge(array $base, array $overrides): array
{
    foreach ($overrides as $key => $value) {
        if (is_array($value) && isset($base[$key]) && is_array($base[$key])) $base[$key] = deep_merge($base[$key], $value);
        else $base[$key] = $value;
    }
    return $base;
}

function load_config(): array
{
    $defaults = default_config();
    if (!is_file(config_path())) return $defaults;
    $contents = file_get_contents(config_path());
    if ($contents === false) throw new RuntimeException('Não foi possível ler a configuração.');
    $data = json_decode($contents, true);
    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('storage/config.json está inválido. Restaure o backup da configuração.');
    }
    foreach ($defaults as $section => $values) {
        if (array_key_exists($section, $data) && !is_array($data[$section])) throw new RuntimeException('Seção de configuração inválida: ' . $section);
        foreach ($values as $key => $value) {
            if (isset($data[$section]) && array_key_exists($key, $data[$section]) && gettype($data[$section][$key]) !== gettype($value)) {
                throw new RuntimeException('Tipo de configuração inválido: ' . $section . '.' . $key);
            }
        }
    }
    return deep_merge($defaults, $data);
}

function save_config(array $config): bool
{
    ensure_storage_directory('');
    $tmp = tempnam(ROOT_PATH . '/storage', 'config-');
    if ($tmp === false) return false;
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    try {
        if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false) return false;
        @chmod($tmp, 0600);
        return rename($tmp, config_path());
    } finally { if (is_file($tmp)) unlink($tmp); }
}

function app_key(): string
{
    $key = trim((string)@file_get_contents(ROOT_PATH . '/config/app.key'));
    if ($key === '') throw new RuntimeException('Chave da aplicação ausente.');
    return hash('sha256', $key, true);
}

function encrypt_secret(string $value): string
{
    if ($value === '') return '';
    $iv = random_bytes(12); $tag = '';
    $cipher = openssl_encrypt($value, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) throw new RuntimeException('Falha ao criptografar segredo.');
    return base64_encode($iv . $tag . $cipher);
}

function decrypt_secret(string $value): string
{
    if ($value === '') return '';
    $raw = base64_decode($value, true);
    if ($raw === false || strlen($raw) < 29) return '';
    $iv = substr($raw, 0, 12); $tag = substr($raw, 12, 16); $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? '' : $plain;
}
