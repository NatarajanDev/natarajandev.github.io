<?php
/**
 * Admin sign-in form.
 *
 * @var string $email
 */
$errors = $_SESSION['_errors'] ?? [];
?>
<h1 class="auth-card__title">Sign in</h1>
<p class="auth-card__subtitle">Use the credentials created during installation.</p>

<form method="post" action="<?= e(url('/admin/login')) ?>" class="form">
    <?= csrf_field() ?>

    <label class="field">
        <span>Email address</span>
        <input type="email" name="email" required autofocus autocomplete="username"
               value="<?= e((string) old('email', $email)) ?>" placeholder="you@example.com">
    </label>

    <label class="field">
        <span>Password</span>
        <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
    </label>

    <label class="check">
        <input type="checkbox" name="remember" value="1">
        <span>Keep me signed in on this device</span>
    </label>

    <button class="btn btn--primary btn--block" type="submit">Sign in</button>
</form>

<p class="auth-card__foot">
    <a href="<?= e(url('/')) ?>">← Back to the site</a>
</p>
