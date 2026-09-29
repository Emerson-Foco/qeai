<?php
require __DIR__ . '/app/bootstrap.php';
require_once ROOT_PATH . '/app/mailer.php';

function contact_redirect(array $feedback): void
{
    $_SESSION['contact_feedback'] = $feedback;
    header('Location: ./#contato', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($config['features']['contact_form'])) {
    header('Location: ./#contato', true, 303);
    exit;
}
$data = [];
foreach (['name', 'email', 'company', 'message'] as $key) $data[$key] = post_string($key);
// Bound session data even when a request bypasses browser validation.
foreach ($data as $key => $value) $data[$key] = substr($value, 0, $key === 'message' ? 16000 : 720);
$feedback = ['type' => 'error', 'values' => $data, 'errors' => []];
if (!verify_csrf($_POST['csrf'] ?? null)) {
    $feedback['message'] = 'Sua sessão expirou. Confira os dados e envie novamente.';
    contact_redirect($feedback);
}
if (!empty($_POST['website'])) contact_redirect(['type' => 'error', 'message' => 'Não foi possível enviar a mensagem.']);
if ($data['name'] === '' || text_length(post_string('name')) > 100) $feedback['errors']['name'] = 'Informe seu nome, com até 100 caracteres.';
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen(post_string('email')) > 180) $feedback['errors']['email'] = 'Informe um e-mail válido, com até 180 caracteres.';
if (text_length(post_string('company')) > 140) $feedback['errors']['company'] = 'Use até 140 caracteres para a empresa.';
if ($data['message'] === '' || text_length(post_string('message')) > 4000) $feedback['errors']['message'] = 'Escreva sua mensagem, com até 4.000 caracteres.';
if (post_string('privacy') !== '1') $feedback['errors']['privacy'] = 'Autorize o uso dos dados para receber uma resposta.';
if ($feedback['errors']) {
    $feedback['message'] = 'Revise os campos indicados abaixo.';
    contact_redirect($feedback);
}
try {
    if (!rate_limit('contact', 4, 900)) {
        $feedback['message'] = 'Você atingiu o limite de tentativas. Aguarde 15 minutos ou fale pelo e-mail ao lado.';
        contact_redirect($feedback);
    }
    send_contact_mail($config, $data);
    contact_redirect(['type' => 'ok', 'message' => 'Mensagem enviada! Obrigado pelo contato. Vamos responder pelo e-mail informado.']);
} catch (Throwable $exception) {
    error_log('[QEAI contact] ' . $exception->getMessage());
    $feedback['message'] = 'Não foi possível enviar agora. Seus dados foram mantidos para uma nova tentativa. Você também pode falar pelo e-mail ao lado.';
    contact_redirect($feedback);
}
