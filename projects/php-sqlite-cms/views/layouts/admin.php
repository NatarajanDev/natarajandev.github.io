<?php
/**
 * Admin panel layout: fixed sidebar, topbar, content area.
 *
 * @var string $content
 * @var array<string, mixed> $meta
 * @var \Cms\Support\View $view
 */

$user = \Cms\App::instance()->auth()->user();
$flash = \Cms\App::instance()->flash()->pull();
$settings = \Cms\App::instance()->settings();
$path = \Cms\Support\Request::capture()->path();
$pageTitle = (string) ($meta['title'] ?? 'Dashboard');
$isAdmin = \Cms\App::instance()->auth()->isAdmin();
$pendingComments = (int) \Cms\App::instance()->db()->scalar("SELECT COUNT(*) FROM comments WHERE status = 'pending'");
$unreadMessages = (int) \Cms\App::instance()->db()->scalar("SELECT COUNT(*) FROM messages WHERE status = 'new'");

$nav = [
    ['label' => 'Dashboard', 'icon' => 'grid', 'url' => '/admin', 'match' => '/admin'],
    ['label' => 'Posts', 'icon' => 'file', 'url' => '/admin/posts', 'match' => '/admin/posts'],
    ['label' => 'Pages', 'icon' => 'layers', 'url' => '/admin/pages', 'match' => '/admin/pages'],
    ['label' => 'Categories', 'icon' => 'tag', 'url' => '/admin/categories', 'match' => '/admin/categories'],
    ['label' => 'Tags', 'icon' => 'tag', 'url' => '/admin/tags', 'match' => '/admin/tags'],
    ['label' => 'Comments', 'icon' => 'message', 'url' => '/admin/comments', 'match' => '/admin/comments', 'badge' => $pendingComments],
    ['label' => 'Media', 'icon' => 'image', 'url' => '/admin/media', 'match' => '/admin/media'],
    ['label' => 'Inbox', 'icon' => 'inbox', 'url' => '/admin/messages', 'match' => '/admin/messages', 'badge' => $unreadMessages],
];

if ($isAdmin) {
    $nav[] = ['label' => 'Users', 'icon' => 'users', 'url' => '/admin/users', 'match' => '/admin/users'];
}

$nav[] = ['label' => 'Settings', 'icon' => 'settings', 'url' => '/admin/settings', 'match' => '/admin/settings'];

$tools = [
    ['label' => 'Tools', 'icon' => 'tool', 'url' => '/admin/tools', 'match' => '/admin/tools'],
    ['label' => 'Audit trail', 'icon' => 'activity', 'url' => '/admin/audit', 'match' => '/admin/audit'],
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($pageTitle) ?> · <?= e((string) $settings->get('site_title')) ?> admin</title>
    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
    <style>:root{--accent:<?= e((string) $settings->get('theme_accent', '#6366f1')) ?>}</style>
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin">
<a class="skip-link" href="#admin-main">Skip to content</a>

<div class="admin-shell">
    <aside class="admin-sidebar" data-sidebar>
        <a class="admin-brand" href="<?= e(url('/admin')) ?>">
            <span class="admin-brand__mark">N</span>
            <span>
                <strong><?= e((string) $settings->get('site_title')) ?></strong>
                <small>Content studio</small>
            </span>
        </a>

        <nav class="admin-nav">
            <?php foreach ($nav as $item): ?>
                <?php
                $active = $item['url'] === '/admin'
                    ? $path === '/admin'
                    : str_starts_with($path, $item['match']);
                ?>
                <a class="admin-nav__link<?= $active ? ' is-active' : '' ?>" href="<?= e(url($item['url'])) ?>">
                    <?= icon($item['icon']) ?>
                    <span><?= e($item['label']) ?></span>
                    <?php if (!empty($item['badge'])): ?>
                        <em class="admin-nav__badge"><?= (int) $item['badge'] ?></em>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>

            <span class="admin-nav__divider">Maintenance</span>
            <?php foreach ($tools as $item): ?>
                <?php $active = str_starts_with($path, $item['match']); ?>
                <a class="admin-nav__link<?= $active ? ' is-active' : '' ?>" href="<?= e(url($item['url'])) ?>">
                    <?= icon($item['icon']) ?>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="admin-sidebar__footer">
            <a class="admin-user" href="<?= e(url('/admin/profile')) ?>">
                <span class="avatar"><?= e(avatar_initials((string) ($user['name'] ?? 'Editor'))) ?></span>
                <span class="admin-user__meta">
                    <strong><?= e((string) ($user['name'] ?? 'Editor')) ?></strong>
                    <small><?= e(ucfirst((string) ($user['role'] ?? 'author'))) ?></small>
                </span>
            </a>
            <form method="post" action="<?= e(url('/admin/logout')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn--ghost btn--sm btn--block" type="submit"><?= icon('logout', 16) ?> Sign out</button>
            </form>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <button class="btn btn--ghost btn--icon" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
                <?= icon('grid') ?>
            </button>
            <h1 class="admin-topbar__title"><?= e($pageTitle) ?></h1>
            <div class="admin-topbar__actions">
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">
                    <?= icon('eye', 16) ?> View site
                </a>
                <a class="btn btn--primary btn--sm" href="<?= e(url('/admin/posts/create')) ?>">
                    <?= icon('plus', 16) ?> New post
                </a>
            </div>
        </header>

        <?php if ($flash !== []): ?>
            <div class="flash-stack">
                <?php foreach ($flash as $message): ?>
                    <div class="flash flash--<?= e($message['type']) ?>" data-flash role="status">
                        <?= e($message['message']) ?>
                        <button type="button" class="flash__close" data-flash-close aria-label="Dismiss">&times;</button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <main class="admin-content" id="admin-main">
            <?= $content ?>
        </main>

        <footer class="admin-footer">
            <span><?= e((string) $settings->get('site_title')) ?> · Nova CMS</span>
            <span>PHP <?= e(PHP_VERSION) ?> · rendered in <?= e(number_format((microtime(true) - CMS_START) * 1000, 1)) ?> ms</span>
        </footer>
    </div>
</div>

<div class="modal" data-modal hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <header class="modal__head">
            <h2 id="modal-title" data-modal-title>Media library</h2>
            <button type="button" class="btn btn--ghost btn--icon" data-modal-close aria-label="Close">&times;</button>
        </header>
        <div class="modal__body" data-modal-body></div>
    </div>
</div>

<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
