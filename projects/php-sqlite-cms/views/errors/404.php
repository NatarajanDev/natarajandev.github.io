<?php
/** Self-contained 404 page (works even without a database). */
$title = (string) ($meta['title'] ?? 'Page not found');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
</head>
<body class="site-body">
<main class="error-page">
    <p class="eyebrow">404</p>
    <h1><?= e($title) ?></h1>
    <p>The page you were looking for has moved, been deleted or never existed.</p>
    <div class="error-page__actions">
        <a class="btn btn--primary" href="<?= e(url('/')) ?>">Back to home</a>
        <a class="btn btn--ghost" href="<?= e(url('/posts')) ?>">Browse articles</a>
        <a class="btn btn--ghost" href="<?= e(url('/search')) ?>">Search</a>
    </div>
</main>
</body>
</html>
