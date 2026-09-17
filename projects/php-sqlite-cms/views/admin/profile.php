<?php
/**
 * Own profile + password change.
 *
 * @var array<string, mixed> $user
 * @var array<string, int> $stats
 * @var array<int, array<string, mixed>> $sessions
 */
$value = static fn (string $key, mixed $default = ''): string => (string) old($key, $user[$key] ?? $default);
?>
<section class="block">
    <header class="block__head">
        <div class="profile-head">
            <span class="avatar avatar--xl"><?= e(avatar_initials((string) $user['name'])) ?></span>
            <div>
                <h2><?= e((string) $user['name']) ?></h2>
                <p class="muted">
                    <?= e(ucfirst((string) $user['role'])) ?> ·
                    <?= e((string) $user['email']) ?> ·
                    joined <?= e(format_date((string) $user['created_at'])) ?>
                </p>
            </div>
        </div>
    </header>

    <div class="stat-grid stat-grid--compact">
        <div class="stat stat--muted"><span class="stat__value"><?= (int) $stats['posts'] ?></span><span class="stat__label">Posts authored</span></div>
        <div class="stat stat--muted"><span class="stat__value"><?= (int) $stats['published'] ?></span><span class="stat__label">Published</span></div>
        <div class="stat stat--muted"><span class="stat__value"><?= number_format((int) $stats['views']) ?></span><span class="stat__label">Total views</span></div>
        <div class="stat stat--muted"><span class="stat__value"><?= (int) $stats['comments'] ?></span><span class="stat__label">Comments received</span></div>
    </div>

    <div class="split">
        <div class="split__main">
            <section class="panel">
                <header class="panel__head"><h3>Profile details</h3></header>
                <form class="panel__body form" method="post" action="<?= e(url('/admin/profile')) ?>">
                    <?= csrf_field() ?><?= method_field('PUT') ?>
                    <div class="field-row">
                        <label class="field">
                            <span>Name</span>
                            <input type="text" name="name" required maxlength="120" value="<?= e($value('name')) ?>">
                        </label>
                        <label class="field">
                            <span>Email address</span>
                            <input type="email" name="email" required maxlength="190" value="<?= e($value('email')) ?>">
                        </label>
                    </div>
                    <label class="field">
                        <span>Bio</span>
                        <textarea name="bio" rows="3" maxlength="500"><?= e($value('bio')) ?></textarea>
                    </label>
                    <div class="field-row">
                        <label class="field">
                            <span>Website</span>
                            <input type="url" name="website" maxlength="190" value="<?= e($value('website')) ?>">
                        </label>
                        <label class="field">
                            <span>Twitter / X</span>
                            <input type="text" name="twitter" maxlength="60" value="<?= e($value('twitter')) ?>">
                        </label>
                    </div>
                    <button class="btn btn--primary" type="submit">Save profile</button>
                </form>
            </section>
        </div>

        <aside class="split__side">
            <section class="panel">
                <header class="panel__head"><h3>Change password</h3></header>
                <form class="panel__body form" method="post" action="<?= e(url('/admin/profile/password')) ?>">
                    <?= csrf_field() ?><?= method_field('PUT') ?>
                    <label class="field">
                        <span>Current password</span>
                        <input type="password" name="current_password" required autocomplete="current-password">
                    </label>
                    <label class="field">
                        <span>New password</span>
                        <input type="password" name="password" required minlength="8" autocomplete="new-password">
                    </label>
                    <label class="field">
                        <span>Confirm new password</span>
                        <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
                    </label>
                    <button class="btn btn--primary btn--block" type="submit">Update password</button>
                    <p class="hint">Changing your password signs out every other device.</p>
                </form>
            </section>

            <section class="panel">
                <header class="panel__head"><h3>Recent sign-ins</h3></header>
                <ul class="mini-list">
                    <?php foreach ($sessions as $session): ?>
                        <li>
                            <div>
                                <strong><?= (int) $session['success'] === 1 ? 'Successful' : 'Failed' ?></strong>
                                <small><?= e((string) $session['ip']) ?> · <?= e(time_ago((string) $session['created_at'])) ?></small>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    <?php if ($sessions === []): ?>
                        <li class="muted">No sign-in history yet.</li>
                    <?php endif; ?>
                </ul>
            </section>
        </aside>
    </div>
</section>
