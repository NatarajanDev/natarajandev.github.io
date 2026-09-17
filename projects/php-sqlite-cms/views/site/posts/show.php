<?php
/**
 * Single article: content, tags, author, related posts and comments.
 *
 * @var array<string, mixed> $post
 * @var array<int, array<string, mixed>> $tags
 * @var array{previous: ?array, next: ?array} $adjacent
 * @var array<int, array<string, mixed>> $related
 * @var array<int, array<string, mixed>> $comments
 * @var bool $allowComments
 */
$publishedAt = (string) ($post['published_at'] ?? $post['created_at']);
$url = absolute_url('/posts/' . $post['slug']);
?>
<article class="article">
    <div class="wrap article__wrap">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(url('/')) ?>">Home</a>
            <span>/</span>
            <?php if (!empty($post['category_slug'])): ?>
                <a href="<?= e(url('/category/' . $post['category_slug'])) ?>"><?= e((string) $post['category_name']) ?></a>
                <span>/</span>
            <?php endif; ?>
            <span class="breadcrumb__current"><?= e(excerpt((string) $post['title'], 52)) ?></span>
        </nav>

        <header class="article__head">
            <?php if (!empty($post['category_name'])): ?>
                <a class="chip chip--accent" href="<?= e(url('/category/' . $post['category_slug'])) ?>">
                    <?= e((string) $post['category_name']) ?>
                </a>
            <?php endif; ?>
            <h1><?= e((string) $post['title']) ?></h1>
            <?php if (!empty($post['excerpt'])): ?>
                <p class="article__standfirst"><?= e((string) $post['excerpt']) ?></p>
            <?php endif; ?>

            <div class="article__meta">
                <span class="byline byline--lg">
                    <span class="avatar"><?= e(avatar_initials((string) ($post['author_name'] ?? 'Editor'))) ?></span>
                    <span>
                        <strong><?= e((string) ($post['author_name'] ?? 'Editor')) ?></strong>
                        <small><?= e(format_date($publishedAt, 'F j, Y')) ?></small>
                    </span>
                </span>
                <span class="dot">·</span>
                <span><?= (int) reading_time((string) $post['content']) ?> min read</span>
                <span class="dot">·</span>
                <span><?= (int) $post['views'] ?> views</span>
                <span class="dot">·</span>
                <a href="#comments"><?= (int) $commentCount ?> comments</a>
            </div>
        </header>

        <?php if (!empty($post['cover_image'])): ?>
            <figure class="article__cover">
                <img src="<?= e(asset((string) $post['cover_image'])) ?>" alt="<?= e((string) $post['title']) ?>" decoding="async">
            </figure>
        <?php endif; ?>

        <div class="article__body prose">
            <?= body_html($post) ?>
        </div>

        <?php if ($tags !== []): ?>
            <footer class="article__tags">
                <span>Tagged</span>
                <?php foreach ($tags as $tag): ?>
                    <a class="chip" href="<?= e(url('/tag/' . $tag['slug'])) ?>">#<?= e((string) $tag['name']) ?></a>
                <?php endforeach; ?>
            </footer>
        <?php endif; ?>

        <div class="article__share">
            <span>Share</span>
            <a class="btn btn--ghost btn--sm" href="https://twitter.com/intent/tweet?url=<?= urlencode($url) ?>&text=<?= urlencode((string) $post['title']) ?>" target="_blank" rel="noopener">X</a>
            <a class="btn btn--ghost btn--sm" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($url) ?>" target="_blank" rel="noopener">LinkedIn</a>
            <a class="btn btn--ghost btn--sm" href="mailto:?subject=<?= urlencode((string) $post['title']) ?>&body=<?= urlencode($url) ?>">Email</a>
            <button class="btn btn--ghost btn--sm" type="button" data-copy="<?= e($url) ?>">Copy link</button>
        </div>

        <nav class="article__nav">
            <?php if (($adjacent['previous'] ?? null) !== null): ?>
                <a class="article__nav-item" href="<?= e(url('/posts/' . $adjacent['previous']['slug'])) ?>">
                    <small>← Previous</small>
                    <strong><?= e(excerpt((string) $adjacent['previous']['title'], 60)) ?></strong>
                </a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
            <?php if (($adjacent['next'] ?? null) !== null): ?>
                <a class="article__nav-item article__nav-item--next" href="<?= e(url('/posts/' . $adjacent['next']['slug'])) ?>">
                    <small>Next →</small>
                    <strong><?= e(excerpt((string) $adjacent['next']['title'], 60)) ?></strong>
                </a>
            <?php endif; ?>
        </nav>

        <?php if ($related !== []): ?>
            <section class="section">
                <header class="section__head"><h2>Related reading</h2></header>
                <div class="grid grid--three">
                    <?php foreach ($related as $item): ?>
                        <?= $view->partial('partials/post-card', ['post' => $item, 'postTags' => []]) ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="comments" id="comments">
            <header class="comments__head">
                <h2><?= (int) $commentCount ?> comment<?= $commentCount === 1 ? '' : 's' ?></h2>
                <p class="muted">Be respectful. Comments are moderated before they appear.</p>
            </header>

            <?php if ($comments !== []): ?>
                <ul class="comment-list">
                    <?php foreach ($comments as $comment): ?>
                        <?= $view->partial('partials/comment', ['comment' => $comment]) ?>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="muted">No comments yet — start the conversation.</p>
            <?php endif; ?>

            <?php if ($allowComments): ?>
                <form class="comment-form" method="post" action="<?= e(url('/posts/' . $post['slug'] . '/comments')) ?>" data-comment-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="started_at" value="<?= (int) $formStartedAt ?>">
                    <input type="hidden" name="parent_id" value="" data-reply-parent>
                    <div class="honeypot" aria-hidden="true">
                        <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                    </div>

                    <h3 data-reply-label>Leave a comment</h3>
                    <div class="field-row">
                        <label class="field">
                            <span>Name <em>*</em></span>
                            <input type="text" name="author_name" required maxlength="80" value="<?= e((string) old('author_name')) ?>">
                        </label>
                        <label class="field">
                            <span>Email <em>*</em></span>
                            <input type="email" name="author_email" required maxlength="190" value="<?= e((string) old('author_email')) ?>">
                        </label>
                        <label class="field">
                            <span>Website</span>
                            <input type="url" name="author_url" maxlength="190" placeholder="https://" value="<?= e((string) old('author_url')) ?>">
                        </label>
                    </div>
                    <label class="field">
                        <span>Comment <em>*</em></span>
                        <textarea name="body" rows="4" required maxlength="4000" placeholder="Share your thoughts…"><?= e((string) old('body')) ?></textarea>
                    </label>
                    <div class="comment-form__actions">
                        <button class="btn btn--primary" type="submit">Post comment</button>
                        <button class="btn btn--ghost" type="button" data-reply-cancel hidden>Cancel reply</button>
                        <p class="muted small">Your email is never published.</p>
                    </div>
                </form>
            <?php else: ?>
                <div class="notice">Comments are closed on this article.</div>
            <?php endif; ?>
        </section>
    </div>
</article>
