<?php

declare(strict_types=1);

function smtp_read($socket, array $expected): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') break;
    }
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $expected, true)) throw new RuntimeException('SMTP respondeu ' . $code . ': ' . trim($response));
    return $response;
}

function smtp_cmd($socket, string $command, array $expected): string
{
    smtp_write($socket, $command . "\r\n");
    return smtp_read($socket, $expected);
}

function smtp_write($socket, string $data): void
{
    $length = strlen($data);
    for ($offset = 0; $offset < $length; $offset += $written) {
        $written = fwrite($socket, substr($data, $offset));
        if ($written === false || $written === 0) throw new RuntimeException('Falha ao escrever no SMTP.');
    }
}

function smtp_address(string $email): string
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email)) throw new RuntimeException('Endereço de e-mail inválido.');
    return $email;
}

function mime_header(string $text): string
{
    if (preg_match('/[\r\n]/', $text)) $text = str_replace(["\r","\n"], '', $text);
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

function smtp_send(array $config, string $to, string $subject, string $html, string $text, ?string $replyEmail = null, ?string $replyName = null): void
{
    $s = $config['smtp'];
    $host = trim((string)$s['host']); $port = (int)$s['port']; $enc = (string)$s['encryption'];
    if (!extension_loaded('openssl')) throw new RuntimeException('Ative a extensão OpenSSL na hospedagem para enviar e-mails.');
    if (!in_array($enc, ['ssl', 'tls'], true)) throw new RuntimeException('Configure SMTP com SSL/TLS ou STARTTLS.');
    if (!preg_match('/^[a-zA-Z0-9](?:[a-zA-Z0-9.-]*[a-zA-Z0-9])?$/', $host) || $port < 1 || $port > 65535) throw new RuntimeException('Host ou porta SMTP inválidos.');
    $user = smtp_address((string)$s['username']); $password = decrypt_secret((string)$s['password_enc']);
    $to = smtp_address($to);
    if ($host === '' || $password === '') throw new RuntimeException('SMTP incompleto. Configure host, usuário e senha.');

    $remote = ($enc === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false]]);
    $socket = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$socket) throw new RuntimeException("Não foi possível conectar ao SMTP: {$errstr} ({$errno}).");
    stream_set_timeout($socket, 15);
    try {
        smtp_read($socket, [220]);
        $ehlo = $_SERVER['SERVER_NAME'] ?? 'qeai.com.br';
        smtp_cmd($socket, 'EHLO ' . preg_replace('/[^A-Za-z0-9.\-]/', '', $ehlo), [250]);
        if ($enc === 'tls') {
            smtp_cmd($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('Falha ao iniciar STARTTLS.');
            smtp_cmd($socket, 'EHLO ' . preg_replace('/[^A-Za-z0-9.\-]/', '', $ehlo), [250]);
        }
        smtp_cmd($socket, 'AUTH LOGIN', [334]);
        smtp_cmd($socket, base64_encode($user), [334]);
        smtp_cmd($socket, base64_encode($password), [235]);
        smtp_cmd($socket, 'MAIL FROM:<' . $user . '>', [250]);
        smtp_cmd($socket, 'RCPT TO:<' . $to . '>', [250,251]);
        smtp_cmd($socket, 'DATA', [354]);

        $boundary = 'b_' . bin2hex(random_bytes(12));
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . mime_header((string)$s['from_name']) . ' <' . $user . '>',
            'To: <' . $to . '>',
            'Subject: ' . mime_header($subject),
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        if ($replyEmail !== null && filter_var($replyEmail, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . ($replyName ? mime_header($replyName) . ' ' : '') . '<' . smtp_address($replyEmail) . '>';
        }
        $body = '--' . $boundary . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text)) . "\r\n";
        $body .= '--' . $boundary . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "\r\n--" . $boundary . "--\r\n";
        $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;
        $message = preg_replace('/^\./m', '..', $message);
        smtp_write($socket, $message . "\r\n.\r\n");
        smtp_read($socket, [250]);
        // DATA 250 confirms acceptance. A disconnect on QUIT must not cause duplicate submissions.
        @fwrite($socket, "QUIT\r\n");
    } finally { fclose($socket); }
}

function send_contact_mail(array $config, array $data): void
{
    $name = e($data['name']); $email = e($data['email']); $company = e($data['company']); $message = nl2br(e($data['message']));
    $html = "<h2>Novo contato pelo site</h2><p><strong>Nome:</strong> {$name}</p><p><strong>E-mail:</strong> {$email}</p><p><strong>Empresa:</strong> {$company}</p><p><strong>Mensagem:</strong><br>{$message}</p>";
    $text = "Novo contato pelo site\nNome: {$data['name']}\nE-mail: {$data['email']}\nEmpresa: {$data['company']}\n\n{$data['message']}";
    smtp_send($config, $config['smtp']['to_email'], 'Novo contato pelo site QEAI — ' . $data['name'], $html, $text, $data['email'], $data['name']);
}

function send_test_mail(array $config): void
{
    smtp_send($config, $config['smtp']['to_email'], 'QEAI — teste de SMTP', '<p>Se você recebeu este e-mail, a configuração SMTP da QEAI está funcionando.</p>', 'Se você recebeu este e-mail, a configuração SMTP da QEAI está funcionando.');
}
