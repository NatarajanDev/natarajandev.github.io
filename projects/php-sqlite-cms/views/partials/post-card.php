<?php
/**
 * Article card used on the home page, archives and search results.
 *
 * @var array<string, mixed> $post
 * @var array<int, array<string, mixed>> $postTags
 * @var bool $large
 */

$postTags ??= [];
$large ??= false;
$url = url('/posts/' . $post['slug']);
$cover = $post['cover_image'] ? asset((string) $post['cover_image']) : null;
?>
<article class="card<?= $large ? ' card--large' : '' ?>" data-reveal>
    <?php if ($cover !== null): ?>
        <a class="card__media" href="<?= e($url) ?>" tabindex="-1" aria-hidden="true">
            <img src="<?= e($cover) ?>" alt="" loading="lazy" decoding="async">
        </a>
    <?php endif; ?>

    <div class="card__body">
        <div class="card__meta">
            <?php if (!empty($post['category_name'])): ?>
                <a class="chip chip--accent" href="<?= e(url('/category/' . $post['category_slug'])) ?>">
                    <?= e((string) $post['category_name']) ?>
                </a>
            <?php endif; ?>
            <time datetime="<?= e((string) ($post['published_at'] ?? $post['created_at'])) ?>">
                <?= e(format_date((string) ($post['published_at'] ?? $post['created_at']))) ?>
            </time>
            <span class="dot">·</span>
            <span><?= (int) reading_time((string) $post['content']) ?> min read</span>
        </div>

        <h3 class="card__title"><a href="<?= e($url) ?>"><?= e((string) $post['title']) ?></a></h3>

        <p class="card__excerpt">
            <?= e((string) ($post['excerpt'] ?: excerpt((string) $post['content'], 170))) ?>
        </p>

        <?php if ($postTags !== []): ?>
            <div class="card__tags">
                <?php foreach (array_slice($postTags, 0, 4) as $tag): ?>
                    <a class="chip" href="<?= e(url('/tag/' . $tag['slug'])) ?>">#<?= e((string) $tag['name']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="card__foot">
            <span class="byline">
                <?= e((string) ($post['author_name'] ?? 'Editor')) ?>
            </span>
            <span class="card__stats">
                <?= icon('eye', 14) ?> <?= (int) $post['views'] ?>
                <?= icon('message', 14) ?> <?= (int) ($post['comment_count'] ?? 0) ?>
            </span>
        </div>
    </div>
</article>
