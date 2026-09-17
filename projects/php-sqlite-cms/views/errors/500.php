<?php
/** Self-contained 500 page. Shown when the app is not in debug mode. */
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Something went wrong</title>
    <link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
</head>
<body class="site-body">
<main class="error-page">
    <p class="eyebrow">500</p>
    <h1>Something went wrong on our side</h1>
    <p>The error has been written to the application log. Please try again in a moment.</p>
    <div class="error-page__actions">
        <a class="btn btn--primary" href="<?= e(url('/')) ?>">Back to home</a>
    </div>
</main>
</body>
</html>
