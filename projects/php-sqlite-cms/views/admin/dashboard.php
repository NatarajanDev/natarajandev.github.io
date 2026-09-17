<?php
/**
 * Admin dashboard.
 *
 * @var array<string, mixed> $summary
 * @var array<string, int> $chart
 * @var array<int, array<string, mixed>> $activity
 * @var array<int, array<string, mixed>> $pendingComments
 * @var array<int, array<string, mixed>> $recentPosts
 * @var array<int, array<string, mixed>> $topPosts
 * @var array<string, int> $storage
 * @var string $greeting
 */
$maxViews = max(1, max(array_values($chart)));
$chartWidth = 640;
$chartHeight = 160;
$points = [];
$labels = [];
$count = count($chart);
foreach (array_values($chart) as $index => $views) {
    $x = $count > 1 ? ($index / ($count - 1)) * $chartWidth : 0;
    $y = $chartHeight - (($views / $maxViews) * ($chartHeight - 20)) - 6;
    $points[] = sprintf('%.1f,%.1f', $x, $y);
    $labels[$index] = $views;
}
$polygon = implode(' ', $points);
$areaPoints = '0,' . $chartHeight . ' ' . $polygon . ' ' . $chartWidth . ',' . $chartHeight;
$totalViews = array_sum($chart);
?>
<section class="dashboard">
    <header class="dashboard__greeting">
        <div>
            <h2><?= e($greeting) ?>, <?= e((string) ($user['name'] ?? 'editor')) ?> 👋</h2>
            <p class="muted">
                <?= (int) $summary['posts']['published'] ?> published ·
                <?= (int) $summary['comments']['pending'] ?> awaiting moderation ·
                <?= (int) $summary['messages']['new'] ?> unread messages
            </p>
        </div>
        <div class="dashboard__greeting-actions">
            <span class="pill"><?= e(date('l, j F Y')) ?></span>
        </div>
    </header>

    <div class="stat-grid">
        <a class="stat" href="<?= e(url('/admin/posts')) ?>">
            <span class="stat__icon"><?= icon('file') ?></span>
            <span class="stat__value"><?= (int) $summary['posts']['published'] ?></span>
            <span class="stat__label">Published posts</span>
            <small><?= (int) $summary['posts']['draft'] ?> drafts · <?= (int) $summary['posts']['scheduled'] ?> scheduled</small>
        </a>
        <a class="stat" href="<?= e(url('/admin/comments?status=pending')) ?>">
            <span class="stat__icon"><?= icon('message') ?></span>
            <span class="stat__value"><?= (int) $summary['comments']['pending'] ?></span>
            <span class="stat__label">Pending comments</span>
            <small><?= (int) $summary['comments']['approved'] ?> approved · <?= (int) $summary['comments']['spam'] ?> spam</small>
        </a>
        <a class="stat" href="<?= e(url('/admin/media')) ?>">
            <span class="stat__icon"><?= icon('image') ?></span>
            <span class="stat__value"><?= (int) $summary['media']['total'] ?></span>
            <span class="stat__label">Media files</span>
            <small><?= e(human_bytes((int) $summary['media']['bytes'])) ?> stored</small>
        </a>
        <a class="stat" href="<?= e(url('/admin/messages')) ?>">
            <span class="stat__icon"><?= icon('inbox') ?></span>
            <span class="stat__value"><?= (int) $summary['messages']['new'] ?></span>
            <span class="stat__label">New messages</span>
            <small><?= (int) $summary['messages']['total'] ?> in the inbox</small>
        </a>
        <div class="stat stat--muted">
            <span class="stat__icon"><?= icon('eye') ?></span>
            <span class="stat__value"><?= number_format((int) $summary['posts']['views']) ?></span>
            <span class="stat__label">Total views</span>
            <small><?= number_format($totalViews) ?> in the last 14 days</small>
        </div>
        <?php if (\Cms\App::instance()->auth()->isAdmin()): ?>
            <a class="stat" href="<?= e(url('/admin/users')) ?>">
                <span class="stat__icon"><?= icon('users') ?></span>
                <span class="stat__value"><?= (int) $summary['users']['total'] ?></span>
                <span class="stat__label">Accounts</span>
                <small><?= (int) $summary['users']['admins'] ?> admin<?= (int) $summary['users']['admins'] === 1 ? '' : 's' ?></small>
            </a>
        <?php endif; ?>
    </div>

    <div class="panel-grid">
        <section class="panel panel--wide">
            <header class="panel__head">
                <h3>Traffic — last 14 days</h3>
                <span class="muted"><?= number_format($totalViews) ?> views</span>
            </header>
            <div class="chart">
                <svg viewBox="0 0 <?= $chartWidth ?> <?= $chartHeight ?>" preserveAspectRatio="none" role="img"
                     aria-label="Daily page views for the last 14 days">
                    <defs>
                        <linearGradient id="chartFill" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--accent)" stop-opacity="0.35"/>
                            <stop offset="100%" stop-color="var(--accent)" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                    <polygon points="<?= e($areaPoints) ?>" fill="url(#chartFill)"/>
                    <polyline points="<?= e($polygon) ?>" fill="none" stroke="var(--accent)" stroke-width="2.5"
                              stroke-linejoin="round" stroke-linecap="round"/>
                </svg>
                <div class="chart__labels">
                    <?php foreach ($chart as $date => $views): ?>
                        <span title="<?= e($date) ?>: <?= (int) $views ?> views">
                            <em><?= (int) $views ?></em>
                            <?= e(date('j M', (int) strtotime((string) $date))) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="panel">
            <header class="panel__head">
                <h3>Storage</h3>
            </header>
            <ul class="kv">
                <li><span>Database</span><strong><?= e(human_bytes((int) $storage['database'])) ?></strong></li>
                <li><span>Uploads</span><strong><?= e(human_bytes((int) $storage['uploads'])) ?></strong></li>
                <li><span>Cache files</span><strong><?= (int) $storage['cache_files'] ?> (<?= e(human_bytes((int) $storage['cache_bytes'])) ?>)</strong></li>
                <li><span>Pages</span><strong><?= (int) $summary['pages']['published'] ?> published</strong></li>
            </ul>
            <a class="btn btn--ghost btn--sm btn--block" href="<?= e(url('/admin/tools')) ?>"><?= icon('tool', 16) ?> Maintenance tools</a>
        </section>
    </div>

    <div class="panel-grid">
        <section class="panel">
            <header class="panel__head">
                <h3>Awaiting moderation</h3>
                <a class="link-more" href="<?= e(url('/admin/comments?status=pending')) ?>">Open queue →</a>
            </header>
            <?php if ($pendingComments === []): ?>
                <p class="muted">Nothing waiting — the queue is clear.</p>
            <?php else: ?>
                <ul class="mini-list">
                    <?php foreach ($pendingComments as $comment): ?>
                        <li>
                            <span class="avatar avatar--sm"><?= e(avatar_initials((string) $comment['author_name'])) ?></span>
                            <div>
                                <strong><?= e((string) $comment['author_name']) ?></strong>
                                <p><?= e(excerpt((string) $comment['body'], 80)) ?></p>
                                <small>on <?= e(excerpt((string) ($comment['post_title'] ?? ''), 40)) ?> · <?= e(time_ago((string) $comment['created_at'])) ?></small>
                            </div>
                            <form method="post" action="<?= e(url('/admin/comments/' . $comment['id'] . '/approve')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn--primary btn--sm" type="submit">Approve</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="panel">
            <header class="panel__head">
                <h3>Most read (30 days)</h3>
            </header>
            <ol class="mini-list mini-list--ranked">
                <?php foreach ($topPosts as $index => $post): ?>
                    <li>
                        <span class="rank"><?= $index + 1 ?></span>
                        <div>
                            <a href="<?= e(url('/admin/posts/' . $post['id'] . '/edit')) ?>"><?= e(excerpt((string) $post['title'], 56)) ?></a>
                            <small><?= (int) $post['views'] ?> views · <?= (int) $post['comment_count'] ?> comments</small>
                        </div>
                    </li>
                <?php endforeach; ?>
                <?php if ($topPosts === []): ?>
                    <li class="muted">No views recorded yet.</li>
                <?php endif; ?>
            </ol>
        </section>
    </div>

    <div class="panel-grid">
        <section class="panel">
            <header class="panel__head">
                <h3>Recently updated</h3>
                <a class="link-more" href="<?= e(url('/admin/posts')) ?>">All posts →</a>
            </header>
            <table class="table table--compact">
                <thead>
                    <tr><th>Title</th><th>Status</th><th>Updated</th></tr>
                </thead>
                <tbody>
                <?php foreach ($recentPosts as $post): ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('/admin/posts/' . $post['id'] . '/edit')) ?>"><?= e(excerpt((string) $post['title'], 52)) ?></a>
                        </td>
                        <td><?= status_badge((string) $post['status']) ?></td>
                        <td class="muted"><?= e(time_ago((string) $post['updated_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($recentPosts === []): ?>
                    <tr><td colspan="3" class="muted">No posts yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </section>

        <section class="panel">
            <header class="panel__head">
                <h3>Activity</h3>
                <a class="link-more" href="<?= e(url('/admin/audit')) ?>">Audit trail →</a>
            </header>
            <ul class="timeline">
                <?php foreach ($activity as $entry): ?>
                    <li>
                        <span class="timeline__dot"></span>
                        <div>
                            <strong><?= e(str_replace(['.', '_'], [' ', ' '], (string) $entry['action'])) ?></strong>
                            <small>
                                <?= e((string) ($entry['user_name'] ?? 'system')) ?>
                                · <?= e(time_ago((string) $entry['created_at'])) ?>
                                <?php if (!empty($entry['entity'])): ?>
                                    · <?= e((string) $entry['entity']) ?><?= $entry['entity_id'] !== null ? ' #' . (int) $entry['entity_id'] : '' ?>
                                <?php endif; ?>
                            </small>
                        </div>
                    </li>
                <?php endforeach; ?>
                <?php if ($activity === []): ?>
                    <li class="muted">No activity recorded yet.</li>
                <?php endif; ?>
            </ul>
        </section>
    </div>
</section>
