<?php
/**
 * Search results page.
 *
 * @var string $term
 * @var \Cms\Support\Paginator $paginator
 * @var array<int, array<string, mixed>> $results
 */
?>
<div class="wrap layout">
    <div class="layout__main">
        <header class="page-head">
            <p class="eyebrow">Search</p>
            <h1><?= $term === '' ? 'Find an article' : 'Results for “' . e($term) . '”' ?></h1>
            <form class="search-box search-box--wide" action="<?= e(url('/search')) ?>" method="get" role="search">
                <?= icon('search', 18) ?>
                <input type="search" name="q" value="<?= e($term) ?>" placeholder="Search titles, tags and body text…"
                       aria-label="Search articles" autofocus>
                <button class="btn btn--primary btn--sm" type="submit">Search</button>
            </form>
            <?php if ($term !== ''): ?>
                <p class="muted"><?= e($paginator->summary()) ?></p>
            <?php endif; ?>
        </header>

        <?php if ($term === ''): ?>
            <div class="empty-state">
                <h3>Type at least two characters</h3>
                <p>Search matches article titles, excerpts and full body text.</p>
            </div>
        <?php elseif ($results === []): ?>
            <div class="empty-state">
                <h3>No matches for “<?= e($term) ?>”</h3>
                <p>Try a shorter phrase, or browse the archive by category and tag.</p>
                <a class="btn btn--ghost" href="<?= e(url('/posts')) ?>">Browse all articles</a>
            </div>
        <?php else: ?>
            <div class="post-list">
                <?php foreach ($results as $post): ?>
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
