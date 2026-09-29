<?php

require __DIR__ . '/app/bootstrap.php';
require_once ROOT_PATH . '/app/public_parts.php';
require_once ROOT_PATH . '/app/blog.php';

function blog_date(?string $datetime): string
{
    if (!$datetime) return '';
    $timestamp = strtotime($datetime);
    if ($timestamp === false) return '';
    $months = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    return date('d', $timestamp) . ' de ' . $months[(int)date('n', $timestamp) - 1] . ' de ' . date('Y', $timestamp);
}

function blog_page_url(int $page, string $base, array $params = []): string
{
    if ($page > 1) $params['page'] = (string)$page;
    $query = $params ? '?' . http_build_query($params) : '';
    return url($base) . $query;
}

function blog_pagination(int $page, int $pages, string $base, array $params = []): string
{
    if ($pages <= 1) return '';
    $html = '<nav class="pagination" aria-label="Paginação">';
    if ($page > 1) {
        $html .= '<a class="pagination-link" rel="prev" href="' . e(blog_page_url($page - 1, $base, $params)) . '">← Anterior</a>';
    } else {
        $html .= '<span class="pagination-link is-disabled">← Anterior</span>';
    }
    $html .= '<span class="pagination-status">Página ' . $page . ' de ' . $pages . '</span>';
    if ($page < $pages) {
        $html .= '<a class="pagination-link" rel="next" href="' . e(blog_page_url($page + 1, $base, $params)) . '">Próxima →</a>';
    } else {
        $html .= '<span class="pagination-link is-disabled">Próxima →</span>';
    }
    return $html . '</nav>';
}

function blog_breadcrumbs(array $items): string
{
    $html = '<nav class="breadcrumbs" aria-label="Trilha de navegação"><ol>';
    $last = count($items) - 1;
    foreach ($items as $index => $item) {
        $html .= '<li>';
        if ($index !== $last && !empty($item['url'])) {
            $html .= '<a href="' . e($item['url']) . '">' . e($item['name']) . '</a>';
        } else {
            $html .= '<span aria-current="page">' . e($item['name']) . '</span>';
        }
        $html .= '</li>';
    }
    return $html . '</ol></nav>';
}

function blog_post_card(array $post): string
{
    $cover = blog_media(isset($post['cover_media_id']) ? (int)$post['cover_media_id'] : null);
    $html = '<article class="post-card">';
    if ($cover) {
        $alt = $cover['alt'] !== '' ? $cover['alt'] : (string)$post['title'];
        $html .= '<a class="post-card-media" href="' . e(url(blog_post_url($post))) . '" tabindex="-1" aria-hidden="true"><img src="' . e($cover['url']) . '" alt="' . e($alt) . '" loading="lazy"'
            . ($cover['width'] > 0 ? ' width="' . (int)$cover['width'] . '"' : '') . ($cover['height'] > 0 ? ' height="' . (int)$cover['height'] . '"' : '')
            . '></a>';
    }
    $html .= '<div class="post-card-body">';
    if (!empty($post['category_name'])) {
        $html .= '<a class="post-category" href="' . e(url(blog_category_url((string)$post['category_slug']))) . '">' . e((string)$post['category_name']) . '</a>';
    }
    $html .= '<h2 class="post-card-title"><a href="' . e(url(blog_post_url($post))) . '">' . e((string)$post['title']) . '</a></h2>';
    $html .= '<p class="post-card-excerpt">' . e(blog_excerpt($post)) . '</p>';
    $html .= '<div class="post-meta"><time datetime="' . e((string)$post['published_at']) . '">' . e(blog_date((string)$post['published_at'])) . '</time><span aria-hidden="true">·</span><span>' . (int)$post['reading_time'] . ' min de leitura</span></div>';
    return $html . '</div></article>';
}

function blog_list_page(array $config, array $result, array $context): void
{
    $base = (string)$context['base'];
    $params = $context['params'] ?? [];
    $title = (string)$context['title'];
    $heading = (string)$context['heading'];
    $description = (string)$context['description'];
    $crumbs = $context['crumbs'];
    $robots = !empty($context['noindex']) ? 'noindex,follow' : 'index,follow';
    $canonical = $context['canonical'];

    $jsonld = blog_jsonld_breadcrumbs(array_map(fn($c) => ['name' => $c['name'], 'url' => $c['url'] ?? ''], $crumbs));

    public_header($config, $title, [
        'canonical' => $canonical,
        'description' => $description,
        'robots' => $robots,
        'jsonld' => $jsonld,
        'styles' => ['assets/css/blog.css'],
        'body_class' => 'blog-page',
    ]);
    ?>
    <main id="conteudo" class="blog-wrap container">
      <?= blog_breadcrumbs($crumbs) ?>
      <header class="blog-head">
        <span class="eyebrow">Blog da QEAI</span>
        <h1><?= e($heading) ?></h1>
        <p class="blog-head-desc"><?= e($description) ?></p>
      </header>
      <?php if (!$result['items']): ?>
        <div class="empty-state"><p>Nenhum conteúdo publicado por aqui ainda. Volte em breve.</p><a class="btn btn-light" href="<?= e(url('blog')) ?>">Ver todos os posts</a></div>
      <?php else: ?>
        <div class="post-grid">
          <?php foreach ($result['items'] as $post) echo blog_post_card($post); ?>
        </div>
        <?= blog_pagination((int)$result['page'], (int)$result['pages'], $base, $params) ?>
      <?php endif; ?>
    </main>
    <?php
    public_footer($config);
}

function blog_single_page(array $config, array $post): void
{
    $tags = blog_tags_for_post((int)$post['id']);
    $seo = blog_post_seo($post);
    $cover = blog_media(isset($post['cover_media_id']) ? (int)$post['cover_media_id'] : null);
    $adjacent = blog_adjacent_posts($post);

    $cacheKey = 'article:' . (int)$post['id'] . ':' . (string)($post['updated_at'] ?? '');
    $content = blog_cache_get($cacheKey);
    if ($content === null) {
        $content = blog_render_markdown((string)$post['body_md']);
        blog_cache_put($cacheKey, $content);
    }

    $crumbs = [
        ['name' => 'Início', 'url' => url('')],
        ['name' => 'Blog', 'url' => url('blog')],
    ];
    if (!empty($post['category_name'])) {
        $crumbs[] = ['name' => (string)$post['category_name'], 'url' => url(blog_category_url((string)$post['category_slug']))];
    }
    $crumbs[] = ['name' => (string)$post['title']];

    $jsonld = blog_jsonld_article($post, $tags)
        . blog_jsonld_breadcrumbs(array_map(fn($c) => ['name' => $c['name'], 'url' => $c['url'] ?? ''], $crumbs));

    $ogImage = $cover ? $cover['url'] : null;

    public_header($config, $seo['title'], [
        'canonical' => $seo['canonical'],
        'description' => $seo['description'],
        'og_type' => 'article',
        'og_image' => $ogImage,
        'jsonld' => $jsonld,
        'styles' => ['assets/css/blog.css'],
        'body_class' => 'blog-page blog-single',
    ]);
    ?>
    <main id="conteudo" class="blog-wrap container">
      <?= blog_breadcrumbs($crumbs) ?>
      <article class="post">
        <header class="post-header">
          <?php if (!empty($post['category_name'])): ?><a class="post-category" href="<?= e(url(blog_category_url((string)$post['category_slug']))) ?>"><?= e((string)$post['category_name']) ?></a><?php endif; ?>
          <h1><?= e((string)$post['title']) ?></h1>
          <p class="post-lead"><?= e(blog_excerpt($post, 240)) ?></p>
          <div class="post-meta">
            <span><?= e((string)($post['author_name'] ?: 'QEAI')) ?></span><span aria-hidden="true">·</span>
            <time datetime="<?= e((string)$post['published_at']) ?>"><?= e(blog_date((string)$post['published_at'])) ?></time><span aria-hidden="true">·</span>
            <span><?= (int)$post['reading_time'] ?> min de leitura</span>
          </div>
        </header>
        <?php if ($cover): ?>
        <figure class="post-cover"><img src="<?= e($cover['url']) ?>" alt="<?= e($cover['alt'] !== '' ? $cover['alt'] : (string)$post['title']) ?>"<?= $cover['width'] > 0 ? ' width="' . (int)$cover['width'] . '"' : '' ?><?= $cover['height'] > 0 ? ' height="' . (int)$cover['height'] . '"' : '' ?>></figure>
        <?php endif; ?>
        <div class="post-body"><?= $content ?></div>
        <?php if ($tags): ?>
        <footer class="post-tags">
          <span>Assuntos:</span>
          <?php foreach ($tags as $tag): ?><a class="tag-pill" href="<?= e(url(blog_tag_url((string)$tag['slug']))) ?>"><?= e((string)$tag['name']) ?></a><?php endforeach; ?>
        </footer>
        <?php endif; ?>
      </article>
      <?php if ($adjacent['prev'] || $adjacent['next']): ?>
      <nav class="post-nav" aria-label="Outros posts">
        <?php if ($adjacent['prev']): ?><a class="post-nav-item" href="<?= e(url('blog/' . rawurlencode((string)$adjacent['prev']['slug']))) ?>"><small>← Mais recente</small><strong><?= e((string)$adjacent['prev']['title']) ?></strong></a><?php else: ?><span></span><?php endif; ?>
        <?php if ($adjacent['next']): ?><a class="post-nav-item post-nav-next" href="<?= e(url('blog/' . rawurlencode((string)$adjacent['next']['slug']))) ?>"><small>Anterior →</small><strong><?= e((string)$adjacent['next']['title']) ?></strong></a><?php endif; ?>
      </nav>
      <?php endif; ?>
      <aside class="post-cta">
        <div><span class="eyebrow">Vamos conversar</span><h2>Sua marca pode ir além.</h2><p>Conte o que você tem em mente e vamos construir juntos a próxima etapa.</p></div>
        <a class="btn btn-primary" href="<?= e(url('') . '#contato') ?>">Falar com a QEAI <span aria-hidden="true">↗</span></a>
      </aside>
    </main>
    <?php
    public_footer($config);
}

function blog_not_found(array $config): void
{
    http_response_code(404);
    $crumbs = [['name' => 'Início', 'url' => url('')], ['name' => 'Blog', 'url' => url('blog')], ['name' => 'Conteúdo não encontrado']];
    public_header($config, 'Conteúdo não encontrado — QEAI', [
        'canonical' => blog_site_url('blog'),
        'description' => 'O conteúdo que você procura não está disponível.',
        'robots' => 'noindex,follow',
        'jsonld' => blog_jsonld_breadcrumbs([['name' => 'Início', 'url' => url('')], ['name' => 'Blog', 'url' => url('blog')], ['name' => 'Não encontrado']]),
        'styles' => ['assets/css/blog.css'],
        'body_class' => 'blog-page',
    ]);
    ?>
    <main id="conteudo" class="blog-wrap container">
      <?= blog_breadcrumbs($crumbs) ?>
      <div class="empty-state">
        <span class="eyebrow">404</span>
        <h1>Não encontramos este conteúdo.</h1>
        <p>O link pode ter mudado ou o conteúdo foi removido. Veja o que já publicamos.</p>
        <a class="btn btn-primary" href="<?= e(url('blog')) ?>">Ir para o blog <span aria-hidden="true">↗</span></a>
      </div>
    </main>
    <?php
    public_footer($config);
}

// --- Roteamento -------------------------------------------------------------

$categorySlug = isset($_GET['category']) ? trim((string)$_GET['category']) : '';
$tagSlug = isset($_GET['tag']) ? trim((string)$_GET['tag']) : '';
$postSlug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
$search = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

try {
    if ($postSlug !== '') {
        $post = blog_find_post($postSlug);
        if (!$post) {
            blog_not_found($config);
        } else {
            blog_single_page($config, $post);
        }
    } elseif ($categorySlug !== '') {
        $category = blog_category($categorySlug);
        if (!$category) {
            blog_not_found($config);
            exit;
        }
        $result = blog_query_posts(['category' => $categorySlug, 'page' => $page]);
        blog_list_page($config, $result, [
            'base' => 'blog/categoria/' . $categorySlug,
            'params' => [],
            'title' => 'Blog · ' . $category['name'] . ' — QEAI',
            'heading' => (string)$category['name'],
            'description' => (string)($category['description'] ?: 'Conteúdos sobre ' . $category['name'] . ' publicados pela QEAI.'),
            'crumbs' => [['name' => 'Início', 'url' => url('')], ['name' => 'Blog', 'url' => url('blog')], ['name' => (string)$category['name']]],
            'canonical' => blog_site_url('blog/categoria/' . $categorySlug),
        ]);
    } elseif ($tagSlug !== '') {
        $tag = blog_tag($tagSlug);
        if (!$tag) {
            blog_not_found($config);
            exit;
        }
        $result = blog_query_posts(['tag' => $tagSlug, 'page' => $page]);
        blog_list_page($config, $result, [
            'base' => 'blog/tag/' . $tagSlug,
            'params' => [],
            'title' => 'Blog · ' . $tag['name'] . ' — QEAI',
            'heading' => '#' . $tag['name'],
            'description' => 'Conteúdos marcados com ' . $tag['name'] . ' no blog da QEAI.',
            'crumbs' => [['name' => 'Início', 'url' => url('')], ['name' => 'Blog', 'url' => url('blog')], ['name' => '#' . $tag['name']]],
            'canonical' => blog_site_url('blog/tag/' . $tagSlug),
        ]);
    } else {
        $result = blog_query_posts(['search' => $search, 'page' => $page]);
        $canonical = $page > 1 ? blog_site_url('blog?page=' . $page) : blog_site_url('blog');
        blog_list_page($config, $result, [
            'base' => 'blog',
            'params' => $search !== '' ? ['q' => $search] : [],
            'title' => $search !== '' ? 'Busca por "' . $search . '" — Blog QEAI' : 'Blog — Marketing, conteúdo e IA | QEAI',
            'heading' => $search !== '' ? 'Resultados para "' . $search . '"' : 'Ideias que movem marcas',
            'description' => $search !== '' ? 'Resultados de busca no blog da QEAI.' : 'Estratégia, conteúdo, inteligência artificial e as novidades da QEAI.',
            'crumbs' => [['name' => 'Início', 'url' => url('')], ['name' => 'Blog']],
            'canonical' => $canonical,
            'noindex' => $search !== '',
        ]);
    }
} catch (Throwable $exception) {
    error_log('[QEAI blog] ' . $exception->getMessage());
    http_response_code(503);
    header('Retry-After: 300');
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>QEAI — Blog</title><body style="font-family:system-ui;max-width:600px;margin:12vh auto;padding:24px;line-height:1.6"><h1>Voltamos em breve.</h1><p>Não foi possível carregar o blog agora. Tente novamente em alguns minutos.</p></body></html>';
}
