<?php
/**
 * Project overview page: explains the architecture behind the CMS.
 */
$features = [
    ['Layered PHP', 'Domain repositories, controllers and views are separated, so SQL never leaks into templates.', 'layers'],
    ['SQLite or MySQL', 'The schema builder emits both dialects; the same migrations run on either engine.', 'settings'],
    ['Markdown authoring', 'A dependency-free renderer handles headings, tables, lists, quotes and code — and escapes raw HTML.', 'file'],
    ['Moderation workflow', 'Comments arrive pending, with honeypot, rate limiting and duplicate detection in front of them.', 'message'],
    ['Media library', 'MIME-validated uploads with GD thumbnails, alt text and a picker used by the editors.', 'image'],
    ['Security defaults', 'Prepared statements, CSRF tokens, bcrypt passwords, login throttling and audited admin actions.', 'shield'],
];

$stack = [
    'Runtime' => 'PHP 8.1+ (tested on 8.3)',
    'Database' => 'SQLite 3 with PDO (MySQL optional)',
    'Frontend' => 'Vanilla JavaScript, hand-written CSS, no build step',
    'Dependencies' => 'None — no Composer, no npm',
    'Testing' => 'Custom runner: php tests/run.php',
];
?>
<div class="wrap page">
    <header class="page-head">
        <p class="eyebrow">About</p>
        <h1>A complete CMS in one deployable folder</h1>
        <p class="muted">
            Nova CMS is the reference application for this portfolio: a public blog and a full
            administration panel built on PHP and SQLite, with every line readable and reviewable.
        </p>
    </header>

    <div class="grid grid--three">
        <?php foreach ($features as [$heading, $text, $iconName]): ?>
            <div class="tile">
                <?= icon($iconName, 22) ?>
                <strong><?= e($heading) ?></strong>
                <p><?= e($text) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <section class="section">
        <header class="section__head"><h2>Stack at a glance</h2></header>
        <dl class="spec-list">
            <?php foreach ($stack as $label => $value): ?>
                <div>
                    <dt><?= e($label) ?></dt>
                    <dd><?= e($value) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </section>

    <section class="section">
        <header class="section__head"><h2>Admin panel tour</h2></header>
        <div class="prose">
            <p>The administration area covers everything a small editorial team needs:</p>
            <ul>
                <li><strong>Dashboard</strong> — publication counters, a 14-day traffic chart, the moderation queue and an activity feed.</li>
                <li><strong>Posts &amp; pages</strong> — filters, bulk actions, scheduling, revision history and restore.</li>
                <li><strong>Taxonomy</strong> — nested categories, tag merging and usage counts.</li>
                <li><strong>Comments</strong> — approve, reply, mark spam or trash, with a full audit trail.</li>
                <li><strong>Media</strong> — drag-and-drop uploads, thumbnails, alt text and a JSON picker endpoint.</li>
                <li><strong>Users &amp; settings</strong> — three roles (admin, editor, author), profile management, SEO and theme options.</li>
                <li><strong>Tools</strong> — cache flush, database backup, JSON export, log viewer and health checks.</li>
            </ul>
            <p>
                The whole application is dependency-free and runs on shared hosting: point a document root at
                <code>public/</code> and open the installer.
            </p>
        </div>
        <div class="hero__actions">
            <a class="btn btn--primary" href="<?= e(url('/admin')) ?>">Open the admin panel</a>
            <a class="btn btn--ghost" href="<?= e(url('/api/posts')) ?>">Explore the JSON API</a>
        </div>
    </section>
</div>
