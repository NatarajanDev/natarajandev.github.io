<?php
/**
 * Static page view; renders the contact form when the slug is "contact".
 *
 * @var array<string, mixed> $page
 * @var array<int, array<string, mixed>> $children
 * @var string $contactEmail
 */
$isContact = $page['slug'] === 'contact';
?>
<div class="wrap page">
    <header class="page-head">
        <p class="eyebrow">Page</p>
        <h1><?= e((string) $page['title']) ?></h1>
        <?php if (!empty($page['seo_description'])): ?>
            <p class="muted"><?= e((string) $page['seo_description']) ?></p>
        <?php endif; ?>
    </header>

    <div class="prose">
        <?= body_html($page) ?>
    </div>

    <?php if ($isContact): ?>
        <section class="contact-block">
            <div class="contact-block__intro">
                <h2>Send a message</h2>
                <p>Prefer email? Write to <a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a>. Messages land in the admin inbox and are usually answered within two working days.</p>
            </div>

            <form class="comment-form" method="post" action="<?= e(url('/contact')) ?>">
                <?= csrf_field() ?>
                <div class="honeypot" aria-hidden="true">
                    <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>
                <div class="field-row">
                    <label class="field">
                        <span>Name <em>*</em></span>
                        <input type="text" name="name" required maxlength="80" value="<?= e((string) old('name')) ?>">
                    </label>
                    <label class="field">
                        <span>Email <em>*</em></span>
                        <input type="email" name="email" required maxlength="190" value="<?= e((string) old('email')) ?>">
                    </label>
                </div>
                <label class="field">
                    <span>Subject <em>*</em></span>
                    <input type="text" name="subject" required maxlength="150" value="<?= e((string) old('subject')) ?>">
                </label>
                <label class="field">
                    <span>Message <em>*</em></span>
                    <textarea name="body" rows="6" required maxlength="4000"><?= e((string) old('body')) ?></textarea>
                </label>
                <button class="btn btn--primary" type="submit">Send message</button>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($children !== []): ?>
        <section class="section">
            <header class="section__head"><h2>Sub-pages</h2></header>
            <ul class="page-children">
                <?php foreach ($children as $child): ?>
                    <li><a href="<?= e(url('/pages/' . $child['slug'])) ?>"><?= e((string) $child['title']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</div>
