<?php
/**
 * Centered card layout for sign-in / installer screens.
 *
 * @var string $content
 * @var array<string, mixed> $meta
 */

$settings = \Cms\App::instance()->settings();
$flash = \Cms\App::instance()->flash()->pull();
$title = (string) ($meta['title'] ?? 'Sign in');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($title) ?> · <?= e((string) $settings->get('site_title')) ?></title>
    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
    <style>:root{--accent:<?= e((string) $settings->get('theme_accent', '#6366f1')) ?>}</style>
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="auth">
<div class="auth-card">
    <div class="auth-card__brand">
        <span class="admin-brand__mark">N</span>
        <div>
            <strong><?= e((string) $settings->get('site_title')) ?></strong>
            <small>Content studio</small>
        </div>
    </div>

    <?php if ($flash !== []): ?>
        <div class="flash-stack">
            <?php foreach ($flash as $message): ?>
                <div class="flash flash--<?= e($message['type']) ?>" role="status"><?= e($message['message']) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?= $content ?>
</div>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
