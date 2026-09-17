<?php
/**
 * Audit trail browser.
 *
 * @var \Cms\Support\Paginator $paginator
 * @var array<int, array<string, mixed>> $entries
 * @var array<string, mixed> $filters
 */
?>
<section class="block">
    <header class="block__head">
        <div>
            <h2>Audit trail</h2>
            <p class="muted"><?= e($paginator->summary()) ?> · every editorial and security action</p>
        </div>
    </header>

    <form class="filter-bar" method="get" action="<?= e(url('/admin/audit')) ?>">
        <label class="filter-bar__search">
            <?= icon('search', 16) ?>
            <input type="search" name="q" value="<?= e((string) $filters['search']) ?>" placeholder="Search actions…">
        </label>
        <select name="entity">
            <option value="">All entities</option>
            <?php foreach (['post', 'page', 'comment', 'media', 'user', 'category', 'tag', 'settings', 'message', 'system'] as $entity): ?>
                <option value="<?= e($entity) ?>" <?= (string) $filters['entity'] === $entity ? 'selected' : '' ?>><?= e(ucfirst($entity)) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn--ghost btn--sm" type="submit">Filter</button>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/audit')) ?>">Reset</a>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Action</th><th>User</th><th>Entity</th><th>Details</th><th>IP</th><th>When</th></tr></thead>
            <tbody>
            <?php foreach ($entries as $entry): ?>
                <tr>
                    <td><code><?= e((string) $entry['action']) ?></code></td>
                    <td>
                        <?php if (!empty($entry['user_name'])): ?>
                            <span class="avatar avatar--sm"><?= e(avatar_initials((string) $entry['user_name'])) ?></span>
                            <?= e((string) $entry['user_name']) ?>
                        <?php else: ?>
                            <span class="muted">system</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= e((string) ($entry['entity'] ?? '—')) ?>
                        <?= $entry['entity_id'] !== null ? '#' . (int) $entry['entity_id'] : '' ?>
                    </td>
                    <td class="table__meta"><?= e(excerpt((string) ($entry['meta'] ?? ''), 90)) ?></td>
                    <td><code><?= e((string) ($entry['ip'] ?? '—')) ?></code></td>
                    <td class="muted"><?= e(time_ago((string) $entry['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($entries === []): ?>
                <tr><td colspan="6" class="table__empty">No audit entries match this filter.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= $view->partial('partials/pagination', ['paginator' => $paginator]) ?>
</section>
