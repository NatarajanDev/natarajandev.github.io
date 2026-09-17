<?php
/**
 * Shared listing template for archives, categories, tags and authors.
 *
 * @var string $heading
 * @var string $subheading
 * @var \Cms\Support\Paginator $paginator
 * @var array<int, array<string, mixed>> $posts
 * @var array<string, mixed> $activeFilter
 */
$eyebrow = (string) ($eyebrow ?? 'Archive');
?>
<div class="wrap layout">
    <div class="layout__main">
        <header class="page-head">
            <p class="eyebrow"><?= e($eyebrow) ?></p>
            <h1><?= e($heading) ?></h1>
            <p class="muted"><?= e($subheading) ?></p>

            <?php if (!empty($author)): ?>
                <div class="author-strip">
                    <span class="avatar avatar--lg"><?= e(avatar_initials((string) $author['name'])) ?></span>
                    <div>
                        <strong><?= e((string) $author['name']) ?></strong>
                        <p><?= e((string) ($author['bio'] ?? '')) ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </header>

        <?php if ($posts === []): ?>
            <div class="empty-state">
                <h3>No articles here yet</h3>
                <p>Try another filter or browse every article.</p>
                <a class="btn btn--ghost" href="<?= e(url('/posts')) ?>">All articles</a>
            </div>
        <?php else: ?>
            <div class="post-list">
                <?php foreach ($posts as $post): ?>
                    <?= $view->partial('partials/post-card', ['post' => $post, 'postTags' => []]) ?>
                <?php endforeach; ?>
            </div>
            <?= $view->partial('partials/pagination', ['paginator' => $paginator]) ?>
        <?php endif; ?>
    </div>

    <?= $view->partial('partials/sidebar', [
        'sidebarPopular' => $sidebarPopular,
        'sidebarTags' => $sidebarTags,
        'sidebarArchive' => $sidebarArchive,
        'sidebarCategories' => $sidebarCategories,
    ]) ?>
</div>
