<?php
/**
 * Revision history with side-by-side content preview.
 *
 * @var array<string, mixed> $post
 * @var array<int, array<string, mixed>> $revisions
 */
?>
<section class="block">
    <header class="block__head">
        <div>
            <h2>Revisions — <?= e((string) $post['title']) ?></h2>
            <p class="muted">The <?= count($revisions) ?> most recent saves. Restoring keeps the current content as a new revision first.</p>
        </div>
        <div class="block__actions">
            <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/posts/' . $post['id'] . '/edit')) ?>">← Back to editor</a>
        </div>
    </header>

    <?php if ($revisions === []): ?>
        <div class="empty-state">
            <h3>No revisions yet</h3>
            <p>A revision is stored every time the post is saved or restored.</p>
        </div>
    <?php else: ?>
        <div class="revision-list">
            <?php foreach ($revisions as $index => $revision): ?>
                <article class="revision">
                    <header class="revision__head">
                        <div>
                            <strong><?= e(format_date((string) $revision['created_at'], 'M j, Y H:i')) ?></strong>
                            <small>
                                <?= e(time_ago((string) $revision['created_at'])) ?>
                                · <?= e((string) ($revision['author_name'] ?? 'system')) ?>
                                · <?= status_badge((string) $revision['status']) ?>
                            </small>
                        </div>
                        <?php if ($index > 0): ?>
                            <form method="post" action="<?= e(url('/admin/posts/' . $post['id'] . '/revisions/' . $revision['id'] . '/restore')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn--ghost btn--sm" type="submit" data-confirm="Restore this revision?">Restore this version</button>
                            </form>
                        <?php else: ?>
                            <span class="badge badge--ok">Current</span>
                        <?php endif; ?>
                    </header>
                    <h3 class="revision__title"><?= e((string) $revision['title']) ?></h3>
                    <details class="revision__details">
                        <summary>Show content snapshot</summary>
                        <pre class="revision__content"><?= e((string) $revision['content']) ?></pre>
                    </details>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
