<?php
/**
 * Test runner — no PHPUnit required.
 *
 *   php tests/run.php             run every suite
 *   php tests/run.php security    run suites whose name contains "security"
 *
 * Unit suites cover the support classes; integration suites create a real
 * SQLite database in a temporary directory, run the migrations and exercise
 * the repositories, services, installer and authentication flow end to end.
 */

declare(strict_types=1);

// ----------------------------------------------------------------- workspace
$root = dirname(__DIR__);
$temp = rtrim(sys_get_temp_dir(), '/') . '/nova-cms-tests-' . bin2hex(random_bytes(4));

foreach (['', '/uploads', '/logs', '/cache', '/sessions'] as $dir) {
    @mkdir($temp . $dir, 0o777, true);
}

putenv('CMS_DB_PATH=' . $temp . '/test.sqlite');
putenv('CMS_STORAGE_PATH=' . $temp);
putenv('CMS_UPLOAD_PATH=' . $temp . '/uploads');
putenv('CMS_ENV=testing');
putenv('CMS_DEBUG=true');
putenv('CMS_URL=http://localhost:8080');
putenv('CMS_TIMEZONE=UTC');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTP_USER_AGENT'] = 'NovaCMS-Test/1.0';

// The web bootstrap starts a session; in the CLI SAPI we do it by hand so the
// authentication suite can use real session storage.
if (session_status() === PHP_SESSION_NONE) {
    session_save_path($temp . '/sessions');
    ini_set('session.use_strict_mode', '0');
    session_start();
}

$filter = $argv[1] ?? null;

/** Boot the application once, after the environment above is in place. */
function test_app(): \Cms\App
{
    static $app = null;
    if ($app === null) {
        $app = require dirname(__DIR__) . '/src/bootstrap.php';
    }

    return $app;
}

/** A throwaway repository bound to the test database. */
function repo(string $class): object
{
    return new $class(test_app()->db());
}

// ------------------------------------------------------------------ harness
final class Assert
{
    public static int $passed = 0;

    /** @var list<string> */
    public static array $failures = [];

    public static string $suite = 'general';

    public static function truthy(mixed $value, string $message): void
    {
        if ($value) {
            self::$passed++;

            return;
        }
        self::$failures[] = sprintf('[%s] %s', self::$suite, $message);
    }

    public static function falsy(mixed $value, string $message): void
    {
        self::truthy(!$value, $message);
    }

    public static function same(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected === $actual) {
            self::$passed++;

            return;
        }
        self::$failures[] = sprintf(
            '[%s] %s — expected %s, got %s',
            self::$suite,
            $message,
            var_export($expected, true),
            var_export($actual, true)
        );
    }

    public static function contains(string $needle, string $haystack, string $message): void
    {
        self::truthy(str_contains($haystack, $needle), $message . ' (missing: ' . $needle . ')');
    }

    public static function throws(callable $callback, string $message): void
    {
        try {
            $callback();
        } catch (\Throwable) {
            self::$passed++;

            return;
        }
        self::$failures[] = sprintf('[%s] %s — expected an exception', self::$suite, $message);
    }
}

/** @var array<string, callable> $suites */
$suites = [];

function suite(string $name, callable $body): void
{
    global $suites;
    $suites[$name] = $body;
}

function section(string $title): void
{
    echo "\n\033[1m{$title}\033[0m\n";
}

// ============================================================ unit: support
suite('support/strings', function (): void {
    Assert::same('hello-world', \Cms\Support\Str::slug('Hello, World!'), 'slugifies punctuation');
    Assert::same('cafe-au-lait', \Cms\Support\Str::slug('Café au lait'), 'transliterates accents');
    Assert::same('item', \Cms\Support\Str::slug('!!!'), 'falls back to a placeholder');
    Assert::same('abc', \Cms\Support\Str::slug('abc', '_'), 'slug respects the separator argument');
    Assert::contains('…', \Cms\Support\Str::excerpt(str_repeat('word ', 80), 40), 'excerpt adds an ellipsis');
    Assert::same('short', \Cms\Support\Str::excerpt('short', 40), 'short text is returned unchanged');
    Assert::same('1.5 KB', \Cms\Support\Str::bytes(1536), 'byte formatting');
    Assert::same('512 B', \Cms\Support\Str::bytes(512), 'small byte formatting');
    Assert::same(1, \Cms\Support\Str::readingTime('a few words'), 'minimum reading time');
    Assert::contains('ago', \Cms\Support\Str::timeAgo((new DateTimeImmutable('-3 hours'))->format('Y-m-d H:i:s')), 'relative time');
    Assert::same('—', \Cms\Support\Str::timeAgo(null), 'missing date placeholder');
    Assert::same('&lt;script&gt;', \Cms\Support\Str::e('<script>'), 'html escaping');
});

suite('support/markdown', function (): void {
    $html = \Cms\Support\Markdown::toHtml("# Title\n\nSome **bold** and *italic* text.\n\n- one\n- two\n\n> quoted");
    Assert::contains('<h1', $html, 'headings');
    Assert::contains('<strong>bold</strong>', $html, 'bold');
    Assert::contains('<em>italic</em>', $html, 'italic');
    Assert::contains('<ul>', $html, 'unordered lists');
    Assert::contains('<blockquote>', $html, 'blockquotes');

    $code = \Cms\Support\Markdown::toHtml("```php\necho 'x';\n```");
    Assert::contains('language-php', $code, 'fenced code carries the language class');

    $escaped = \Cms\Support\Markdown::toHtml('<script>alert(1)</script>');
    Assert::falsy(str_contains($escaped, '<script>'), 'raw html in markdown is escaped');

    $table = \Cms\Support\Markdown::toHtml("| a | b |\n| --- | --- |\n| 1 | 2 |");
    Assert::contains('<table>', $table, 'pipe tables');

    $link = \Cms\Support\Markdown::toHtml('[docs](https://example.com)');
    Assert::contains('href="https://example.com"', $link, 'links');

    $image = \Cms\Support\Markdown::toHtml('![alt text](/uploads/a.png)');
    Assert::contains('<img', $image, 'images');
    Assert::contains('alt="alt text"', $image, 'image alt text');

    $bare = \Cms\Support\Markdown::toHtml('javascript:alert(1)');
    Assert::falsy(str_contains($bare, 'href="javascript:'), 'javascript: urls are not linkified');
});

suite('support/validator', function (): void {
    $ok = \Cms\Support\Validator::make(
        ['title' => 'Hello', 'email' => 'a@b.com', 'slug' => 'my-post', 'status' => 'draft'],
        ['title' => 'required|min:3|max:10', 'email' => 'required|email', 'slug' => 'slug', 'status' => 'in:draft,published']
    );
    Assert::truthy($ok->passes(), 'valid payload passes');

    $bad = \Cms\Support\Validator::make(
        ['title' => '', 'email' => 'nope', 'slug' => 'Not A Slug', 'status' => 'weird'],
        ['title' => 'required', 'email' => 'email', 'slug' => 'slug', 'status' => 'in:draft,published']
    );
    Assert::truthy($bad->fails(), 'invalid payload fails');
    Assert::same(4, count($bad->flatErrors()), 'one error per broken rule');
    Assert::truthy(str_contains($bad->flatErrors()[0], 'required'), 'required message is descriptive');

    $mismatch = \Cms\Support\Validator::make(
        ['password' => 'secret123', 'password_confirmation' => 'secret124'],
        ['password_confirmation' => 'same:password']
    );
    Assert::truthy($mismatch->fails(), 'password confirmation mismatch detected');

    $short = \Cms\Support\Validator::make(['password' => 'abc'], ['password' => 'min:8']);
    Assert::truthy($short->fails(), 'min length enforced');
});

suite('support/cache', function () use ($temp): void {
    $cache = new \Cms\Support\Cache($temp . '/cache');
    Assert::same(null, $cache->get('missing'), 'missing key returns null');
    $cache->put('greeting', 'hello', 60);
    Assert::same('hello', $cache->get('greeting'), 'stores a value');
    Assert::same('computed', $cache->remember('computed', 60, static fn (): string => 'computed'), 'remember executes the callback');
    Assert::same('computed', $cache->remember('computed', 60, static fn (): string => 'other'), 'remember returns the cached value');
    $cache->put('expired', 'x', -1);
    Assert::same(null, $cache->get('expired'), 'expired entries are dropped');
    Assert::truthy($cache->stats()['files'] >= 1, 'stats report cached files');
    $cache->flush();
    Assert::same(null, $cache->get('greeting'), 'flush clears entries');
});

suite('support/response', function (): void {
    $json = \Cms\Support\Response::json(['ok' => true], 201);
    Assert::same(201, $json->status(), 'json status code');
    Assert::same('application/json; charset=utf-8', $json->headers()['Content-Type'], 'json content type');
    Assert::contains('"ok":true', $json->body(), 'json body');

    $redirect = \Cms\Support\Response::redirect('/admin');
    Assert::same(302, $redirect->status(), 'redirect status');
    Assert::truthy(str_ends_with($redirect->headers()['Location'], '/admin'), 'redirect target');

    $download = \Cms\Support\Response::download('data', 'backup.sqlite', 'application/vnd.sqlite3');
    Assert::contains('backup.sqlite', $download->headers()['Content-Disposition'], 'download filename');
    Assert::truthy($download->withHeader('X-Test', '1')->headers()['X-Test'] === '1', 'headers are chainable');
});

// ======================================================= integration: database
suite('database/migrations', function (): void {
    $app = test_app();
    $migrator = $app->installer()->migrator();
    $executed = $migrator->run();

    Assert::truthy(count($executed) >= 5, 'all migrations executed');
    Assert::same([], $migrator->pending(), 'nothing pending after a run');
    Assert::truthy(count($migrator->status()) >= 5, 'migration history recorded');
    Assert::same([], $migrator->run(), 'migrations are idempotent');

    foreach (['users', 'posts', 'pages', 'categories', 'tags', 'comments', 'media', 'messages', 'settings', 'audit_log', 'post_views'] as $table) {
        Assert::truthy($app->db()->tableExists($table), "table {$table} exists");
    }

    Assert::truthy(!$app->db()->tableExists('does_not_exist'), 'unknown tables are reported absent');
});

suite('repository/settings', function (): void {
    $settings = test_app()->settings();
    $settings->seedDefaults();
    Assert::same('Nova CMS', $settings->get('site_title'), 'seed provides branded defaults');
    Assert::same('fallback', $settings->get('definitely_missing', 'fallback'), 'missing keys fall back');

    $settings->set('site_title', 'Test Publication');
    Assert::same('Test Publication', $settings->get('site_title'), 'values are written');
    $settings->set('comments_enabled', true);
    Assert::truthy($settings->bool('comments_enabled'), 'boolean coercion');
    $settings->setMany(['posts_per_page' => '12', 'theme_accent' => '#0ea5e9']);
    Assert::same(12, $settings->int('posts_per_page'), 'integer coercion');
    Assert::same('#0ea5e9', $settings->get('theme_accent'), 'bulk writes');
});

suite('repository/users', function (): void {
    $users = repo(\Cms\Repository\UserRepository::class);
    $id = $users->create([
        'name' => 'Ada Lovelace',
        'email' => 'Ada@Example.com',
        'password_hash' => password_hash('correct horse', PASSWORD_DEFAULT),
        'role' => 'admin',
        'bio' => 'Mathematician',
    ]);

    Assert::truthy($id > 0, 'user created');
    Assert::same('ada@example.com', $users->find($id)['email'], 'emails are normalised to lowercase');
    Assert::truthy(!array_key_exists('password_hash', $users->find($id)), 'password hash is not leaked by find()');
    Assert::truthy($users->emailExists('ada@example.com'), 'email uniqueness check');
    Assert::falsy($users->emailExists('other@example.com'), 'unknown email reported free');

    $users->update($id, ['bio' => 'First programmer']);
    Assert::same('First programmer', $users->find($id)['bio'], 'user updated');

    $hash = password_hash('rotated-secret', PASSWORD_DEFAULT);
    $users->updatePassword($id, $hash);
    Assert::truthy(password_verify('rotated-secret', $users->findWithHash($id)['password_hash']), 'password rotation');

    $users->recordLoginAttempt('ada@example.com', '203.0.113.10', false);
    Assert::truthy(count($users->recentAttempts(5)) >= 1, 'login attempts recorded');
    Assert::truthy($users->pruneAttempts(0) >= 1, 'attempts pruned');

    $users->storeRememberToken($id, 'selector123', hash('sha256', 'validator'), (new DateTimeImmutable('+1 day'))->format('Y-m-d H:i:s'));
    Assert::same($id, (int) $users->findRememberToken('selector123')['user_id'], 'remember token stored');
    $users->deleteRememberTokens($id);
    Assert::same(null, $users->findRememberToken('selector123'), 'remember tokens revoked');

    Assert::same(1, $users->countAdmins(), 'admin count');
    Assert::truthy(is_array($users->counts()), 'user counts');
    Assert::truthy($users->findByNameSlug('ada-lovelace') !== null, 'author lookup by name slug');
});

suite('repository/taxonomy', function (): void {
    $categories = repo(\Cms\Repository\CategoryRepository::class);
    $engineering = $categories->create(['name' => 'Engineering', 'slug' => $categories->uniqueSlug('Engineering'), 'color' => '#6366f1']);
    $frontend = $categories->create(['name' => 'Frontend', 'slug' => $categories->uniqueSlug('Frontend'), 'parent_id' => $engineering]);
    Assert::same(2, $categories->count(), 'categories created');

    $tree = $categories->tree();
    Assert::same(1, count($tree), 'tree has a single root');
    Assert::same(1, count($tree[0]['children']), 'child nested under the root');
    Assert::same('Frontend', $tree[0]['children'][0]['name'], 'nested node is the child');

    $duplicate = $categories->create(['name' => 'Engineering', 'slug' => $categories->uniqueSlug('Engineering')]);
    Assert::same('engineering-2', $categories->find($duplicate)['slug'], 'slug collisions resolved');
    Assert::truthy($categories->slugExists('engineering'), 'slug existence check');
    $categories->delete($duplicate);
    Assert::same(null, $categories->find($duplicate), 'category deleted');

    $tags = repo(\Cms\Repository\TagRepository::class);
    $ids = $tags->resolveNames(['php', 'sqlite', 'php']);
    Assert::same(2, count($ids), 'duplicate tag names collapse');
    Assert::same(2, $tags->count(), 'tags stored');
    Assert::same('php', $tags->find($ids[0])['slug'], 'tag slugs generated');

    $target = $tags->create('databases');
    $tags->merge($ids[1], $target);
    Assert::same(null, $tags->find($ids[1]), 'source tag removed after merge');
    Assert::same(2, $tags->count(), 'merge keeps the target');
});

suite('repository/posts', function (): void {
    $app = test_app();
    $posts = repo(\Cms\Repository\PostRepository::class);
    $authorId = (int) repo(\Cms\Repository\UserRepository::class)->all()[0]['id'];
    $category = $app->db()->first('SELECT id FROM categories LIMIT 1');

    $postId = $posts->create([
        'user_id' => $authorId,
        'category_id' => (int) $category['id'],
        'title' => 'Testing a CMS with SQLite',
        'slug' => $posts->uniqueSlug('Testing a CMS with SQLite'),
        'excerpt' => 'Integration testing notes.',
        'content' => "# Heading\n\nBody copy for the test.",
        'status' => 'published',
        'featured' => true,
        'published_at' => (new DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'),
    ]);

    Assert::truthy($postId > 0, 'post created');
    $post = $posts->find($postId);
    Assert::same('testing-a-cms-with-sqlite', $post['slug'], 'slug generated');
    Assert::same('Engineering', $post['category_name'], 'category joined');
    Assert::same('Ada Lovelace', $post['author_name'], 'author joined');
    Assert::same(0, (int) $post['comment_count'], 'comment count joined');

    $draftId = $posts->create([
        'user_id' => $authorId,
        'title' => 'Draft in progress',
        'slug' => $posts->uniqueSlug('Draft in progress'),
        'content' => 'Draft body',
        'status' => 'draft',
    ]);

    Assert::same(null, $posts->findBySlug('draft-in-progress'), 'drafts excluded from the public site');
    Assert::truthy($posts->findBySlug('draft-in-progress', false) !== null, 'drafts visible to administrators');
    Assert::same(1, $posts->published(1, 10)['total'], 'published listing excludes drafts');
    Assert::same(1, $posts->paginate(['status' => 'draft'], 1, 10)['total'], 'status filter');
    Assert::same(1, $posts->paginate(['search' => 'integration'], 1, 10)['total'], 'search filter');
    Assert::same(1, $posts->paginate(['featured' => true], 1, 10)['total'], 'featured filter');
    Assert::same(1, $posts->paginate(['category_id' => (int) $category['id']], 1, 10)['total'], 'category filter');
    Assert::same(0, $posts->paginate(['category_id' => 9999], 1, 10)['total'], 'unknown category returns nothing');

    $tags = repo(\Cms\Repository\TagRepository::class);
    $posts->syncTags($postId, $tags->resolveNames(['php', 'sqlite']));
    Assert::same(2, count($posts->tagsFor($postId)), 'tags attached');
    Assert::same(2, count($posts->tagsForMany([$postId])[$postId]), 'bulk tag lookup');
    $posts->syncTags($postId, $tags->resolveNames(['php']));
    Assert::same(1, count($posts->tagsFor($postId)), 'tag sync replaces the set');

    $posts->incrementViews($postId);
    $posts->incrementViews($postId);
    Assert::same(2, (int) $posts->find($postId)['views'], 'views incremented');
    Assert::truthy(array_sum($posts->viewsSeries(7)) >= 2, 'daily view series recorded');

    $posts->addRevision($posts->find($postId), $authorId);
    $posts->addRevision($posts->find($postId), $authorId);
    $revisions = $posts->revisions($postId);
    Assert::truthy(count($revisions) >= 2, 'revisions stored');
    Assert::truthy($posts->findRevision((int) $revisions[0]['id'], $postId) !== null, 'single revision fetched');
    Assert::same(null, $posts->findRevision((int) $revisions[0]['id'], 999999), 'revision is scoped to its post');

    $counts = $posts->counts();
    Assert::same(1, $counts['published'], 'published count');
    Assert::same(1, $counts['draft'], 'draft count');
    Assert::same(1, $counts['featured'], 'featured count');

    Assert::same(1, $posts->setStatus([$draftId], 'published'), 'bulk publish');
    Assert::same(2, $posts->counts()['published'], 'count reflects the bulk change');
    Assert::same(1, $posts->setFeatured([$draftId], true), 'feature toggled');
    Assert::same(2, $posts->counts()['featured'], 'featured count updated');

    $archive = $posts->archive();
    Assert::truthy(count($archive) >= 1, 'archive buckets built');
    Assert::truthy(count($posts->popular(3, 30)) >= 1, 'popular posts');
    Assert::truthy(count($posts->featured(3)) >= 1, 'featured posts');
    Assert::truthy(count($posts->latestForAdmin(3)) >= 1, 'admin latest posts');
    Assert::truthy(count($posts->adjacent($posts->find($postId))) === 2, 'adjacent posts');
    Assert::truthy(count($posts->related($posts->find($postId), 2)) >= 1, 'related posts');

    Assert::same(1, $posts->deleteMany([$draftId]), 'bulk delete');
    Assert::same(null, $posts->find($draftId), 'deleted post is gone');
    Assert::same(0, count($posts->tagsFor($draftId)), 'tag links removed with the post');
});

suite('repository/comments', function (): void {
    $app = test_app();
    $comments = repo(\Cms\Repository\CommentRepository::class);
    $postId = (int) $app->db()->first('SELECT id FROM posts LIMIT 1')['id'];

    $root = $comments->create([
        'post_id' => $postId,
        'author_name' => 'Reader One',
        'author_email' => 'reader@example.com',
        'body' => 'Great write-up, thanks!',
        'status' => 'approved',
        'ip' => '198.51.100.4',
    ]);
    $reply = $comments->create([
        'post_id' => $postId,
        'parent_id' => $root,
        'author_name' => 'Author',
        'author_email' => 'author@example.com',
        'body' => 'Glad it helped.',
        'status' => 'approved',
        'ip' => '198.51.100.5',
    ]);
    $pending = $comments->create([
        'post_id' => $postId,
        'author_name' => 'Spam Bot',
        'author_email' => 'bot@example.net',
        'body' => 'Buy cheap things',
        'status' => 'pending',
        'ip' => '198.51.100.6',
    ]);

    $tree = $comments->treeForPost($postId);
    Assert::same(1, count($tree), 'single approved root');
    Assert::same(1, count($tree[0]['children']), 'reply nested');
    Assert::same(1, $tree[0]['children'][0]['depth'], 'reply depth');

    Assert::same(2, $comments->countForPost($postId), 'approved count');
    Assert::same(1, $comments->counts()['pending'], 'pending count');
    Assert::same(2, (int) repo(\Cms\Repository\PostRepository::class)->find($postId)['comment_count'], 'post listing exposes the approved comment count');

    Assert::same(1, $comments->setStatusMany([$pending], 'spam'), 'bulk status change');
    Assert::same(1, $comments->counts()['spam'], 'spam recorded');
    $comments->setStatus($pending, 'trash');
    Assert::same(1, $comments->counts()['trash'], 'single status change');

    Assert::truthy($comments->isDuplicate($postId, 'reader@example.com', 'Great write-up, thanks!', 60), 'duplicate detection');
    Assert::falsy($comments->isDuplicate($postId, 'reader@example.com', 'A different body', 60), 'different bodies are not duplicates');
    Assert::same(1, $comments->recentCountByIp('198.51.100.4', 60), 'ip rate counting');
    Assert::truthy(count($comments->recent(5, 'pending')) === 0, 'recent pending is empty');

    $comments->delete($root);
    Assert::same(null, $comments->find($root), 'root deleted');
    Assert::same(null, $comments->find($reply)['parent_id'], 'orphaned replies are promoted to root');
    Assert::same(1, $comments->countForPost($postId), 'approved count after delete');
});

suite('repository/media+messages', function (): void {
    $media = repo(\Cms\Repository\MediaRepository::class);
    $id = $media->create([
        'user_id' => 1,
        'filename' => 'photo.jpg',
        'original_name' => 'Photo.jpg',
        'path' => 'uploads/2026/01/photo.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 20480,
        'width' => 1200,
        'height' => 800,
        'alt_text' => 'A photo',
    ]);

    Assert::same('Photo.jpg', $media->find($id)['original_name'], 'media stored');
    $stats = $media->stats();
    Assert::same(1, $stats['total'], 'media counted');
    Assert::same(1, $stats['images'], 'images counted');
    Assert::same(20480, $stats['bytes'], 'bytes counted');

    $media->update($id, ['alt_text' => 'Updated alt', 'bogus' => 'ignored']);
    Assert::same('Updated alt', $media->find($id)['alt_text'], 'media metadata updated');
    Assert::same(1, $media->paginate(['type' => 'image'], 1, 10)['total'], 'type filter');
    Assert::same(1, $media->paginate(['search' => 'photo'], 1, 10)['total'], 'search filter');
    Assert::same(0, $media->paginate(['type' => 'video'], 1, 10)['total'], 'unknown type returns nothing');
    Assert::truthy(count($media->recent(5)) === 1, 'recent media');
    $media->delete($id);
    Assert::same(null, $media->find($id), 'media deleted');

    $messages = repo(\Cms\Repository\MessageRepository::class);
    $messageId = $messages->create([
        'name' => 'Prospect',
        'email' => 'prospect@example.com',
        'subject' => 'Project enquiry',
        'body' => 'We would like to discuss a build.',
        'ip' => '203.0.113.77',
    ]);
    Assert::same(1, $messages->unreadCount(), 'unread counted');
    Assert::same(1, $messages->counts()['total'], 'message counts');
    $messages->setStatus($messageId, 'read');
    Assert::same(0, $messages->unreadCount(), 'status update clears unread');
    Assert::same(1, $messages->paginate([], 1, 10)['total'], 'message listing');
    $messages->delete($messageId);
    Assert::same(null, $messages->find($messageId), 'message deleted');
});

// ======================================================= integration: services
suite('service/installer+auth', function (): void {
    $app = test_app();
    $installer = $app->installer();
    Assert::truthy($installer->isInstalled(), 'installer detects the existing users');

    $adminId = $installer->createAdmin('Site Owner', 'owner@example.com', 'super-secret-1');
    Assert::truthy($adminId > 0, 'administrator created');
    Assert::same(1, $app->db()->scalar('SELECT COUNT(*) FROM users WHERE role = ?', ['admin']), 'admin role assigned');

    $hash = (string) $app->db()->scalar('SELECT password_hash FROM users WHERE id = ?', [$adminId]);
    Assert::falsy($hash === 'super-secret-1', 'passwords are never stored in plain text');
    Assert::truthy(str_starts_with($hash, '$2y$') || str_starts_with($hash, '$argon2'), 'password hashed with a modern algorithm');

    $before = repo(\Cms\Repository\PostRepository::class)->counts();
    $installer->seedDemo($adminId);
    $after = repo(\Cms\Repository\PostRepository::class)->counts();
    Assert::truthy($after['published'] >= 5, 'demo articles seeded');
    Assert::truthy(repo(\Cms\Repository\PageRepository::class)->counts()['published'] >= 3, 'demo pages seeded');
    Assert::truthy(count(repo(\Cms\Repository\TagRepository::class)->all()) >= 5, 'demo tags seeded');
    Assert::truthy(count(repo(\Cms\Repository\CategoryRepository::class)->all()) >= 3, 'demo categories seeded');
    Assert::truthy(count(repo(\Cms\Repository\CommentRepository::class)->recent(10, 'pending')) >= 1, 'demo comments seeded');

    $installer->seedDemo($adminId);
    Assert::same($after['published'], repo(\Cms\Repository\PostRepository::class)->counts()['published'], 'seeding twice is a no-op');
    Assert::truthy($after['published'] >= $before['published'], 'seeding never removes content');

    // ------------------------------------------------------------------- auth
    $auth = $app->auth();
    Assert::truthy(!$auth->attempt('owner@example.com', 'wrong-password'), 'wrong password rejected');
    Assert::truthy($auth->attempt('owner@example.com', 'super-secret-1'), 'correct password accepted');
    Assert::truthy($auth->check(), 'session established');
    Assert::truthy(!$auth->guest(), 'guest flag cleared');
    Assert::truthy($auth->isAdmin(), 'admin role recognised');
    Assert::truthy($auth->canModerate(), 'moderation permission');
    Assert::same($adminId, $auth->id(), 'current user id');
    Assert::same(4, $auth->attemptsRemaining('owner@example.com'), 'failed attempt counted once');

    $auth->logout();
    Assert::truthy(!$auth->check(), 'logout clears the session');

    for ($i = 0; $i < 6; $i++) {
        $auth->attempt('owner@example.com', 'nope-' . $i);
    }
    Assert::falsy($auth->attempt('owner@example.com', 'super-secret-1'), 'account throttled after repeated failures');
    Assert::same(0, $auth->attemptsRemaining('owner@example.com'), 'no attempts remaining');

    repo(\Cms\Repository\UserRepository::class)->pruneAttempts(0);
    Assert::truthy($auth->attempt('owner@example.com', 'super-secret-1'), 'login works again once attempts are cleared');
    $auth->logout();

    // Remember-me round trip.
    $auth->attempt('owner@example.com', 'super-secret-1', true);
    Assert::same($adminId, $auth->id(), 'remember-me login');
    Assert::truthy($app->db()->scalar('SELECT COUNT(*) FROM remember_tokens') >= 1, 'remember token stored');
    $auth->logout();
    Assert::same(0, (int) $app->db()->scalar('SELECT COUNT(*) FROM remember_tokens'), 'logout revokes remember tokens');
});

suite('service/dashboard+feeds', function (): void {
    $app = test_app();
    $dashboard = $app->dashboard();

    $summary = $dashboard->summary();
    Assert::truthy($summary['posts']['published'] >= 5, 'dashboard summary counts posts');
    Assert::truthy(isset($summary['users']['total']), 'dashboard summary counts users');
    Assert::same(14, count($dashboard->chart(14)), 'chart series length');
    Assert::truthy(is_array($dashboard->activity(5)), 'activity feed');
    Assert::truthy(is_array($dashboard->pendingComments(5)), 'pending comments widget');
    Assert::truthy(is_array($dashboard->recentPosts(5)), 'recent posts widget');
    Assert::truthy(is_array($dashboard->topPosts(5)), 'top posts widget');
    Assert::truthy(array_key_exists('bytes', $dashboard->storage()), 'storage widget');

    $feed = new \Cms\Service\Feed($app);
    $rss = $feed->rss(5);
    Assert::contains('<rss', $rss, 'rss document');
    Assert::contains('<item>', $rss, 'rss items');
    Assert::contains('&lt;', $rss, 'rss content is escaped');
    $json = json_decode($feed->json(5), true);
    Assert::same('https://jsonfeed.org/version/1.1', $json['version'], 'json feed version');
    Assert::truthy(count($json['items']) >= 1, 'json feed items');

    $sitemap = new \Cms\Service\Sitemap($app);
    Assert::contains('<urlset', $sitemap->xml(), 'sitemap document');
    Assert::contains('Sitemap:', $sitemap->robots(), 'robots points at the sitemap');

    $activity = $app->activity();
    $activity->record('test.action', 'post', 1, ['note' => 'unit test']);
    Assert::truthy($activity->repository()->count() >= 1, 'audit entries recorded');
    Assert::truthy(count($activity->recent(5)) >= 1, 'audit listing');
});

// ============================================================= security suite
suite('security/csrf+sessions', function (): void {
    $token = \Cms\Support\Csrf::token();
    Assert::same(64, strlen($token), 'token has 256 bits of entropy');
    Assert::truthy(\Cms\Support\Csrf::verify($token), 'generated token verifies');
    Assert::falsy(\Cms\Support\Csrf::verify('forged'), 'forged token rejected');
    Assert::falsy(\Cms\Support\Csrf::verify(null), 'empty token rejected');
    Assert::falsy(\Cms\Support\Csrf::verify(''), 'blank token rejected');

    $rotated = $token;
    \Cms\Support\Csrf::rotate();
    Assert::falsy(\Cms\Support\Csrf::verify($rotated), 'rotation invalidates the old token');

    Assert::truthy(\Cms\Support\Str::e('"quoted" & <b>') === '&quot;quoted&quot; &amp; &lt;b&gt;', 'escaping covers quotes and ampersands');
});

suite('security/uploads', function () use ($temp): void {
    $app = test_app();
    $uploader = $app->uploader();

    // A PHP script renamed to .png must be rejected: MIME beats the extension.
    $disguised = $temp . '/payload.png';
    file_put_contents($disguised, "<?php echo 'pwned'; ?>");
    $result = $uploader->store([
        'name' => 'payload.png',
        'type' => 'image/png',
        'tmp_name' => $disguised,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($disguised),
    ]);
    Assert::falsy($result['ok'], 'disguised script rejected');
    Assert::contains('Unsupported file type', (string) $result['error'], 'rejection explains why');

    // …as must an executable, a huge file and a failed transfer.
    $oversized = $uploader->store(['name' => 'big.png', 'tmp_name' => $disguised, 'error' => UPLOAD_ERR_OK, 'size' => 50 * 1024 * 1024]);
    Assert::falsy($oversized['ok'], 'oversized upload rejected');
    $failed = $uploader->store(['name' => 'x.png', 'error' => UPLOAD_ERR_INI_SIZE]);
    Assert::falsy($failed['ok'], 'php upload error surfaced');

    // A real image is accepted, measured, thumbnailed and stored safely.
    $png = $temp . '/real.png';
    $image = imagecreatetruecolor(64, 32);
    imagefilledrectangle($image, 0, 0, 63, 31, imagecolorallocate($image, 99, 102, 241));
    imagepng($image, $png);
    imagedestroy($image);

    $stored = $uploader->store([
        'name' => 'Cover Photo.png',
        'type' => 'image/png',
        'tmp_name' => $png,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($png),
    ]);

    Assert::truthy($stored['ok'], 'valid png accepted');
    Assert::contains('uploads/', (string) $stored['media']['path'], 'stored under uploads/');
    Assert::same(64, (int) $stored['media']['width'], 'image width detected');
    Assert::same(32, (int) $stored['media']['height'], 'image height detected');
    Assert::falsy(str_contains((string) $stored['media']['filename'], ' '), 'stored filenames are slugged');
    Assert::truthy(is_file($app->path('uploads') . '/' . substr((string) $stored['media']['path'], strlen('uploads/'))), 'file written to disk');
    Assert::truthy($stored['media']['thumb_path'] !== null && is_file($app->path('uploads') . '/' . substr((string) $stored['media']['thumb_path'], strlen('uploads/'))), 'thumbnail created');

    $uploader->delete((string) $stored['media']['path']);
    Assert::falsy(is_file($app->path('uploads') . '/' . substr((string) $stored['media']['path'], strlen('uploads/'))), 'file removed with delete()');
});

suite('security/access-control', function (): void {
    $app = test_app();
    $router = new \Cms\Support\Router();

    $router->get('/admin', static fn (): \Cms\Support\Response => \Cms\Support\Response::make('dashboard'), ['middleware' => ['auth']]);
    $router->get('/admin/only', static fn (): \Cms\Support\Response => \Cms\Support\Response::make('admins'), ['middleware' => ['admin']]);
    $router->post('/admin/save', static fn (): \Cms\Support\Response => \Cms\Support\Response::make('saved'), ['middleware' => ['csrf']]);
    $router->get('/hello/{name}', static fn (\Cms\App $app, string $name): \Cms\Support\Response => \Cms\Support\Response::make('hi ' . $name));
    $router->get('/files/{path?}', static fn (): \Cms\Support\Response => \Cms\Support\Response::make('optional'));
    $router->get('/named', static fn (): \Cms\Support\Response => \Cms\Support\Response::make('named'), ['name' => 'named.route']);

    // A tiny fake request factory: Request is immutable and reads $_SERVER.
    $fake = static function (string $method, string $path, array $body = []): \Cms\Support\Request {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $path;
        $_POST = $body;
        \Cms\Support\Request::swap(null);

        return \Cms\Support\Request::capture();
    };

    // Guests are bounced to the login screen.
    $guest = $router->dispatch($fake('GET', '/admin'), $app);
    Assert::same(302, $guest->status(), 'guest redirected away from the admin');
    Assert::contains('/admin/login', $guest->headers()['Location'], 'redirect target is the login page');

    $app->auth()->logout();

    // A missing CSRF token is reported as 419.
    Assert::same(419, $router->dispatch($fake('POST', '/admin/save'), $app)->status(), 'missing CSRF token rejected');
    $withToken = $router->dispatch($fake('POST', '/admin/save', ['_token' => \Cms\Support\Csrf::token()]), $app);
    Assert::same(200, $withToken->status(), 'valid CSRF token accepted');

    // Route parameters, optional segments and named routes.
    Assert::same('hi world', $router->dispatch($fake('GET', '/hello/world'), $app)->body(), 'route parameters reach the handler');
    Assert::same('optional', $router->dispatch($fake('GET', '/files'), $app)->body(), 'optional segments match');
    Assert::truthy(str_ends_with($router->route('named.route'), '/named'), 'named routes resolve');
    Assert::same(404, $router->dispatch($fake('GET', '/nowhere'), $app)->status(), 'unknown paths return 404');
    Assert::same(405, $router->dispatch($fake('DELETE', '/named'), $app)->status(), 'wrong verb returns 405');

    // Signed in as an administrator: both areas respond.
    $app->auth()->login(repo(\Cms\Repository\UserRepository::class)->findByEmail('owner@example.com'));
    Assert::same(200, $router->dispatch($fake('GET', '/admin'), $app)->status(), 'authenticated request allowed');
    Assert::same(200, $router->dispatch($fake('GET', '/admin/only'), $app)->status(), 'admin-only route allowed for admins');

    $app->auth()->logout();
    Assert::same(302, $router->dispatch($fake('GET', '/admin/only'), $app)->status(), 'admin-only route still guarded after logout');

    // The admin middleware answers 403 for a signed-in non-admin.
    $users = repo(\Cms\Repository\UserRepository::class);
    $authorId = $users->create([
        'name' => 'Erin Author',
        'email' => 'erin@example.com',
        'password_hash' => password_hash('author-pass', PASSWORD_DEFAULT),
        'role' => 'author',
    ]);
    $app->auth()->login($users->findByEmail('erin@example.com'));
    Assert::falsy($app->auth()->isAdmin(), 'author is not an administrator');
    Assert::truthy($app->auth()->canModerate(), 'authors may moderate their own comments');
    Assert::same(200, $router->dispatch($fake('GET', '/admin'), $app)->status(), 'authors reach their dashboard');
    $forbidden = $router->dispatch($fake('GET', '/admin/only'), $app);
    Assert::same(403, $forbidden->status(), 'authors are forbidden from admin-only routes');
    $app->auth()->logout();
    $users->delete($authorId);

    \Cms\Support\Request::swap(null);
});

// ============================================================ view rendering
suite('views/rendering', function (): void {
    $app = test_app();
    $view = $app->view();

    $notFound = $view->render('errors/404', ['meta' => ['title' => 'Page not found']]);
    Assert::contains('404', $notFound, '404 view renders standalone');
    Assert::contains('noindex', $notFound, '404 view is noindex');

    $home = $view->render('site/home', [
        'featured' => [],
        'latest' => [],
        'categories' => [],
        'stats' => ['articles' => 6, 'categories' => 3, 'tags' => 6, 'comments' => 2],
        'sidebarPopular' => [],
        'sidebarTags' => [],
        'sidebarArchive' => [],
        'sidebarCategories' => [],
    ]);
    Assert::contains('Latest articles', $home, 'home view renders');
    Assert::contains('Test Publication', $home, 'layout renders the configured site title');
    Assert::contains('site.js', $home, 'layout loads the site script');

    $post = repo(\Cms\Repository\PostRepository::class)->findBySlug('testing-a-cms-with-sqlite');
    Assert::truthy($post !== null, 'fixture post available to views');

    $show = $view->render('site/posts/show', [
        'post' => $post,
        'tags' => [],
        'adjacent' => ['previous' => null, 'next' => null],
        'related' => [],
        'comments' => [],
        'commentCount' => 0,
        'totalComments' => 0,
        'allowComments' => true,
        'perPage' => 6,
        'formStartedAt' => time(),
        'sidebarPopular' => [],
        'sidebarTags' => [],
        'sidebarArchive' => [],
        'sidebarCategories' => [],
        'meta' => ['title' => $post['title']],
    ]);
    Assert::contains('Testing a CMS with SQLite', $show, 'post view renders the title');
    Assert::contains('<h1', $show, 'markdown body rendered');

    $login = $view->render('auth/login', [
        'meta' => ['title' => 'Sign in'],
        'email' => '',
    ]);
    Assert::contains('name="password"', $login, 'login form renders');
    Assert::contains('name="_token"', $login, 'login form carries a CSRF token');

    Assert::throws(static fn (): string => $app->view()->render('views/does-not-exist'), 'missing templates throw');
});

// ------------------------------------------------------------------- runner
$app = test_app();
$selected = [];

foreach ($suites as $name => $body) {
    if ($filter === null || str_contains($name, $filter)) {
        $selected[$name] = $body;
    }
}

echo "\033[1mNova CMS test suite\033[0m";
echo $filter !== null ? " (filter: {$filter})\n" : "\n";
echo 'PHP ' . PHP_VERSION . ' · ' . PHP_OS_FAMILY . ' · PDO ' . (new PDO('sqlite::memory:'))->getAttribute(PDO::ATTR_CLIENT_VERSION) . "\n";

$started = microtime(true);

foreach ($selected as $name => $body) {
    Assert::$suite = $name;
    section($name);
    try {
        $body();
        echo "  ✓ suite completed\n";
    } catch (\Throwable $e) {
        Assert::$failures[] = sprintf('[%s] aborted — %s: %s (%s:%d)', $name, $e::class, $e->getMessage(), basename($e->getFile()), $e->getLine());
        echo '  ✗ aborted: ' . $e->getMessage() . "\n";
    }
}

$elapsed = microtime(true) - $started;

echo "\n" . str_repeat('─', 64) . "\n";
if (Assert::$failures === []) {
    printf("\033[32m✓ %d assertions passed in %.2fs\033[0m\n", Assert::$passed, $elapsed);
} else {
    printf("\033[31m✗ %d passed, %d failed in %.2fs\033[0m\n", Assert::$passed, count(Assert::$failures), $elapsed);
    foreach (Assert::$failures as $failure) {
        echo "  • {$failure}\n";
    }
}

// ------------------------------------------------------------------ cleanup
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($files as $file) {
    $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
}
@rmdir($temp);

exit(Assert::$failures === [] ? 0 : 1);
