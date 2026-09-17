<?php
/**
 * Admin panel routes. Everything below /admin requires a session unless it is
 * explicitly marked as a guest route.
 *
 * @var \Cms\Support\Router $router
 */

declare(strict_types=1);

use Cms\Controllers\Admin\AuthController;
use Cms\Controllers\Admin\CommentController;
use Cms\Controllers\Admin\DashboardController;
use Cms\Controllers\Admin\MediaController;
use Cms\Controllers\Admin\MessageController;
use Cms\Controllers\Admin\PageController;
use Cms\Controllers\Admin\PostController;
use Cms\Controllers\Admin\ProfileController;
use Cms\Controllers\Admin\SettingController;
use Cms\Controllers\Admin\TaxonomyController;
use Cms\Controllers\Admin\ToolController;
use Cms\Controllers\Admin\UserController;

// -------------------------------------------------------------------- session
$router->group(['prefix' => '/admin'], function ($router): void {
    $router->get('/login', [AuthController::class, 'showLogin'], ['middleware' => 'guest', 'name' => 'admin.login']);
    $router->post('/login', [AuthController::class, 'login'], ['middleware' => ['guest', 'csrf'], 'name' => 'admin.login.post']);
    $router->post('/logout', [AuthController::class, 'logout'], ['middleware' => ['auth', 'csrf'], 'name' => 'admin.logout']);
});

// ------------------------------------------------------------------ dashboard
$router->group(['prefix' => '/admin', 'middleware' => ['auth', 'csrf']], function ($router): void {
    $router->get('/', [DashboardController::class, 'index'], ['name' => 'admin.dashboard']);

    // Posts
    $router->get('/posts', [PostController::class, 'index'], ['name' => 'admin.posts']);
    $router->get('/posts/create', [PostController::class, 'create'], ['name' => 'admin.posts.create']);
    $router->post('/posts', [PostController::class, 'store']);
    $router->post('/posts/bulk', [PostController::class, 'bulk'], ['name' => 'admin.posts.bulk']);
    $router->get('/posts/{id}/edit', [PostController::class, 'edit'], ['name' => 'admin.posts.edit']);
    $router->put('/posts/{id}', [PostController::class, 'update']);
    $router->delete('/posts/{id}', [PostController::class, 'destroy']);
    $router->get('/posts/{id}/revisions', [PostController::class, 'revisions'], ['name' => 'admin.posts.revisions']);
    $router->post('/posts/{id}/revisions/{revisionId}/restore', [PostController::class, 'restoreRevision']);

    // Pages
    $router->get('/pages', [PageController::class, 'index'], ['name' => 'admin.pages']);
    $router->get('/pages/create', [PageController::class, 'create'], ['name' => 'admin.pages.create']);
    $router->post('/pages', [PageController::class, 'store']);
    $router->post('/pages/bulk', [PageController::class, 'bulk'], ['name' => 'admin.pages.bulk']);
    $router->get('/pages/{id}/edit', [PageController::class, 'edit'], ['name' => 'admin.pages.edit']);
    $router->put('/pages/{id}', [PageController::class, 'update']);
    $router->delete('/pages/{id}', [PageController::class, 'destroy']);

    // Taxonomy
    $router->get('/categories', [TaxonomyController::class, 'categories'], ['name' => 'admin.categories']);
    $router->post('/categories', [TaxonomyController::class, 'storeCategory']);
    $router->put('/categories/{id}', [TaxonomyController::class, 'updateCategory']);
    $router->delete('/categories/{id}', [TaxonomyController::class, 'destroyCategory']);

    $router->get('/tags', [TaxonomyController::class, 'tags'], ['name' => 'admin.tags']);
    $router->post('/tags', [TaxonomyController::class, 'storeTag']);
    $router->put('/tags/{id}', [TaxonomyController::class, 'updateTag']);
    $router->post('/tags/{id}/merge', [TaxonomyController::class, 'mergeTag']);
    $router->delete('/tags/{id}', [TaxonomyController::class, 'destroyTag']);

    // Comments
    $router->get('/comments', [CommentController::class, 'index'], ['name' => 'admin.comments']);
    $router->post('/comments/bulk', [CommentController::class, 'bulk'], ['name' => 'admin.comments.bulk']);
    $router->post('/comments/{id}/approve', [CommentController::class, 'approve']);
    $router->post('/comments/{id}/unapprove', [CommentController::class, 'unapprove']);
    $router->post('/comments/{id}/spam', [CommentController::class, 'spam']);
    $router->post('/comments/{id}/trash', [CommentController::class, 'trash']);
    $router->post('/comments/{id}/reply', [CommentController::class, 'reply']);
    $router->delete('/comments/{id}', [CommentController::class, 'destroy']);

    // Media
    $router->get('/media', [MediaController::class, 'index'], ['name' => 'admin.media']);
    $router->post('/media', [MediaController::class, 'upload'], ['name' => 'admin.media.upload']);
    $router->get('/media/picker', [MediaController::class, 'picker'], ['name' => 'admin.media.picker']);
    $router->put('/media/{id}', [MediaController::class, 'update']);
    $router->delete('/media/{id}', [MediaController::class, 'destroy']);

    // Inbox
    $router->get('/messages', [MessageController::class, 'index'], ['name' => 'admin.messages']);
    $router->get('/messages/{id}', [MessageController::class, 'show'], ['name' => 'admin.messages.show']);
    $router->put('/messages/{id}', [MessageController::class, 'update']);
    $router->delete('/messages/{id}', [MessageController::class, 'destroy']);

    // Account
    $router->get('/profile', [ProfileController::class, 'index'], ['name' => 'admin.profile']);
    $router->put('/profile', [ProfileController::class, 'update']);
    $router->put('/profile/password', [ProfileController::class, 'password']);

    // Settings
    $router->get('/settings', [SettingController::class, 'index'], ['name' => 'admin.settings']);
    $router->put('/settings', [SettingController::class, 'update']);

    // Tools
    $router->get('/tools', [ToolController::class, 'index'], ['name' => 'admin.tools']);
    $router->post('/tools/cache', [ToolController::class, 'flushCache']);
    $router->post('/tools/migrate', [ToolController::class, 'runMigrations']);
    $router->post('/tools/prune', [ToolController::class, 'prune']);
    $router->get('/tools/backup', [ToolController::class, 'backup'], ['name' => 'admin.tools.backup']);
    $router->get('/tools/export', [ToolController::class, 'export'], ['name' => 'admin.tools.export']);
    $router->get('/tools/logs', [ToolController::class, 'logs'], ['name' => 'admin.logs']);
    $router->post('/tools/logs/clear', [ToolController::class, 'clearLog']);
    $router->get('/audit', [ToolController::class, 'audit'], ['name' => 'admin.audit']);

    // Users (administrators only)
    $router->group(['middleware' => 'role:admin'], function ($router): void {
        $router->get('/users', [UserController::class, 'index'], ['name' => 'admin.users']);
        $router->get('/users/create', [UserController::class, 'create'], ['name' => 'admin.users.create']);
        $router->post('/users', [UserController::class, 'store']);
        $router->get('/users/{id}/edit', [UserController::class, 'edit'], ['name' => 'admin.users.edit']);
        $router->put('/users/{id}', [UserController::class, 'update']);
        $router->delete('/users/{id}', [UserController::class, 'destroy'], ['name' => 'admin.users.delete']);
        $router->post('/users/security/clear-attempts', [UserController::class, 'clearAttempts']);
    });
});
