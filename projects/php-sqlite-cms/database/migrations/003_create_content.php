<?php

declare(strict_types=1);

use Cms\Database\Schema;

return function (Schema $schema): void {
    $schema->create('posts', [
        $schema->id(),
        $schema->integer('user_id'),
        $schema->integer('category_id', true),
        $schema->string('title', 200),
        $schema->string('slug', 220),
        $schema->text('excerpt'),
        $schema->text('content'),
        $schema->string('content_format', 20, false, 'markdown'),
        $schema->string('cover_image', 255, true),
        $schema->string('status', 20, false, 'draft'),
        $schema->boolean('featured'),
        $schema->boolean('allow_comments', true),
        $schema->integer('views', false, 0),
        $schema->string('seo_title', 255, true),
        $schema->text('seo_description'),
        $schema->string('og_image', 255, true),
        $schema->string('canonical_url', 255, true),
        $schema->datetime('published_at'),
        $schema->datetime('created_at'),
        $schema->datetime('updated_at'),
    ], [
        'unique' => ['posts_slug_unique' => ['slug']],
        'index' => [
            'posts_status_published_index' => ['status', 'published_at'],
            'posts_category_index' => ['category_id'],
            'posts_author_index' => ['user_id'],
            'posts_featured_index' => ['featured'],
        ],
    ]);

    $schema->create('post_tags', [
        $schema->integer('post_id'),
        $schema->integer('tag_id'),
    ], [
        'unique' => ['post_tags_unique' => ['post_id', 'tag_id']],
        'index' => ['post_tags_tag_index' => ['tag_id']],
    ]);

    $schema->create('pages', [
        $schema->id(),
        $schema->integer('user_id'),
        $schema->integer('parent_id', true),
        $schema->string('title', 200),
        $schema->string('slug', 220),
        $schema->text('content'),
        $schema->string('content_format', 20, false, 'markdown'),
        $schema->string('template', 40, false, 'default'),
        $schema->string('status', 20, false, 'published'),
        $schema->boolean('show_in_menu'),
        $schema->integer('sort_order', false, 0),
        $schema->string('seo_title', 255, true),
        $schema->text('seo_description'),
        $schema->datetime('created_at'),
        $schema->datetime('updated_at'),
    ], [
        'unique' => ['pages_slug_unique' => ['slug']],
        'index' => ['pages_status_index' => ['status'], 'pages_parent_index' => ['parent_id']],
    ]);

    $schema->create('post_revisions', [
        $schema->id(),
        $schema->integer('post_id'),
        $schema->integer('user_id', true),
        $schema->string('title', 200),
        $schema->text('content'),
        $schema->string('status', 20),
        $schema->datetime('created_at'),
    ], [
        'index' => ['post_revisions_post_index' => ['post_id', 'created_at']],
    ]);

    $schema->create('post_views', [
        $schema->integer('post_id'),
        $schema->date('view_date'),
        $schema->integer('views', false, 0),
    ], [
        'unique' => ['post_views_unique' => ['post_id', 'view_date']],
        'index' => ['post_views_date_index' => ['view_date']],
    ]);
};
