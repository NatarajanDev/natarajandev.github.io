<?php
/**
 * Create / edit user.
 *
 * @var array<string, mixed>|null $user
 * @var array<int, string> $roles
 * @var array<int, string> $statuses
 * @var int|null $postCount
 */
$isEdit = $user !== null;
$action = $isEdit ? '/admin/users/' . $user['id'] : '/admin/users';
$value = static fn (string $key, mixed $default = ''): string => (string) old($key, $user[$key] ?? $default);
$roleDescriptions = [
    'admin' => 'Full access: users, settings, tools and every post.',
    'editor' => 'Moderate comments, manage media and edit any post.',
    'author' => 'Write and manage only their own posts.',
];
?>
<section class="block block--narrow">
    <header class="block__head">
        <div>
            <h2><?= $isEdit ? 'Edit user' : 'New user' ?></h2>
            <?php if ($isEdit): ?>
                <p class="muted">
                    <?= (int) ($postCount ?? 0) ?> post<?= (int) ($postCount ?? 0) === 1 ? '' : 's' ?> ·
                    last sign-in <?= e($user['last_login_at'] === null ? 'never' : time_ago((string) $user['last_login_at'])) ?>
                </p>
            <?php endif; ?>
        </div>
        <div class="block__actions">
            <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/users')) ?>">← All users</a>
        </div>
    </header>

    <form method="post" action="<?= e(url($action)) ?>" class="form form--card">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif; ?>

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

        <div class="field-row">
            <label class="field">
                <span>Role</span>
                <select name="role">
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= e($role) ?>" <?= $value('role', 'author') === $role ? 'selected' : '' ?>>
                            <?= e(ucfirst($role)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="hint"><?= e($roleDescriptions[$value('role', 'author')] ?? '') ?></small>
            </label>
            <label class="field">
                <span>Status</span>
                <select name="status">
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= e($status) ?>" <?= $value('status', 'active') === $status ? 'selected' : '' ?>>
                            <?= e(ucfirst($status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="field-row">
            <label class="field">
                <span><?= $isEdit ? 'New password' : 'Password' ?> <?php if ($isEdit): ?><em>leave blank to keep the current one</em><?php endif; ?></span>
                <input type="password" name="password" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?> minlength="8">
            </label>
            <label class="field">
                <span>Confirm password</span>
                <input type="password" name="password_confirmation" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?> minlength="8">
            </label>
        </div>

        <label class="field">
            <span>Bio <em>shown on the author archive</em></span>
            <textarea name="bio" rows="3" maxlength="500"><?= e($value('bio')) ?></textarea>
        </label>

        <div class="field-row">
            <label class="field">
                <span>Website</span>
                <input type="url" name="website" maxlength="190" value="<?= e($value('website')) ?>" placeholder="https://">
            </label>
            <label class="field">
                <span>Twitter / X handle</span>
                <input type="text" name="twitter" maxlength="60" value="<?= e($value('twitter')) ?>" placeholder="@handle">
            </label>
        </div>

        <div class="form__actions">
            <button class="btn btn--primary" type="submit"><?= $isEdit ? 'Save user' : 'Create user' ?></button>
            <a class="btn btn--ghost" href="<?= e(url('/admin/users')) ?>">Cancel</a>
            <?php if ($isEdit): ?>
                <button class="btn btn--danger" type="submit" formaction="<?= e(url('/admin/users/' . $user['id'])) ?>"
                        formmethod="post" name="_method" value="DELETE"
                        data-confirm="Delete this user account?">Delete user</button>
            <?php endif; ?>
        </div>
    </form>
</section>
