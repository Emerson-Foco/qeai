<?php

require __DIR__ . '/app/bootstrap.php';
require_once ROOT_PATH . '/app/public_parts.php';
require_once ROOT_PATH . '/app/blog.php';

function sitemap_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function sitemap_lastmod(?string $datetime): string
{
    $timestamp = $datetime ? strtotime($datetime) : false;
    return date('Y-m-d', $timestamp ?: time());
}

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=900');

$urls = [];
$staticPages = [
    ['loc' => blog_site_url(''), 'lastmod' => date('Y-m-d'), 'priority' => '1.0'],
    ['loc' => blog_site_url('blog'), 'lastmod' => date('Y-m-d'), 'priority' => '0.9'],
    ['loc' => blog_site_url('privacidade'), 'lastmod' => date('Y-m-d'), 'priority' => '0.3'],
    ['loc' => blog_site_url('termos'), 'lastmod' => date('Y-m-d'), 'priority' => '0.3'],
    ['loc' => blog_site_url('cookies'), 'lastmod' => date('Y-m-d'), 'priority' => '0.3'],
];
foreach ($staticPages as $page) $urls[] = $page;

try {
    $posts = db()->query('SELECT p.slug, p.updated_at, p.published_at FROM blog_posts p WHERE ' . blog_public_where('p') . ' ORDER BY p.published_at DESC')->fetchAll();
    foreach ($posts as $post) {
        $urls[] = ['loc' => blog_site_url('blog/' . rawurlencode((string)$post['slug'])), 'lastmod' => sitemap_lastmod((string)($post['updated_at'] ?: $post['published_at'])), 'priority' => '0.8'];
    }
    foreach (blog_categories() as $category) {
        $urls[] = ['loc' => blog_site_url('blog/categoria/' . rawurlencode((string)$category['slug'])), 'lastmod' => date('Y-m-d'), 'priority' => '0.4'];
    }
    foreach (blog_tags() as $tag) {
        $urls[] = ['loc' => blog_site_url('blog/tag/' . rawurlencode((string)$tag['slug'])), 'lastmod' => date('Y-m-d'), 'priority' => '0.3'];
    }
} catch (Throwable $exception) {
    error_log('[QEAI sitemap] ' . $exception->getMessage());
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $entry): ?>
  <url>
    <loc><?= sitemap_escape($entry['loc']) ?></loc>
    <lastmod><?= sitemap_escape($entry['lastmod']) ?></lastmod>
    <priority><?= sitemap_escape($entry['priority']) ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
