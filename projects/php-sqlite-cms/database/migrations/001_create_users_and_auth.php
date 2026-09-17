<?php

declare(strict_types=1);

use Cms\Database\Schema;

return function (Schema $schema): void {
    $schema->create('users', [
        $schema->id(),
        $schema->string('name', 120),
        $schema->string('email', 190),
        $schema->string('password_hash', 255),
        $schema->string('role', 20, false, 'author'),
        $schema->string('status', 20, false, 'active'),
        $schema->text('bio'),
        $schema->string('avatar', 255, true),
        $schema->string('website', 255, true),
        $schema->string('twitter', 100, true),
        $schema->datetime('last_login_at'),
        $schema->datetime('created_at'),
        $schema->datetime('updated_at'),
    ], [
        'unique' => ['users_email_unique' => ['email']],
        'index' => ['users_role_index' => ['role'], 'users_status_index' => ['status']],
    ]);

    $schema->create('login_attempts', [
        $schema->id(),
        $schema->string('identifier', 190),
        $schema->string('ip', 45, true),
        $schema->boolean('success'),
        $schema->datetime('created_at'),
    ], [
        'index' => ['login_attempts_lookup' => ['identifier', 'ip', 'created_at']],
    ]);

    $schema->create('remember_tokens', [
        $schema->id(),
        $schema->integer('user_id'),
        $schema->string('selector', 64),
        $schema->string('validator_hash', 64),
        $schema->datetime('expires_at'),
        $schema->datetime('created_at'),
    ], [
        'unique' => ['remember_tokens_selector_unique' => ['selector']],
        'index' => ['remember_tokens_user_index' => ['user_id']],
    ]);
};
