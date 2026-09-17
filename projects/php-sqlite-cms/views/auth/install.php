<?php
/**
 * First-run installer.
 *
 * @var array<int, array{label: string, ok: bool, detail: string}> $requirements
 * @var array<string, string> $values
 */
$ready = !in_array(false, array_column($requirements, 'ok'), true);
?>
<h1 class="auth-card__title">Install Nova CMS</h1>
<p class="auth-card__subtitle">Creates the database schema and your administrator account.</p>

<ul class="requirements">
    <?php foreach ($requirements as $requirement): ?>
        <li class="<?= $requirement['ok'] ? 'is-ok' : 'is-fail' ?>">
            <span class="requirements__icon"><?= $requirement['ok'] ? '✓' : '!' ?></span>
            <span>
                <strong><?= e($requirement['label']) ?></strong>
                <small><?= e($requirement['detail']) ?></small>
            </span>
        </li>
    <?php endforeach; ?>
</ul>

<?php if (!$ready): ?>
    <div class="notice notice--warn">
        Fix the items marked above, then reload this page. You can still continue if only the
        optional GD extension is missing.
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('/install')) ?>" class="form">
    <?= csrf_field() ?>

    <label class="field">
        <span>Site title</span>
        <input type="text" name="site_title" required maxlength="120" value="<?= e((string) old('site_title', $values['site_title'])) ?>">
    </label>

    <label class="field">
        <span>Tagline <em>(optional)</em></span>
        <input type="text" name="tagline" maxlength="160" placeholder="What is this publication about?">
    </label>

    <hr class="form__divider">

    <label class="field">
        <span>Your name</span>
        <input type="text" name="name" required maxlength="120" value="<?= e((string) old('name')) ?>">
    </label>

    <label class="field">
        <span>Email address</span>
        <input type="email" name="email" required maxlength="190" value="<?= e((string) old('email')) ?>" placeholder="you@example.com">
    </label>

    <div class="field-row">
        <label class="field">
            <span>Password</span>
            <input type="password" name="password" required minlength="8" autocomplete="new-password">
        </label>
        <label class="field">
            <span>Confirm password</span>
            <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
        </label>
    </div>

    <label class="check">
        <input type="checkbox" name="demo_content" value="1" checked>
        <span>Install demo articles, pages and comments</span>
    </label>

    <button class="btn btn--primary btn--block" type="submit">Run installation</button>
</form>
