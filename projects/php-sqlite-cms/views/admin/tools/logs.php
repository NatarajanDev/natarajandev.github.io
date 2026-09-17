<?php
/**
 * System log viewer.
 *
 * @var array<int, array<string, mixed>> $entries
 * @var array<int, string> $dates
 * @var string $activeDate
 */
$levelTone = static fn (string $level): string => match (strtoupper($level)) {
    'ERROR' => 'danger',
    'WARNING' => 'warn',
    'DEBUG' => 'muted',
    default => 'info',
};
?>
<section class="block">
    <header class="block__head">
        <div>
            <h2>System log</h2>
            <p class="muted"><?= count($entries) ?> recent entries (newest first) · <?= e($activeDate) ?></p>
        </div>
        <div class="block__actions">
            <form class="inline-form" method="get" action="<?= e(url('/admin/tools/logs')) ?>">
                <select name="date" onchange="this.form.submit()">
                    <?php foreach ($dates as $date): ?>
                        <option value="<?= e($date) ?>" <?= $date === $activeDate ? 'selected' : '' ?>><?= e($date) ?></option>
                    <?php endforeach; ?>
                    <?php if ($dates === []): ?>
                        <option value="<?= e($activeDate) ?>"><?= e($activeDate) ?></option>
                    <?php endif; ?>
                </select>
            </form>
            <form method="post" action="<?= e(url('/admin/tools/logs/clear')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="date" value="<?= e($activeDate) ?>">
                <button class="btn btn--ghost btn--sm btn--danger" type="submit" data-confirm="Delete this log file?">Clear log</button>
            </form>
        </div>
    </header>

    <?php if ($entries === []): ?>
        <div class="empty-state">
            <h3>No entries</h3>
            <p>Nothing has been logged for <?= e($activeDate) ?>. Errors, warnings and admin actions appear here.</p>
        </div>
    <?php else: ?>
        <div class="log-list">
            <?php foreach ($entries as $entry): ?>
                <details class="log-entry">
                    <summary>
                        <span class="badge badge--<?= e($levelTone((string) $entry['level'])) ?>"><?= e((string) $entry['level']) ?></span>
                        <time><?= e((string) $entry['time']) ?></time>
                        <span class="log-entry__message"><?= e(excerpt((string) $entry['message'], 120)) ?></span>
                    </summary>
                    <pre class="log-entry__body"><?= e((string) json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
