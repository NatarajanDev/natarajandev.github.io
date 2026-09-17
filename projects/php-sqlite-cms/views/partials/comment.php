<?php
/**
 * Recursive comment node.
 *
 * @var array<string, mixed> $comment
 */

$depth = (int) ($comment['depth'] ?? 0);
?>
<li class="comment" id="comment-<?= (int) $comment['id'] ?>" data-depth="<?= $depth ?>">
    <div class="comment__avatar"><?= e(avatar_initials((string) $comment['author_name'])) ?></div>
    <div class="comment__body">
        <div class="comment__head">
            <?php if (!empty($comment['author_url'])): ?>
                <a class="comment__author" href="<?= e((string) $comment['author_url']) ?>" rel="nofollow noopener" target="_blank">
                    <?= e((string) $comment['author_name']) ?>
                </a>
            <?php else: ?>
                <strong class="comment__author"><?= e((string) $comment['author_name']) ?></strong>
            <?php endif; ?>
            <time datetime="<?= e((string) $comment['created_at']) ?>"><?= e(time_ago((string) $comment['created_at'])) ?></time>
        </div>
        <p class="comment__text"><?= nl2br(e((string) $comment['body'])) ?></p>
        <button class="comment__reply" type="button"
                data-reply-to="<?= (int) $comment['id'] ?>"
                data-reply-name="<?= e((string) $comment['author_name']) ?>">
            Reply
        </button>

        <?php if (!empty($comment['children'])): ?>
            <ul class="comment__children">
                <?php foreach ($comment['children'] as $child): ?>
                    <?= $view->partial('partials/comment', ['comment' => $child]) ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</li>
