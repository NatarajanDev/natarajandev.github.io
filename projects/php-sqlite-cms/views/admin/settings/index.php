<?php
/**
 * Settings tabs.
 *
 * @var string $tab
 * @var array<string, array<string, array{0: string, 1: string}>> $tabs
 * @var array<string, mixed> $values
 */
$labels = [
    'general' => 'General',
    'content' => 'Content',
    'comments' => 'Comments',
    'appearance' => 'Appearance',
    'seo' => 'SEO & analytics',
    'social' => 'Social profiles',
];
$fields = $tabs[$tab];
?>
<section class="block">
    <div class="tabs">
        <?php foreach ($tabs as $key => $_fields): ?>
            <a class="tabs__item<?= $tab === $key ? ' is-active' : '' ?>" href="<?= e(url('/admin/settings?tab=' . $key)) ?>">
                <?= e($labels[$key] ?? ucfirst($key)) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <form method="post" action="<?= e(url('/admin/settings')) ?>" class="form form--card">
        <?= csrf_field() ?>
        <?= method_field('PUT') ?>
        <input type="hidden" name="tab" value="<?= e($tab) ?>">

        <?php foreach ($fields as $key => [$label, $type]): ?>
            <?php $current = (string) ($values[$key] ?? ''); ?>

            <?php if ($type === 'toggle'): ?>
                <label class="check check--card">
                    <input type="checkbox" name="<?= e($key) ?>" value="1" <?= $current === '1' ? 'checked' : '' ?>>
                    <span>
                        <strong><?= e($label) ?></strong>
                        <small><?= e($key) ?></small>
                    </span>
                </label>
            <?php elseif ($type === 'textarea'): ?>
                <label class="field">
                    <span><?= e($label) ?></span>
                    <textarea name="<?= e($key) ?>" rows="4"><?= e($current) ?></textarea>
                    <small class="hint"><?= e($key) ?></small>
                </label>
            <?php elseif ($type === 'select'): ?>
                <?php $options = explode(',', substr($type, 7)); ?>
                <label class="field">
                    <span><?= e($label) ?></span>
                    <select name="<?= e($key) ?>">
                        <?php foreach ($options as $option): ?>
                            <option value="<?= e($option) ?>" <?= $current === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="hint"><?= e($key) ?></small>
                </label>
            <?php elseif ($type === 'color'): ?>
                <label class="field">
                    <span><?= e($label) ?></span>
                    <input type="color" name="<?= e($key) ?>" value="<?= e($current !== '' ? $current : '#6366f1') ?>">
                    <small class="hint"><?= e($key) ?></small>
                </label>
            <?php else: ?>
                <label class="field">
                    <span><?= e($label) ?></span>
                    <input type="<?= e($type === 'email' ? 'email' : ($type === 'number' ? 'number' : 'text')) ?>"
                           name="<?= e($key) ?>" value="<?= e($current) ?>"
                           <?= $type === 'number' ? 'min="1" max="60"' : '' ?>>
                    <small class="hint"><?= e($key) ?></small>
                </label>
            <?php endif; ?>
        <?php endforeach; ?>

        <div class="form__actions">
            <button class="btn btn--primary" type="submit">Save settings</button>
            <span class="muted">Cached widgets are refreshed automatically.</span>
        </div>
    </form>
</section>
