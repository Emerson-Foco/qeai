<?php

declare(strict_types=1);

require_once ROOT_PATH . '/app/url.php';

function theme_style(array $config): string
{
    $defaults = default_config()['theme'];
    $style = ':root{';
    foreach ($defaults as $key => $fallback) $style .= '--' . $key . ':' . safe_hex($config['theme'][$key], $fallback) . ';';
    return $style . '}';
}

function asset_version(string $path): string
{
    $version = is_file(ROOT_PATH . '/' . $path) ? (string)filemtime(ROOT_PATH . '/' . $path) : '1';
    return url($path) . '?v=' . $version;
}

/**
 * @param array{canonical?:string,description?:string,og_type?:string,og_image?:string,robots?:string,jsonld?:string,styles?:array,body_class?:string} $options
 */
function public_header(array $config, string $title, array $options = []): void
{
    $canonicalPath = match (basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'))) {
        'privacy.php' => '/privacidade',
        'cookies.php' => '/cookies',
        default => '/',
    };
    $canonicalUrl = $options['canonical'] ?? (rtrim((string)$config['site']['url'], '/') . $canonicalPath);
    $description = $options['description'] ?? 'Estratégia, conteúdo e inteligência artificial para colocar sua marca em movimento. Conheça a QEAI e vamos conversar sobre seu negócio.';
    $ogType = $options['og_type'] ?? 'website';
    $robots = $options['robots'] ?? 'index,follow';
    $bodyClass = $options['body_class'] ?? '';
    $home = url('');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?=e($title)?></title>
  <meta name="description" content="<?=e($description)?>">
  <meta name="robots" content="<?=e($robots)?>">
  <meta name="theme-color" content="<?=e(safe_hex($config['theme']['dark'], '#11132B'))?>">
  <meta property="og:title" content="<?=e($title)?>">
  <meta property="og:description" content="<?=e($description)?>">
  <meta property="og:type" content="<?=e($ogType)?>">
  <meta property="og:locale" content="pt_BR">
  <meta property="og:url" content="<?=e($canonicalUrl)?>">
  <?php if (!empty($options['og_image'])): ?><meta property="og:image" content="<?=e($options['og_image'])?>"><?php endif; ?>
  <meta name="twitter:card" content="<?=!empty($options['og_image']) ? 'summary_large_image' : 'summary'?>">
  <link rel="canonical" href="<?=e($canonicalUrl)?>">
  <link rel="icon" href="<?=url('assets/img/logo.svg')?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?=asset_version('assets/css/site.css')?>">
  <?php if (!empty($options['styles'])): foreach ($options['styles'] as $stylePath): ?>
  <link rel="stylesheet" href="<?=asset_version($stylePath)?>">
  <?php endforeach; endif; ?>
  <?php if (!empty($options['jsonld'])) echo $options['jsonld']; ?>
  <style><?=theme_style($config)?></style>
</head>
<body class="<?=e($bodyClass)?>">
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<nav class="nav" aria-label="Navegação principal"><div class="container nav-inner">
  <a class="brand" href="<?=e($home)?>" aria-label="QEAI — início"><img src="<?=url('assets/img/logo.svg')?>" alt="QEAI" width="128" height="48"></a>
  <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="nav-links" data-nav-toggle>Menu <span aria-hidden="true">☰</span></button>
  <div class="nav-links" id="nav-links"><a href="<?=e($home)?>#servicos">O que fazemos</a><a href="<?=e(url('blog'))?>">Blog</a><a href="<?=e($home)?>#como-funciona">Como funciona</a><a href="<?=e($home)?>#contato">Contato</a><a class="btn btn-primary" href="<?=e($home)?>#contato">Vamos conversar <span aria-hidden="true">↗</span></a></div>
</div></nav>
<?php
}

function cookie_ui(): void
{
?>
<div class="cookie-banner" data-cookie-banner role="region" aria-label="Preferências de privacidade">
  <div class="cookie-row"><div><strong>Sua privacidade vem primeiro.</strong><p>Usamos cookies essenciais para o site funcionar. Você pode escolher suas preferências. <a href="<?=url('cookies')?>">Saiba mais.</a></p></div><div class="cookie-actions"><button class="btn btn-light" type="button" data-cookie-reject>Só essenciais</button><button class="btn btn-light" type="button" data-cookie-settings>Preferências</button><button class="btn btn-primary" type="button" data-cookie-accept>Aceitar todos</button></div></div>
</div>
<div class="cookie-modal" data-cookie-modal role="dialog" aria-modal="true" aria-labelledby="cookie-title" tabindex="-1">
  <div class="cookie-box"><h2 id="cookie-title">Preferências de privacidade</h2><p>Você controla os cookies opcionais. Os necessários mantêm o site seguro e funcionando.</p>
    <div class="cookie-option"><div><strong>Necessários</strong><br><small>Sessão, segurança e suas preferências.</small></div><button type="button" class="toggle on" disabled aria-label="Cookies necessários, sempre ativos" aria-pressed="true"></button></div>
    <div class="cookie-option"><div><strong>Analytics</strong><br><small>Nenhuma ferramenta de análise instalada.</small></div><button type="button" class="toggle" data-consent="analytics" aria-label="Permitir cookies de analytics" aria-pressed="false"></button></div>
    <div class="cookie-option"><div><strong>Marketing</strong><br><small>Nenhum pixel de publicidade instalado.</small></div><button type="button" class="toggle" data-consent="marketing" aria-label="Permitir cookies de marketing" aria-pressed="false"></button></div>
    <div class="cookie-actions"><button class="btn btn-light" type="button" data-cookie-close>Cancelar</button><button class="btn btn-primary" type="button" data-cookie-save>Salvar preferências</button></div>
  </div>
</div>
<?php
}

function public_footer(array $config): void
{
    $cnpj = normalize_cnpj((string)($config['site']['cnpj'] ?? ''));
    $home = url('');
?>
<footer class="footer"><div class="container footer-inner"><div class="footer-brand"><a href="<?=e($home)?>" aria-label="QEAI — início"><img src="<?=url('assets/img/logo.svg')?>" alt="QEAI" width="110" height="42" loading="lazy"></a><small>© <?=date('Y')?> QEAI. Marketing, pessoas e tecnologia.</small><?php if ($cnpj !== ''): ?><small>CNPJ <?=e(format_cnpj($cnpj))?></small><?php endif; ?></div><div class="footer-links"><a href="<?=e(url('blog'))?>">Blog</a><a href="<?=e(url('privacidade'))?>">Privacidade</a><a href="<?=e(url('cookies'))?>">Cookies</a><a href="<?=e(url('cookies'))?>" data-cookie-settings>Preferências de cookies</a></div></div></footer>
<?php
    if (!empty($config['features']['whatsapp'])) {
        $number = preg_replace('/\D+/', '', $config['whatsapp']['number'] ?? '');
        if (is_string($number) && preg_match('/^\d{10,15}$/', $number)) {
            echo '<a class="whatsapp" target="_blank" rel="noopener noreferrer" href="https://wa.me/' . e($number) . '?text=' . e(rawurlencode((string)$config['whatsapp']['message'])) . '" aria-label="Falar no WhatsApp"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M20 11.6a8 8 0 0 1-11.9 7L4 20l1.3-4.2A8 8 0 1 1 20 11.6Z"/><path d="M9 7.5c-2 2 3.5 7.5 5.5 5.5l1-1 2 1.5c-1 4-8.5 1-10-3-.6-1.5-.5-2.7.5-3.5Z"/></svg></a>';
        }
    }
    cookie_ui();
    echo '<script src="' . asset_version('assets/js/site.js') . '" defer></script></body></html>';
}
