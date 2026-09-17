<?php
/**
 * Public routes.
 *
 * @var \Cms\Support\Router $router
 */

declare(strict_types=1);

use Cms\Controllers\InstallController;
use Cms\Controllers\Site\ApiController;
use Cms\Controllers\Site\ArchiveController;
use Cms\Controllers\Site\FeedController;
use Cms\Controllers\Site\HomeController;
use Cms\Controllers\Site\PageController;
use Cms\Controllers\Site\PostController;
use Cms\Controllers\Site\SearchController;

// ------------------------------------------------------------------- installer
$router->get('/install', [InstallController::class, 'show'], ['name' => 'install']);
$router->post('/install', [InstallController::class, 'install'], ['middleware' => 'csrf']);

// ----------------------------------------------------------------------- blog
$router->get('/', [HomeController::class, 'index'], ['name' => 'home']);
$router->get('/about', [HomeController::class, 'about'], ['name' => 'about']);
$router->get('/posts', [ArchiveController::class, 'index'], ['name' => 'posts.index']);

$router->get('/posts/{slug}', [PostController::class, 'show'], ['name' => 'posts.show']);
$router->post('/posts/{slug}/comments', [PostController::class, 'comment'], [
    'middleware' => 'csrf',
    'name' => 'posts.comment',
]);

$router->get('/category/{slug}', [ArchiveController::class, 'category'], ['name' => 'category']);
$router->get('/tag/{slug}', [ArchiveController::class, 'tag'], ['name' => 'tag']);
$router->get('/author/{slug}', [ArchiveController::class, 'author'], ['name' => 'author']);
$router->get('/archive/{year}', [ArchiveController::class, 'date'], ['name' => 'archive.year']);
$router->get('/archive/{year}/{month}', [ArchiveController::class, 'date'], ['name' => 'archive.month']);

$router->get('/pages/{slug}', [PageController::class, 'show'], ['name' => 'pages.show']);
$router->post('/contact', [PageController::class, 'contact'], ['middleware' => 'csrf', 'name' => 'contact']);

$router->get('/search', [SearchController::class, 'index'], ['name' => 'search']);

// ------------------------------------------------------------------ feeds/seo
$router->get('/feed.xml', [FeedController::class, 'rss'], ['name' => 'feed.rss']);
$router->get('/feed.json', [FeedController::class, 'jsonFeed'], ['name' => 'feed.json']);
$router->get('/sitemap.xml', [FeedController::class, 'sitemap'], ['name' => 'sitemap']);
$router->get('/robots.txt', [FeedController::class, 'robots'], ['name' => 'robots']);

// ------------------------------------------------------------------------- api
$router->get('/api/posts', [ApiController::class, 'posts'], ['name' => 'api.posts']);
$router->get('/api/posts/{slug}', [ApiController::class, 'post'], ['name' => 'api.post']);
$router->post('/api/posts/{slug}/view', [ApiController::class, 'recordView'], ['name' => 'api.view']);
$router->get('/api/categories', [ApiController::class, 'categories'], ['name' => 'api.categories']);
$router->get('/api/tags', [ApiController::class, 'tags'], ['name' => 'api.tags']);
$router->get('/api/comments/{postId}', [ApiController::class, 'comments'], ['name' => 'api.comments']);
$router->get('/api/search', [SearchController::class, 'api'], ['name' => 'api.search']);
$router->get('/api/stats', [ApiController::class, 'stats'], ['name' => 'api.stats']);
$router->get('/api/health', [ApiController::class, 'health'], ['name' => 'api.health']);
