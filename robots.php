<?php

declare(strict_types=1);

define('ROOT_PATH', __DIR__);
require_once ROOT_PATH . '/app/config_store.php';
require_once ROOT_PATH . '/app/url.php';

$config = load_config();

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: public, max-age=3600');

$sitemap = site_url('sitemap.xml');
?>
User-agent: *
Allow: /
Disallow: /workspace/
Disallow: /app/
Disallow: /config/
Disallow: /storage/
Disallow: /private-docs/
Disallow: /blog/api/

Sitemap: <?= htmlspecialchars($sitemap, ENT_QUOTES, 'UTF-8') ?>
