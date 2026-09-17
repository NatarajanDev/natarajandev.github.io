<?php
/**
 * Scheduled maintenance — run from cron every few minutes:
 *
 *   *\/5 * * * * php /path/to/projects/php-sqlite-cms/bin/cron.php >> /dev/null 2>&1
 *
 * Publishes scheduled posts, prunes stale sessions data, logs and audit rows.
 */

declare(strict_types=1);

/** @var \Cms\App $app */
$app = require dirname(__DIR__) . '/src/bootstrap.php';

use Cms\Repository\PostRepository;

$posts = new PostRepository($app->db());
$published = 0;

foreach ($posts->dueForPublishing() as $post) {
    $posts->setStatus([(int) $post['id']], 'published');
    $published++;
    $app->log()->info('Scheduled post published', ['id' => (int) $post['id'], 'slug' => $post['slug']]);
}

foreach (['sidebar.popular', 'sidebar.tags', 'sidebar.archive', 'sidebar.categories', 'menu.pages'] as $key) {
    $app->cache()->forget($key);
}

$logFiles = $app->log()->prune(14);
$attempts = (new \Cms\Repository\UserRepository($app->db()))->pruneAttempts(30);
$audit = $app->activity()->repository()->prune(90);

printf(
    "[%s] published=%d logs_pruned=%d attempts_pruned=%d audit_pruned=%d\n",
    date('Y-m-d H:i:s'),
    $published,
    $logFiles,
    $attempts,
    $audit
);

$app->log()->info('cron run', [
    'published' => $published,
    'logs_pruned' => $logFiles,
    'attempts_pruned' => $attempts,
    'audit_pruned' => $audit,
]);
