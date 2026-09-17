<?php
/**
 * Post editor with live Markdown preview, media picker and SEO panel.
 *
 * @var array<string, mixed>|null $post
 * @var array<int, array<string, mixed>> $tags
 * @var array<int, array<string, mixed>> $categories
 * @var array<int, string> $statuses
 * @var array<int, array<string, mixed>> $recentMedia
 * @var array<int, array<string, mixed>> $revisions
 */
$isEdit = $post !== null;
$action = $isEdit ? '/admin/posts/' . $post['id'] : '/admin/posts';
$value = static fn (string $key, mixed $default = ''): string => (string) old($key, $post[$key] ?? $default);
$tagString = implode(', ', array_map(static fn (array $tag): string => (string) $tag['name'], $tags));
$currentStatus = $value('status', 'draft');
?>
<section class="block">
    <header class="block__head">
        <div>
            <h2><?= $isEdit ? 'Edit post' : 'New post' ?></h2>
            <?php if ($isEdit): ?>
                <p class="muted">
                    <code>/posts/<?= e((string) $post['slug']) ?></code> ·
                    <?= (int) $post['views'] ?> views ·
                    <?= e(time_ago((string) $post['updated_at'])) ?>
                </p>
            <?php endif; ?>
        </div>
        <div class="block__actions">
            <?php if ($isEdit): ?>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/posts/' . $post['slug'])) ?>" target="_blank" rel="noopener"><?= icon('eye', 16) ?> Preview</a>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/posts/' . $post['id'] . '/revisions')) ?>"><?= icon('clock', 16) ?> Revisions</a>
            <?php endif; ?>
        </div>
    </header>

    <form method="post" action="<?= e(url($action)) ?>" class="editor" data-editor>
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <?= method_field('PUT') ?>
        <?php endif; ?>

        <div class="editor__main">
            <label class="field">
                <span>Title</span>
                <input type="text" name="title" required maxlength="200" data-editor-title
                       value="<?= e($value('title')) ?>" placeholder="A headline that earns the click">
            </label>

            <label class="field">
                <span>Slug <em>optional — generated from the title</em></span>
                <input type="text" name="slug" maxlength="220" value="<?= e($value('slug')) ?>" placeholder="auto-generated-from-title">
            </label>

            <label class="field">
                <span>Excerpt <em>shown in listings and feeds</em></span>
                <textarea name="excerpt" rows="2" maxlength="400" data-editor-excerpt><?= e($value('excerpt')) ?></textarea>
            </label>

            <div class="editor__toolbar">
                <div class="editor__tabs">
                    <button class="btn btn--ghost btn--sm is-active" type="button" data-editor-mode="write">Write</button>
                    <button class="btn btn--ghost btn--sm" type="button" data-editor-mode="preview">Preview</button>
                    <button class="btn btn--ghost btn--sm" type="button" data-editor-mode="split">Split</button>
                </div>
                <div class="editor__insert">
                    <button class="btn btn--ghost btn--sm" type="button" data-insert="**Bold**">B</button>
                    <button class="btn btn--ghost btn--sm" type="button" data-insert="*Italic*"><em>I</em></button>
                    <button class="btn btn--ghost btn--sm" type="button" data-insert="## Heading">H2</button>
                    <button class="btn btn--ghost btn--sm" type="button" data-insert="- item\n- item">List</button>
                    <button class="btn btn--ghost btn--sm" type="button" data-insert="> Quote">Quote</button>
                    <button class="btn btn--ghost btn--sm" type="button" data-insert="```php\n\n```">Code</button>
                    <button class="btn btn--ghost btn--sm" type="button" data-insert="[text](https://)">Link</button>
                    <button class="btn btn--ghost btn--sm" type="button" data-insert-media>Image</button>
                    <span class="editor__count" data-editor-count></span>
                </div>
            </div>

            <div class="editor__panes" data-editor-panes>
                <textarea class="editor__textarea" name="content" required rows="22" data-editor-content
                          placeholder="Write in Markdown…"><?= e($value('content')) ?></textarea>
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
                        <select name="status" data-editor-status>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?= e($status) ?>" <?= $currentStatus === $status ? 'selected' : '' ?>>
                                    <?= e(ucfirst($status)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="field">
                        <span>Publish date <em>leave blank for now</em></span>
                        <input type="datetime-local" name="published_at"
                               value="<?= e($value('published_at') !== '' ? date('Y-m-d\TH:i', (int) strtotime($value('published_at'))) : '') ?>">
                    </label>

                    <label class="check">
                        <input type="checkbox" name="featured" value="1" <?= $value('featured') === '1' ? 'checked' : '' ?>>
                        <span>Feature on the home page</span>
                    </label>

                    <label class="check">
                        <input type="checkbox" name="allow_comments" value="1" <?= old('title') !== null || $post === null || (int) $post['allow_comments'] === 1 ? 'checked' : '' ?>>
                        <span>Allow comments</span>
                    </label>

                    <div class="panel__actions">
                        <button class="btn btn--primary btn--block" type="submit">
                            <?= $isEdit ? 'Save changes' : 'Create post' ?>
                        </button>
                        <?php if ($isEdit): ?>
                            <button class="btn btn--ghost btn--block btn--danger" type="submit"
                                    formaction="<?= e(url('/admin/posts/' . $post['id'])) ?>" formmethod="post" name="_method" value="DELETE"
                                    data-confirm="Delete this post permanently?">Delete post</button>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="panel">
                <header class="panel__head"><h3>Organise</h3></header>
                <div class="panel__body">
                    <label class="field">
                        <span>Category</span>
                        <select name="category_id">
                            <option value="">Uncategorised</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>"
                                    <?= (int) $value('category_id') === (int) $category['id'] ? 'selected' : '' ?>>
                                    <?= e((string) $category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="field">
                        <span>Tags <em>comma separated</em></span>
                        <input type="text" name="tags" value="<?= e((string) old('tags', $tagString)) ?>"
                               placeholder="php, sqlite, performance" list="tag-suggestions">
                        <datalist id="tag-suggestions">
                            <?php foreach (\Cms\App::instance()->db()->all('SELECT name FROM tags ORDER BY name ASC') as $suggestion): ?>
                                <option value="<?= e((string) $suggestion['name']) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </label>

                    <div class="field">
                        <span>Cover image</span>
                        <div class="media-field" data-media-field>
                            <img data-media-preview
                                 src="<?= e($value('cover_image') !== '' ? asset($value('cover_image')) : asset('img/placeholder.svg')) ?>"
                                 alt="">
                            <input type="text" name="cover_image" value="<?= e($value('cover_image')) ?>" data-media-input
                                   placeholder="uploads/2026/01/image.jpg">
                            <div class="media-field__actions">
                                <button class="btn btn--ghost btn--sm" type="button" data-media-pick>Choose</button>
                                <button class="btn btn--ghost btn--sm" type="button" data-media-clear>Clear</button>
                            </div>
                        </div>
                    </div>

                    <?php if ($recentMedia !== []): ?>
                        <div class="field">
                            <span>Recent uploads</span>
                            <div class="media-strip">
                                <?php foreach ($recentMedia as $item): ?>
                                    <button type="button" class="media-strip__item" data-media-url="<?= e((string) $item['path']) ?>">
                                        <img src="<?= e(asset((string) ($item['thumb_path'] ?? $item['path']))) ?>" alt="" loading="lazy">
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
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
                    <label class="field">
                        <span>Social image</span>
                        <input type="text" name="og_image" value="<?= e($value('og_image')) ?>" placeholder="uploads/2026/01/social.jpg">
                    </label>
                    <label class="field">
                        <span>Canonical URL</span>
                        <input type="url" name="canonical_url" value="<?= e($value('canonical_url')) ?>" placeholder="https://">
                    </label>
                    <p class="hint">Search preview</p>
                    <div class="serp">
                        <span class="serp__url"><?= e(absolute_url('/posts/' . ($value('slug') !== '' ? $value('slug') : 'your-post'))) ?></span>
                        <strong class="serp__title"><?= e($value('seo_title') !== '' ? $value('seo_title') : $value('title')) ?></strong>
                        <p class="serp__desc"><?= e(excerpt($value('seo_description') !== '' ? $value('seo_description') : $value('excerpt'), 150)) ?></p>
                    </div>
                </div>
            </section>

            <?php if ($revisions !== []): ?>
                <section class="panel">
                    <header class="panel__head"><h3>Revision history</h3></header>
                    <ul class="mini-list">
                        <?php foreach ($revisions as $revision): ?>
                            <li>
                                <div>
                                    <strong><?= e(time_ago((string) $revision['created_at'])) ?></strong>
                                    <small><?= e((string) ($revision['author_name'] ?? 'system')) ?> · <?= (int) strlen((string) $revision['content']) ?> chars</small>
                                </div>
                                <form method="post" action="<?= e(url('/admin/posts/' . $post['id'] . '/revisions/' . $revision['id'] . '/restore')) ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn--ghost btn--sm" type="submit" data-confirm="Restore this revision over the current content?">Restore</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </aside>
    </form>
</section>
