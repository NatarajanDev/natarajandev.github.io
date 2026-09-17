<?php
/**
 * Public site layout.
 *
 * @var string $content
 * @var array<string, mixed> $meta
 * @var array<string, mixed> $settings
 * @var \Cms\Support\View $view
 */

$siteTitle = (string) ($settings['site_title'] ?? 'Nova CMS');
$tagline = (string) ($settings['site_tagline'] ?? '');
$pageTitle = (string) ($meta['title'] ?? '');
$title = $pageTitle === '' ? $siteTitle : sprintf('%s — %s', $pageTitle, $siteTitle);
$description = (string) ($meta['description'] ?? $settings['site_description'] ?? '');
$canonical = (string) ($meta['canonical'] ?? absolute_url(\Cms\Support\Request::capture()->path()));
$robots = (string) ($meta['robots'] ?? 'index,follow');
$menu = menu_tree();
$accent = (string) ($settings['theme_accent'] ?? '#6366f1');
$accentSoft = $accent;
$flash = \Cms\App::instance()->flash()->pull();
$path = \Cms\Support\Request::capture()->path();
$isActive = static fn (string $prefix): string => $prefix !== '/' && str_starts_with($path, $prefix) ? ' is-active' : '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($settings['theme_mode'] ?? 'dark') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e(excerpt($description, 160)) ?>">
    <meta name="robots" content="<?= e($robots) ?>">
    <?php if (!empty($settings['site_keywords'])): ?>
        <meta name="keywords" content="<?= e((string) $settings['site_keywords']) ?>">
    <?php endif; ?>
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta name="theme-color" content="<?= e($accent) ?>">

    <!-- Open Graph / Twitter -->
    <meta property="og:site_name" content="<?= e($siteTitle) ?>">
    <meta property="og:title" content="<?= e($pageTitle === '' ? $siteTitle : $pageTitle) ?>">
    <meta property="og:description" content="<?= e(excerpt($description, 200)) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <meta property="og:type" content="<?= e((string) ($meta['type'] ?? 'website')) ?>">
    <?php if (!empty($meta['image'])): ?>
        <meta property="og:image" content="<?= e(asset((string) $meta['image'])) ?>">
    <?php endif; ?>
    <?php if (!empty($meta['published_time'])): ?>
        <meta property="article:published_time" content="<?= e(date('c', (int) strtotime((string) $meta['published_time']))) ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">

    <link rel="alternate" type="application/rss+xml" title="<?= e($siteTitle) ?> RSS" href="<?= e(absolute_url('/feed.xml')) ?>">
    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
    <style>:root{--accent:<?= e($accent) ?>}</style>
    <link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
    <?php if (!empty($meta['structured_data'])): ?>
        <?php foreach ((array) $meta['structured_data'] as $block): ?>
            <script type="application/ld+json"><?= json_encode($block, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="site-body">
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header" data-header>
    <div class="wrap site-header__inner">
        <a class="brand" href="<?= e(url('/')) ?>">
            <span class="brand__mark"><?= e(mb_substr($siteTitle, 0, 1)) ?></span>
            <span class="brand__text">
                <strong><?= e($siteTitle) ?></strong>
                <small><?= e(excerpt($tagline, 46)) ?></small>
            </span>
        </a>

        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" data-nav-toggle>
            <span></span><span></span><span></span>
            <span class="sr-only">Toggle navigation</span>
        </button>

        <nav class="site-nav" id="site-nav" data-nav>
            <a class="site-nav__link<?= $path === '/' ? ' is-active' : '' ?>" href="<?= e(url('/')) ?>">Home</a>
            <a class="site-nav__link<?= e($isActive('/posts')) ?>" href="<?= e(url('/posts')) ?>">Articles</a>
            <?php foreach ($menu as $item): ?>
                <a class="site-nav__link<?= e($isActive('/pages/' . $item['slug'])) ?>"
                   href="<?= e(url('/pages/' . $item['slug'])) ?>"><?= e($item['title']) ?></a>
            <?php endforeach; ?>
            <a class="site-nav__link<?= e($isActive('/about')) ?>" href="<?= e(url('/about')) ?>">About</a>
        </nav>

        <div class="site-header__actions">
            <form class="search-box" action="<?= e(url('/search')) ?>" method="get" role="search" data-search-form>
                <?= icon('search', 16) ?>
                <input type="search" name="q" placeholder="Search articles…" aria-label="Search articles"
                       autocomplete="off" data-search-input
                       value="<?= e((string) ($_GET['q'] ?? '')) ?>">
                <div class="search-box__results" data-search-results hidden></div>
            </form>
            <button class="theme-toggle" type="button" data-theme-toggle aria-label="Toggle colour theme">
                <span class="theme-toggle__sun">☀</span><span class="theme-toggle__moon">☾</span>
            </button>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin')) ?>">Admin</a>
        </div>
    </div>
</header>

<?php if ($flash !== []): ?>
    <div class="wrap flash-stack">
        <?php foreach ($flash as $message): ?>
            <div class="flash flash--<?= e($message['type']) ?>" role="status">
                <?= e($message['message']) ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<main id="main">
    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="wrap site-footer__inner">
        <div class="site-footer__brand">
            <strong><?= e($siteTitle) ?></strong>
            <p><?= e((string) ($settings['footer_text'] ?? '')) ?></p>
        </div>
        <div class="site-footer__links">
            <h3>Explore</h3>
            <a href="<?= e(url('/posts')) ?>">All articles</a>
            <a href="<?= e(url('/search')) ?>">Search</a>
            <a href="<?= e(url('/feed.xml')) ?>">RSS feed</a>
            <a href="<?= e(url('/sitemap.xml')) ?>">Sitemap</a>
        </div>
        <div class="site-footer__links">
            <h3>Elsewhere</h3>
            <?php foreach (['social_twitter' => 'Twitter / X', 'social_github' => 'GitHub', 'social_linkedin' => 'LinkedIn'] as $key => $label): ?>
                <?php if (!empty($settings[$key])): ?>
                    <a href="<?= e((string) $settings[$key]) ?>" rel="me noopener" target="_blank"><?= e($label) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
            <a href="<?= e(url('/pages/privacy')) ?>">Privacy</a>
        </div>
    </div>
    <div class="wrap site-footer__base">
        <span>&copy; <?= date('Y') ?> <?= e($siteTitle) ?></span>
        <span>PHP <?= e(PHP_VERSION) ?> · SQLite · no framework</span>
    </div>
</footer>

<div class="search-overlay" data-search-overlay hidden></div>
<script src="<?= e(asset('js/site.js')) ?>" defer></script>
</body>
</html>
