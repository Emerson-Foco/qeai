<?php

declare(strict_types=1);

require_once ROOT_PATH . '/app/db.php';
require_once ROOT_PATH . '/app/blog.php';

/** Registra uma ação no log de auditoria do blog. */
function blog_audit(string $actorType, ?int $actorId, string $action, string $entityType = '', ?int $entityId = null, array $meta = []): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO blog_audit_logs (actor_type, actor_id, action, entity_type, entity_id, ip, user_agent, meta)'
            . ' VALUES (:at, :ai, :action, :et, :eid, :ip, :ua, :meta)'
        );
        $stmt->execute([
            ':at' => $actorType,
            ':ai' => $actorId,
            ':action' => $action,
            ':et' => $entityType,
            ':eid' => $entityId,
            ':ip' => client_ip(),
            ':ua' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300),
            ':meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
        ]);
    } catch (Throwable $exception) {
        error_log('[QEAI blog audit] ' . $exception->getMessage());
    }
}

function blog_admin_categories(): array
{
    return db()->query('SELECT * FROM blog_categories ORDER BY name ASC')->fetchAll();
}

/** Lista posts para o painel (todos os status menos lixeira, ou só lixeira). */
function blog_admin_posts(string $status = '', string $search = ''): array
{
    $where = [];
    $params = [];
    if ($status === 'trash') {
        $where[] = "p.status = 'trash'";
    } elseif ($status !== '') {
        $where[] = 'p.status = :status';
        $params[':status'] = $status;
    } else {
        $where[] = "p.status <> 'trash'";
    }
    if ($search !== '') {
        $where[] = '(p.title LIKE :search OR p.slug LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }
    $sql = 'SELECT p.*, c.name AS category_name FROM blog_posts p'
        . ' LEFT JOIN blog_categories c ON c.id = p.category_id'
        . ' WHERE ' . implode(' AND ', $where)
        . ' ORDER BY p.updated_at DESC, p.id DESC LIMIT 200';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function blog_admin_get_post(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM blog_posts WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

function blog_admin_unique_slug(string $base, int $ignoreId = 0): string
{
    $base = blog_slugify($base);
    $slug = $base;
    $suffix = 2;
    while (true) {
        $stmt = db()->prepare('SELECT id FROM blog_posts WHERE slug = :slug AND id <> :id LIMIT 1');
        $stmt->execute([':slug' => $slug, ':id' => $ignoreId]);
        if ($stmt->fetch() === false) return $slug;
        $slug = $base . '-' . $suffix++;
    }
}

function blog_admin_tag_names(int $postId): string
{
    $stmt = db()->prepare('SELECT t.name FROM blog_tags t JOIN blog_post_tags pt ON pt.tag_id = t.id WHERE pt.post_id = :id ORDER BY t.name');
    $stmt->execute([':id' => $postId]);
    return implode(', ', array_column($stmt->fetchAll(), 'name'));
}

function blog_admin_sync_tags(int $postId, string $tagInput): void
{
    $names = array_values(array_unique(array_filter(array_map('trim', explode(',', $tagInput)), fn($v) => $v !== '')));
    $names = array_slice($names, 0, 20);
    db()->prepare('DELETE FROM blog_post_tags WHERE post_id = :id')->execute([':id' => $postId]);
    foreach ($names as $name) {
        $slug = blog_slugify($name);
        if ($slug === '') continue;
        $insert = db()->prepare('INSERT INTO blog_tags (name, slug) VALUES (:n, :s) ON DUPLICATE KEY UPDATE name = VALUES(name)');
        $insert->execute([':n' => substr($name, 0, 120), ':s' => $slug]);
        $find = db()->prepare('SELECT id FROM blog_tags WHERE slug = :s LIMIT 1');
        $find->execute([':s' => $slug]);
        $tagId = (int)$find->fetchColumn();
        if ($tagId > 0) db()->prepare('INSERT IGNORE INTO blog_post_tags (post_id, tag_id) VALUES (:p, :t)')->execute([':p' => $postId, ':t' => $tagId]);
    }
}

/**
 * Cria ou atualiza um post. Retorna ['id' => int, 'errors' => array].
 * @param array<string,mixed> $input
 */
function blog_admin_save_post(array $input, int $ignoreId = 0, string $source = 'panel', string $actorType = 'user', ?int $actorId = null): array
{
    $errors = [];
    $title = trim((string)($input['title'] ?? ''));
    $excerpt = trim((string)($input['excerpt'] ?? ''));
    $body = (string)($input['body_md'] ?? '');
    $seoTitle = trim((string)($input['seo_title'] ?? ''));
    $seoDescription = trim((string)($input['seo_description'] ?? ''));
    $canonical = trim((string)($input['canonical_url'] ?? ''));
    $status = (string)($input['status'] ?? 'draft');
    $categoryId = (int)($input['category_id'] ?? 0);
    $coverId = (int)($input['cover_media_id'] ?? 0);
    $featured = !empty($input['featured']) ? 1 : 0;
    $publishedInput = trim((string)($input['published_at'] ?? ''));

    if ($title === '' || text_length($title) > 255) $errors['title'] = 'Informe um título de até 255 caracteres.';
    if (text_length($excerpt) > 500) $errors['excerpt'] = 'O resumo deve ter até 500 caracteres.';
    if (text_length($body) > 200000) $errors['body_md'] = 'O conteúdo é muito longo.';
    if (text_length($seoTitle) > 255) $errors['seo_title'] = 'Título de SEO muito longo.';
    if (text_length($seoDescription) > 320) $errors['seo_description'] = 'Descrição de SEO muito longa.';
    if ($canonical !== '' && !filter_var($canonical, FILTER_VALIDATE_URL)) $errors['canonical_url'] = 'URL canônica inválida.';
    if (!in_array($status, ['draft', 'published', 'scheduled', 'trash'], true)) $status = 'draft';

    $publishedAt = null;
    if ($publishedInput !== '') {
        $timestamp = strtotime(str_replace('T', ' ', $publishedInput));
        if ($timestamp === false) $errors['published_at'] = 'Data de publicação inválida.';
        else $publishedAt = date('Y-m-d H:i:s', $timestamp);
    }
    if ($status === 'scheduled') {
        if ($publishedAt === null) $errors['published_at'] = 'Agendamento precisa de uma data futura.';
        elseif (strtotime($publishedAt) <= time()) $errors['published_at'] = 'A data do agendamento deve estar no futuro.';
    }

    if ($categoryId > 0) {
        $exists = db()->prepare('SELECT id FROM blog_categories WHERE id = :id LIMIT 1');
        $exists->execute([':id' => $categoryId]);
        if ($exists->fetch() === false) $categoryId = 0;
    }

    if ($errors) return ['id' => $ignoreId, 'errors' => $errors];

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $slugBase = trim((string)($input['slug'] ?? '')) !== '' ? (string)$input['slug'] : $title;
        $slug = blog_admin_unique_slug($slugBase, $ignoreId);
        $readingTime = blog_reading_time($body);
        $data = [
            ':slug' => $slug,
            ':title' => $title,
            ':excerpt' => $excerpt,
            ':body_md' => $body,
            ':status' => $status,
            ':published_at' => $publishedAt,
            ':seo_title' => $seoTitle,
            ':seo_description' => $seoDescription,
            ':canonical_url' => $canonical,
            ':category_id' => $categoryId > 0 ? $categoryId : null,
            ':cover_media_id' => $coverId > 0 ? $coverId : null,
            ':featured' => $featured,
            ':reading_time' => $readingTime,
        ];

        if ($ignoreId > 0) {
            $stmt = $pdo->prepare(
                'UPDATE blog_posts SET slug = :slug, title = :title, excerpt = :excerpt, body_md = :body_md,'
                . ' status = :status, published_at = COALESCE(:published_at, NOW()), seo_title = :seo_title,'
                . ' seo_description = :seo_description, canonical_url = :canonical_url, category_id = :category_id,'
                . ' cover_media_id = :cover_media_id, featured = :featured, reading_time = :reading_time, deleted_at = NULL'
                . ' WHERE id = :id'
            );
            $data[':id'] = $ignoreId;
            $stmt->execute($data);
            $postId = $ignoreId;
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO blog_posts (slug, title, excerpt, body_md, status, published_at, seo_title,'
                . ' seo_description, canonical_url, category_id, cover_media_id, featured, reading_time, source, api_token_id, author_name)'
                . ' VALUES (:slug, :title, :excerpt, :body_md, :status, COALESCE(:published_at, NOW()), :seo_title,'
                . ' :seo_description, :canonical_url, :category_id, :cover_media_id, :featured, :reading_time, :source, :api_token_id, :author_name)'
            );
            $data[':source'] = $source;
            $data[':api_token_id'] = $actorType === 'token' ? $actorId : null;
            $data[':author_name'] = 'QEAI';
            $stmt->execute($data);
            $postId = (int)$pdo->lastInsertId();
        }

        $pdo->prepare('INSERT INTO blog_revisions (post_id, title, body_md, changed_by) VALUES (:p, :t, :b, :c)')
            ->execute([':p' => $postId, ':t' => $title, ':b' => $body, ':c' => 'QEAI']);

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    blog_admin_sync_tags($postId, (string)($input['tags'] ?? ''));
    blog_cache_flush();
    blog_audit($actorType, $actorId, $ignoreId > 0 ? 'post.update' : 'post.create', 'post', $postId, ['status' => $status, 'source' => $source]);
    return ['id' => $postId, 'errors' => []];
}

function blog_admin_set_status(int $id, string $status, string $actorType = 'user', ?int $actorId = null): void
{
    if (!in_array($status, ['draft', 'published', 'scheduled', 'trash'], true)) return;
    if ($status === 'published') {
        db()->prepare("UPDATE blog_posts SET status = 'published', published_at = COALESCE(published_at, NOW()), deleted_at = NULL WHERE id = :id")->execute([':id' => $id]);
    } elseif ($status === 'draft') {
        db()->prepare("UPDATE blog_posts SET status = 'draft' WHERE id = :id")->execute([':id' => $id]);
    } elseif ($status === 'trash') {
        db()->prepare("UPDATE blog_posts SET status = 'trash', deleted_at = NOW() WHERE id = :id")->execute([':id' => $id]);
    } else {
        db()->prepare("UPDATE blog_posts SET status = :s WHERE id = :id")->execute([':s' => $status, ':id' => $id]);
    }
    blog_cache_flush();
    blog_audit($actorType, $actorId, 'post.status', 'post', $id, ['status' => $status]);
}

function blog_admin_restore(int $id, string $actorType = 'user', ?int $actorId = null): void
{
    db()->prepare("UPDATE blog_posts SET status = 'draft', deleted_at = NULL WHERE id = :id")->execute([':id' => $id]);
    blog_cache_flush();
    blog_audit($actorType, $actorId, 'post.restore', 'post', $id);
}

function blog_admin_purge(int $id, string $actorType = 'user', ?int $actorId = null): void
{
    db()->prepare('DELETE FROM blog_posts WHERE id = :id')->execute([':id' => $id]);
    blog_cache_flush();
    blog_audit($actorType, $actorId, 'post.purge', 'post', $id);
}

function blog_admin_revisions(int $postId): array
{
    $stmt = db()->prepare('SELECT id, title, changed_by, created_at FROM blog_revisions WHERE post_id = :id ORDER BY id DESC LIMIT 50');
    $stmt->execute([':id' => $postId]);
    return $stmt->fetchAll();
}

function blog_admin_get_revision(int $revisionId): ?array
{
    $stmt = db()->prepare('SELECT * FROM blog_revisions WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $revisionId]);
    return $stmt->fetch() ?: null;
}

function blog_admin_restore_revision(int $revisionId): ?int
{
    $revision = blog_admin_get_revision($revisionId);
    if (!$revision) return null;
    db()->prepare('UPDATE blog_posts SET title = :t, body_md = :b, reading_time = :r WHERE id = :id')->execute([
        ':t' => $revision['title'],
        ':b' => $revision['body_md'],
        ':r' => blog_reading_time((string)$revision['body_md']),
        ':id' => (int)$revision['post_id'],
    ]);
    blog_cache_flush();
    blog_audit('user', null, 'post.revision_restore', 'post', (int)$revision['post_id'], ['revision' => $revisionId]);
    return (int)$revision['post_id'];
}

// --- Mídia -------------------------------------------------------------------

function blog_admin_media(int $limit = 100): array
{
    $stmt = db()->prepare('SELECT * FROM blog_media ORDER BY id DESC LIMIT ' . max(1, min(300, $limit)));
    $stmt->execute();
    return $stmt->fetchAll();
}

function blog_admin_get_media(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM blog_media WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

function blog_admin_update_media(int $id, string $alt, string $title): void
{
    db()->prepare('UPDATE blog_media SET alt = :a, title = :t WHERE id = :id')->execute([
        ':a' => substr($alt, 0, 300), ':t' => substr($title, 0, 300), ':id' => $id,
    ]);
    blog_audit('user', null, 'media.update', 'media', $id);
}

function blog_admin_delete_media(int $id): void
{
    $media = blog_admin_get_media($id);
    if (!$media) return;
    require_once ROOT_PATH . '/app/storage.php';
    storage_delete_object_file($media);
    db()->prepare('UPDATE blog_posts SET cover_media_id = NULL WHERE cover_media_id = :id')->execute([':id' => $id]);
    db()->prepare('DELETE FROM blog_media WHERE id = :id')->execute([':id' => $id]);
    blog_audit('user', null, 'media.delete', 'media', $id);
}

// --- Tokens de API -----------------------------------------------------------

function blog_admin_tokens(): array
{
    return db()->query('SELECT id, name, mode, scopes, allowed_ips, last_used_at, expires_at, revoked_at, created_at FROM blog_api_tokens ORDER BY id DESC')->fetchAll();
}

function blog_admin_token_create(string $name, string $mode, array $scopes, ?string $allowedIps, ?string $expiresAt): string
{
    $secret = bin2hex(random_bytes(24));
    $token = 'qeai_blog_' . $secret;
    $stmt = db()->prepare(
        'INSERT INTO blog_api_tokens (name, token_hash, mode, scopes, allowed_ips, expires_at)'
        . ' VALUES (:n, :h, :m, :s, :ip, :exp)'
    );
    $stmt->execute([
        ':n' => substr($name, 0, 120) ?: 'Token',
        ':h' => hash('sha256', $token),
        ':m' => $mode,
        ':s' => json_encode(array_values($scopes), JSON_UNESCAPED_UNICODE),
        ':ip' => $allowedIps ?: null,
        ':exp' => $expiresAt ?: null,
    ]);
    blog_audit('user', null, 'token.create', 'token', (int)db()->lastInsertId(), ['mode' => $mode]);
    return $token;
}

function blog_admin_token_revoke(int $id): void
{
    db()->prepare('UPDATE blog_api_tokens SET revoked_at = NOW() WHERE id = :id')->execute([':id' => $id]);
    blog_audit('user', null, 'token.revoke', 'token', $id);
}

// --- Autenticação de token de API -------------------------------------------

/** Retorna a linha do token válido ou null. */
function blog_token_authenticate(?string $token): ?array
{
    if (!is_string($token) || $token === '') return null;
    $stmt = db()->prepare('SELECT * FROM blog_api_tokens WHERE token_hash = :h LIMIT 1');
    $stmt->execute([':h' => hash('sha256', $token)]);
    $row = $stmt->fetch();
    if ($row === false) return null;
    if (!empty($row['revoked_at'])) return null;
    if (!empty($row['expires_at']) && strtotime((string)$row['expires_at']) < time()) return null;
    return $row;
}

function blog_token_scopes(array $token): array
{
    $scopes = json_decode((string)$token['scopes'], true);
    return is_array($scopes) ? $scopes : [];
}

function blog_token_can(array $token, string $scope): bool
{
    return in_array($scope, blog_token_scopes($token), true);
}

function blog_token_ips_ok(array $token): bool
{
    $allowed = trim((string)($token['allowed_ips'] ?? ''));
    if ($allowed === '') return true;
    $ips = array_filter(array_map('trim', explode(',', $allowed)));
    return in_array(client_ip(), $ips, true);
}

function blog_token_touch(int $id): void
{
    db()->prepare('UPDATE blog_api_tokens SET last_used_at = NOW() WHERE id = :id')->execute([':id' => $id]);
}
