<?php

declare(strict_types=1);

require_once ROOT_PATH . '/app/db.php';
require_once ROOT_PATH . '/app/url.php';

/**
 * Armazenamento de mídia do blog com dois drivers:
 *  - local: arquivos no servidor (padrão, usado no desenvolvimento sem S3);
 *  - s3: AWS S3 (upload direto com URL assinada), com migração do que for local.
 */

const STORAGE_MAX_IMAGE_BYTES = 8 * 1024 * 1024;   // 8 MB
const STORAGE_MAX_VIDEO_BYTES = 64 * 1024 * 1024;  // 64 MB
const STORAGE_ALLOWED_MIME = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
    'video/mp4' => 'mp4',
    'video/webm' => 'webm',
];

// --- Configurações (tabela blog_settings) -----------------------------------

function storage_setting_flush(): void
{
    // Reinicia o cache estático de configurações.
    storage_settings(true);
}

function storage_settings(bool $reset = false): array
{
    static $cache = null;
    if ($reset) $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    try {
        foreach (db()->query('SELECT name, value, encrypted FROM blog_settings') as $row) {
            $cache[(string)$row['name']] = (int)$row['encrypted'] === 1
                ? decrypt_secret((string)$row['value'])
                : (string)$row['value'];
        }
    } catch (Throwable $exception) {
        error_log('[QEAI storage] ' . $exception->getMessage());
    }
    return $cache;
}

function storage_setting(string $name, string $default = ''): string
{
    $settings = storage_settings();
    return isset($settings[$name]) ? (string)$settings[$name] : $default;
}

function storage_setting_set(string $name, string $value, bool $encrypted = false): void
{
    $stmt = db()->prepare(
        'INSERT INTO blog_settings (name, value, encrypted) VALUES (:n, :v, :e)'
        . ' ON DUPLICATE KEY UPDATE value = VALUES(value), encrypted = VALUES(encrypted)'
    );
    $stmt->execute([
        ':n' => $name,
        ':v' => $encrypted && $value !== '' ? encrypt_secret($value) : $value,
        ':e' => $encrypted ? 1 : 0,
    ]);
    storage_setting_flush();
}

// --- S3 (SigV4 em PHP puro) --------------------------------------------------

function storage_s3_config(): array
{
    return [
        'bucket' => trim(storage_setting('s3_bucket')),
        'region' => trim(storage_setting('s3_region')),
        'access' => trim(storage_setting('s3_access_key')),
        'secret' => trim(storage_setting('s3_secret_key')),
        'prefix' => trim(storage_setting('s3_prefix', 'blog/')),
        'public_base' => trim(storage_setting('s3_public_base')),
        'endpoint' => trim(storage_setting('s3_endpoint')),
    ];
}

function storage_s3_configured(): bool
{
    $c = storage_s3_config();
    return $c['bucket'] !== '' && $c['region'] !== '' && $c['access'] !== '' && $c['secret'] !== '';
}

function storage_driver(): string
{
    return storage_s3_configured() ? 's3' : 'local';
}

function s3_host(): string
{
    $c = storage_s3_config();
    if ($c['endpoint'] !== '') return preg_replace('#^https?://#', '', rtrim($c['endpoint'], '/'));
    return $c['bucket'] . '.s3.' . $c['region'] . '.amazonaws.com';
}

function s3_canonical_uri(string $key): string
{
    $c = storage_s3_config();
    $key = ltrim($key, '/');
    $encoded = implode('/', array_map('rawurlencode', explode('/', $key)));
    return $c['endpoint'] !== '' ? '/' . $c['bucket'] . '/' . $encoded : '/' . $encoded;
}

function s3_object_url(string $key): string
{
    $c = storage_s3_config();
    if ($c['public_base'] !== '') return rtrim($c['public_base'], '/') . '/' . ltrim($key, '/');
    return 'https://' . s3_host() . s3_canonical_uri($key);
}

function s3_host_url(): string
{
    $c = storage_s3_config();
    return $c['endpoint'] !== '' ? 'https://' . s3_host() . '/' : 'https://' . s3_host();
}

function s3_signing_key(array $config, string $dateStamp): string
{
    $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $config['secret'], true);
    $kRegion = hash_hmac('sha256', $config['region'], $kDate, true);
    $kService = hash_hmac('sha256', 's3', $kRegion, true);
    return hash_hmac('sha256', 'aws4_request', $kService, true);
}

/** URL assinada para o navegador enviar o arquivo direto ao S3 (PUT). */
function s3_presign_put(string $key, int $expires = 900): string
{
    if (!storage_s3_configured()) throw new RuntimeException('S3 não configurado.');
    $c = storage_s3_config();
    $host = s3_host();
    $amzDate = gmdate('Ymd\THis\Z');
    $dateStamp = substr($amzDate, 0, 8);
    $scope = $dateStamp . '/' . $c['region'] . '/s3/aws4_request';
    $params = [
        'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
        'X-Amz-Credential' => $c['access'] . '/' . $scope,
        'X-Amz-Date' => $amzDate,
        'X-Amz-Expires' => (string)$expires,
        'X-Amz-SignedHeaders' => 'host',
    ];
    ksort($params);
    $canonicalQuery = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $canonicalUri = s3_canonical_uri($key);
    $canonicalRequest = implode("\n", ['PUT', $canonicalUri, $canonicalQuery, "host:$host\n", 'host', 'UNSIGNED-PAYLOAD']);
    $stringToSign = "AWS4-HMAC-SHA256\n$amzDate\n$scope\n" . hash('sha256', $canonicalRequest);
    $signature = hash_hmac('sha256', $stringToSign, s3_signing_key($c, $dateStamp));
    return 'https://' . $host . $canonicalUri . '?' . $canonicalQuery . '&X-Amz-Signature=' . $signature;
}

/** Cabeçalhos de uma requisição S3 autenticada (DELETE/PUT/HEAD). */
function s3_auth_headers(string $method, string $key, string $body = '', array $extra = []): array
{
    $c = storage_s3_config();
    $host = s3_host();
    $amzDate = gmdate('Ymd\THis\Z');
    $dateStamp = substr($amzDate, 0, 8);
    $scope = $dateStamp . '/' . $c['region'] . '/s3/aws4_request';
    $payloadHash = hash('sha256', $body);
    $canonicalUri = s3_canonical_uri($key);
    $headers = array_merge(['host' => $host, 'x-amz-content-sha256' => $payloadHash, 'x-amz-date' => $amzDate], $extra);
    ksort($headers);
    $canonicalHeaders = '';
    $signed = [];
    foreach ($headers as $name => $value) {
        $lower = strtolower((string)$name);
        $canonicalHeaders .= $lower . ':' . trim((string)$value) . "\n";
        $signed[] = $lower;
    }
    $signedHeaders = implode(';', $signed);
    $canonicalRequest = implode("\n", [$method, $canonicalUri, '', $canonicalHeaders, $signedHeaders, $payloadHash]);
    $stringToSign = "AWS4-HMAC-SHA256\n$amzDate\n$scope\n" . hash('sha256', $canonicalRequest);
    $signature = hash_hmac('sha256', $stringToSign, s3_signing_key($c, $dateStamp));
    $authorization = 'AWS4-HMAC-SHA256 Credential=' . $c['access'] . '/' . $scope
        . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;
    $out = ['Authorization: ' . $authorization];
    foreach ($headers as $name => $value) $out[] = $name . ': ' . $value;
    return $out;
}

function s3_curl(string $method, string $url, array $headers, ?string $payload = null, bool $headOnly = false): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_NOBODY => $headOnly,
    ]);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($response === false) throw new RuntimeException('Falha de rede no S3: ' . $error);
    return ['status' => $status, 'body' => (string)$response];
}

function s3_put_object(string $key, string $body, string $mime = 'application/octet-stream'): void
{
    $headers = s3_auth_headers('PUT', $key, $body, ['content-type' => $mime]);
    $result = s3_curl('PUT', s3_host_url() . ltrim(s3_canonical_uri($key), '/'), $headers, $body);
    if ($result['status'] < 200 || $result['status'] >= 300) {
        throw new RuntimeException('S3 recusou o envio (' . $result['status'] . ').');
    }
}

function s3_delete_object(string $key): void
{
    $headers = s3_auth_headers('DELETE', $key, '');
    $result = s3_curl('DELETE', s3_host_url() . ltrim(s3_canonical_uri($key), '/'), $headers);
    // 204 (sucesso) e 404 (já não existe) são aceitáveis.
    if ($result['status'] !== 204 && $result['status'] !== 200 && $result['status'] !== 404) {
        throw new RuntimeException('S3 recusou a exclusão (' . $result['status'] . ').');
    }
}

// --- Driver local ------------------------------------------------------------

function storage_local_dir(): string
{
    $dir = ROOT_PATH . '/media/blog';
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Não foi possível criar media/blog.');
    }
    $htaccess = ROOT_PATH . '/media/.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents($htaccess, "<FilesMatch \"\\.(php|phtml|phar|php[0-9])$\">\n    Require all denied\n</FilesMatch>\n");
    }
    return $dir;
}

function storage_normalize_file(array $file): array
{
    $mime = '';
    if (isset($file['tmp_name']) && is_file((string)$file['tmp_name']) && function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_file($finfo, (string)$file['tmp_name']);
            finfo_close($finfo);
            if (is_string($detected)) $mime = $detected;
        }
    }
    if ($mime === '' && isset($file['type'])) $mime = (string)$file['type'];
    if (!isset(STORAGE_ALLOWED_MIME[$mime])) throw new RuntimeException('Tipo de arquivo não permitido (' . ($mime ?: 'desconhecido') . ').');
    $extension = STORAGE_ALLOWED_MIME[$mime];
    $size = (int)($file['size'] ?? 0);
    $max = str_starts_with($mime, 'video/') ? STORAGE_MAX_VIDEO_BYTES : STORAGE_MAX_IMAGE_BYTES;
    if ($size <= 0 || $size > $max) throw new RuntimeException('Arquivo vazio ou acima do limite permitido.');

    $dimensions = [0, 0];
    if (str_starts_with($mime, 'image/')) {
        $info = @getimagesize((string)$file['tmp_name']);
        if (is_array($info)) $dimensions = [(int)$info[0], (int)$info[1]];
    }
    return ['mime' => $mime, 'extension' => $extension, 'size' => $size, 'width' => $dimensions[0], 'height' => $dimensions[1]];
}

function storage_random_name(string $extension): string
{
    return date('Y/m') . '/' . bin2hex(random_bytes(12)) . '.' . $extension;
}

/** Salva um upload no driver ativo e registra em blog_media. */
function storage_store_upload(array $file, string $alt = '', string $title = '', string $uploadedBy = ''): int
{
    $meta = storage_normalize_file($file);
    $name = storage_random_name($meta['extension']);

    if (storage_driver() === 's3') {
        $c = storage_s3_config();
        $key = rtrim($c['prefix'], '/') . '/' . $name;
        $body = file_get_contents((string)$file['tmp_name']);
        if ($body === false) throw new RuntimeException('Não foi possível ler o arquivo enviado.');
        s3_put_object($key, $body, $meta['mime']);
        $driver = 's3';
        $path = $key;
        $url = s3_object_url($key);
        $bucket = $c['bucket'];
    } else {
        $dir = storage_local_dir() . '/' . dirname($name);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('Não foi possível criar a pasta de mídia.');
        $destination = storage_local_dir() . '/' . $name;
        if (!move_uploaded_file((string)$file['tmp_name'], $destination) && !@rename((string)$file['tmp_name'], $destination)) {
            throw new RuntimeException('Não foi possível salvar o arquivo enviado.');
        }
        @chmod($destination, 0640);
        $driver = 'local';
        $path = $name;
        $url = url('media/blog/' . $name);
        $bucket = '';
    }

    $stmt = db()->prepare(
        'INSERT INTO blog_media (driver, bucket, path, url, mime, width, height, size, alt, title, uploaded_by)'
        . ' VALUES (:driver, :bucket, :path, :url, :mime, :width, :height, :size, :alt, :title, :by)'
    );
    $stmt->execute([
        ':driver' => $driver,
        ':bucket' => $bucket,
        ':path' => $path,
        ':url' => $url,
        ':mime' => $meta['mime'],
        ':width' => $meta['width'],
        ':height' => $meta['height'],
        ':size' => $meta['size'],
        ':alt' => $alt,
        ':title' => $title,
        ':by' => $uploadedBy,
    ]);
    return (int)db()->lastInsertId();
}

/** Gera uma chave única para um novo objeto no S3 (upload direto pela API). */
function storage_s3_new_key(string $extension): string
{
    $c = storage_s3_config();
    return rtrim($c['prefix'], '/') . '/' . date('Y/m') . '/' . bin2hex(random_bytes(12)) . '.' . $extension;
}

/** Registra uma mídia que já foi enviada direto ao S3. */
function storage_register_s3_media(string $key, string $mime, int $size = 0, int $width = 0, int $height = 0, string $alt = '', string $title = '', string $uploadedBy = ''): int
{
    if (!storage_s3_configured()) throw new RuntimeException('S3 não configurado.');
    $c = storage_s3_config();
    $key = ltrim($key, '/');
    if (!str_starts_with($key, rtrim($c['prefix'], '/') . '/')) throw new RuntimeException('Chave de objeto fora do prefixo permitido.');
    $stmt = db()->prepare(
        'INSERT INTO blog_media (driver, bucket, path, url, mime, width, height, size, alt, title, uploaded_by)'
        . ' VALUES (\'s3\', :bucket, :path, :url, :mime, :width, :height, :size, :alt, :title, :by)'
    );
    $stmt->execute([
        ':bucket' => $c['bucket'], ':path' => $key, ':url' => s3_object_url($key), ':mime' => $mime,
        ':width' => $width, ':height' => $height, ':size' => $size,
        ':alt' => substr($alt, 0, 300), ':title' => substr($title, 0, 300), ':by' => substr($uploadedBy, 0, 120),
    ]);
    return (int)db()->lastInsertId();
}

/** Remove o arquivo físico de uma mídia (banco é tratado pelo chamador). */
function storage_delete_object_file(array $media): void
{
    try {
        if ($media['driver'] === 's3' && storage_s3_configured()) {
            s3_delete_object((string)$media['path']);
        } elseif ($media['driver'] === 'local') {
            $file = storage_local_dir() . '/' . ltrim((string)$media['path'], '/');
            if (is_file($file)) @unlink($file);
        }
    } catch (Throwable $exception) {
        error_log('[QEAI storage delete] ' . $exception->getMessage());
    }
}

/** Migra toda a mídia local para o S3. Retorna [migradas, falhas]. */
function storage_migrate_local_to_s3(): array
{
    if (!storage_s3_configured()) throw new RuntimeException('Configure o S3 antes de migrar.');
    $c = storage_s3_config();
    $rows = db()->query("SELECT * FROM blog_media WHERE driver = 'local'")->fetchAll();
    $migrated = 0;
    $failed = 0;
    foreach ($rows as $media) {
        $file = storage_local_dir() . '/' . ltrim((string)$media['path'], '/');
        if (!is_file($file)) { $failed++; continue; }
        $body = file_get_contents($file);
        if ($body === false) { $failed++; continue; }
        try {
            $key = rtrim($c['prefix'], '/') . '/' . ltrim((string)$media['path'], '/');
            s3_put_object($key, $body, (string)$media['mime']);
            $stmt = db()->prepare("UPDATE blog_media SET driver = 's3', bucket = :b, path = :p, url = :u WHERE id = :id");
            $stmt->execute([':b' => $c['bucket'], ':p' => $key, ':u' => s3_object_url($key), ':id' => (int)$media['id']]);
            @unlink($file);
            $migrated++;
        } catch (Throwable $exception) {
            error_log('[QEAI storage migrate] ' . $exception->getMessage());
            $failed++;
        }
    }
    return ['migrated' => $migrated, 'failed' => $failed];
}
