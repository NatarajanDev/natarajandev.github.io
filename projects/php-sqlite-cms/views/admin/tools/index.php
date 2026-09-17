<?php
/**
 * Maintenance tools and system health.
 *
 * @var array<string, mixed> $health
 * @var array<string, int> $storage
 * @var array<int, array{migration: string, batch: int, ran_at: ?string}> $migrations
 * @var array<int, string> $pendingMigrations
 * @var array<int, string> $logs
 * @var int $auditCount
 */
?>
<section class="block">
    <header class="block__head">
        <div>
            <h2>Maintenance</h2>
            <p class="muted">Cache, database, exports and logs.</p>
        </div>
    </header>

    <div class="tool-grid">
        <article class="tool">
            <h3><?= icon('chart', 18) ?> Cache</h3>
            <p><?= (int) $storage['cache_files'] ?> file(s), <?= e(human_bytes((int) $storage['cache_bytes'])) ?>. Cleared automatically when content changes.</p>
            <form method="post" action="<?= e(url('/admin/tools/cache')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn--ghost btn--sm" type="submit">Flush cache</button>
            </form>
        </article>

        <article class="tool">
            <h3><?= icon('layers', 18) ?> Database</h3>
            <p><?= e(human_bytes((int) $storage['database'])) ?> on disk · driver <code><?= e((string) $health['driver']) ?></code>
                · SQLite <?= e((string) $health['sqlite_version']) ?></p>
            <div class="tool__actions">
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/tools/backup')) ?>"><?= icon('upload', 16) ?> Download backup</a>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/tools/export')) ?>"><?= icon('file', 16) ?> Export JSON</a>
            </div>
        </article>

        <article class="tool">
            <h3><?= icon('activity', 18) ?> Migrations</h3>
            <p>
                <?= count($migrations) ?> applied
                <?php if ($pendingMigrations !== []): ?>
                    · <strong><?= count($pendingMigrations) ?> pending</strong>
                <?php else: ?>
                    · schema up to date
                <?php endif; ?>
            </p>
            <form method="post" action="<?= e(url('/admin/tools/migrate')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn--ghost btn--sm" type="submit">Run migrations</button>
            </form>
        </article>

        <article class="tool">
            <h3><?= icon('shield', 18) ?> Housekeeping</h3>
            <p>Prune logs older than 14 days, login attempts older than 30 days and <?= (int) $auditCount ?> audit entries older than 90 days.</p>
            <form method="post" action="<?= e(url('/admin/tools/prune')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn--ghost btn--sm" type="submit" data-confirm="Prune old logs and records?">Prune now</button>
            </form>
        </article>

        <article class="tool">
            <h3><?= icon('file', 18) ?> Logs</h3>
            <p><?= count($logs) ?> daily log file(s). Latest: <?= e($logs[0] ?? 'none') ?></p>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/tools/logs')) ?>">Open log viewer</a>
        </article>

        <article class="tool">
            <h3><?= icon('users', 18) ?> Audit trail</h3>
            <p><?= (int) $auditCount ?> recorded admin action(s).</p>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/audit')) ?>">View activity</a>
        </article>
    </div>

    <div class="panel-grid">
        <section class="panel">
            <header class="panel__head"><h3>Environment</h3></header>
            <ul class="kv">
                <li><span>PHP</span><strong><?= e((string) $health['php_version']) ?> (<?= e((string) $health['sapi']) ?>)</strong></li>
                <li><span>Timezone</span><strong><?= e((string) $health['timezone']) ?></strong></li>
                <li><span>Memory limit</span><strong><?= e((string) $health['memory_limit']) ?></strong></li>
                <li><span>Max upload</span><strong><?= e((string) $health['max_upload']) ?></strong></li>
                <li><span>Storage writable</span><strong><?= $health['storage_writable'] ? 'yes' : 'no' ?></strong></li>
                <li><span>Uploads writable</span><strong><?= $health['uploads_writable'] ? 'yes' : 'no' ?></strong></li>
            </ul>
        </section>

        <section class="panel">
            <header class="panel__head"><h3>Extensions</h3></header>
            <div class="panel__body tag-cloud">
                <?php foreach ($health['extensions'] as $extension => $loaded): ?>
                    <span class="chip <?= $loaded ? 'chip--accent' : '' ?>">
                        <?= e((string) $extension) ?> <?= $loaded ? '✓' : '—' ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <section class="panel">
        <header class="panel__head"><h3>Applied migrations</h3></header>
        <div class="table-wrap">
            <table class="table table--compact">
                <thead><tr><th>Migration</th><th>Batch</th><th>Ran at</th></tr></thead>
                <tbody>
                <?php foreach (array_reverse($migrations) as $migration): ?>
                    <tr>
                        <td><code><?= e($migration['migration']) ?></code></td>
                        <td><?= (int) $migration['batch'] ?></td>
                        <td class="muted"><?= e((string) ($migration['ran_at'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($migrations === []): ?>
                    <tr><td colspan="3" class="table__empty">No migrations recorded.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
