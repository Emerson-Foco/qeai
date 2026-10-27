<?php

declare(strict_types=1);
define('ROOT_PATH', dirname(__DIR__));
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_exception_handler(function (Throwable $exception): void {
    error_log('[QEAI] ' . $exception->getMessage());
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    header('Retry-After: 300');
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>QEAI — manutenção</title><body style="font-family:system-ui;max-width:600px;margin:12vh auto;padding:24px;line-height:1.6"><h1>Voltamos em breve.</h1><p>Não foi possível carregar o site agora. Tente novamente em alguns minutos.</p></body></html>';
});
require_once ROOT_PATH . '/app/config_store.php';
require_once ROOT_PATH . '/app/url.php';
require_once ROOT_PATH . '/app/security.php';
ensure_storage_directory('');
ensure_storage_directory('rate-limit');
ensure_storage_directory('sessions');
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_save_path(ROOT_PATH . '/storage/sessions');
    session_name('qeai_session');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);
    if (!session_start()) throw new RuntimeException('Não foi possível iniciar a sessão. Verifique as permissões de storage/sessions.');
}
header('Cache-Control: no-store');
$config = load_config();
