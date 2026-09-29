<?php

declare(strict_types=1);

require_once ROOT_PATH . '/app/db.php';

/**
 * Autenticação e autorização do painel: usuários por e-mail e grupos de acesso
 * (papéis) com permissões configuráveis. Usa o banco do site.
 */

// --- Catálogo de permissões --------------------------------------------------

function auth_permissions_catalog(): array
{
    return [
        'site.settings' => 'Configurações do site',
        'site.users' => 'Usuários e grupos de acesso',
        'blog.view' => 'Ver o blog',
        'blog.create' => 'Criar posts',
        'blog.edit.any' => 'Editar posts',
        'blog.publish' => 'Publicar/despublicar',
        'blog.delete' => 'Excluir posts',
        'blog.media' => 'Gerenciar mídia',
        'blog.tokens' => 'Gerenciar tokens de API',
        'blog.settings' => 'Configurações do blog',
        'blog.audit' => 'Ver auditoria',
    ];
}

// --- Esquema (auto-recuperação em bancos antigos) ----------------------------

function auth_install_schema(): void
{
    $pdo = db();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS roles ('
        . ' id INT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(80) NOT NULL, slug VARCHAR(80) NOT NULL,'
        . ' permissions LONGTEXT NOT NULL, is_system TINYINT(1) NOT NULL DEFAULT 0,'
        . ' created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
        . ' updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,'
        . ' PRIMARY KEY (id), UNIQUE KEY uq_roles_slug (slug)'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users ('
        . ' id INT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(120) NOT NULL DEFAULT \'\', email VARCHAR(190) NOT NULL,'
        . ' password_hash VARCHAR(255) NOT NULL, role_id INT UNSIGNED NULL,'
        . ' status ENUM(\'active\',\'disabled\') NOT NULL DEFAULT \'active\','
        . ' must_change_password TINYINT(1) NOT NULL DEFAULT 0, last_login_at DATETIME NULL, created_by INT UNSIGNED NULL,'
        . ' created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
        . ' updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,'
        . ' PRIMARY KEY (id), UNIQUE KEY uq_users_email (email), KEY idx_users_role (role_id)'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS user_tokens ('
        . ' id INT UNSIGNED NOT NULL AUTO_INCREMENT, user_id INT UNSIGNED NOT NULL,'
        . ' purpose ENUM(\'reset\',\'invite\') NOT NULL, token_hash CHAR(64) NOT NULL,'
        . ' expires_at DATETIME NOT NULL, used_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
        . ' PRIMARY KEY (id), UNIQUE KEY uq_user_tokens_hash (token_hash), KEY idx_user_tokens_user (user_id, purpose)'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $stmt = $pdo->prepare('INSERT INTO roles (name, slug, permissions, is_system) VALUES (:n, :s, :p, 1) ON DUPLICATE KEY UPDATE name = VALUES(name)');
    $defaults = [
        ['ADM', 'adm', '["*"]'],
        ['Desenvolvedor', 'desenvolvedor', '["site.settings","blog.view","blog.create","blog.edit.any","blog.publish","blog.delete","blog.media","blog.tokens","blog.settings","blog.audit"]'],
        ['Escritor', 'escritor', '["blog.view","blog.create","blog.edit.any","blog.publish","blog.media"]'],
        ['Convidado', 'convidado', '["blog.view"]'],
    ];
    foreach ($defaults as $role) $stmt->execute([':n' => $role[0], ':s' => $role[1], ':p' => $role[2]]);
}

function auth_installed(): bool
{
    try {
        return (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
    } catch (Throwable $exception) {
        return false;
    }
}

// --- Grupos de acesso --------------------------------------------------------

function auth_roles(): array
{
    return db()->query('SELECT * FROM roles ORDER BY is_system DESC, name ASC')->fetchAll();
}

function auth_role(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM roles WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

function auth_role_permissions(array $role): array
{
    $permissions = json_decode((string)$role['permissions'], true);
    return is_array($permissions) ? $permissions : [];
}

function auth_slug(string $value): string
{
    $value = strtolower(trim($value));
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false) $value = $converted;
    }
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-') ?: 'grupo';
}

function auth_role_save(?int $id, string $name, array $permissions): int
{
    $name = substr(trim($name), 0, 80) ?: 'Grupo';
    $catalog = array_keys(auth_permissions_catalog());
    $permissions = array_values(array_intersect($permissions, $catalog));
    $json = json_encode($permissions, JSON_UNESCAPED_UNICODE);
    if ($id !== null && $id > 0) {
        db()->prepare('UPDATE roles SET name = :n, permissions = :p WHERE id = :id')->execute([':n' => $name, ':p' => $json, ':id' => $id]);
        return $id;
    }
    $slug = auth_slug($name);
    $suffix = 2;
    while (true) {
        $exists = db()->prepare('SELECT id FROM roles WHERE slug = :s LIMIT 1');
        $exists->execute([':s' => $slug]);
        if ($exists->fetch() === false) break;
        $slug = auth_slug($name) . '-' . $suffix++;
    }
    db()->prepare('INSERT INTO roles (name, slug, permissions, is_system) VALUES (:n, :s, :p, 0)')->execute([':n' => $name, ':s' => $slug, ':p' => $json]);
    return (int)db()->lastInsertId();
}

function auth_role_in_use(int $id): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE role_id = :id');
    $stmt->execute([':id' => $id]);
    return (int)$stmt->fetchColumn();
}

function auth_role_delete(int $id): void
{
    $role = auth_role($id);
    if ($role === null) return;
    if ((int)$role['is_system'] === 1) throw new RuntimeException('Grupos padrão não podem ser excluídos.');
    if (auth_role_in_use($id) > 0) throw new RuntimeException('Há usuários neste grupo. Mova-os antes de excluir.');
    db()->prepare('DELETE FROM roles WHERE id = :id')->execute([':id' => $id]);
}

// --- Usuários ----------------------------------------------------------------

function auth_users(): array
{
    return db()->query('SELECT u.*, r.name AS role_name FROM users u LEFT JOIN roles r ON r.id = u.role_id ORDER BY u.name ASC, u.email ASC')->fetchAll();
}

function auth_user(int $id): ?array
{
    $stmt = db()->prepare('SELECT u.*, r.name AS role_name, r.permissions AS role_permissions FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

function auth_user_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT u.*, r.permissions AS role_permissions FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.email = :e LIMIT 1');
    $stmt->execute([':e' => mb_strtolower(trim($email), 'UTF-8')]);
    return $stmt->fetch() ?: null;
}

function auth_generate_password(int $length = 14): string
{
    $alphabet = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%';
    $password = '';
    for ($i = 0; $i < $length; $i++) $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    return $password;
}

function auth_user_create(string $name, string $email, string $password, int $roleId, bool $mustChange = false, ?int $createdBy = null): int
{
    $email = mb_strtolower(trim($email), 'UTF-8');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Informe um e-mail válido.');
    if (auth_user_by_email($email) !== null) throw new RuntimeException('Já existe um usuário com este e-mail.');
    if (auth_role($roleId) === null) throw new RuntimeException('Grupo de acesso inválido.');
    if (strlen($password) < 10 || strlen($password) > 72) throw new RuntimeException('A senha deve ter de 10 a 72 bytes.');
    $stmt = db()->prepare('INSERT INTO users (name, email, password_hash, role_id, must_change_password, created_by) VALUES (:n, :e, :p, :r, :m, :c)');
    $stmt->execute([
        ':n' => substr(trim($name), 0, 120),
        ':e' => $email,
        ':p' => password_hash($password, PASSWORD_DEFAULT),
        ':r' => $roleId,
        ':m' => $mustChange ? 1 : 0,
        ':c' => $createdBy,
    ]);
    return (int)db()->lastInsertId();
}

function auth_user_update(int $id, string $name, string $email, int $roleId, string $status): void
{
    $email = mb_strtolower(trim($email), 'UTF-8');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Informe um e-mail válido.');
    $existing = auth_user_by_email($email);
    if ($existing !== null && (int)$existing['id'] !== $id) throw new RuntimeException('Já existe um usuário com este e-mail.');
    if (auth_role($roleId) === null) throw new RuntimeException('Grupo de acesso inválido.');
    if (!in_array($status, ['active', 'disabled'], true)) $status = 'active';
    db()->prepare('UPDATE users SET name = :n, email = :e, role_id = :r, status = :s WHERE id = :id')
        ->execute([':n' => substr(trim($name), 0, 120), ':e' => $email, ':r' => $roleId, ':s' => $status, ':id' => $id]);
}

function auth_user_set_password(int $id, string $password, bool $mustChange = false): void
{
    if (strlen($password) < 10 || strlen($password) > 72) throw new RuntimeException('A senha deve ter de 10 a 72 bytes.');
    db()->prepare('UPDATE users SET password_hash = :p, must_change_password = :m WHERE id = :id')
        ->execute([':p' => password_hash($password, PASSWORD_DEFAULT), ':m' => $mustChange ? 1 : 0, ':id' => $id]);
}

function auth_user_delete(int $id): void
{
    if (auth_count_admins() <= 1 && auth_user_is_admin($id)) throw new RuntimeException('É preciso manter ao menos um ADM ativo.');
    db()->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $id]);
}

function auth_user_is_admin(int $id): bool
{
    $user = auth_user($id);
    if ($user === null) return false;
    $permissions = json_decode((string)($user['role_permissions'] ?? '[]'), true);
    return is_array($permissions) && in_array('*', $permissions, true);
}

function auth_count_admins(): int
{
    $sql = "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.permissions LIKE '%\"*\"%'";
    return (int)db()->query($sql)->fetchColumn();
}

// --- Tokens de redefinição e convite ----------------------------------------

function auth_create_token(int $userId, string $purpose, int $ttlSeconds = 604800): string
{
    if (!in_array($purpose, ['reset', 'invite'], true)) throw new RuntimeException('Tipo de token inválido.');
    $ttl = max(60, min(2592000, $ttlSeconds));
    $raw = bin2hex(random_bytes(32));
    db()->prepare("INSERT INTO user_tokens (user_id, purpose, token_hash, expires_at) VALUES (:u, :p, :h, DATE_ADD(NOW(), INTERVAL $ttl SECOND))")
        ->execute([':u' => $userId, ':p' => $purpose, ':h' => hash('sha256', $raw)]);
    return $raw;
}

function auth_find_token(string $raw, string $purpose): ?array
{
    if (!in_array($purpose, ['reset', 'invite'], true) || $raw === '') return null;
    $stmt = db()->prepare(
        'SELECT t.*, u.email, u.name, u.status FROM user_tokens t JOIN users u ON u.id = t.user_id'
        . ' WHERE t.token_hash = :h AND t.purpose = :p AND t.used_at IS NULL AND t.expires_at > NOW() LIMIT 1'
    );
    $stmt->execute([':h' => hash('sha256', $raw), ':p' => $purpose]);
    return $stmt->fetch() ?: null;
}

function auth_find_any_token(string $raw): ?array
{
    return auth_find_token($raw, 'reset') ?? auth_find_token($raw, 'invite');
}

function auth_consume_token(int $id): void
{
    db()->prepare('UPDATE user_tokens SET used_at = NOW() WHERE id = :id')->execute([':id' => $id]);
}

function auth_invalidate_tokens(int $userId, string $purpose): void
{
    db()->prepare('UPDATE user_tokens SET used_at = NOW() WHERE user_id = :u AND purpose = :p AND used_at IS NULL')
        ->execute([':u' => $userId, ':p' => $purpose]);
}

// --- Sessão ------------------------------------------------------------------

function auth_login(string $email, string $password): bool
{
    $user = auth_user_by_email($email);
    if ($user === null || $user['status'] !== 'active') return false;
    if (!password_verify($password, (string)$user['password_hash'])) return false;
    session_regenerate_id(true);
    $_SESSION['qeai_user'] = (int)$user['id'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')->execute([':id' => (int)$user['id']]);
    return true;
}

function auth_logout(): void
{
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $params['path'], 'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    session_destroy();
}

function auth_check(): bool
{
    return !empty($_SESSION['qeai_user']);
}

function auth_current(): ?array
{
    static $user = false;
    if ($user !== false) return $user;
    if (empty($_SESSION['qeai_user'])) return $user = null;
    $loaded = auth_user((int)$_SESSION['qeai_user']);
    if ($loaded === null || $loaded['status'] !== 'active') {
        unset($_SESSION['qeai_user']);
        return $user = null;
    }
    return $user = $loaded;
}

function auth_user_id(): ?int
{
    $user = auth_current();
    return $user === null ? null : (int)$user['id'];
}

function auth_can(string $permission): bool
{
    $user = auth_current();
    if ($user === null) return false;
    $permissions = json_decode((string)($user['role_permissions'] ?? '[]'), true);
    if (!is_array($permissions)) return false;
    return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
}

function auth_require(string $permission): void
{
    if (!auth_can($permission)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Acesso negado</title><body style="font-family:system-ui;max-width:560px;margin:12vh auto;padding:24px;line-height:1.6"><h1>Acesso negado</h1><p>Seu grupo de acesso não permite esta ação.</p><p><a href="./">Voltar ao painel</a></p></body></html>';
        exit;
    }
}
