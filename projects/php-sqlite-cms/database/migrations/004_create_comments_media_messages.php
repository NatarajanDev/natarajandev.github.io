<?php

declare(strict_types=1);

use Cms\Database\Schema;

return function (Schema $schema): void {
    $schema->create('comments', [
        $schema->id(),
        $schema->integer('post_id'),
        $schema->integer('parent_id', true),
        $schema->string('author_name', 120),
        $schema->string('author_email', 190),
        $schema->string('author_url', 255, true),
        $schema->text('body'),
        $schema->string('status', 20, false, 'pending'),
        $schema->string('ip', 45, true),
        $schema->string('user_agent', 255, true),
        $schema->datetime('created_at'),
        $schema->datetime('updated_at'),
    ], [
        'index' => [
            'comments_post_status_index' => ['post_id', 'status'],
            'comments_status_index' => ['status'],
            'comments_parent_index' => ['parent_id'],
        ],
    ]);

    $schema->create('media', [
        $schema->id(),
        $schema->integer('user_id'),
        $schema->string('filename'),
        $schema->string('original_name'),
        $schema->string('path'),
        $schema->string('thumb_path', 255, true),
        $schema->string('mime_type', 100),
        $schema->integer('size', false, 0),
        $schema->integer('width', true),
        $schema->integer('height', true),
        $schema->string('alt_text', 255, true),
        $schema->string('caption', 255, true),
        $schema->datetime('created_at'),
    ], [
        'index' => ['media_user_index' => ['user_id'], 'media_mime_index' => ['mime_type']],
    ]);

    $schema->create('messages', [
        $schema->id(),
        $schema->string('name', 120),
        $schema->string('email', 190),
        $schema->string('subject', 200),
        $schema->text('body'),
        $schema->string('status', 20, false, 'new'),
        $schema->string('ip', 45, true),
        $schema->datetime('created_at'),
    ], [
        'index' => ['messages_status_index' => ['status', 'created_at']],
    ]);
};
