<?php

require dirname(__DIR__) . '/app/bootstrap.php';
require_once ROOT_PATH . '/app/url.php';
require_once ROOT_PATH . '/app/auth.php';

if (!auth_check()) { header('Location: ./', true, 303); exit; }
auth_require('site.users');

$message = $_SESSION['panel_message'] ?? '';
$messageType = $_SESSION['panel_message_type'] ?? 'ok';
unset($_SESSION['panel_message'], $_SESSION['panel_message_type']);
$newPassword = $_SESSION['new_user_password'] ?? '';
$newPasswordEmail = $_SESSION['new_user_password_email'] ?? '';
$inviteLink = $_SESSION['new_invite_link'] ?? '';
$inviteEmail = $_SESSION['new_invite_email'] ?? '';
unset($_SESSION['new_user_password'], $_SESSION['new_user_password_email'], $_SESSION['new_invite_link'], $_SESSION['new_invite_email']);

function workspace_invite_url(string $token): string
{
    return site_url('workspace/?token=' . rawurlencode($token));
}

function users_flash(string $message, string $url = 'users.php', string $type = 'ok'): void
{
    $_SESSION['panel_message'] = $message;
    $_SESSION['panel_message_type'] = $type;
    header('Location: ' . $url, true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf($_POST['csrf'] ?? null)) throw new RuntimeException('Sua sessão expirou. Recarregue a página e tente novamente.');
        $action = post_string('action');
        $catalog = array_keys(auth_permissions_catalog());

        if ($action === 'create_user') {
            $roleId = (int)post_string('role_id');
            $access = post_string('access') === 'password' ? 'password' : 'invite';
            $password = post_string('password');
            if ($access === 'invite') {
                $userId = auth_user_create(post_string('name'), post_string('email'), auth_generate_password(), $roleId, false, auth_user_id());
                $raw = auth_create_token($userId, 'invite', 604800);
                $_SESSION['new_invite_link'] = workspace_invite_url($raw);
                $_SESSION['new_invite_email'] = post_string('email');
            } else {
                $temporary = $password === '';
                if ($temporary) $password = auth_generate_password();
                $userId = auth_user_create(post_string('name'), post_string('email'), $password, $roleId, $temporary, auth_user_id());
                if ($temporary) { $_SESSION['new_user_password'] = $password; $_SESSION['new_user_password_email'] = post_string('email'); }
            }
            users_flash('Usuário criado.');
        } elseif ($action === 'invite_user') {
            $id = (int)post_string('id');
            $user = auth_user($id);
            if ($user === null) throw new RuntimeException('Usuário não encontrado.');
            auth_invalidate_tokens($id, 'invite');
            $raw = auth_create_token($id, 'invite', 604800);
            $_SESSION['new_invite_link'] = workspace_invite_url($raw);
            $_SESSION['new_invite_email'] = (string)$user['email'];
            users_flash('Link de acesso gerado.');
        } elseif ($action === 'update_user') {
            auth_user_update((int)post_string('id'), post_string('name'), post_string('email'), (int)post_string('role_id'), post_string('status'));
            users_flash('Usuário atualizado.');
        } elseif ($action === 'reset_password') {
            $id = (int)post_string('id');
            $user = auth_user($id);
            if ($user === null) throw new RuntimeException('Usuário não encontrado.');
            $password = auth_generate_password();
            auth_user_set_password($id, $password, true);
            $_SESSION['new_user_password'] = $password;
            $_SESSION['new_user_password_email'] = (string)$user['email'];
            users_flash('Senha provisória gerada.');
        } elseif ($action === 'delete_user') {
            $id = (int)post_string('id');
            if ($id === auth_user_id()) throw new RuntimeException('Você não pode excluir o próprio usuário.');
            auth_user_delete($id);
            users_flash('Usuário excluído.');
        } elseif ($action === 'save_role') {
            $permissions = isset($_POST['permissions']) && is_array($_POST['permissions']) ? array_map('strval', $_POST['permissions']) : [];
            $roleId = (int)post_string('id');
            auth_role_save($roleId > 0 ? $roleId : null, post_string('name'), $permissions);
            users_flash('Grupo de acesso salvo.');
        } elseif ($action === 'delete_role') {
            auth_role_delete((int)post_string('id'));
            users_flash('Grupo excluído.');
        }
    } catch (Throwable $exception) {
        error_log('[QEAI users] ' . $exception->getMessage());
        $message = $exception instanceof RuntimeException ? $exception->getMessage() : 'Não foi possível concluir a operação.';
        $messageType = 'err';
    }
}

$users = auth_users();
$roles = auth_roles();
$catalog = auth_permissions_catalog();
$currentUser = auth_current();
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Usuários e grupos — QEAI</title><link rel="icon" href="../assets/img/logo.svg"><link rel="stylesheet" href="../assets/css/workspace.css?v=3"><link rel="stylesheet" href="../assets/css/blog-admin.css?v=2"></head><body>
<header class="panel-header"><a href="../"><img src="../assets/img/logo.svg" alt="QEAI" width="110"></a><span>Painel do site</span><a href="../" target="_blank" rel="noopener">Ver site ↗</a></header>
<main class="wrap">
<nav class="panel-nav" aria-label="Seções do painel">
  <a href="./">Visão geral</a>
  <?php if (auth_can('blog.view')): ?><a href="blog.php">Blog</a><?php endif; ?>
  <a class="is-active" href="users.php">Usuários e grupos</a>
  <span class="spacer"></span>
  <span class="panel-user"><?=e((string)$currentUser['name'] ?: (string)$currentUser['email'])?></span>
</nav>
<?php if ($message): ?><div class="status <?=$messageType === 'err' ? 'err' : 'ok'?>" role="<?=$messageType === 'err' ? 'alert' : 'status'?>"><?=e($message)?></div><?php endif; ?>
<?php if ($newPassword !== ''): ?>
<div class="status ok" role="status">Senha provisória de <strong><?=e($newPasswordEmail)?></strong>: <code class="password-reveal"><?=e($newPassword)?></code><br><small>Copie e envie para a pessoa. Ela será obrigada a trocar no primeiro acesso. Esta senha não será exibida novamente.</small></div>
<?php endif; ?>
<?php if ($inviteLink !== ''): ?>
<div class="status ok" role="status">Link de acesso de <strong><?=e($inviteEmail)?></strong>:<br><code class="password-reveal"><?=e($inviteLink)?></code><br><small>Envie este link para a pessoa definir a senha. Vale por 7 dias e não será exibido novamente.</small></div>
<?php endif; ?>
<div class="top"><div><span class="eyebrow">Acessos</span><h1>Usuários e grupos</h1><p class="muted">Cada grupo define o que a pessoa pode fazer no painel.</p></div></div>

<section class="box"><h2>Adicionar usuário</h2>
  <form method="post" class="grid"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="create_user">
    <label>Nome<input name="name" maxlength="120" required></label>
    <label>E-mail<input type="email" name="email" required autocomplete="off"></label>
    <label>Grupo de acesso<select name="role_id" required><?php foreach ($roles as $role): ?><option value="<?=(int)$role['id']?>"><?=e((string)$role['name'])?></option><?php endforeach; ?></select></label>
    <label>Forma de acesso<select name="access"><option value="invite">Convidar por link (recomendado)</option><option value="password">Definir senha agora</option></select></label>
    <label>Senha (só para "Definir senha agora")<input type="text" name="password" minlength="10" maxlength="72" autocomplete="off" placeholder="Deixe vazio para gerar uma provisória"><small>No convite por link, a própria pessoa cria a senha.</small></label>
    <div class="full"><button class="btn">Adicionar usuário</button></div>
  </form>
</section>

<section class="box"><h2>Usuários</h2>
  <table class="admin-table"><thead><tr><th>Nome</th><th>E-mail</th><th>Grupo</th><th>Status</th><th>Último acesso</th><th class="actions">Ações</th></tr></thead><tbody>
  <?php foreach ($users as $user): ?>
    <tr>
      <td colspan="6">
        <form method="post" class="row-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="update_user"><input type="hidden" name="id" value="<?=(int)$user['id']?>">
          <input name="name" value="<?=e((string)$user['name'])?>" maxlength="120" aria-label="Nome">
          <input type="email" name="email" value="<?=e((string)$user['email'])?>" aria-label="E-mail">
          <select name="role_id" aria-label="Grupo"><?php foreach ($roles as $role): ?><option value="<?=(int)$role['id']?>" <?=(int)$user['role_id'] === (int)$role['id'] ? 'selected' : ''?>><?=e((string)$role['name'])?></option><?php endforeach; ?></select>
          <select name="status" aria-label="Status"><option value="active" <?=$user['status'] === 'active' ? 'selected' : ''?>>Ativo</option><option value="disabled" <?=$user['status'] === 'disabled' ? 'selected' : ''?>>Desativado</option></select>
          <button class="btn secondary small">Salvar</button>
          <small><?= $user['last_login_at'] ? 'Último acesso: ' . e(date('d/m/Y H:i', strtotime((string)$user['last_login_at']))) : 'Nunca acessou' ?></small>
        </form>
        <div class="row-actions inline">
          <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="invite_user"><input type="hidden" name="id" value="<?=(int)$user['id']?>"><button class="btn secondary small">Gerar link de acesso</button></form>
          <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="<?=(int)$user['id']?>"><button class="btn secondary small">Gerar senha provisória</button></form>
          <?php if ((int)$user['id'] !== auth_user_id()): ?><form method="post" onsubmit="return confirm('Excluir este usuário?');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?=(int)$user['id']?>"><button class="btn danger small">Excluir</button></form><?php endif; ?>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</section>

<section class="box"><h2>Grupos de acesso</h2>
  <p class="muted">Marque o que cada grupo pode fazer. Grupos padrão podem ser ajustados, mas não excluídos.</p>
  <?php foreach ($roles as $role): $rolePerms = auth_role_permissions($role); ?>
    <div class="role-block">
      <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_role"><input type="hidden" name="id" value="<?=(int)$role['id']?>">
        <div class="role-head"><input name="name" value="<?=e((string)$role['name'])?>" maxlength="80" aria-label="Nome do grupo"></div>
        <div class="checks">
          <?php foreach ($catalog as $permission => $label): ?>
          <label class="check"><input type="checkbox" name="permissions[]" value="<?=e($permission)?>" <?=in_array('*', $rolePerms, true) || in_array($permission, $rolePerms, true) ? 'checked' : ''?>><span><?=e($label)?> <code><?=e($permission)?></code></span></label>
          <?php endforeach; ?>
        </div>
        <?php if (in_array('*', $rolePerms, true)): ?><p class="hint">Este grupo tem acesso total.</p><?php endif; ?>
        <div><button class="btn secondary small">Salvar grupo</button></div>
      </form>
      <?php if ((int)$role['is_system'] !== 1): ?><form method="post" class="role-delete" onsubmit="return confirm('Excluir este grupo?');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_role"><input type="hidden" name="id" value="<?=(int)$role['id']?>"><button class="btn danger small">Excluir grupo</button></form><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <form method="post" class="role-block new-role"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_role"><input type="hidden" name="id" value="0">
    <div class="role-head"><input name="name" placeholder="Nome do novo grupo" maxlength="80" required aria-label="Nome do novo grupo"></div>
    <div class="checks">
      <?php foreach ($catalog as $permission => $label): ?>
      <label class="check"><input type="checkbox" name="permissions[]" value="<?=e($permission)?>"><span><?=e($label)?> <code><?=e($permission)?></code></span></label>
      <?php endforeach; ?>
    </div>
    <div><button class="btn">Criar grupo</button></div>
  </form>
</section>
<p class="panel-footer">QEAI · Marketing, pessoas e tecnologia.</p></main></body></html>
