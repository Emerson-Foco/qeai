<?php

require dirname(__DIR__) . '/app/bootstrap.php';
require_once ROOT_PATH . '/app/url.php';
require_once ROOT_PATH . '/app/blog.php';
require_once ROOT_PATH . '/app/blog_admin.php';
require_once ROOT_PATH . '/app/storage.php';
require_once ROOT_PATH . '/app/auth.php';

if (!auth_check()) {
    header('Location: ./', true, 303);
    exit;
}
auth_require('blog.view');

$self = 'blog.php';
$notice = $_SESSION['blog_message'] ?? '';
$noticeType = $_SESSION['blog_message_type'] ?? 'ok';
unset($_SESSION['blog_message'], $_SESSION['blog_message_type']);

function blog_admin_flash(string $message, string $type, string $url): void
{
    $_SESSION['blog_message'] = $message;
    $_SESSION['blog_message_type'] = $type;
    header('Location: ' . $url, true, 303);
    exit;
}

function blog_status_label(string $status): string
{
    return match ($status) {
        'published' => 'Publicado',
        'scheduled' => 'Agendado',
        'trash' => 'Lixeira',
        default => 'Rascunho',
    };
}

function blog_asset(string $path): string
{
    return url($path) . '?v=' . (is_file(ROOT_PATH . '/' . $path) ? (string)filemtime(ROOT_PATH . '/' . $path) : '1');
}

/** Campos do formulário de post, sem salvar nada. */
function blog_input_from_post(): array
{
    return [
        'title' => post_string('title'),
        'slug' => post_string('slug'),
        'excerpt' => post_string('excerpt'),
        'body_md' => (string)($_POST['body_md'] ?? ''),
        'status' => post_string('status'),
        'published_at' => post_string('published_at'),
        'seo_title' => post_string('seo_title'),
        'seo_description' => post_string('seo_description'),
        'canonical_url' => post_string('canonical_url'),
        'category_id' => (int)post_string('category_id'),
        'new_category' => post_string('new_category'),
        'cover_media_id' => (int)post_string('cover_media_id'),
        'featured' => isset($_POST['featured']),
        'tags' => post_string('tags'),
    ];
}

/**
 * Pré-visualização privada: renderiza o post como ele ficará no blog e lista as
 * configurações. Só é acessível com sessão autenticada (robots noindex).
 */
function blog_preview_page(array $config, array $data, string $tagNames, int $editId): void
{
    header('X-Robots-Tag: noindex, nofollow');
    $cover = blog_media(isset($data['cover_media_id']) ? (int)$data['cover_media_id'] : 0);
    $status = (string)($data['status'] ?? 'draft');
    $categoryName = '';
    if (!empty($data['category_id'])) {
        foreach (blog_admin_categories() as $category) {
            if ((int)$category['id'] === (int)$data['category_id']) { $categoryName = (string)$category['name']; break; }
        }
    }
    if ($categoryName === '' && !empty($data['new_category'])) $categoryName = (string)$data['new_category'];
    $publishedAt = !empty($data['published_at']) ? date('d/m/Y H:i', strtotime((string)$data['published_at'])) : '—';
    $body = trim((string)($data['body_md'] ?? ''));
    $rendered = $body !== '' ? blog_render_markdown($body) : '<p class="muted">O conteúdo ainda está vazio.</p>';
    $backUrl = $editId > 0 ? url('workspace/blog.php') . '?view=edit&id=' . $editId : '';
    $title = (string)($data['title'] ?? '');
    ?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Pré-visualização — <?=e($title !== '' ? $title : 'Post')?></title><link rel="icon" href="<?=e(url('assets/img/logo.svg'))?>"><link rel="stylesheet" href="<?=e(blog_asset('assets/css/site.css'))?>"><link rel="stylesheet" href="<?=e(blog_asset('assets/css/blog.css'))?>"><link rel="stylesheet" href="<?=e(blog_asset('assets/css/blog-admin.css'))?>"></head><body class="blog-page">
<div class="preview-bar"><strong>Pré-visualização</strong><span class="badge badge-<?=e($status)?>"><?=e(blog_status_label($status))?></span><span class="preview-note">Visível apenas para você · não é pública nem indexada</span><span class="spacer"></span><?php if ($backUrl !== ''): ?><a class="btn secondary small" href="<?=e($backUrl)?>">Voltar ao editor</a><?php else: ?><button class="btn secondary small" type="button" onclick="window.close()">Fechar</button><?php endif; ?><button class="btn secondary small" type="button" onclick="window.location.reload()">Atualizar</button></div>
<main class="blog-wrap container">
  <article class="post">
    <header class="post-header">
      <?php if ($categoryName !== ''): ?><span class="post-category"><?=e($categoryName)?></span><?php endif; ?>
      <h1><?=e($title !== '' ? $title : 'Sem título')?></h1>
      <?php if (!empty($data['excerpt'])): ?><p class="post-lead"><?=e((string)$data['excerpt'])?></p><?php endif; ?>
      <div class="post-meta"><span><?=e((string)($config['site']['name'] ?? 'QEAI'))?></span><span aria-hidden="true">·</span><span><?=e($publishedAt)?></span></div>
    </header>
    <?php if ($cover): ?><figure class="post-cover"><img src="<?=e((string)$cover['url'])?>" alt="<?=e((string)($cover['alt'] !== '' ? $cover['alt'] : $title))?>"></figure><?php endif; ?>
    <div class="post-body"><?= $rendered ?></div>
    <?php if ($tagNames !== ''): ?><footer class="post-tags"><span>Assuntos:</span><?php foreach (array_filter(array_map('trim', explode(',', $tagNames))) as $tag): ?><span class="tag-pill"><?=e($tag)?></span><?php endforeach; ?></footer><?php endif; ?>
  </article>
  <aside class="preview-settings">
    <h2>Configurações do post</h2>
    <dl>
      <dt>Status</dt><dd><?=e(blog_status_label($status))?></dd>
      <dt>Slug</dt><dd><code><?=e((string)($data['slug'] ?? '') !== '' ? (string)$data['slug'] : '(gerado ao salvar)')?></code></dd>
      <dt>Publicação</dt><dd><?=e($publishedAt)?></dd>
      <dt>Categoria</dt><dd><?=e($categoryName !== '' ? $categoryName : '—')?></dd>
      <dt>Tags</dt><dd><?=e($tagNames !== '' ? $tagNames : '—')?></dd>
      <dt>Destacado</dt><dd><?= !empty($data['featured']) ? 'Sim' : 'Não' ?></dd>
      <dt>Título de SEO</dt><dd><?=e((string)($data['seo_title'] ?? '') !== '' ? (string)$data['seo_title'] : '—')?></dd>
      <dt>Descrição de SEO</dt><dd><?=e((string)($data['seo_description'] ?? '') !== '' ? (string)$data['seo_description'] : '—')?></dd>
      <dt>URL canônica</dt><dd><?=e((string)($data['canonical_url'] ?? '') !== '' ? (string)$data['canonical_url'] : '—')?></dd>
      <dt>Capa</dt><dd><?= $cover ? 'Definida (#' . (int)$cover['id'] . ')' : '—' ?></dd>
    </dl>
    <p class="hint">Esta pré-visualização não é pública e não aparece nos buscadores.</p>
  </aside>
</main>
</body></html>
<?php
    exit;
}

$view = (string)($_GET['view'] ?? 'list');
$editId = (int)($_GET['id'] ?? 0);
$formErrors = [];
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Salvamento automático (AJAX, retorna JSON). Não publica: preserva o status atual.
    if (post_string('action') === 'autosave') {
        header('Content-Type: application/json; charset=UTF-8');
        if (!verify_csrf($_POST['csrf'] ?? null)) { http_response_code(403); echo json_encode(['error' => 'Sessão expirada.']); exit; }
        $autoId = (int)post_string('id');
        if (!auth_can($autoId > 0 ? 'blog.edit.any' : 'blog.create')) { http_response_code(403); echo json_encode(['error' => 'Sem permissão para salvar.']); exit; }
        try {
            $input = blog_input_from_post();
            if ($autoId > 0) {
                $existing = blog_admin_get_post($autoId);
                if ($existing === null) throw new RuntimeException('Post não encontrado.');
                $input['status'] = (string)$existing['status'];
            } else {
                $input['status'] = 'draft';
            }
            $result = blog_admin_save_post($input, $autoId, 'panel', 'user', auth_user_id());
            if ($result['errors']) { http_response_code(422); echo json_encode(['error' => implode(' ', $result['errors'])]); exit; }
            $saved = blog_admin_get_post((int)$result['id']);
            echo json_encode(['id' => (int)$result['id'], 'slug' => $saved['slug'], 'status' => $saved['status'], 'saved_at' => date('H:i:s')], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $exception) {
            error_log('[QEAI blog autosave] ' . $exception->getMessage());
            http_response_code(422);
            echo json_encode(['error' => $exception instanceof RuntimeException ? $exception->getMessage() : 'Falha ao salvar automaticamente.']);
        }
        exit;
    }
    // Upload de imagem do editor (AJAX, retorna JSON).
    if (post_string('action') === 'upload_inline') {
        header('Content-Type: application/json; charset=UTF-8');
        if (!verify_csrf($_POST['csrf'] ?? null)) { http_response_code(403); echo json_encode(['error' => 'Sessão expirada.']); exit; }
        if (!auth_can('blog.media')) { http_response_code(403); echo json_encode(['error' => 'Sem permissão para enviar mídia.']); exit; }
        try {
            if (empty($_FILES['file']['name'])) throw new RuntimeException('Arquivo ausente.');
            $mediaId = storage_store_upload($_FILES['file'], post_string('alt'), '', 'QEAI');
            $media = blog_admin_get_media($mediaId);
            echo json_encode(['url' => $media['url'], 'id' => $mediaId, 'alt' => $media['alt']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $exception) {
            error_log('[QEAI blog inline] ' . $exception->getMessage());
            http_response_code(422);
            echo json_encode(['error' => $exception instanceof RuntimeException ? $exception->getMessage() : 'Falha no upload.']);
        }
        exit;
    }
    try {
        if (!verify_csrf($_POST['csrf'] ?? null)) throw new RuntimeException('Sua sessão expirou. Recarregue a página e tente novamente.');
        $action = post_string('action');

        // Pré-visualização: abre em nova aba e NÃO salva nada.
        if (($_GET['view'] ?? '') === 'preview') {
            blog_preview_page($config, blog_input_from_post(), post_string('tags'), (int)post_string('id'));
        }

        // Autorização por ação.
        if ($action === 'save_post') {
            auth_require((int)post_string('id') > 0 ? 'blog.edit.any' : 'blog.create');
        } elseif ($action === 'status') {
            auth_require(post_string('status') === 'trash' ? 'blog.delete' : 'blog.publish');
        } elseif (in_array($action, ['restore', 'purge'], true)) {
            auth_require('blog.delete');
        } elseif ($action === 'restore_revision') {
            auth_require('blog.edit.any');
        } elseif (in_array($action, ['upload_media', 'media_update', 'media_delete'], true)) {
            auth_require('blog.media');
        } elseif ($action === 'migrate_s3') {
            auth_require('blog.settings');
        } elseif (in_array($action, ['token_create', 'token_revoke'], true)) {
            auth_require('blog.tokens');
        } elseif ($action === 'save_settings') {
            auth_require('blog.settings');
        }

        if ($action === 'save_post') {
            $id = (int)post_string('id');
            $input = blog_input_from_post();

            $newCategory = post_string('new_category');
            if ($newCategory !== '') {
                $slug = blog_slugify($newCategory);
                db()->prepare('INSERT INTO blog_categories (name, slug) VALUES (:n, :s) ON DUPLICATE KEY UPDATE name = VALUES(name)')
                    ->execute([':n' => substr($newCategory, 0, 120), ':s' => $slug]);
                $find = db()->prepare('SELECT id FROM blog_categories WHERE slug = :s LIMIT 1');
                $find->execute([':s' => $slug]);
                $input['category_id'] = (int)$find->fetchColumn();
            }

            if (!empty($_FILES['cover_file']['name']) && (int)($_FILES['cover_file']['error'] ?? 1) === UPLOAD_ERR_OK) {
                $input['cover_media_id'] = storage_store_upload($_FILES['cover_file'], $input['title'], $input['title'], 'QEAI');
            }

            $result = blog_admin_save_post($input, $id, 'panel', 'user', auth_user_id());
            if ($result['errors']) {
                $formErrors = $result['errors'];
                $formData = $input;
                $view = $id > 0 ? 'edit' : 'new';
                $editId = $id;
            } else {
                blog_admin_flash('Post salvo com sucesso.', 'ok', $self . '?view=edit&id=' . $result['id']);
            }
        } elseif ($action === 'status') {
            $id = (int)post_string('id');
            blog_admin_set_status($id, post_string('status'), 'user', auth_user_id());
            blog_admin_flash('Status atualizado.', 'ok', $self);
        } elseif ($action === 'restore') {
            blog_admin_restore((int)post_string('id'), 'user', auth_user_id());
            blog_admin_flash('Post restaurado da lixeira.', 'ok', $self . '?status=trash');
        } elseif ($action === 'purge') {
            blog_admin_purge((int)post_string('id'), 'user', auth_user_id());
            blog_admin_flash('Post excluído definitivamente.', 'ok', $self . '?status=trash');
        } elseif ($action === 'restore_revision') {
            $postId = blog_admin_restore_revision((int)post_string('revision_id'));
            blog_admin_flash('Revisão restaurada.', 'ok', $postId ? $self . '?view=edit&id=' . $postId : $self);
        } elseif ($action === 'upload_media') {
            if (empty($_FILES['media_file']['name'])) throw new RuntimeException('Selecione um arquivo para enviar.');
            $mediaId = storage_store_upload($_FILES['media_file'], post_string('alt'), post_string('title'), 'QEAI');
            blog_admin_flash('Mídia enviada (#' . $mediaId . ') no armazenamento ' . storage_driver() . '.', 'ok', $self . '?view=media');
        } elseif ($action === 'media_update') {
            blog_admin_update_media((int)post_string('id'), post_string('alt'), post_string('title'));
            blog_admin_flash('Mídia atualizada.', 'ok', $self . '?view=media');
        } elseif ($action === 'media_delete') {
            blog_admin_delete_media((int)post_string('id'));
            blog_admin_flash('Mídia removida.', 'ok', $self . '?view=media');
        } elseif ($action === 'migrate_s3') {
            $result = storage_migrate_local_to_s3();
            blog_admin_flash('Migração concluída: ' . $result['migrated'] . ' enviadas, ' . $result['failed'] . ' com falha.', 'ok', $self . '?view=media');
        } elseif ($action === 'token_create') {
            $mode = post_string('mode') === 'publisher' ? 'publisher' : 'draft';
            $scopes = isset($_POST['scopes']) && is_array($_POST['scopes']) ? array_map('strval', $_POST['scopes']) : [];
            $token = blog_admin_token_create(post_string('name'), $mode, $scopes, post_string('allowed_ips'), post_string('expires_at'));
            blog_admin_flash('Token criado. Copie agora, ele não será exibido de novo: ' . $token, 'ok', $self . '?view=tokens');
        } elseif ($action === 'token_revoke') {
            blog_admin_token_revoke((int)post_string('id'));
            blog_admin_flash('Token revogado.', 'ok', $self . '?view=tokens');
        } elseif ($action === 'save_settings') {
            foreach (['blog_title', 'blog_description', 'blog_author', 's3_bucket', 's3_region', 's3_access_key', 's3_prefix', 's3_public_base', 's3_endpoint'] as $field) {
                storage_setting_set($field, post_string($field));
            }
            $secret = post_string('s3_secret_key');
            if ($secret !== '') storage_setting_set('s3_secret_key', $secret, true);
            blog_admin_flash('Configurações salvas. Driver de mídia atual: ' . storage_driver() . '.', 'ok', $self . '?view=settings');
        }
    } catch (Throwable $exception) {
        error_log('[QEAI blog admin] ' . $exception->getMessage());
        $notice = $exception instanceof RuntimeException ? $exception->getMessage() : 'Não foi possível concluir a operação.';
        $noticeType = 'err';
    }
}

$blogSettings = storage_settings();
$panelTitle = 'Blog';

function blog_panel_start(string $title, string $active, array $config, bool $withEditor = false): void
{
    $links = [['key' => 'list', 'label' => 'Posts', 'href' => 'blog.php']];
    if (auth_can('blog.media')) $links[] = ['key' => 'media', 'label' => 'Mídia', 'href' => 'blog.php?view=media'];
    if (auth_can('blog.tokens')) $links[] = ['key' => 'tokens', 'label' => 'API', 'href' => 'blog.php?view=tokens'];
    if (auth_can('blog.settings')) $links[] = ['key' => 'settings', 'label' => 'Configurações', 'href' => 'blog.php?view=settings'];
    if (auth_can('blog.audit')) $links[] = ['key' => 'audit', 'label' => 'Auditoria', 'href' => 'blog.php?view=audit'];
    $currentUser = auth_current();
    ?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?=e($title)?> — QEAI Blog</title><link rel="icon" href="../assets/img/logo.svg"><link rel="stylesheet" href="../assets/css/workspace.css?v=3"><link rel="stylesheet" href="../assets/css/blog-admin.css?v=2"><?php if ($withEditor): ?><link rel="stylesheet" href="../assets/vendor/toastui/toastui-editor.min.css"><script src="../assets/vendor/toastui/toastui-editor-all.min.js" defer></script><script src="../assets/vendor/toastui/pt-br.js" defer></script><script src="../assets/js/blog-editor.js" defer></script><?php endif; ?></head><body>
<header class="panel-header"><a href="../"><img src="../assets/img/logo.svg" alt="QEAI" width="110"></a><span>Blog do site</span><a href="../" target="_blank" rel="noopener">Ver site ↗</a></header>
<main class="wrap">
<nav class="panel-nav" aria-label="Seções do blog">
  <?php foreach ($links as $link): ?><a class="<?=$active === $link['key'] ? 'is-active' : ''?>" href="<?=e($link['href'])?>"><?=e($link['label'])?></a><?php endforeach; ?>
  <span class="spacer"></span><span class="panel-user"><?=e((string)($currentUser['name'] ?: $currentUser['email']))?></span><a href="../">Painel do site</a>
</nav>
<?php
}

function blog_panel_notice(string $notice, string $type): void
{
    if ($notice === '') return;
    echo '<div class="status ' . ($type === 'err' ? 'err' : 'ok') . '" role="' . ($type === 'err' ? 'alert' : 'status') . '" style="overflow-wrap:anywhere">' . e($notice) . '</div>';
}

// --- Controller de visualização --------------------------------------------
$categories = blog_admin_categories();

if ($view === 'preview') {
    $post = $editId > 0 ? blog_admin_get_post($editId) : null;
    if (!$post) { http_response_code(404); exit('Post não encontrado.'); }
    blog_preview_page($config, $post, blog_admin_tag_names($editId), $editId);
}

if ($view === 'audit') {
    auth_require('blog.audit');
    $rows = db()->query('SELECT * FROM blog_audit_logs ORDER BY id DESC LIMIT 200')->fetchAll();
    blog_panel_start('Auditoria', 'audit', $config);
    blog_panel_notice($notice, $noticeType);
    ?>
    <div class="top"><div><span class="eyebrow">Governança</span><h1>Log de auditoria</h1><p class="muted">Ações do painel e da API (200 registros mais recentes).</p></div></div>
    <section class="box"><table class="admin-table"><thead><tr><th>Quando</th><th>Ator</th><th>Ação</th><th>Entidade</th><th>IP</th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?><tr><td><?=e(date('d/m/Y H:i', strtotime((string)$row['created_at'])))?></td><td><?=e((string)$row['actor_type'])?><?= $row['actor_id'] ? ' #' . (int)$row['actor_id'] : '' ?></td><td><?=e((string)$row['action'])?></td><td><?=e((string)$row['entity_type'])?><?= $row['entity_id'] ? ' #' . (int)$row['entity_id'] : '' ?></td><td><?=e((string)$row['ip'])?></td></tr><?php endforeach; ?>
    </tbody></table></section>
    <?php
    echo '</main></body></html>';
    exit;
}

if ($view === 'tokens') {
    auth_require('blog.tokens');
    blog_panel_start('Tokens de API', 'tokens', $config);
    blog_panel_notice($notice, $noticeType);
    $tokens = blog_admin_tokens();
    ?>
    <div class="top"><div><span class="eyebrow">Integrações</span><h1>Tokens de API</h1><p class="muted">Permitem que um agente externo crie conteúdo. Escopos definem o que cada token pode fazer.</p></div></div>
    <section class="box"><h2>Novo token</h2>
      <form method="post" class="grid"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="token_create">
        <label>Nome<input name="name" required maxlength="120" placeholder="Ex.: Agente de conteúdo"></label>
        <label>Modo<select name="mode"><option value="draft">Rascunho (não publica)</option><option value="publisher">Publicador (publica direto)</option></select></label>
        <label class="full">Escopos<div class="checks">
          <?php foreach (['posts:read' => 'Ler posts', 'drafts:write' => 'Criar/editar rascunhos', 'posts:publish' => 'Publicar', 'posts:delete' => 'Excluir', 'media:write' => 'Enviar mídia'] as $scope => $label): ?>
          <label class="check"><input type="checkbox" name="scopes[]" value="<?=e($scope)?>"><span><?=e($label)?> <code><?=e($scope)?></code></span></label>
          <?php endforeach; ?>
        </div></label>
        <label>IPs permitidos (opcional)<input name="allowed_ips" placeholder="200.1.2.3, 200.1.2.4"></label>
        <label>Expira em (opcional)<input type="date" name="expires_at"></label>
        <div class="full"><button class="btn">Criar token</button></div>
      </form>
    </section>
    <section class="box"><h2>Tokens existentes</h2>
      <?php if (!$tokens): ?><p class="muted">Nenhum token criado.</p><?php else: ?>
      <table class="admin-table"><thead><tr><th>Nome</th><th>Modo</th><th>Escopos</th><th>Último uso</th><th>Status</th><th></th></tr></thead><tbody>
      <?php foreach ($tokens as $token): ?>
      <tr><td><?=e((string)$token['name'])?></td><td><?=e((string)$token['mode'])?></td><td><small><?=e(implode(', ', json_decode((string)$token['scopes'], true) ?: []))?></small></td><td><?= $token['last_used_at'] ? e(date('d/m/Y H:i', strtotime((string)$token['last_used_at']))) : '—' ?></td><td><?= $token['revoked_at'] ? 'Revogado' : ($token['expires_at'] && strtotime((string)$token['expires_at']) < time() ? 'Expirado' : 'Ativo') ?></td><td><?php if (!$token['revoked_at']): ?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="token_revoke"><input type="hidden" name="id" value="<?=(int)$token['id']?>"><button class="btn secondary small">Revogar</button></form><?php endif; ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
      <?php endif; ?>
    </section>
    <?php
    echo '</main></body></html>';
    exit;
}

if ($view === 'settings') {
    auth_require('blog.settings');
    blog_panel_start('Configurações', 'settings', $config);
    blog_panel_notice($notice, $noticeType);
    $driver = storage_driver();
    ?>
    <div class="top"><div><span class="eyebrow">Configurações</span><h1>Blog e armazenamento</h1><p class="muted">Identidade do blog e conexão com o S3. Sem S3 configurado, a mídia é salva no servidor (ideal só para desenvolvimento).</p></div></div>
    <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_settings">
    <section class="box"><h2>Identidade do blog</h2><div class="grid">
      <label class="full">Título<input name="blog_title" value="<?=e($blogSettings['blog_title'] ?? '')?>" maxlength="255" placeholder="Blog da QEAI"></label>
      <label class="full">Descrição<input name="blog_description" value="<?=e($blogSettings['blog_description'] ?? '')?>" maxlength="320"></label>
      <label>Autor padrão<input name="blog_author" value="<?=e($blogSettings['blog_author'] ?? 'QEAI')?>" maxlength="120"></label>
    </div></section>
    <section class="box"><h2>AWS S3</h2><p class="muted">Driver atual: <strong><?=e($driver)?></strong>. As chaves ficam cifradas no banco.</p><div class="grid">
      <label>Bucket<input name="s3_bucket" value="<?=e($blogSettings['s3_bucket'] ?? '')?>"></label>
      <label>Região<input name="s3_region" value="<?=e($blogSettings['s3_region'] ?? '')?>" placeholder="sa-east-1"></label>
      <label>Access key<input name="s3_access_key" value="<?=e($blogSettings['s3_access_key'] ?? '')?>"></label>
      <label>Secret key<input type="password" name="s3_secret_key" placeholder="Deixe vazio para manter"><small>Só é alterada se você preencher.</small></label>
      <label>Prefixo das chaves<input name="s3_prefix" value="<?=e($blogSettings['s3_prefix'] ?? 'blog/')?>"></label>
      <label>URL pública/base (opcional)<input name="s3_public_base" value="<?=e($blogSettings['s3_public_base'] ?? '')?>" placeholder="https://cdn.exemplo.com"></label>
      <label class="full">Endpoint (opcional)<input name="s3_endpoint" value="<?=e($blogSettings['s3_endpoint'] ?? '')?>" placeholder="Para provedores compatíveis"></label>
    </div></section>
    <div class="save-bar"><span>As alterações são aplicadas ao salvar.</span><button class="btn">Salvar configurações</button></div>
    </form>
    <section class="box"><h2>Migrar mídia para o S3</h2><p class="muted">Depois de configurar e salvar as credenciais, envie toda a mídia local para o S3.</p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="migrate_s3"><button class="btn secondary">Migrar mídia local para o S3</button></form></section>
    <?php
    echo '</main></body></html>';
    exit;
}

if ($view === 'media') {
    auth_require('blog.media');
    $media = blog_admin_media();
    blog_panel_start('Mídia', 'media', $config);
    blog_panel_notice($notice, $noticeType);
    ?>
    <div class="top"><div><span class="eyebrow">Biblioteca</span><h1>Mídia</h1><p class="muted">Driver ativo: <strong><?=e(storage_driver())?></strong>.</p></div></div>
    <section class="box"><h2>Enviar arquivo</h2>
      <form method="post" enctype="multipart/form-data" class="grid"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="upload_media">
        <label>Arquivo<input type="file" name="media_file" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm" required></label>
        <label>Texto alternativo (alt)<input name="alt" maxlength="300"></label>
        <label>Título<input name="title" maxlength="300"></label>
        <div class="full"><button class="btn">Enviar mídia</button></div>
      </form>
    </section>
    <section class="box"><h2>Arquivos</h2>
      <?php if (!$media): ?><p class="muted">Nenhuma mídia enviada.</p><?php else: ?>
      <div class="media-grid">
      <?php foreach ($media as $item): ?>
        <div class="media-card">
          <div class="media-thumb"><?php if (str_starts_with((string)$item['mime'], 'image/')): ?><img src="<?=e((string)$item['url'])?>" alt="<?=e((string)$item['alt'])?>" loading="lazy"><?php else: ?><span><?=e((string)$item['mime'])?></span><?php endif; ?></div>
          <form method="post" class="media-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="media_update"><input type="hidden" name="id" value="<?=(int)$item['id']?>">
            <input name="alt" value="<?=e((string)$item['alt'])?>" placeholder="alt" maxlength="300">
            <input name="title" value="<?=e((string)$item['title'])?>" placeholder="título" maxlength="300">
            <small>#<?=(int)$item['id']?> · <?=e((string)$item['driver'])?> · <?=(int)round(((int)$item['size'])/1024)?> KB</small>
            <div class="media-actions"><button class="btn secondary small">Salvar</button><button class="btn danger small" name="action" value="media_delete" formnovalidate>Excluir</button></div>
          </form>
        </div>
      <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>
    <?php
    echo '</main></body></html>';
    exit;
}

if ($view === 'revisions' && $editId > 0) {
    $post = blog_admin_get_post($editId);
    if (!$post) { http_response_code(404); exit('Post não encontrado.'); }
    $revisions = blog_admin_revisions($editId);
    blog_panel_start('Revisões', 'list', $config);
    blog_panel_notice($notice, $noticeType);
    ?>
    <div class="top"><div><span class="eyebrow">Histórico</span><h1>Revisões de “<?=e((string)$post['title'])?>”</h1></div><a class="btn secondary" href="blog.php?view=edit&id=<?=(int)$post['id']?>">Voltar ao editor</a></div>
    <section class="box"><table class="admin-table"><thead><tr><th>Quando</th><th>Título</th><th>Por</th><th></th></tr></thead><tbody>
    <?php foreach ($revisions as $revision): ?><tr><td><?=e(date('d/m/Y H:i', strtotime((string)$revision['created_at'])))?></td><td><?=e((string)$revision['title'])?></td><td><?=e((string)$revision['changed_by'])?></td><td><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="restore_revision"><input type="hidden" name="revision_id" value="<?=(int)$revision['id']?>"><button class="btn secondary small">Restaurar</button></form></td></tr><?php endforeach; ?>
    </tbody></table></section>
    <?php
    echo '</main></body></html>';
    exit;
}

if ($view === 'new' || $view === 'edit') {
    auth_require($view === 'new' ? 'blog.create' : 'blog.edit.any');
    $post = null;
    if ($view === 'edit' && $editId > 0) $post = blog_admin_get_post($editId);
    if ($view === 'edit' && !$post) { http_response_code(404); exit('Post não encontrado.'); }

    // Une dados do banco, valores reenviados com erro e padrões.
    $data = $post ?? ['id' => 0, 'title' => '', 'slug' => '', 'excerpt' => '', 'body_md' => '', 'status' => 'draft', 'published_at' => '', 'seo_title' => '', 'seo_description' => '', 'canonical_url' => '', 'category_id' => 0, 'cover_media_id' => 0, 'featured' => 0];
    if ($formData) $data = array_merge($data, $formData, ['id' => $editId]);
    $data['tags'] = $post ? blog_admin_tag_names((int)$post['id']) : ($formData['tags'] ?? '');
    $publishedValue = !empty($data['published_at']) ? date('Y-m-d\TH:i', strtotime((string)$data['published_at'])) : '';
    $cover = !empty($data['cover_media_id']) ? blog_admin_get_media((int)$data['cover_media_id']) : null;

    blog_panel_start($view === 'new' ? 'Novo post' : 'Editar post', 'list', $config, true);
    blog_panel_notice($notice, $noticeType);
    if ($formErrors) echo '<div class="status err" role="alert">Revise os campos indicados abaixo.</div>';
    ?>
    <div class="top"><div><span class="eyebrow"><?=e(blog_status_label((string)$data['status']))?></span><h1><?= $view === 'new' ? 'Novo post' : 'Editar post' ?></h1></div><div class="top-actions"><?php if ($post): ?><a class="btn secondary" href="blog.php?view=preview&id=<?=(int)$post['id']?>" target="_blank" rel="noopener">Pré-visualizar</a><a class="btn secondary" href="blog.php?view=revisions&id=<?=(int)$post['id']?>">Revisões</a><?php endif; ?><a class="btn secondary" href="blog.php">Voltar</a></div></div>
    <form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_post"><input type="hidden" name="id" value="<?=(int)$data['id']?>">
    <section class="box"><h2>Conteúdo</h2><div class="grid">
      <label class="full">Título<?php if (!empty($formErrors['title'])): ?><small class="field-error"><?=e($formErrors['title'])?></small><?php endif; ?><input name="title" value="<?=e((string)$data['title'])?>" required maxlength="255"></label>
      <label>Slug (URL)<input name="slug" value="<?=e((string)$data['slug'])?>" maxlength="180" placeholder="gerado do título se vazio"></label>
      <label>Status<select name="status"><?php foreach (['draft' => 'Rascunho', 'published' => 'Publicado', 'scheduled' => 'Agendado'] as $value => $label): ?><option value="<?=e($value)?>" <?=$data['status'] === $value ? 'selected' : ''?>><?=e($label)?></option><?php endforeach; ?></select></label>
      <label>Publicar em<input type="datetime-local" name="published_at" value="<?=e($publishedValue)?>"><?php if (!empty($formErrors['published_at'])): ?><small class="field-error"><?=e($formErrors['published_at'])?></small><?php endif; ?></label>
      <label>Categoria<select name="category_id"><option value="0">— sem categoria —</option><?php foreach ($categories as $category): ?><option value="<?=(int)$category['id']?>" <?=(int)$data['category_id'] === (int)$category['id'] ? 'selected' : ''?>><?=e((string)$category['name'])?></option><?php endforeach; ?></select></label>
      <label>Nova categoria (opcional)<input name="new_category" maxlength="120" placeholder="Cria e seleciona"></label>
      <label>Tags (separadas por vírgula)<input name="tags" value="<?=e((string)$data['tags'])?>" maxlength="500"></label>
      <label class="full">Resumo<input name="excerpt" value="<?=e((string)$data['excerpt'])?>" maxlength="500"></label>
      <div class="full editor-field"><span class="field-label">Conteúdo<?php if (!empty($formErrors['body_md'])): ?> <small class="field-error"><?=e($formErrors['body_md'])?></small><?php endif; ?></span><textarea name="body_md" id="body_md" rows="16" class="mono" aria-label="Conteúdo do post"><?=e((string)$data['body_md'])?></textarea><div id="blog-editor" class="blog-editor" data-upload="blog.php"<?= (auth_can('blog.create') || auth_can('blog.edit.any')) ? ' data-autosave="blog.php"' : '' ?>></div><div class="editor-status"><small class="hint">Edição visual por padrão; use a aba “Markdown” para o texto puro. Sem JavaScript, o campo de texto acima continua funcionando.</small><span id="autosave-status" class="autosave-status" aria-live="polite"></span></div></div>
    </div></section>
    <section class="box"><h2>Capa e destaque</h2><div class="grid">
      <label>Imagem de capa<input type="file" name="cover_file" accept="image/jpeg,image/png,image/webp,image/gif"><small>Opcional. Substitui a capa atual.</small></label>
      <label>Capa atual<input name="cover_media_id" value="<?=(int)$data['cover_media_id']?>" inputmode="numeric"><small>ID de mídia. <?= $cover ? 'Atual: ' . e((string)$cover['url']) : 'Nenhuma capa.' ?></small></label>
      <label class="check full"><input type="checkbox" name="featured" <?=!empty($data['featured']) ? 'checked' : ''?>><span>Destacar este post</span></label>
    </div></section>
    <section class="box"><h2>SEO</h2><div class="grid">
      <label class="full">Título de SEO<input name="seo_title" value="<?=e((string)$data['seo_title'])?>" maxlength="255"><?php if (!empty($formErrors['seo_title'])): ?><small class="field-error"><?=e($formErrors['seo_title'])?></small><?php endif; ?></label>
      <label class="full">Descrição de SEO<input name="seo_description" value="<?=e((string)$data['seo_description'])?>" maxlength="320"><?php if (!empty($formErrors['seo_description'])): ?><small class="field-error"><?=e($formErrors['seo_description'])?></small><?php endif; ?></label>
      <label class="full">URL canônica (opcional)<input name="canonical_url" value="<?=e((string)$data['canonical_url'])?>" maxlength="500"><?php if (!empty($formErrors['canonical_url'])): ?><small class="field-error"><?=e($formErrors['canonical_url'])?></small><?php endif; ?></label>
    </div></section>
    <div class="save-bar"><span>O post é salvo e uma revisão é registrada.</span><div class="save-actions"><button class="btn secondary" type="submit" formtarget="_blank" formnovalidate formaction="blog.php?view=preview">Pré-visualizar</button><button class="btn">Salvar post</button></div></div>
    </form>
    <?php
    echo '</main></body></html>';
    exit;
}

// --- Lista (padrão) ----------------------------------------------------------
$filterStatus = (string)($_GET['status'] ?? '');
$filterSearch = trim((string)($_GET['q'] ?? ''));
$posts = blog_admin_posts($filterStatus, $filterSearch);

blog_panel_start('Posts', 'list', $config);
blog_panel_notice($notice, $noticeType);
?>
<div class="top"><div><span class="eyebrow">Conteúdo</span><h1>Posts do blog</h1><p class="muted">Crie, edite, agende e publique. A lixeira permite recuperar.</p></div><a class="btn" href="blog.php?view=new">Novo post</a></div>
<section class="box">
  <form method="get" class="grid"><input type="hidden" name="view" value="list">
    <label>Filtrar por status<select name="status" onchange="this.form.submit()"><option value="">Todos</option><?php foreach (['draft' => 'Rascunhos', 'published' => 'Publicados', 'scheduled' => 'Agendados', 'trash' => 'Lixeira'] as $value => $label): ?><option value="<?=e($value)?>" <?=$filterStatus === $value ? 'selected' : ''?>><?=e($label)?></option><?php endforeach; ?></select></label>
    <label>Buscar<input name="q" value="<?=e($filterSearch)?>" placeholder="título ou slug"></label>
    <div class="full"><button class="btn secondary">Aplicar</button></div>
  </form>
</section>
<section class="box">
  <?php if (!$posts): ?><p class="muted">Nenhum post encontrado.</p><?php else: ?>
  <table class="admin-table"><thead><tr><th>Título</th><th>Status</th><th>Categoria</th><th>Atualizado</th><th class="actions">Ações</th></tr></thead><tbody>
  <?php foreach ($posts as $post): ?>
    <tr>
      <td><strong><?=e((string)$post['title'])?></strong><br><small><?=e((string)$post['slug'])?></small></td>
      <td><span class="badge badge-<?=e((string)$post['status'])?>"><?=e(blog_status_label((string)$post['status']))?></span></td>
      <td><?=e((string)($post['category_name'] ?? '—'))?></td>
      <td><?=e(date('d/m/Y H:i', strtotime((string)$post['updated_at'])))?><?= $post['status'] === 'scheduled' && $post['published_at'] ? '<br><small>agendado: ' . e(date('d/m/Y H:i', strtotime((string)$post['published_at']))) . '</small>' : '' ?></td>
      <td class="actions">
        <div class="row-actions">
          <a class="btn secondary small" href="blog.php?view=edit&id=<?=(int)$post['id']?>">Editar</a>
          <a class="btn secondary small" href="blog.php?view=preview&id=<?=(int)$post['id']?>" target="_blank" rel="noopener">Ver</a>
          <?php if ($post['status'] !== 'published'): ?>
          <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?=(int)$post['id']?>"><input type="hidden" name="status" value="published"><button class="btn secondary small">Publicar</button></form>
          <?php else: ?>
          <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?=(int)$post['id']?>"><input type="hidden" name="status" value="draft"><button class="btn secondary small">Despublicar</button></form>
          <?php endif; ?>
          <?php if ($post['status'] === 'trash'): ?>
          <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="restore"><input type="hidden" name="id" value="<?=(int)$post['id']?>"><button class="btn secondary small">Restaurar</button></form>
          <form method="post" onsubmit="return confirm('Excluir definitivamente? Esta ação não pode ser desfeita.');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="purge"><input type="hidden" name="id" value="<?=(int)$post['id']?>"><button class="btn danger small">Excluir</button></form>
          <?php else: ?>
          <form method="post" onsubmit="return confirm('Mover para a lixeira?');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?=(int)$post['id']?>"><input type="hidden" name="status" value="trash"><button class="btn danger small">Lixeira</button></form>
          <?php endif; ?>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
  <?php endif; ?>
</section>
<?php
echo '</main></body></html>';
