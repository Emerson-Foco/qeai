<?php

require __DIR__ . '/app/bootstrap.php';
require_once ROOT_PATH . '/app/public_parts.php';
require_once ROOT_PATH . '/app/blog.php';

function feed_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

header('Content-Type: application/rss+xml; charset=UTF-8');
header('Cache-Control: public, max-age=900');

$limit = 20;
$posts = [];
try {
    $posts = blog_query_posts(['per_page' => $limit, 'page' => 1])['items'];
} catch (Throwable $exception) {
    error_log('[QEAI feed] ' . $exception->getMessage());
}

$site = blog_site_url('');
$title = 'Blog da QEAI';
$description = 'Estratégia, conteúdo e inteligência artificial para colocar sua marca em movimento.';

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title><?= feed_escape($title) ?></title>
    <link><?= feed_escape($site) ?></link>
    <description><?= feed_escape($description) ?></description>
    <language>pt-BR</language>
    <atom:link href="<?= feed_escape(blog_site_url('feed')) ?>" rel="self" type="application/rss+xml" />
    <lastBuildDate><?= feed_escape(date(DATE_RSS)) ?></lastBuildDate>
<?php foreach ($posts as $post): $link = blog_site_url(blog_post_url($post)); ?>
    <item>
      <title><?= feed_escape((string)$post['title']) ?></title>
      <link><?= feed_escape($link) ?></link>
      <guid isPermaLink="true"><?= feed_escape($link) ?></guid>
      <pubDate><?= feed_escape(date(DATE_RSS, strtotime((string)$post['published_at']) ?: time())) ?></pubDate>
      <description><?= feed_escape(blog_excerpt($post, 240)) ?></description>
    </item>
<?php endforeach; ?>
  </channel>
</rss>
