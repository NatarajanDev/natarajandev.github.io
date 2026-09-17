<?php
/**
 * Page editor.
 *
 * @var array<string, mixed>|null $page
 * @var array<int, array<string, mixed>> $parents
 */
$isEdit = $page !== null;
$action = $isEdit ? '/admin/pages/' . $page['id'] : '/admin/pages';
$value = static fn (string $key, mixed $default = ''): string => (string) old($key, $page[$key] ?? $default);
?>
<section class="block">
    <header class="block__head">
        <div>
            <h2><?= $isEdit ? 'Edit page' : 'New page' ?></h2>
            <?php if ($isEdit): ?>
                <p class="muted"><code>/pages/<?= e((string) $page['slug']) ?></code> · updated <?= e(time_ago((string) $page['updated_at'])) ?></p>
            <?php endif; ?>
        </div>
        <div class="block__actions">
            <?php if ($isEdit): ?>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/pages/' . $page['slug'])) ?>" target="_blank" rel="noopener"><?= icon('eye', 16) ?> View</a>
            <?php endif; ?>
        </div>
    </header>

    <form method="post" action="<?= e(url($action)) ?>" class="editor" data-editor>
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif; ?>

        <div class="editor__main">
            <label class="field">
                <span>Title</span>
                <input type="text" name="title" required maxlength="200" value="<?= e($value('title')) ?>" data-editor-title>
            </label>

            <label class="field">
                <span>Slug <em>optional</em></span>
                <input type="text" name="slug" maxlength="220" value="<?= e($value('slug')) ?>" placeholder="auto-generated">
            </label>

            <div class="editor__toolbar">
                <div class="editor__tabs">
                    <button class="btn btn--ghost btn--sm is-active" type="button" data-editor-mode="write">Write</button>
                    <button class="btn btn--ghost btn--sm" type="button" data-editor-mode="preview">Preview</button>
                    <button class="btn btn--ghost btn--sm" type="button" data-editor-mode="split">Split</button>
                </div>
                <div class="editor__insert">
                    <button class="btn btn--ghost btn--sm" type="button" data-insert="## Heading">H2</button>
                    <button class="btn btn--ghost btn--sm" type="button" data-insert="- item\n- item">List</button>
                    <button class="btn btn--ghost btn--sm" type="button" data-insert="[text](https://)">Link</button>
                    <span class="editor__count" data-editor-count></span>
                </div>
            </div>

            <div class="editor__panes" data-editor-panes>
                <textarea class="editor__textarea" name="content" required rows="20" data-editor-content><?= e($value('content')) ?></textarea>
                <div class="editor__preview prose" data-editor-preview></div>
            </div>

            <input type="hidden" name="content_format" value="markdown">
        </div>

        <aside class="editor__side">
            <section class="panel">
                <header class="panel__head"><h3>Publish</h3></header>
                <div class="panel__body">
                    <label class="field">
                        <span>Status</span>
                        <select name="status">
                            <?php foreach (['published' => 'Published', 'draft' => 'Draft'] as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $value('status', 'published') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="check">
                        <input type="checkbox" name="show_in_menu" value="1" <?= $value('show_in_menu') === '1' ? 'checked' : '' ?>>
                        <span>Show in the main menu</span>
                    </label>

                    <label class="field">
                        <span>Sort order</span>
                        <input type="number" name="sort_order" value="<?= e($value('sort_order', '0')) ?>" min="0" max="999">
                    </label>

                    <label class="field">
                        <span>Parent page</span>
                        <select name="parent_id">
                            <option value="">None (top level)</option>
                            <?php foreach ($parents as $parent): ?>
                                <option value="<?= (int) $parent['id'] ?>" <?= (int) $value('parent_id') === (int) $parent['id'] ? 'selected' : '' ?>>
                                    <?= e((string) $parent['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="field">
                        <span>Template</span>
                        <select name="template">
                            <?php foreach (['default' => 'Default', 'wide' => 'Wide', 'landing' => 'Landing'] as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $value('template', 'default') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <div class="panel__actions">
                        <button class="btn btn--primary btn--block" type="submit"><?= $isEdit ? 'Save page' : 'Create page' ?></button>
                        <?php if ($isEdit): ?>
                            <button class="btn btn--ghost btn--block btn--danger" type="submit"
                                    formaction="<?= e(url('/admin/pages/' . $page['id'])) ?>" formmethod="post" name="_method" value="DELETE"
                                    data-confirm="Delete this page?">Delete page</button>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="panel">
                <header class="panel__head"><h3>SEO</h3></header>
                <div class="panel__body">
                    <label class="field">
                        <span>Meta title</span>
                        <input type="text" name="seo_title" maxlength="200" value="<?= e($value('seo_title')) ?>">
                    </label>
                    <label class="field">
                        <span>Meta description</span>
                        <textarea name="seo_description" rows="3" maxlength="320"><?= e($value('seo_description')) ?></textarea>
                    </label>
                </div>
            </section>
        </aside>
    </form>
</section>
