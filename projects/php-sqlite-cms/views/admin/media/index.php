<?php
/**
 * Media library grid with drag-and-drop upload.
 *
 * @var \Cms\Support\Paginator $paginator
 * @var array<int, array<string, mixed>> $items
 * @var array<string, mixed> $filters
 * @var array<string, int> $stats
 * @var int $maxUpload
 */
?>
<section class="block">
    <header class="block__head">
        <div>
            <h2>Media library</h2>
            <p class="muted">
                <?= (int) $stats['total'] ?> files · <?= e(human_bytes((int) $stats['bytes'])) ?> ·
                <?= (int) $stats['images'] ?> images · <?= (int) $stats['documents'] ?> documents
            </p>
        </div>
    </header>

    <form class="filter-bar" method="get" action="<?= e(url('/admin/media')) ?>">
        <label class="filter-bar__search">
            <?= icon('search', 16) ?>
            <input type="search" name="q" value="<?= e((string) $filters['search']) ?>" placeholder="Search file name or alt text…">
        </label>
        <select name="type">
            <option value="">All types</option>
            <?php foreach (['image' => 'Images', 'application/pdf' => 'PDF', 'image/svg' => 'SVG'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= (string) $filters['type'] === (string) $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn--ghost btn--sm" type="submit">Filter</button>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/media')) ?>">Reset</a>
    </form>

    <form class="dropzone" method="post" action="<?= e(url('/admin/media')) ?>" enctype="multipart/form-data" data-dropzone>
        <?= csrf_field() ?>
        <input type="file" name="file" accept="image/*,application/pdf" hidden data-dropzone-input>
        <div class="dropzone__inner">
            <?= icon('upload', 26) ?>
            <strong>Drop a file here or click to upload</strong>
            <small>Images and PDF up to <?= e(human_bytes($maxUpload)) ?>. Thumbnails are generated automatically.</small>
            <button class="btn btn--primary btn--sm" type="button" data-dropzone-browse>Choose file</button>
        </div>
    </form>

    <?php if ($items === []): ?>
        <div class="empty-state">
            <h3>No media yet</h3>
            <p>Upload the first image to use it as a cover or inside an article.</p>
        </div>
    <?php else: ?>
        <div class="media-grid">
            <?php foreach ($items as $item): ?>
                <figure class="media-card">
                    <div class="media-card__frame">
                        <?php if (str_starts_with((string) $item['mime_type'], 'image/')): ?>
                            <img src="<?= e(asset((string) ($item['thumb_path'] ?? $item['path']))) ?>" alt="<?= e((string) ($item['alt_text'] ?? '')) ?>" loading="lazy">
                        <?php else: ?>
                            <span class="media-card__file"><?= icon('file', 26) ?><small><?= e(strtoupper((string) pathinfo((string) $item['filename'], PATHINFO_EXTENSION))) ?></small></span>
                        <?php endif; ?>
                    </div>
                    <figcaption class="media-card__meta">
                        <strong title="<?= e((string) $item['original_name']) ?>"><?= e(excerpt((string) $item['original_name'], 26)) ?></strong>
                        <small>
                            <?= e(human_bytes((int) $item['size'])) ?>
                            <?= $item['width'] !== null ? ' · ' . (int) $item['width'] . '×' . (int) $item['height'] : '' ?>
                        </small>
                        <div class="media-card__actions">
                            <button class="btn btn--ghost btn--icon" type="button" data-copy="<?= e(absolute_url('/' . $item['path'])) ?>" title="Copy URL"><?= icon('link', 16) ?></button>
                            <button class="btn btn--ghost btn--icon" type="button" data-media-edit="<?= (int) $item['id'] ?>"
                                    data-alt="<?= e((string) ($item['alt_text'] ?? '')) ?>"
                                    data-caption="<?= e((string) ($item['caption'] ?? '')) ?>"
                                    data-url="<?= e(url('/admin/media/' . $item['id'])) ?>" title="Edit details"><?= icon('edit', 16) ?></button>
                            <form method="post" action="<?= e(url('/admin/media/' . $item['id'])) ?>">
                                <?= csrf_field() ?><?= method_field('DELETE') ?>
                                <button class="btn btn--ghost btn--icon btn--danger" type="submit" title="Delete"
                                        data-confirm="Delete this file from disk?"><?= icon('trash', 16) ?></button>
                            </form>
                        </div>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>

        <?= $view->partial('partials/pagination', ['paginator' => $paginator]) ?>
    <?php endif; ?>
</section>
