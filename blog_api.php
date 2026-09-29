<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require_once ROOT_PATH . '/app/url.php';
require_once ROOT_PATH . '/app/blog.php';
require_once ROOT_PATH . '/app/blog_admin.php';
require_once ROOT_PATH . '/app/storage.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function api_out($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_error(string $code, string $message, int $status): void
{
    api_out(['error' => ['code' => $code, 'message' => $message]], $status);
}

function api_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function api_token_from_request(): ?string
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if ($header !== '' && preg_match('/^Bearer\s+(.+)$/i', trim($header), $match)) return trim($match[1]);
    $key = (string)($_SERVER['HTTP_X_API_KEY'] ?? '');
    return $key !== '' ? trim($key) : null;
}

function api_require_scope(array $token, string $scope): void
{
    if (!blog_token_can($token, $scope)) api_error('forbidden', 'Escopo ausente: ' . $scope . '.', 403);
}

/** Publicar exige token em modo publicador com o escopo correspondente. */
function api_require_publish(array $token): void
{
    if ((string)$token['mode'] !== 'publisher' || !blog_token_can($token, 'posts:publish')) {
        api_error('forbidden', 'Publicação exige um token publicador com o escopo posts:publish.', 403);
    }
}

function api_resolve_status(array $body, bool $isCreate, ?string $current = null): string
{
    $requested = array_key_exists('status', $body) ? (string)$body['status'] : ($isCreate ? 'draft' : (string)$current);
    return in_array($requested, ['draft', 'published', 'scheduled'], true) ? $requested : 'draft';
}

/** Converte o corpo JSON em entrada aceita por blog_admin_save_post. */
function api_post_input(array $token, array $body, ?array $existing, bool $isCreate): array
{
    $fields = ['title', 'slug', 'excerpt', 'body_md', 'published_at', 'seo_title', 'seo_description', 'canonical_url'];
    $input = [];
    foreach ($fields as $field) {
        if (array_key_exists($field, $body)) $input[$field] = (string)$body[$field];
        elseif ($existing !== null && array_key_exists($field, $existing)) $input[$field] = (string)$existing[$field];
    }
    $input['category_id'] = array_key_exists('category_id', $body) ? (int)$body['category_id'] : (int)($existing['category_id'] ?? 0);
    $input['cover_media_id'] = array_key_exists('cover_media_id', $body) ? (int)$body['cover_media_id'] : (int)($existing['cover_media_id'] ?? 0);
    $input['featured'] = array_key_exists('featured', $body) ? !empty($body['featured']) : !empty($existing['featured'] ?? false);
    if (array_key_exists('tags', $body)) {
        $input['tags'] = is_array($body['tags']) ? implode(', ', array_map('strval', $body['tags'])) : (string)$body['tags'];
    } else {
        $input['tags'] = $existing !== null ? blog_admin_tag_names((int)$existing['id']) : '';
    }
    $input['status'] = api_resolve_status($body, $isCreate, $existing['status'] ?? null);
    return $input;
}

function api_post_summary(array $post): array
{
    return [
        'id' => (int)$post['id'],
        'slug' => (string)$post['slug'],
        'title' => (string)$post['title'],
        'status' => (string)$post['status'],
        'published_at' => $post['published_at'],
        'updated_at' => $post['updated_at'],
        'url' => blog_site_url(blog_post_url($post)),
    ];
}

// --- Autenticação ------------------------------------------------------------

$token = blog_token_authenticate(api_token_from_request());
if ($token === null) api_error('unauthorized', 'Token de API inválido ou ausente.', 401);
if (!blog_token_ips_ok($token)) api_error('forbidden', 'Endereço IP não autorizado para este token.', 403);

$tokenId = (int)$token['id'];
$actorId = $tokenId;
blog_token_touch($tokenId);
if (!rate_limit('blog-api-' . $tokenId, 120, 60)) api_error('rate_limited', 'Limite de requisições excedido. Tente novamente em instantes.', 429);

// --- Roteamento --------------------------------------------------------------

$uriPath = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$prefix = base_path() . '/blog/api';
if (str_starts_with($uriPath, $prefix)) {
    $relative = substr($uriPath, strlen($prefix));
} elseif (isset($_GET['path'])) {
    $relative = '/' . ltrim((string)$_GET['path'], '/');
} else {
    $relative = '';
}
$segments = array_values(array_filter(explode('/', trim($relative, '/')), static fn($s) => $s !== ''));
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$body = in_array($method, ['POST', 'PATCH', 'PUT'], true) ? api_body() : [];

if (!$segments) {
    api_out(['name' => 'QEAI Blog API', 'version' => 1, 'driver' => storage_driver()]);
}

$resource = $segments[0];
$id = isset($segments[1]) && ctype_digit($segments[1]) ? (int)$segments[1] : null;
$sub = $segments[2] ?? null;

if ($resource === 'posts') {
    if ($id === null) {
        if ($method === 'GET') {
            api_require_scope($token, 'posts:read');
            $status = isset($_GET['status']) ? (string)$_GET['status'] : '';
            $search = isset($_GET['q']) ? (string)$_GET['q'] : '';
            $posts = blog_admin_posts($status, $search);
            api_out(['data' => array_map('api_post_summary', $posts)]);
        }
        if ($method === 'POST') {
            $publishing = in_array((string)($body['status'] ?? ''), ['published', 'scheduled'], true);
            if ($publishing) api_require_publish($token); else api_require_scope($token, 'drafts:write');
            $result = blog_admin_save_post(api_post_input($token, $body, null, true), 0, 'api', 'token', $actorId);
            if ($result['errors']) api_error('validation_error', implode(' ', $result['errors']), 422);
            $post = blog_admin_get_post((int)$result['id']);
            api_out(['data' => api_post_summary($post)], 201);
        }
        api_error('method_not_allowed', 'Método não permitido.', 405);
    }

    $post = blog_admin_get_post($id);
    if ($post === null) api_error('not_found', 'Post não encontrado.', 404);

    if ($sub === 'publish' && $method === 'POST') {
        api_require_scope($token, 'posts:publish');
        if ((string)$token['mode'] !== 'publisher') api_error('forbidden', 'Token em modo rascunho não pode publicar.', 403);
        blog_admin_set_status($id, 'published', 'token', $actorId);
        api_out(['data' => api_post_summary(blog_admin_get_post($id))]);
    }

    if ($method === 'GET') {
        api_require_scope($token, 'posts:read');
        api_out(['data' => api_post_summary($post) + ['excerpt' => (string)$post['excerpt'], 'body_md' => (string)$post['body_md'], 'tags' => blog_admin_tag_names($id)]]);
    }
    if ($method === 'PATCH' || $method === 'PUT') {
        $publishing = in_array((string)($body['status'] ?? ''), ['published', 'scheduled'], true);
        if ($publishing) api_require_publish($token); else api_require_scope($token, 'drafts:write');
        $result = blog_admin_save_post(api_post_input($token, $body, $post, false), $id, 'api', 'token', $actorId);
        if ($result['errors']) api_error('validation_error', implode(' ', $result['errors']), 422);
        api_out(['data' => api_post_summary(blog_admin_get_post($id))]);
    }
    if ($method === 'DELETE') {
        api_require_scope($token, 'posts:delete');
        if (isset($_GET['permanent']) && $_GET['permanent'] === '1') blog_admin_purge($id, 'token', $actorId);
        else blog_admin_set_status($id, 'trash', 'token', $actorId);
        api_out(['data' => ['id' => $id, 'deleted' => true]]);
    }
    api_error('method_not_allowed', 'Método não permitido.', 405);
}

if ($resource === 'media') {
    api_require_scope($token, 'media:write');
    $mediaSub = $segments[1] ?? null;

    if ($mediaSub === 'presign' && $method === 'POST') {
        if (storage_driver() !== 's3') api_error('conflict', 'O driver de mídia atual é local; envie o arquivo diretamente em POST /blog/api/media.', 409);
        $extension = strtolower((string)($body['extension'] ?? 'jpg'));
        if (!in_array($extension, array_values(STORAGE_ALLOWED_MIME), true)) $extension = 'jpg';
        $key = storage_s3_new_key($extension);
        api_out(['data' => ['upload_url' => s3_presign_put($key), 'key' => $key, 'public_url' => s3_object_url($key), 'expires_in' => 900]]);
    }

    if ($mediaSub === 'confirm' && $method === 'POST') {
        if (storage_driver() !== 's3') api_error('conflict', 'Confirmar só é necessário no driver S3.', 409);
        try {
            $mediaId = storage_register_s3_media(
                (string)($body['key'] ?? ''),
                (string)($body['mime'] ?? 'application/octet-stream'),
                (int)($body['size'] ?? 0),
                (int)($body['width'] ?? 0),
                (int)($body['height'] ?? 0),
                (string)($body['alt'] ?? ''),
                (string)($body['title'] ?? ''),
                'API'
            );
        } catch (Throwable $exception) {
            api_error('invalid_media', $exception->getMessage(), 422);
        }
        blog_audit('token', $actorId, 'media.create', 'media', $mediaId, ['driver' => 's3']);
        $media = blog_admin_get_media($mediaId);
        api_out(['data' => ['id' => $mediaId, 'url' => $media['url']]], 201);
    }

    if ($mediaSub === null && $method === 'POST') {
        if (storage_driver() === 's3') api_error('conflict', 'No driver S3 use /blog/api/media/presign e /blog/api/media/confirm.', 409);
        if (empty($_FILES['file']['name'])) api_error('invalid_media', 'Envie o arquivo no campo "file".', 422);
        try {
            $mediaId = storage_store_upload($_FILES['file'], (string)($_POST['alt'] ?? ''), (string)($_POST['title'] ?? ''), 'API');
        } catch (Throwable $exception) {
            api_error('invalid_media', $exception->getMessage(), 422);
        }
        blog_audit('token', $actorId, 'media.create', 'media', $mediaId, ['driver' => 'local']);
        $media = blog_admin_get_media($mediaId);
        api_out(['data' => ['id' => $mediaId, 'url' => $media['url']]], 201);
    }

    api_error('method_not_allowed', 'Método não permitido para este recurso.', 405);
}

api_error('not_found', 'Recurso não encontrado.', 404);
