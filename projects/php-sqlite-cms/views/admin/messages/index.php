<?php
/**
 * Contact inbox.
 *
 * @var \Cms\Support\Paginator $paginator
 * @var array<int, array<string, mixed>> $messages
 * @var array<string, mixed> $filters
 * @var array<string, int> $counts
 */
$tabs = [
    '' => ['All', $counts['total']],
    'new' => ['Unread', $counts['new']],
    'read' => ['Read', $counts['read']],
    'archived' => ['Archived', $counts['archived']],
];
$current = (string) $filters['status'];
?>
<section class="block">
    <div class="tabs">
        <?php foreach ($tabs as $value => [$label, $total]): ?>
            <a class="tabs__item<?= $current === $value ? ' is-active' : '' ?>"
               href="<?= e(url('/admin/messages' . ($value === '' ? '' : '?status=' . $value))) ?>">
                <?= e($label) ?> <em><?= (int) $total ?></em>
            </a>
        <?php endforeach; ?>
    </div>

    <form class="filter-bar" method="get" action="<?= e(url('/admin/messages')) ?>">
        <?php if ($current !== ''): ?><input type="hidden" name="status" value="<?= e($current) ?>"><?php endif; ?>
        <label class="filter-bar__search">
            <?= icon('search', 16) ?>
            <input type="search" name="q" value="<?= e((string) $filters['search']) ?>" placeholder="Search name, email or subject…">
        </label>
        <button class="btn btn--ghost btn--sm" type="submit">Search</button>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/messages')) ?>">Reset</a>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>From</th><th>Subject</th><th>Status</th><th>Received</th><th class="table__actions">Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($messages as $message): ?>
                <tr class="<?= $message['status'] === 'new' ? 'is-unread' : '' ?>">
                    <td>
                        <a class="table__title" href="<?= e(url('/admin/messages/' . $message['id'])) ?>"><?= e((string) $message['name']) ?></a>
                        <div class="table__meta"><a href="mailto:<?= e((string) $message['email']) ?>"><?= e((string) $message['email']) ?></a></div>
                    </td>
                    <td>
                        <?= e((string) $message['subject']) ?>
                        <div class="table__meta"><?= e(excerpt((string) $message['body'], 90)) ?></div>
                    </td>
                    <td><?= status_badge((string) $message['status']) ?></td>
                    <td class="muted"><?= e(time_ago((string) $message['created_at'])) ?></td>
                    <td class="table__actions">
                        <div class="table__actions-inner">
                            <a class="btn btn--ghost btn--icon" href="<?= e(url('/admin/messages/' . $message['id'])) ?>" title="Read"><?= icon('eye', 16) ?></a>
                            <form method="post" action="<?= e(url('/admin/messages/' . $message['id'])) ?>">
                                <?= csrf_field() ?><?= method_field('DELETE') ?>
                                <button class="btn btn--ghost btn--icon btn--danger" type="submit" title="Delete"
                                        data-confirm="Delete this message?"><?= icon('trash', 16) ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($messages === []): ?>
                <tr><td colspan="5" class="table__empty">Nothing in the inbox.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= $view->partial('partials/pagination', ['paginator' => $paginator]) ?>
</section>
