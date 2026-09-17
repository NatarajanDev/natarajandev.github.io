<?php
/**
 * Category management: inline create/edit form plus the nested list.
 *
 * @var array<int, array<string, mixed>> $categories
 * @var array<int, array<string, mixed>> $tree
 */
$editing = null;
$editId = (int) (\Cms\Support\Request::capture()->query()['edit'] ?? 0);
foreach ($categories as $category) {
    if ((int) $category['id'] === $editId) {
        $editing = $category;
    }
}
$renderRow = static function (array $category, int $depth = 0) use (&$renderRow, $categories): string {
    $children = array_values(array_filter($categories, static fn (array $c): bool => (int) ($c['parent_id'] ?? 0) === (int) $category['id']));
    $html = '<tr class="cat-depth-' . $depth . '">';
    $html .= '<td><span class="swatch" style="background:' . e((string) ($category['color'] ?: '#6366f1')) . '"></span> ';
    $html .= '<a class="table__title" href="' . e(url('/admin/categories?edit=' . (int) $category['id'])) . '">' . e((string) $category['name']) . '</a>';
    $html .= '<div class="table__meta"><code>/category/' . e((string) $category['slug']) . '</code></div></td>';
    $html .= '<td>' . ($category['parent_name'] === null ? '<span class="muted">—</span>' : e((string) $category['parent_name'])) . '</td>';
    $html .= '<td>' . e(excerpt((string) ($category['description'] ?? ''), 70)) . '</td>';
    $html .= '<td class="table__num">' . (int) ($category['published_count'] ?? 0) . '</td>';
    $html .= '<td class="table__num">' . (int) ($category['post_count'] ?? 0) . '</td>';
    $html .= '<td class="table__num">' . (int) $category['sort_order'] . '</td>';
    $html .= '<td class="table__actions"><div class="table__actions-inner">';
    $html .= '<a class="btn btn--ghost btn--icon" href="' . e(url('/admin/categories?edit=' . (int) $category['id'])) . '" title="Edit">' . icon('edit', 16) . '</a>';
    $html .= '<form method="post" action="' . e(url('/admin/categories/' . (int) $category['id'])) . '">' . csrf_field() . method_field('DELETE');
    $html .= '<button class="btn btn--ghost btn--icon btn--danger" type="submit" title="Delete" data-confirm="Delete this category? Its posts become uncategorised.">' . icon('trash', 16) . '</button></form>';
    $html .= '</div></td></tr>';
    foreach ($children as $child) {
        $html .= $renderRow($child, $depth + 1);
    }

    return $html;
};
?>
<section class="block">
    <header class="block__head">
        <div>
            <h2>Categories</h2>
            <p class="muted"><?= count($categories) ?> categories · nesting is one level deep</p>
        </div>
    </header>

    <div class="split">
        <div class="split__main">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th><th>Parent</th><th>Description</th>
                            <th class="table__num">Published</th><th class="table__num">All</th>
                            <th class="table__num">Order</th><th class="table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tree as $root): ?>
                        <?= $renderRow($root) ?>
                    <?php endforeach; ?>
                    <?php if ($tree === []): ?>
                        <tr><td colspan="7" class="table__empty">No categories yet — create one to organise posts.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <aside class="split__side">
            <section class="panel">
                <header class="panel__head"><h3><?= $editing === null ? 'New category' : 'Edit category' ?></h3></header>
                <form class="panel__body form" method="post"
                      action="<?= e(url($editing === null ? '/admin/categories' : '/admin/categories/' . $editing['id'])) ?>">
                    <?= csrf_field() ?>
                    <?php if ($editing !== null): ?><?= method_field('PUT') ?><?php endif; ?>

                    <label class="field">
                        <span>Name</span>
                        <input type="text" name="name" required maxlength="120" value="<?= e((string) ($editing['name'] ?? '')) ?>">
                    </label>
                    <label class="field">
                        <span>Slug <em>optional</em></span>
                        <input type="text" name="slug" maxlength="140" value="<?= e((string) ($editing['slug'] ?? '')) ?>">
                    </label>
                    <label class="field">
                        <span>Description</span>
                        <textarea name="description" rows="3" maxlength="300"><?= e((string) ($editing['description'] ?? '')) ?></textarea>
                    </label>
                    <label class="field">
                        <span>Colour</span>
                        <input type="color" name="color" value="<?= e((string) ($editing['color'] ?? '#6366f1')) ?>">
                    </label>
                    <label class="field">
                        <span>Parent</span>
                        <select name="parent_id">
                            <option value="">None</option>
                            <?php foreach ($categories as $category): ?>
                                <?php if ($editing !== null && (int) $category['id'] === (int) $editing['id']) { continue; } ?>
                                <option value="<?= (int) $category['id'] ?>"
                                    <?= (int) ($editing['parent_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>>
                                    <?= e((string) $category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field">
                        <span>Sort order</span>
                        <input type="number" name="sort_order" min="0" max="999" value="<?= (int) ($editing['sort_order'] ?? 0) ?>">
                    </label>

                    <button class="btn btn--primary btn--block" type="submit"><?= $editing === null ? 'Create category' : 'Save changes' ?></button>
                    <?php if ($editing !== null): ?>
                        <a class="btn btn--ghost btn--block" href="<?= e(url('/admin/categories')) ?>">Cancel edit</a>
                    <?php endif; ?>
                </form>
            </section>
        </aside>
    </div>
</section>
