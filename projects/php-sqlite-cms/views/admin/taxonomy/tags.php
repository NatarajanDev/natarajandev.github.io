<?php
/**
 * Tag management with merge support.
 *
 * @var array<int, array<string, mixed>> $tags
 * @var array<int, array<string, mixed>> $popular
 */
?>
<section class="block">
    <header class="block__head">
        <div>
            <h2>Tags</h2>
            <p class="muted"><?= count($tags) ?> tags · merging moves every post to the target tag</p>
        </div>
    </header>

    <div class="split">
        <div class="split__main">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Name</th><th>Slug</th><th class="table__num">Posts</th><th class="table__actions">Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tags as $tag): ?>
                        <tr>
                            <td>
                                <form class="inline-form" method="post" action="<?= e(url('/admin/tags/' . $tag['id'])) ?>">
                                    <?= csrf_field() ?><?= method_field('PUT') ?>
                                    <input class="inline-input" type="text" name="name" value="<?= e((string) $tag['name']) ?>" maxlength="60">
                                    <button class="btn btn--ghost btn--sm" type="submit">Rename</button>
                                </form>
                            </td>
                            <td><code>#<?= e((string) $tag['slug']) ?></code></td>
                            <td class="table__num"><?= (int) ($tag['post_count'] ?? 0) ?></td>
                            <td class="table__actions">
                                <div class="table__actions-inner">
                                    <form class="inline-form" method="post" action="<?= e(url('/admin/tags/' . $tag['id'] . '/merge')) ?>">
                                        <?= csrf_field() ?>
                                        <select name="target_id" required>
                                            <option value="">Merge into…</option>
                                            <?php foreach ($tags as $target): ?>
                                                <?php if ((int) $target['id'] === (int) $tag['id']) { continue; } ?>
                                                <option value="<?= (int) $target['id'] ?>"><?= e((string) $target['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn--ghost btn--sm" type="submit" data-confirm="Merge this tag into the selected one?">Merge</button>
                                    </form>
                                    <form method="post" action="<?= e(url('/admin/tags/' . $tag['id'])) ?>">
                                        <?= csrf_field() ?><?= method_field('DELETE') ?>
                                        <button class="btn btn--ghost btn--icon btn--danger" type="submit" title="Delete"
                                                data-confirm="Delete this tag? Posts stay, the tag link is removed."><?= icon('trash', 16) ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($tags === []): ?>
                        <tr><td colspan="4" class="table__empty">No tags yet. Add them while writing a post, or create one here.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <aside class="split__side">
            <section class="panel">
                <header class="panel__head"><h3>New tag</h3></header>
                <form class="panel__body form" method="post" action="<?= e(url('/admin/tags')) ?>">
                    <?= csrf_field() ?>
                    <label class="field">
                        <span>Name</span>
                        <input type="text" name="name" required maxlength="60" placeholder="e.g. performance">
                    </label>
                    <button class="btn btn--primary btn--block" type="submit">Create tag</button>
                </form>
            </section>

            <?php if ($popular !== []): ?>
                <section class="panel">
                    <header class="panel__head"><h3>Most used</h3></header>
                    <div class="panel__body tag-cloud">
                        <?php foreach ($popular as $tag): ?>
                            <a class="chip" href="<?= e(url('/tag/' . $tag['slug'])) ?>" target="_blank" rel="noopener">
                                #<?= e((string) $tag['name']) ?> <em><?= (int) $tag['post_count'] ?></em>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </aside>
    </div>
</section>
