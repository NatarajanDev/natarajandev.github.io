<?php
/**
 * Public sidebar widgets.
 *
 * @var array<int, array<string, mixed>> $sidebarPopular
 * @var array<int, array<string, mixed>> $sidebarTags
 * @var array<int, array<string, mixed>> $sidebarArchive
 * @var array<int, array<string, mixed>> $sidebarCategories
 */
?>
<aside class="sidebar">
    <section class="widget">
        <h2 class="widget__title"><?= icon('chart', 16) ?> Most read</h2>
        <ol class="widget__list widget__list--ranked">
            <?php foreach (($sidebarPopular ?? []) as $index => $item): ?>
                <li>
                    <span class="rank"><?= $index + 1 ?></span>
                    <a href="<?= e(url('/posts/' . $item['slug'])) ?>"><?= e((string) $item['title']) ?></a>
                    <small><?= (int) $item['views'] ?> views</small>
                </li>
            <?php endforeach; ?>
            <?php if (($sidebarPopular ?? []) === []): ?>
                <li class="muted">No traffic recorded yet.</li>
            <?php endif; ?>
        </ol>
    </section>

    <section class="widget">
        <h2 class="widget__title"><?= icon('tag', 16) ?> Categories</h2>
        <ul class="widget__list">
            <?php foreach (($sidebarCategories ?? []) as $category): ?>
                <li>
                    <a href="<?= e(url('/category/' . $category['slug'])) ?>">
                        <span class="swatch" style="background:<?= e((string) ($category['color'] ?: '#6366f1')) ?>"></span>
                        <?= e((string) $category['name']) ?>
                    </a>
                    <small><?= (int) ($category['published_count'] ?? 0) ?></small>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="widget">
        <h2 class="widget__title"><?= icon('tag', 16) ?> Popular tags</h2>
        <div class="tag-cloud">
            <?php foreach (($sidebarTags ?? []) as $tag): ?>
                <a class="chip" href="<?= e(url('/tag/' . $tag['slug'])) ?>">#<?= e((string) $tag['name']) ?> <em><?= (int) ($tag['post_count'] ?? 0) ?></em></a>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if (($sidebarArchive ?? []) !== []): ?>
        <section class="widget">
            <h2 class="widget__title"><?= icon('clock', 16) ?> Archive</h2>
            <ul class="widget__list widget__list--compact">
                <?php foreach ($sidebarArchive as $entry): ?>
                    <?php
                    $label = (new \DateTimeImmutable(sprintf('%04d-%02d-01', (int) $entry['year'], (int) $entry['month'])))->format('F Y');
                    $link = sprintf('/archive/%d/%02d', (int) $entry['year'], (int) $entry['month']);
                    ?>
                    <li>
                        <a href="<?= e(url($link)) ?>"><?= e($label) ?></a>
                        <small><?= (int) $entry['total'] ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <section class="widget widget--cta">
        <h2 class="widget__title"><?= icon('message', 16) ?> Subscribe</h2>
        <p>New articles are published every week. Follow along with the RSS feed or the JSON feed for apps.</p>
        <div class="widget__actions">
            <a class="btn btn--primary btn--sm" href="<?= e(url('/feed.xml')) ?>">RSS feed</a>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('/feed.json')) ?>">JSON feed</a>
        </div>
    </section>
</aside>
