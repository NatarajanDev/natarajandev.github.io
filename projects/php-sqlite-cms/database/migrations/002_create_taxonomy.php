<?php

declare(strict_types=1);

use Cms\Database\Schema;

return function (Schema $schema): void {
    $schema->create('categories', [
        $schema->id(),
        $schema->integer('parent_id', true),
        $schema->string('name', 120),
        $schema->string('slug', 140),
        $schema->text('description'),
        $schema->string('color', 20, true),
        $schema->integer('sort_order', false, 0),
        $schema->datetime('created_at'),
        $schema->datetime('updated_at'),
    ], [
        'unique' => ['categories_slug_unique' => ['slug']],
        'index' => ['categories_parent_index' => ['parent_id']],
    ]);

    $schema->create('tags', [
        $schema->id(),
        $schema->string('name', 90),
        $schema->string('slug', 110),
        $schema->integer('usage_count', false, 0),
        $schema->datetime('created_at'),
    ], [
        'unique' => ['tags_slug_unique' => ['slug']],
    ]);
};
