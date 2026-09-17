<?php
/**
 * Moderation queue.
 *
 * Per-comment actions use `formaction` on buttons belonging to the bulk form,
 * and reply forms live outside it (referenced with the HTML `form` attribute)
 * so no nested <form> elements are produced.
 *
 * @var \Cms\Support\Paginator $paginator
 * @var array<int, array<string, mixed>> $comments
 * @var array<string, mixed> $filters
 * @var array<string, int> $counts
 */
$tabs = [
    'pending' => ['Pending', $counts['pending']],
    'approved' => ['Approved', $counts['approved']],
    'spam' => ['Spam', $counts['spam']],
    'trash' => ['Trash', $counts['trash']],
    'all' => ['All', $counts['total']],
];
$current = (string) ($filters['status'] ?: 'all');
?>
<section class="block">
    <div class="tabs">
        <?php foreach ($tabs as $value => [$label, $total]): ?>
            <a class="tabs__item<?= $current === $value ? ' is-active' : '' ?>" href="<?= e(url('/admin/comments?status=' . $value)) ?>">
                <?= e($label) ?> <em><?= (int) $total ?></em>
            </a>
        <?php endforeach; ?>
    </div>

    <form class="filter-bar" method="get" action="<?= e(url('/admin/comments')) ?>">
        <input type="hidden" name="status" value="<?= e($current) ?>">
        <label class="filter-bar__search">
            <?= icon('search', 16) ?>
            <input type="search" name="q" value="<?= e((string) $filters['search']) ?>" placeholder="Search author, email or body…">
        </label>
        <button class="btn btn--ghost btn--sm" type="submit">Search</button>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/comments?status=' . $current)) ?>">Reset</a>
    </form>

    <?php if ($comments === []): ?>
        <div class="empty-state">
            <h3>Queue is clear</h3>
            <p>No comments in this state right now.</p>
        </div>
    <?php else: ?>
        <form id="bulk-comments" method="post" action="<?= e(url('/admin/comments/bulk')) ?>" data-bulk-form>
            <?= csrf_field() ?>
            <input type="hidden" name="return_status" value="<?= e($current) ?>">

            <div class="bulk-bar">
                <span class="bulk-bar__count"><strong data-bulk-count>0</strong> selected</span>
                <select name="action" required>
                    <option value="">Bulk action…</option>
                    <option value="approve">Approve</option>
                    <option value="pending">Move to pending</option>
                    <option value="spam">Mark as spam</option>
                    <option value="trash">Move to trash</option>
                    <option value="delete">Delete permanently</option>
                </select>
                <button class="btn btn--ghost btn--sm" type="submit" data-confirm="Apply this action to the selected comments?">Apply</button>
            </div>
        </form>

        <ul class="comment-queue">
            <?php foreach ($comments as $comment): ?>
                <?php $formId = 'reply-' . (int) $comment['id']; ?>
                <li class="comment-card">
                    <div class="comment-card__head">
                        <label class="comment-card__check">
                            <input type="checkbox" name="ids[]" value="<?= (int) $comment['id'] ?>" form="bulk-comments" data-check-item>
                        </label>
                        <span class="avatar avatar--sm"><?= e(avatar_initials((string) $comment['author_name'])) ?></span>
                        <div class="comment-card__who">
                            <strong><?= e((string) $comment['author_name']) ?></strong>
                            <small>
                                <?= e((string) $comment['author_email']) ?>
                                <?php if (!empty($comment['ip'])): ?> · <?= e((string) $comment['ip']) ?><?php endif; ?>
                                · <?= e(time_ago((string) $comment['created_at'])) ?>
                            </small>
                        </div>
                        <div class="comment-card__badges">
                            <?= status_badge((string) $comment['status']) ?>
                        </div>
                    </div>

                    <p class="comment-card__body"><?= nl2br(e((string) $comment['body'])) ?></p>

                    <div class="comment-card__foot">
                        <span class="muted">
                            on
                            <a href="<?= e(url('/posts/' . $comment['post_slug'])) ?>" target="_blank" rel="noopener">
                                <?= e(excerpt((string) ($comment['post_title'] ?? 'post'), 60)) ?>
                            </a>
                        </span>
                        <div class="comment-card__actions">
                            <button class="btn btn--ghost btn--sm" type="submit" form="bulk-comments"
                                    formaction="<?= e(url('/admin/comments/' . $comment['id'] . '/approve')) ?>">Approve</button>
                            <button class="btn btn--ghost btn--sm" type="submit" form="bulk-comments"
                                    formaction="<?= e(url('/admin/comments/' . $comment['id'] . '/unapprove')) ?>">Unapprove</button>
                            <button class="btn btn--ghost btn--sm" type="submit" form="bulk-comments"
                                    formaction="<?= e(url('/admin/comments/' . $comment['id'] . '/spam')) ?>">Spam</button>
                            <button class="btn btn--ghost btn--sm" type="submit" form="bulk-comments"
                                    formaction="<?= e(url('/admin/comments/' . $comment['id'] . '/trash')) ?>">Trash</button>
                            <button class="btn btn--ghost btn--sm btn--danger" type="submit" form="bulk-comments"
                                    formaction="<?= e(url('/admin/comments/' . $comment['id'])) ?>"
                                    name="_method" value="DELETE" data-confirm="Delete this comment permanently?">Delete</button>
                        </div>
                    </div>

                    <details class="comment-card__reply">
                        <summary>Reply as editor</summary>
                        <textarea name="body" form="<?= e($formId) ?>" rows="3" required placeholder="Write a reply…"></textarea>
                        <input type="hidden" name="return_status" value="<?= e($current) ?>" form="<?= e($formId) ?>">
                        <button class="btn btn--primary btn--sm" type="submit" form="<?= e($formId) ?>">Publish reply</button>
                    </details>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php foreach ($comments as $comment): ?>
            <form id="reply-<?= (int) $comment['id'] ?>" method="post"
                  action="<?= e(url('/admin/comments/' . $comment['id'] . '/reply')) ?>" hidden>
                <?= csrf_field() ?>
            </form>
        <?php endforeach; ?>
    <?php endif; ?>

    <?= $view->partial('partials/pagination', ['paginator' => $paginator]) ?>
</section>
