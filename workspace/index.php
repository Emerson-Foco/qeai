<?php

require dirname(__DIR__) . '/app/bootstrap.php';
require_once ROOT_PATH . '/app/mailer.php';
require_once ROOT_PATH . '/app/auth.php';
require_once ROOT_PATH . '/app/url.php';

$installed = auth_installed();
$message = $_SESSION['panel_message'] ?? '';
unset($_SESSION['panel_message']);
$error = '';
function panel_success(string $message): void
{
    $_SESSION['panel_message'] = $message;
    header('Location: ./', true, 303);
    exit;
}

function workspace_base_url(): string
{
    return site_url('workspace');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = post_string('action');
        if (!verify_csrf($_POST['csrf'] ?? null)) throw new RuntimeException('Sua sessão expirou. Recarregue a página e tente novamente.');

        if ($action === 'setup' && !$installed) {
            if (!rate_limit('workspace-setup', 6, 900)) throw new RuntimeException('Muitas tentativas de instalação. Aguarde 15 minutos.');
            $setupKey = trim((string)@file_get_contents(ROOT_PATH . '/config/bootstrap.key'));
            if ($setupKey === '' || !hash_equals($setupKey, post_string('setup_key'))) throw new RuntimeException('Chave de instalação inválida ou ausente. Confira config/bootstrap.key.');
            $name = post_string('name') ?: 'ADM';
            $email = post_string('email');
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Informe um e-mail válido para o ADM.');
            if (strlen($password) < 10 || strlen($password) > 72) throw new RuntimeException('Use uma senha de 10 a 72 bytes.');

            $setupLock = fopen(ROOT_PATH . '/storage/setup.lock', 'c');
            if (!$setupLock) throw new RuntimeException('Não foi possível iniciar a instalação. Verifique storage/.');
            try {
                if (!flock($setupLock, LOCK_EX)) throw new RuntimeException('Não foi possível bloquear a instalação.');
                if (auth_installed()) throw new RuntimeException('A instalação já foi concluída. Recarregue a página para entrar.');
                auth_install_schema();
                $admRoleId = (int)db()->query("SELECT id FROM roles WHERE slug = 'adm' LIMIT 1")->fetchColumn();
                auth_user_create($name, $email, $password, $admRoleId, false, null);
                if (file_put_contents(ROOT_PATH . '/storage/installed.lock', date(DATE_ATOM)) === false) error_log('[QEAI] Falha ao gravar o marcador de instalação.');
            } finally { flock($setupLock, LOCK_UN); fclose($setupLock); }
            panel_success('Configuração inicial concluída. Entre com seu e-mail e senha.');
        } elseif ($action === 'login' && $installed) {
            if (!rate_limit('workspace-login', 8, 900)) throw new RuntimeException('Muitas tentativas. Aguarde 15 minutos.');
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            if (!auth_login(post_string('email'), $password)) throw new RuntimeException('E-mail ou senha incorretos.');
            panel_success('Bem-vindo ao painel da QEAI.');
        } elseif ($action === 'forgot' && $installed) {
            if (!rate_limit('workspace-forgot', 5, 900)) throw new RuntimeException('Muitas solicitações. Aguarde 15 minutos.');
            $email = post_string('email');
            $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? auth_user_by_email($email) : null;
            if ($user !== null && $user['status'] === 'active') {
                auth_invalidate_tokens((int)$user['id'], 'reset');
                $raw = auth_create_token((int)$user['id'], 'reset', 3600);
                $link = site_url('workspace/?token=' . rawurlencode($raw));
                $html = '<p>Recebemos um pedido para redefinir sua senha no painel da QEAI.</p><p><a href="' . e($link) . '">Definir nova senha</a></p><p>O link vale por 1 hora. Se não foi você, ignore este e-mail.</p>';
                $text = "Redefinir senha no painel da QEAI:\n" . $link . "\nO link vale por 1 hora.";
                try { smtp_send($config, (string)$user['email'], 'QEAI — redefinição de senha', $html, $text); }
                catch (Throwable $exception) { error_log('[QEAI forgot] ' . $exception->getMessage()); }
            }
            $_SESSION['panel_message'] = 'Se o e-mail estiver cadastrado, enviamos um link para redefinir a senha.';
            header('Location: ./?forgot=1', true, 303);
            exit;
        } elseif ($action === 'set_password' && !auth_check() && $installed) {
            if (!rate_limit('workspace-setpass', 10, 900)) throw new RuntimeException('Muitas tentativas. Aguarde 15 minutos.');
            $token = post_string('token');
            $new = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
            $confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
            $entry = auth_find_any_token($token);
            if ($entry === null) throw new RuntimeException('Link inválido ou expirado. Solicite um novo.');
            if ($new !== $confirm) throw new RuntimeException('As senhas não conferem.');
            auth_user_set_password((int)$entry['user_id'], $new, false);
            db()->prepare("UPDATE users SET status = 'active' WHERE id = :id")->execute([':id' => (int)$entry['user_id']]);
            auth_consume_token((int)$entry['id']);
            panel_success('Senha definida. Entre com seu e-mail e a nova senha.');
        } elseif ($action === 'logout') {
            if (!auth_check()) throw new RuntimeException('Sessão expirada.');
            auth_logout();
            header('Location: ./', true, 303);
            exit;
        } elseif (auth_check()) {
            $currentUser = auth_current();
            if ($action === 'password') {
                $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
                $new = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
                if (!rate_limit('workspace-password', 6, 900)) throw new RuntimeException('Muitas tentativas. Aguarde 15 minutos.');
                if (!password_verify($current, (string)$currentUser['password_hash'])) throw new RuntimeException('A senha atual está incorreta.');
                auth_user_set_password((int)$currentUser['id'], $new, false);
                session_regenerate_id(true);
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                panel_success('Senha atualizada.');
            } elseif ($action === 'save' && auth_can('site.settings') && empty($currentUser['must_change_password'])) {
                $updated = $config;
                foreach (['contact_email', 'smtp_username', 'to_email'] as $field) if (!filter_var(post_string($field), FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Confira os endereços de e-mail informados.');
                $host = post_string('smtp_host');
                if (strlen($host) > 253 || !preg_match('/^[a-zA-Z0-9](?:[a-zA-Z0-9.-]*[a-zA-Z0-9])?$/', $host)) throw new RuntimeException('Informe um host SMTP válido, sem protocolo ou porta.');
                $port = filter_var(post_string('smtp_port'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
                $encryption = post_string('smtp_encryption');
                if (!$port || !in_array($encryption, ['ssl', 'tls'], true)) throw new RuntimeException('Confira a porta e a criptografia SMTP.');
                $number = preg_replace('/\D+/', '', post_string('whatsapp_number'));
                if (isset($_POST['whatsapp_enabled']) && !preg_match('/^\d{10,15}$/', $number)) throw new RuntimeException('Para ativar o WhatsApp, informe o número com DDI e DDD.');
                $cnpj = normalize_cnpj(post_string('cnpj'));
                if ($cnpj !== '' && !cnpj_is_valid($cnpj)) throw new RuntimeException('Informe um CNPJ válido, com 14 dígitos.');
                $updated['features'] = ['contact_form' => isset($_POST['contact_form']), 'whatsapp' => isset($_POST['whatsapp_enabled'])];
                $updated['whatsapp'] = ['number' => $number, 'message' => substr(post_string('whatsapp_message'), 0, 1000)];
                $updated['site']['contact_email'] = post_string('contact_email');
                $updated['site']['cnpj'] = $cnpj;
                foreach (['primary', 'secondary', 'accent', 'dark'] as $key) $updated['theme'][$key] = safe_hex(post_string($key), $config['theme'][$key]);
                $updated['smtp'] = array_merge($config['smtp'], ['host' => $host, 'port' => $port, 'encryption' => $encryption, 'username' => post_string('smtp_username'), 'from_name' => substr(post_string('from_name'), 0, 100), 'to_email' => post_string('to_email')]);
                if (is_string($_POST['smtp_password'] ?? null) && $_POST['smtp_password'] !== '') $updated['smtp']['password_enc'] = encrypt_secret($_POST['smtp_password']);
                if (!save_config($updated)) throw new RuntimeException('Não foi possível salvar. Verifique as permissões de storage/.');
                panel_success('Configurações salvas.');
            } elseif ($action === 'test_email' && auth_can('site.settings') && empty($currentUser['must_change_password'])) {
                if (!rate_limit('workspace-email', 4, 900)) throw new RuntimeException('Limite de tentativas atingido. Aguarde 15 minutos.');
                send_test_mail($config);
                panel_success('E-mail de verifica��o enviado para ' . $config['smtp']['to_email'] . '.');
            }
        }
    } catch (Throwable $exception) {
        error_log('[QEAI workspace] ' . $exception->getMessage());
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Não foi possível concluir a operação. Confira o log PHP da hospedagem.';
    }
}

$currentUser = auth_check() ? auth_current() : null;
$mustChange = $currentUser && !empty($currentUser['must_change_password']);
$canSettings = $currentUser && auth_can('site.settings');
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>QEAI — Painel</title><link rel="icon" href="../assets/img/logo.svg"><link rel="stylesheet" href="../assets/css/workspace.css?v=3"><link rel="stylesheet" href="../assets/css/blog-admin.css?v=2"></head><body>
<header class="panel-header"><a href="../"><img src="../assets/img/logo.svg" alt="QEAI" width="110"></a><span>Painel do site</span><a href="../" target="_blank" rel="noopener">Ver site ↗</a></header>
<main class="wrap">
<?php if ($message): ?><div class="status ok" role="status"><?=e($message)?></div><?php endif; ?>
<?php if ($error): ?><div class="status err" role="alert"><?=e($error)?></div><?php endif; ?>
<?php if (!$installed): ?>
<section class="box auth-box"><span class="eyebrow">Primeiro acesso</span><h1>Vamos preparar seu painel.</h1><p class="muted">Informe a chave do arquivo <code>config/bootstrap.key</code> e crie o ADM (acesso principal) com e-mail e senha.</p><form method="post" class="grid"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="setup"><label class="full">Chave de instalação<input name="setup_key" required autocomplete="off"></label><label>Nome<input name="name" value="ADM" maxlength="120"></label><label>E-mail<input type="email" name="email" required autocomplete="email" placeholder="voce@empresa.com.br"></label><label class="full">Senha<input type="password" name="password" minlength="10" maxlength="72" required autocomplete="new-password"><small>Use pelo menos 10 caracteres.</small></label><button class="btn full">Criar ADM</button></form></section>
<?php elseif (!$currentUser): ?>
<?php if (isset($_GET['token'])): $tokenEntry = auth_find_any_token((string)$_GET['token']); ?>
  <?php if ($tokenEntry === null): ?>
  <section class="box auth-box"><span class="eyebrow">Acesso</span><h1>Link inválido ou expirado.</h1><p class="muted">Solicite um novo link de acesso ou redefinição de senha.</p><p><a class="btn btn-light" href="./?forgot=1">Solicitar novo link</a></p></section>
  <?php else: ?>
  <section class="box auth-box"><span class="eyebrow">Definir senha</span><h1>Crie sua nova senha.</h1><p class="muted">Para <strong><?=e((string)$tokenEntry['email'])?></strong>.</p><form method="post" class="grid"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="set_password"><input type="hidden" name="token" value="<?=e((string)$_GET['token'])?>"><label class="full">Nova senha<input type="password" name="new_password" minlength="10" maxlength="72" required autocomplete="new-password"><small>Use pelo menos 10 caracteres.</small></label><label class="full">Confirme a nova senha<input type="password" name="confirm_password" minlength="10" maxlength="72" required autocomplete="new-password"></label><button class="btn full">Salvar senha</button></form></section>
  <?php endif; ?>
<?php elseif (isset($_GET['forgot'])): ?>
  <section class="box auth-box"><span class="eyebrow">Recuperar acesso</span><h1>Esqueceu a senha?</h1><p class="muted">Informe seu e-mail e enviaremos um link para redefinir a senha.</p><form method="post" class="grid"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="forgot"><label class="full">E-mail<input type="email" name="email" required autocomplete="email"></label><button class="btn full">Enviar link</button></form><p class="hint"><a href="./">Voltar ao login</a></p></section>
<?php else: ?>
<section class="box auth-box"><span class="eyebrow">Seu espaço de gestão</span><h1>Bem-vindo de volta.</h1><p class="muted">Entre com seu e-mail para gerenciar o site.</p><form method="post" class="grid"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="login"><label class="full">E-mail<input type="email" name="email" required autocomplete="username"></label><label class="full">Senha<input type="password" name="password" required autocomplete="current-password"></label><button class="btn full">Entrar no painel</button></form><p class="hint"><a href="./?forgot=1">Esqueci minha senha</a></p></section>
<?php endif; ?>
<?php else: ?>
<nav class="panel-nav" aria-label="Seções do painel">
  <a class="is-active" href="./">Visão geral</a>
  <?php if (auth_can('blog.view')): ?><a href="blog.php">Blog</a><?php endif; ?>
  <?php if (auth_can('site.users')): ?><a href="users.php">Usuários e grupos</a><?php endif; ?>
  <span class="spacer"></span>
  <span class="panel-user"><?=e((string)$currentUser['name'] ?: (string)$currentUser['email'])?> · <?=e((string)($currentUser['role_name'] ?? '—'))?></span>
</nav>
<div class="top"><div><span class="eyebrow">Visão geral</span><h1>Olá, <?=e((string)($currentUser['name'] ?: $currentUser['email']))?>.</h1><p class="muted"><?= $mustChange ? 'Defina uma nova senha para continuar.' : 'Gerencie os canais de contato, a identidade visual e os envios.' ?></p></div><form action="logout.php" method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><button class="btn secondary">Sair</button></form></div>
<?php if ($mustChange): ?>
<section class="box"><h2>Defina sua senha</h2><p class="muted">Por segurança, troque a senha provisória antes de usar o painel.</p><form method="post" class="grid"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="password"><label>Senha provisória<input type="password" name="current_password" required autocomplete="current-password"></label><label>Nova senha<input type="password" name="new_password" minlength="10" maxlength="72" required autocomplete="new-password"></label><div class="full"><button class="btn">Salvar nova senha</button></div></form></section>
<?php elseif (!$canSettings): ?>
<section class="box"><h2>Bem-vindo</h2><p class="muted">Seu grupo de acesso não inclui as configurações do site. Use o menu acima para acessar o que estiver disponível.</p></section>
<?php else: ?>
<section class="box"><h2>Saúde da instalação</h2><div class="health-grid"><div><span>PHP</span><strong><?=e(PHP_VERSION)?></strong></div><div><span>Criptografia</span><strong><?=extension_loaded('openssl') ? 'OpenSSL ativo' : 'Ative OpenSSL'?></strong></div><div><span>Armazenamento</span><strong><?=is_writable(ROOT_PATH . '/storage') ? 'Disponível' : 'Revisar permissões'?></strong></div><div><span>E-mail</span><strong><?=!empty($config['smtp']['password_enc']) ? 'Senha configurada' : 'Configurar SMTP'?></strong></div></div><p class="hint">Depois de configurar o SMTP, envie uma verifica��o para confirmar a entrega.</p></section>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save">
<section class="box"><h2>Canais de contato</h2><p class="muted">Escolha como os visitantes conversam com você.</p><div class="grid"><label class="check"><input type="checkbox" name="contact_form" <?=!empty($config['features']['contact_form']) ? 'checked' : ''?>><span>Ativar formulário de contato</span></label><label class="check"><input type="checkbox" name="whatsapp_enabled" <?=!empty($config['features']['whatsapp']) ? 'checked' : ''?>><span>Ativar botão do WhatsApp</span></label><label>WhatsApp com DDI e DDD<input name="whatsapp_number" value="<?=e($config['whatsapp']['number'])?>" placeholder="5511999999999" inputmode="tel" maxlength="25"></label><label>E-mail público<input type="email" name="contact_email" value="<?=e($config['site']['contact_email'])?>" required></label><label>CNPJ<input name="cnpj" value="<?=e(format_cnpj((string)($config['site']['cnpj'] ?? '')))?>" placeholder="00.000.000/0000-00" inputmode="numeric" maxlength="18"><small>Aparece no rodapé do site. Deixe vazio para ocultar.</small></label><label class="full">Mensagem inicial do WhatsApp<textarea name="whatsapp_message" maxlength="1000"><?=e($config['whatsapp']['message'])?></textarea></label></div></section>
<section class="box"><h2>Identidade visual</h2><p class="muted">Personalize as cores da marca.</p><div class="grid colors"><?php foreach (['primary' => 'Primária', 'secondary' => 'Secundária', 'accent' => 'Destaque', 'dark' => 'Escura'] as $key => $label): ?><label><?=e($label)?><input type="color" name="<?=e($key)?>" value="<?=e(safe_hex($config['theme'][$key], default_config()['theme'][$key]))?>"></label><?php endforeach; ?></div></section>
<section class="box"><h2>Envio de e-mails</h2><p class="muted">Use os dados da sua caixa de e-mail na Hostinger. Salve antes de enviar uma verifica��o.</p><div class="grid"><label>Servidor SMTP<input name="smtp_host" value="<?=e($config['smtp']['host'])?>" required placeholder="smtp.hostinger.com"></label><label>Porta<input type="number" name="smtp_port" value="<?=e((string)$config['smtp']['port'])?>" min="1" max="65535" required></label><label>Conexão segura<select name="smtp_encryption"><option value="ssl" <?=$config['smtp']['encryption'] === 'ssl' ? 'selected' : ''?>>SSL/TLS — normalmente porta 465</option><option value="tls" <?=$config['smtp']['encryption'] === 'tls' ? 'selected' : ''?>>STARTTLS — normalmente porta 587</option></select></label><label>Usuário (e-mail completo)<input type="email" name="smtp_username" value="<?=e($config['smtp']['username'])?>" required autocomplete="off"></label><label>Senha da caixa de e-mail<input type="password" name="smtp_password" autocomplete="new-password" placeholder="Deixe vazio para manter a atual"></label><label>Nome do remetente<input name="from_name" value="<?=e($config['smtp']['from_name'])?>" maxlength="100" required></label><label class="full">Receber mensagens neste e-mail<input type="email" name="to_email" value="<?=e($config['smtp']['to_email'])?>" required></label></div></section>
<div class="save-bar"><span>As alterações são aplicadas ao salvar.</span><button class="btn">Salvar configurações</button></div></form>
<section class="box"><h2>Verifica��o de entrega</h2><p class="muted">Envie uma mensagem para o destinatário salvo acima.</p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="test_email"><button class="btn secondary">Enviar e-mail de verifica��o</button></form></section>
<?php endif; ?>
<section class="box"><h2>Segurança do acesso</h2><form method="post" class="grid"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="password"><label>Senha atual<input type="password" name="current_password" required autocomplete="current-password"></label><label>Nova senha<input type="password" name="new_password" minlength="10" maxlength="72" required autocomplete="new-password"></label><div class="full"><button class="btn secondary">Atualizar senha</button></div></form></section>
<?php endif; ?>
<p class="panel-footer">QEAI · Marketing, pessoas e tecnologia.</p></main></body></html>
