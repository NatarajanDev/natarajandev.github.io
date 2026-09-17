<?php
/**
 * Page list.
 *
 * @var \Cms\Support\Paginator $paginator
 * @var array<int, array<string, mixed>> $pages
 * @var array<string, mixed> $filters
 * @var array<string, int> $counts
 */
$tabs = ['' => ['All', $counts['total']], 'published' => ['Published', $counts['published']], 'draft' => ['Drafts', $counts['drafts']]];
?>
<section class="block">
    <div class="tabs">
        <?php foreach ($tabs as $value => [$label, $total]): ?>
            <a class="tabs__item<?= (string) $filters['status'] === (string) $value ? ' is-active' : '' ?>"
               href="<?= e(url('/admin/pages' . ($value === '' ? '' : '?status=' . $value))) ?>">
                <?= e($label) ?> <em><?= (int) $total ?></em>
            </a>
        <?php endforeach; ?>
        <span class="tabs__spacer"></span>
        <a class="btn btn--primary btn--sm" href="<?= e(url('/admin/pages/create')) ?>"><?= icon('plus', 16) ?> New page</a>
    </div>

    <form class="filter-bar" method="get" action="<?= e(url('/admin/pages')) ?>">
        <label class="filter-bar__search">
            <?= icon('search', 16) ?>
            <input type="search" name="q" value="<?= e((string) $filters['search']) ?>" placeholder="Search pages…">
        </label>
        <button class="btn btn--ghost btn--sm" type="submit">Search</button>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/pages')) ?>">Reset</a>
    </form>

    <form method="post" action="<?= e(url('/admin/pages/bulk')) ?>" data-bulk-form>
        <?= csrf_field() ?>
        <div class="bulk-bar">
            <span class="bulk-bar__count"><strong data-bulk-count>0</strong> selected</span>
            <select name="action" required>
                <option value="">Bulk action…</option>
                <option value="publish">Publish</option>
                <option value="draft">Move to draft</option>
                <option value="delete">Delete permanently</option>
            </select>
            <button class="btn btn--ghost btn--sm" type="submit" data-confirm="Apply this action to the selected pages?">Apply</button>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th class="table__check"><input type="checkbox" data-check-all aria-label="Select all"></th>
                        <th>Title</th>
                        <th>Parent</th>
                        <th>Status</th>
                        <th>In menu</th>
                        <th>Order</th>
                        <th>Updated</th>
                        <th class="table__actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pages as $page): ?>
                    <tr>
                        <td class="table__check"><input type="checkbox" name="ids[]" value="<?= (int) $page['id'] ?>" data-check-item></td>
                        <td>
                            <a class="table__title" href="<?= e(url('/admin/pages/' . $page['id'] . '/edit')) ?>"><?= e((string) $page['title']) ?></a>
                            <div class="table__meta"><code>/pages/<?= e((string) $page['slug']) ?></code></div>
                        </td>
                        <td><?= $page['parent_name'] === null ? '<span class="muted">—</span>' : e((string) $page['parent_name']) ?></td>
                        <td><?= status_badge((string) $page['status']) ?></td>
                        <td><?= (int) $page['show_in_menu'] === 1 ? '<span class="badge badge--ok">Yes</span>' : '<span class="muted">No</span>' ?></td>
                        <td class="table__num"><?= (int) $page['sort_order'] ?></td>
                        <td class="muted"><?= e(time_ago((string) $page['updated_at'])) ?></td>
                        <td class="table__actions">
                            <div class="table__actions-inner">
                                <a class="btn btn--ghost btn--icon" href="<?= e(url('/pages/' . $page['slug'])) ?>" target="_blank" rel="noopener" title="View"><?= icon('eye', 16) ?></a>
                                <a class="btn btn--ghost btn--icon" href="<?= e(url('/admin/pages/' . $page['id'] . '/edit')) ?>" title="Edit"><?= icon('edit', 16) ?></a>
                                <button class="btn btn--ghost btn--icon btn--danger" type="submit" title="Delete"
                                        formaction="<?= e(url('/admin/pages/' . $page['id'])) ?>" formmethod="post" name="_method" value="DELETE"
                                        data-confirm="Delete “<?= e((string) $page['title']) ?>”?"><?= icon('trash', 16) ?></button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($pages === []): ?>
                    <tr><td colspan="8" class="table__empty">No pages yet. Create your first one.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>

    <?= $view->partial('partials/pagination', ['paginator' => $paginator]) ?>
</section>
