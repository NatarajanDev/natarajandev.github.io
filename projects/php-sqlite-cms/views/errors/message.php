<?php
/**
 * Generic HTTP error page used for 403 / 405 / 419.
 *
 * @var string $code
 * @var string $heading
 * @var string $explanation
 */
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($heading) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
</head>
<body class="site-body">
<main class="error-page">
    <p class="eyebrow"><?= e($code) ?></p>
    <h1><?= e($heading) ?></h1>
    <p><?= e($explanation) ?></p>
    <div class="error-page__actions">
        <a class="btn btn--primary" href="<?= e(url('/')) ?>">Back to home</a>
        <a class="btn btn--ghost" href="<?= e(url('/admin')) ?>">Admin panel</a>
    </div>
</main>
</body>
</html>
