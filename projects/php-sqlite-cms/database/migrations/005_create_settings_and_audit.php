<?php

declare(strict_types=1);

use Cms\Database\Schema;

return function (Schema $schema): void {
    $schema->create('settings', [
        $schema->string('key', 120),
        $schema->text('value'),
        $schema->datetime('updated_at'),
    ], [
        'unique' => ['settings_key_unique' => ['key']],
    ]);

    $schema->create('audit_log', [
        $schema->id(),
        $schema->integer('user_id', true),
        $schema->string('action', 120),
        $schema->string('entity', 60, true),
        $schema->integer('entity_id', true),
        $schema->text('meta'),
        $schema->string('ip', 45, true),
        $schema->datetime('created_at'),
    ], [
        'index' => ['audit_created_index' => ['created_at'], 'audit_entity_index' => ['entity', 'entity_id']],
    ]);
};
