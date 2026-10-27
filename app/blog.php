<?php

declare(strict_types=1);

require_once ROOT_PATH . '/app/db.php';

/**
 * Camada de leitura do blog. Tudo aqui é preguiçoso: nenhuma consulta acontece
 * apenas por incluir este arquivo.
 */

// --- Utilidades -------------------------------------------------------------

function blog_slugify(string $value): string
{
    $value = trim($value);
    if ($value === '') return 'post';
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false) $value = $converted;
    }
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value === '' ? 'post' : substr($value, 0, 180);
}

function blog_render_markdown(string $markdown): string
{
    require_once ROOT_PATH . '/app/vendor/parsedown/Parsedown.php';
    static $parser = null;
    if ($parser === null) {
        $parser = new Parsedown();
        $parser->setSafeMode(true);   // remove HTML cru: o conteúdo vem de fora
        $parser->setBreaksEnabled(false);
    }
    return $parser->text($markdown);
}

function blog_reading_time(string $markdown): int
{
    $text = preg_replace('/[`*_>#\[\]\(\)!|~-]+/u', ' ', $markdown) ?? $markdown;
    $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return max(1, (int)ceil(count($words) / 200));
}

function blog_excerpt(array $post, int $limit = 180): string
{
    $excerpt = trim((string)($post['excerpt'] ?? ''));
    if ($excerpt !== '') return $excerpt;
    $plain = preg_replace('/[`*_>#\[\]\(\)!|~-]+/u', ' ', (string)($post['body_md'] ?? '')) ?? '';
    $plain = trim(preg_replace('/\s+/u', ' ', $plain) ?? '');
    if (function_exists('mb_strlen') && mb_strlen($plain) > $limit) return rtrim(mb_substr($plain, 0, $limit)) . '…';
    return $plain;
}

function blog_site_url(string $path = ''): string
{
    return site_url($path);
}

function blog_post_url(array $post): string
{
    return 'blog/' . rawurlencode((string)$post['slug']);
}

function blog_category_url(string $slug): string
{
    return 'blog/categoria/' . rawurlencode($slug);
}

function blog_tag_url(string $slug): string
{
    return 'blog/tag/' . rawurlencode($slug);
}

// --- Cache de HTML ----------------------------------------------------------

function blog_cache_dir(): string
{
    ensure_storage_directory('blog-cache');
    return ROOT_PATH . '/storage/blog-cache';
}

function blog_cache_get(string $key, int $ttl = 3600): ?string
{
    $file = blog_cache_dir() . '/' . hash('sha256', $key) . '.html';
    if (!is_file($file)) return null;
    if ((time() - (int)filemtime($file)) > $ttl) return null;
    $contents = file_get_contents($file);
    return $contents === false ? null : $contents;
}

function blog_cache_put(string $key, string $html): void
{
    $file = blog_cache_dir() . '/' . hash('sha256', $key) . '.html';
    @file_put_contents($file, $html, LOCK_EX);
}

function blog_cache_flush(): void
{
    $dir = blog_cache_dir();
    foreach (glob($dir . '/*.html') ?: [] as $file) @unlink($file);
}

// --- Consultas --------------------------------------------------------------

function blog_public_where(string $alias = 'p'): string
{
    return $alias . ".status = 'published' AND " . $alias . '.deleted_at IS NULL AND ('
        . $alias . '.published_at IS NULL OR ' . $alias . '.published_at <= NOW())';
}

/**
 * @return array{items:array,total:int,page:int,pages:int,per_page:int}
 */
function blog_query_posts(array $options = []): array
{
    $page = max(1, (int)($options['page'] ?? 1));
    $perPage = min(24, max(1, (int)($options['per_page'] ?? 9)));
    $where = [blog_public_where('p')];
    $params = [];
    $join = '';

    if (!empty($options['category'])) {
        $join .= ' JOIN blog_categories fc ON fc.id = p.category_id';
        $where[] = 'fc.slug = :category';
        $params[':category'] = (string)$options['category'];
    }
    if (!empty($options['tag'])) {
        $join .= ' JOIN blog_post_tags pt ON pt.post_id = p.id JOIN blog_tags t ON t.id = pt.tag_id';
        $where[] = 't.slug = :tag';
        $params[':tag'] = (string)$options['tag'];
    }
    if (!empty($options['search'])) {
        $where[] = '(p.title LIKE :search OR p.excerpt LIKE :search OR p.body_md LIKE :search)';
        $params[':search'] = '%' . (string)$options['search'] . '%';
    }
    if (!empty($options['exclude'])) {
        $where[] = 'p.id <> :exclude';
        $params[':exclude'] = (int)$options['exclude'];
    }

    $whereSql = implode(' AND ', $where);
    $pdo = db();

    $countStmt = $pdo->prepare("SELECT COUNT(DISTINCT p.id) FROM blog_posts p $join WHERE $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug"
        . " FROM blog_posts p $join"
        . " LEFT JOIN blog_categories c ON c.id = p.category_id"
        . " WHERE $whereSql"
        . " ORDER BY p.published_at DESC, p.id DESC"
        . " LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);

    return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
}

function blog_find_post(string $slug): ?array
{
    $stmt = db()->prepare(
        'SELECT p.*, c.name AS category_name, c.slug AS category_slug'
        . ' FROM blog_posts p LEFT JOIN blog_categories c ON c.id = p.category_id'
        . ' WHERE p.slug = :slug AND ' . blog_public_where('p') . ' LIMIT 1'
    );
    $stmt->execute([':slug' => $slug]);
    $post = $stmt->fetch();
    return $post === false ? null : $post;
}

function blog_adjacent_posts(array $post): array
{
    $pdo = db();
    $publishedAt = (string)($post['published_at'] ?? '');
    $prev = $pdo->prepare(
        'SELECT p.slug, p.title FROM blog_posts p WHERE ' . blog_public_where('p')
        . ' AND (p.published_at > :date OR (p.published_at = :date2 AND p.id > :id))'
        . ' ORDER BY p.published_at ASC, p.id ASC LIMIT 1'
    );
    $prev->execute([':date' => $publishedAt, ':date2' => $publishedAt, ':id' => (int)$post['id']]);
    $next = $pdo->prepare(
        'SELECT p.slug, p.title FROM blog_posts p WHERE ' . blog_public_where('p')
        . ' AND (p.published_at < :date OR (p.published_at = :date2 AND p.id < :id))'
        . ' ORDER BY p.published_at DESC, p.id DESC LIMIT 1'
    );
    $next->execute([':date' => $publishedAt, ':date2' => $publishedAt, ':id' => (int)$post['id']]);
    return [
        'prev' => $prev->fetch() ?: null,
        'next' => $next->fetch() ?: null,
    ];
}

function blog_categories(): array
{
    $sql = 'SELECT c.*, COUNT(p.id) AS post_count FROM blog_categories c'
        . ' LEFT JOIN blog_posts p ON p.category_id = c.id AND ' . blog_public_where('p')
        . ' GROUP BY c.id HAVING post_count > 0 ORDER BY c.name ASC';
    return db()->query($sql)->fetchAll();
}

function blog_category(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM blog_categories WHERE slug = :slug LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    return $stmt->fetch() ?: null;
}

function blog_tags(): array
{
    $sql = 'SELECT t.*, COUNT(p.id) AS post_count FROM blog_tags t'
        . ' JOIN blog_post_tags pt ON pt.tag_id = t.id'
        . ' JOIN blog_posts p ON p.id = pt.post_id AND ' . blog_public_where('p')
        . ' GROUP BY t.id ORDER BY t.name ASC';
    return db()->query($sql)->fetchAll();
}

function blog_tag(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM blog_tags WHERE slug = :slug LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    return $stmt->fetch() ?: null;
}

function blog_tags_for_post(int $postId): array
{
    $stmt = db()->prepare(
        'SELECT t.* FROM blog_tags t JOIN blog_post_tags pt ON pt.tag_id = t.id'
        . ' WHERE pt.post_id = :id ORDER BY t.name ASC'
    );
    $stmt->execute([':id' => $postId]);
    return $stmt->fetchAll();
}

function blog_media(?int $mediaId): ?array
{
    if ($mediaId === null || $mediaId <= 0) return null;
    $stmt = db()->prepare('SELECT * FROM blog_media WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $mediaId]);
    return $stmt->fetch() ?: null;
}

// --- SEO / dados estruturados -----------------------------------------------

function blog_post_seo(array $post): array
{
    $title = trim((string)($post['seo_title'] ?? ''));
    if ($title === '') $title = (string)$post['title'] . ' — QEAI';

    $description = trim((string)($post['seo_description'] ?? ''));
    if ($description === '') $description = blog_excerpt($post, 155);

    $canonical = trim((string)($post['canonical_url'] ?? ''));
    if ($canonical === '') $canonical = blog_site_url(blog_post_url($post));

    return ['title' => $title, 'description' => $description, 'canonical' => $canonical];
}

function blog_jsonld(array $data): string
{
    return '<script type="application/ld+json">'
        . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . '</script>';
}

function blog_jsonld_article(array $post, array $tags): string
{
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => (string)$post['title'],
        'description' => blog_excerpt($post, 200),
        'datePublished' => date(DATE_ATOM, strtotime((string)$post['published_at'])),
        'dateModified' => date(DATE_ATOM, strtotime((string)($post['updated_at'] ?: $post['published_at']))),
        'mainEntityOfPage' => blog_site_url(blog_post_url($post)),
        'author' => ['@type' => 'Organization', 'name' => (string)($post['author_name'] ?: 'QEAI')],
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'QEAI',
            'logo' => ['@type' => 'ImageObject', 'url' => blog_site_url('assets/img/logo.svg')],
        ],
    ];
    if (!empty($tags)) $data['keywords'] = implode(', ', array_column($tags, 'name'));
    $cover = blog_media(isset($post['cover_media_id']) ? (int)$post['cover_media_id'] : null);
    if ($cover) $data['image'] = $cover['url'];
    return blog_jsonld($data);
}

function blog_jsonld_breadcrumbs(array $items): string
{
    $list = [];
    $position = 1;
    foreach ($items as $item) {
        $entry = ['@type' => 'ListItem', 'position' => $position++, 'name' => $item['name']];
        if (!empty($item['url'])) $entry['item'] = $item['url'];
        $list[] = $entry;
    }
    return blog_jsonld(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list]);
}
