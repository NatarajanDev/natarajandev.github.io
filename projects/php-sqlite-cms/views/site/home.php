<?php
/**
 * Landing page: hero, featured articles, latest posts, categories and sidebar.
 *
 * @var array<int, array<string, mixed>> $featured
 * @var array<int, array<string, mixed>> $latest
 * @var array<string, int> $stats
 * @var array<int, array<string, mixed>> $categories
 */

$title = (string) ($settings['site_title'] ?? 'Nova CMS');
$tagline = (string) ($settings['site_tagline'] ?? '');
$lead = $featured[0] ?? ($latest[0] ?? null);
$secondary = array_slice(array_merge($featured, $latest), 1, 4);
?>
<section class="hero">
    <div class="wrap hero__inner">
        <div class="hero__copy">
            <p class="eyebrow">PHP <?= e(PHP_VERSION) ?> · SQLite · vanilla JS</p>
            <h1><?= e($title) ?></h1>
            <p class="hero__lead"><?= e((string) ($settings['site_description'] ?? '')) ?></p>
            <div class="hero__actions">
                <a class="btn btn--primary" href="<?= e(url('/posts')) ?>">Browse articles</a>
                <a class="btn btn--ghost" href="<?= e(url('/about')) ?>">How it is built</a>
            </div>
            <dl class="hero__stats">
                <div><dt><?= (int) ($stats['articles'] ?? 0) ?></dt><dd>Articles</dd></div>
                <div><dt><?= (int) ($stats['categories'] ?? 0) ?></dt><dd>Categories</dd></div>
                <div><dt><?= (int) ($stats['tags'] ?? 0) ?></dt><dd>Tags</dd></div>
                <div><dt><?= (int) ($stats['comments'] ?? 0) ?></dt><dd>Comments</dd></div>
            </dl>
        </div>

        <?php if ($lead !== null): ?>
            <a class="hero__feature" href="<?= e(url('/posts/' . $lead['slug'])) ?>">
                <span class="hero__badge">Featured</span>
                <?php if (!empty($lead['cover_image'])): ?>
                    <img src="<?= e(asset((string) $lead['cover_image'])) ?>" alt="" loading="lazy">
                <?php endif; ?>
                <h2><?= e((string) $lead['title']) ?></h2>
                <p><?= e((string) ($lead['excerpt'] ?: excerpt((string) $lead['content'], 130))) ?></p>
                <span class="hero__meta">
                    <?= e(format_date((string) ($lead['published_at'] ?? $lead['created_at']))) ?>
                    · <?= (int) reading_time((string) $lead['content']) ?> min read
                </span>
            </a>
        <?php endif; ?>
    </div>
</section>

<div class="wrap layout">
    <div class="layout__main">
        <?php if ($secondary !== []): ?>
            <section class="section">
                <header class="section__head">
                    <h2>Editors' picks</h2>
                    <a class="link-more" href="<?= e(url('/posts')) ?>">All articles →</a>
                </header>
                <div class="grid grid--two">
                    <?php foreach ($secondary as $index => $post): ?>
                        <?= $view->partial('partials/post-card', [
                            'post' => $post,
                            'postTags' => [],
                            'large' => $index === 0,
                        ]) ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="section">
            <header class="section__head">
                <h2>Latest articles</h2>
                <span class="muted"><?= count($latest) ?> newest</span>
            </header>

            <?php if ($latest === []): ?>
                <div class="empty-state">
                    <h3>Nothing published yet</h3>
                    <p>Sign in to the admin panel and write the first article.</p>
                    <a class="btn btn--primary" href="<?= e(url('/admin')) ?>">Open the admin panel</a>
                </div>
            <?php else: ?>
                <div class="post-list">
                    <?php foreach ($latest as $post): ?>
                        <?= $view->partial('partials/post-card', ['post' => $post, 'postTags' => []]) ?>
                    <?php endforeach; ?>
                </div>
                <div class="section__foot">
                    <a class="btn btn--ghost" href="<?= e(url('/posts')) ?>">See every article</a>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($categories !== []): ?>
            <section class="section">
                <header class="section__head">
                    <h2>Browse by category</h2>
                </header>
                <div class="grid grid--three">
                    <?php foreach ($categories as $category): ?>
                        <a class="tile" href="<?= e(url('/category/' . $category['slug'])) ?>">
                            <span class="swatch" style="background:<?= e((string) ($category['color'] ?: '#6366f1')) ?>"></span>
                            <strong><?= e((string) $category['name']) ?></strong>
                            <small><?= (int) ($category['published_count'] ?? 0) ?> published</small>
                            <?php if (!empty($category['description'])): ?>
                                <p><?= e(excerpt((string) $category['description'], 90)) ?></p>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <?= $view->partial('partials/sidebar', [
        'sidebarPopular' => $sidebarPopular,
        'sidebarTags' => $sidebarTags,
        'sidebarArchive' => $sidebarArchive,
        'sidebarCategories' => $sidebarCategories,
    ]) ?>
</div>
