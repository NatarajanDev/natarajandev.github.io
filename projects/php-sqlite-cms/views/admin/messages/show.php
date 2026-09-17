<?php
/**
 * Single inbox message.
 *
 * @var array<string, mixed> $message
 */
?>
<section class="block">
    <header class="block__head">
        <div>
            <h2><?= e((string) $message['subject']) ?></h2>
            <p class="muted">
                From <strong><?= e((string) $message['name']) ?></strong>
                &lt;<a href="mailto:<?= e((string) $message['email']) ?>"><?= e((string) $message['email']) ?></a>&gt;
                · <?= e(format_date((string) $message['created_at'], 'M j, Y H:i')) ?>
                · <?= e((string) $message['ip']) ?>
            </p>
        </div>
        <div class="block__actions">
            <a class="btn btn--ghost btn--sm" href="mailto:<?= e((string) $message['email']) ?>?subject=<?= urlencode('Re: ' . (string) $message['subject']) ?>"><?= icon('message', 16) ?> Reply by email</a>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/messages')) ?>">← Inbox</a>
        </div>
    </header>

    <article class="message-body">
        <?= nl2br(e((string) $message['body'])) ?>
    </article>

    <footer class="block__foot">
        <form class="inline-form" method="post" action="<?= e(url('/admin/messages/' . $message['id'])) ?>">
            <?= csrf_field() ?><?= method_field('PUT') ?>
            <select name="status">
                <?php foreach (['new' => 'Unread', 'read' => 'Read', 'archived' => 'Archived'] as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $message['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn--ghost btn--sm" type="submit">Update status</button>
        </form>
        <form method="post" action="<?= e(url('/admin/messages/' . $message['id'])) ?>">
            <?= csrf_field() ?><?= method_field('DELETE') ?>
            <button class="btn btn--ghost btn--sm btn--danger" type="submit" data-confirm="Delete this message?">Delete</button>
        </form>
    </footer>
</section>
