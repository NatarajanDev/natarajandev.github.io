<?php
/**
 * User administration.
 *
 * @var array<int, array<string, mixed>> $users
 * @var array<string, int> $counts
 * @var array<int, array<string, mixed>> $attempts
 */
?>
<section class="block">
    <header class="block__head">
        <div>
            <h2>Users</h2>
            <p class="muted">
                <?= (int) $counts['total'] ?> accounts · <?= (int) $counts['admins'] ?> administrator<?= $counts['admins'] === 1 ? '' : 's' ?> ·
                <?= (int) $counts['new_this_month'] ?> joined this month
            </p>
        </div>
        <div class="block__actions">
            <a class="btn btn--primary btn--sm" href="<?= e(url('/admin/users/create')) ?>"><?= icon('plus', 16) ?> New user</a>
        </div>
    </header>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th class="table__num">Posts</th><th>Last sign-in</th><th class="table__actions">Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td>
                        <span class="avatar avatar--sm"><?= e(avatar_initials((string) $user['name'])) ?></span>
                        <a class="table__title" href="<?= e(url('/admin/users/' . $user['id'] . '/edit')) ?>"><?= e((string) $user['name']) ?></a>
                    </td>
                    <td><a href="mailto:<?= e((string) $user['email']) ?>"><?= e((string) $user['email']) ?></a></td>
                    <td><span class="badge badge--info"><?= e(ucfirst((string) $user['role'])) ?></span></td>
                    <td><?= status_badge((string) $user['status']) ?></td>
                    <td class="table__num"><?= (int) $user['post_count'] ?></td>
                    <td class="muted"><?= e($user['last_login_at'] === null ? 'never' : time_ago((string) $user['last_login_at'])) ?></td>
                    <td class="table__actions">
                        <div class="table__actions-inner">
                            <a class="btn btn--ghost btn--icon" href="<?= e(url('/admin/users/' . $user['id'] . '/edit')) ?>" title="Edit"><?= icon('edit', 16) ?></a>
                            <form method="post" action="<?= e(url('/admin/users/' . $user['id'])) ?>">
                                <?= csrf_field() ?><?= method_field('DELETE') ?>
                                <button class="btn btn--ghost btn--icon btn--danger" type="submit" title="Delete"
                                        data-confirm="Delete this user? Their posts will be reassigned to you."><?= icon('trash', 16) ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <section class="panel">
        <header class="panel__head">
            <h3>Recent sign-in attempts</h3>
            <form method="post" action="<?= e(url('/admin/users/security/clear-attempts')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn--ghost btn--sm" type="submit" data-confirm="Clear the whole login-attempt log?"><?= icon('shield', 16) ?> Clear log</button>
            </form>
        </header>
        <div class="table-wrap">
            <table class="table table--compact">
                <thead><tr><th>Identifier</th><th>IP address</th><th>Result</th><th>When</th></tr></thead>
                <tbody>
                <?php foreach ($attempts as $attempt): ?>
                    <tr>
                        <td><?= e((string) $attempt['identifier']) ?></td>
                        <td><code><?= e((string) $attempt['ip']) ?></code></td>
                        <td><?= (int) $attempt['success'] === 1 ? '<span class="badge badge--ok">Success</span>' : '<span class="badge badge--danger">Failed</span>' ?></td>
                        <td class="muted"><?= e(time_ago((string) $attempt['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($attempts === []): ?>
                    <tr><td colspan="4" class="table__empty">No attempts recorded.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
