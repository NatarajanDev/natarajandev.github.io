<?php

declare(strict_types=1);

namespace Cms\Service;

use Cms\App;
use Cms\Database\Migrator;
use Cms\Repository\CategoryRepository;
use Cms\Repository\CommentRepository;
use Cms\Repository\PageRepository;
use Cms\Repository\PostRepository;
use Cms\Repository\SettingRepository;
use Cms\Repository\TagRepository;
use Cms\Repository\UserRepository;

/**
 * First-run installer: creates the schema, the first admin account and
 * (optionally) a small set of demo articles so the site is not empty.
 */
final class Installer
{
    public function __construct(private App $app)
    {
    }

    public function migrator(): Migrator
    {
        return new Migrator($this->app->db(), $this->app->path('migrations'));
    }

    public function isInstalled(): bool
    {
        try {
            if (!$this->app->db()->tableExists('users')) {
                return false;
            }

            return (int) $this->app->db()->scalar('SELECT COUNT(*) FROM users') > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<string> */
    public function migrate(): array
    {
        $migrator = $this->migrator();
        $executed = $migrator->run();
        (new SettingRepository($this->app->db()))->seedDefaults();

        return $executed;
    }

    public function createAdmin(string $name, string $email, string $password, array $profile = []): int
    {
        $users = new UserRepository($this->app->db());

        return $users->create([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'admin',
            'status' => 'active',
            'bio' => $profile['bio'] ?? 'Founder and editor of this publication.',
            'website' => $profile['website'] ?? '',
            'twitter' => $profile['twitter'] ?? '',
        ]);
    }

    /**
     * Seed a realistic demo publication (categories, tags, posts, pages,
     * comments, a greeting message).
     */
    public function seedDemo(int $adminId): void
    {
        $db = $this->app->db();
        $posts = new PostRepository($db);
        $pages = new PageRepository($db);
        $categories = new CategoryRepository($db);
        $tags = new TagRepository($db);
        $comments = new CommentRepository($db);
        $messages = new \Cms\Repository\MessageRepository($db);

        if ((int) $db->scalar('SELECT COUNT(*) FROM posts') > 0) {
            return;
        }

        $categoryIds = [];
        foreach ([
            ['Engineering', 'Deep dives into the code we ship.', '#6366f1'],
            ['Product', 'Release notes and product decisions.', '#0ea5e9'],
            ['Design', 'Interface craft, typography and layout.', '#f97316'],
        ] as $index => [$name, $description, $color]) {
            $categoryIds[$name] = $categories->create([
                'name' => $name,
                'slug' => str_slug($name),
                'description' => $description,
                'color' => $color,
                'sort_order' => $index,
            ]);
        }

        $tagIds = [];
        foreach (['php', 'sqlite', 'architecture', 'performance', 'security', 'markdown'] as $tag) {
            $tagIds[$tag] = $tags->create($tag);
        }

        $articles = [
            [
                'title' => 'Building a CMS on PHP and SQLite',
                'category' => 'Engineering',
                'tags' => ['php', 'sqlite', 'architecture'],
                'excerpt' => 'Why a single-file database is a serious option for production content sites, and how to keep the data layer portable.',
                'featured' => 1,
                'days_ago' => 2,
                'content' => <<<'MD'
SQLite is not a toy. For a content site that serves thousands of reads a day and a handful of writes, it is often *the* pragmatic choice: one file to back up, no separate service to operate, and PDO bindings identical to MySQL.

## Design rules that keep it fast

1. Run in **WAL** mode so readers never block the writer.
2. Enable `foreign_keys` on every connection — SQLite ignores them by default.
3. Keep write transactions short; batch bulk operations.
4. Index the columns you filter on: `status`, `published_at`, `slug`.

```sql
PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;
CREATE INDEX posts_status_published_index ON posts (status, published_at);
```

## Keeping MySQL possible

The schema layer in this project emits either SQLite or MySQL definitions, so the same migrations run on both engines:

| Concern | SQLite | MySQL |
| --- | --- | --- |
| Auto id | `INTEGER PRIMARY KEY AUTOINCREMENT` | `INT AUTO_INCREMENT` |
| Text | `TEXT` | `LONGTEXT` |
| Booleans | `INTEGER` 0/1 | `TINYINT(1)` |

> Portability is not about never using a feature — it is about knowing exactly where you used it.

Everything else (queries, repositories, views) stays engine agnostic.
MD,
            ],
            [
                'title' => 'A practical guide to comment moderation',
                'category' => 'Product',
                'tags' => ['security', 'architecture'],
                'excerpt' => 'Spam arrives in waves. A small, opinionated moderation pipeline beats a heavy plugin every time.',
                'featured' => 0,
                'days_ago' => 6,
                'content' => <<<'MD'
A comment box is the only place strangers can write into your database. Treat it accordingly.

## The pipeline

- **Honeypot + timing check** — bots fill hidden fields; humans take more than two seconds.
- **Rate limit** — no more than three comments per IP per five minutes.
- **Duplicate detection** — identical body from the same email within ten minutes is dropped.
- **Default to pending** — nothing appears on the site until an editor approves it.

Every state change is recorded in the audit trail, so you can always answer "who approved this?".
MD,
            ],
            [
                'title' => 'Markdown authoring without a build step',
                'category' => 'Engineering',
                'tags' => ['markdown', 'php'],
                'excerpt' => 'A 200-line renderer covers headings, lists, tables, quotes and fenced code — and it escapes everything by default.',
                'featured' => 0,
                'days_ago' => 11,
                'content' => <<<'MD'
Shipping a CMS means deciding how authors write. Rich-text editors produce HTML you then have to sanitise; a small Markdown subset produces HTML *you* generated.

## What the renderer covers

- `# heading` through `###### heading`
- bold, italic, strikethrough and `inline code`
- links, images, blockquotes and horizontal rules
- ordered/unordered lists
- fenced code blocks with a language hint
- pipe tables

Raw HTML in the source is escaped, so a post can only ever contain the tags the renderer emits.
MD,
            ],
            [
                'title' => 'Designing an admin panel authors actually enjoy',
                'category' => 'Design',
                'tags' => ['performance', 'architecture'],
                'excerpt' => 'Editor experience is a feature: instant live preview, autosaved drafts, keyboard shortcuts and clear status colours.',
                'featured' => 1,
                'days_ago' => 18,
                'content' => <<<'MD'
The admin panel is the part of a CMS that people use every day. Optimise for the 80% path: write, preview, publish.

## Small details that matter

1. A **live Markdown preview** beside the editor.
2. **Word count, reading time and slug** preview updated as you type.
3. Status colours that match the front end (`draft`, `scheduled`, `published`).
4. Bulk actions on the list screens, with a confirmation step for destructive ones.
5. Revision history so a mistake is never permanent.
MD,
            ],
            [
                'title' => 'Hardening a PHP application in one afternoon',
                'category' => 'Engineering',
                'tags' => ['security', 'php'],
                'excerpt' => 'The checklist that removes most real-world PHP vulnerabilities: CSRF, prepared statements, output escaping, upload validation.',
                'featured' => 0,
                'days_ago' => 25,
                'content' => <<<'MD'
Most breaches in small PHP applications are not exotic. They are the same five mistakes.

## The checklist

- **Prepared statements everywhere.** No string interpolation into SQL, ever.
- **Escape on output**, not on input: `htmlspecialchars($value, ENT_QUOTES)`.
- **CSRF tokens** on every state-changing form, compared with `hash_equals`.
- **Session hygiene**: regenerate the id on login, `HttpOnly`, `SameSite=Lax`.
- **Upload validation** by detected MIME type — never trust the extension.
- **Login throttling** per email and IP to blunt credential stuffing.

Each item is a handful of lines; together they close the door on the majority of automated attacks.
MD,
            ],
        ];

        foreach ($articles as $article) {
            $publishedAt = (new \DateTimeImmutable("-{$article['days_ago']} days"))->format('Y-m-d H:i:s');
            $postId = $posts->create([
                'user_id' => $adminId,
                'category_id' => $categoryIds[$article['category']] ?? null,
                'title' => $article['title'],
                'slug' => $posts->uniqueSlug($article['title']),
                'excerpt' => $article['excerpt'],
                'content' => $article['content'],
                'status' => 'published',
                'featured' => $article['featured'],
                'allow_comments' => 1,
                'seo_title' => $article['title'],
                'seo_description' => $article['excerpt'],
                'published_at' => $publishedAt,
            ]);

            $posts->syncTags($postId, array_map(static fn (string $tag): int => $tagIds[$tag], $article['tags']));

            // A little traffic history so the dashboard chart is not empty.
            foreach (range(13, 0) as $offset) {
                $views = random_int(4, 38);
                $db->insert('post_views', [
                    'post_id' => $postId,
                    'view_date' => (new \DateTimeImmutable("-{$offset} days"))->format('Y-m-d'),
                    'views' => $views,
                ]);
                $db->run('UPDATE posts SET views = views + ? WHERE id = ?', [$views, $postId]);
            }

            $revisionContent = $article['content'];
            $posts->addRevision([
                'id' => $postId,
                'title' => $article['title'],
                'content' => $revisionContent,
                'status' => 'published',
            ], $adminId);
        }

        $pages = [
            ['About', 'about', 1, "## Who we are\n\nNova CMS is a demonstration of a complete PHP + SQLite publishing platform: front end, admin panel, media library and moderation queue in a single deployable folder.\n\nIt is intentionally dependency-free — no Composer packages, no build step — so you can read every line that runs.\n\n## Stack\n\n- PHP 8.1+ with PDO SQLite\n- Vanilla JavaScript and hand-written CSS\n- Markdown authoring with a small renderer"],
            ['Contact', 'contact', 1, "We read every message. Use the form below and we will get back to you within two working days."],
            ['Privacy', 'privacy', 0, "## Data we store\n\n- Comments: name, email, body, IP address and user agent.\n- Contact messages: name, email, subject and body.\n- Editorial account details.\n\nNothing is shared with third parties. Comment IP addresses are used only for spam prevention and are pruned with the moderation log."],
        ];

        foreach ($pages as $index => [$title, $slug, $inMenu, $content]) {
            $pages->create([
                'user_id' => $adminId,
                'title' => $title,
                'slug' => $slug,
                'content' => $content,
                'status' => 'published',
                'show_in_menu' => $inMenu,
                'sort_order' => $index,
                'seo_title' => $title,
                'seo_description' => excerpt($content, 150),
            ]);
        }

        $firstPost = $db->first('SELECT id FROM posts ORDER BY id ASC LIMIT 1');
        if ($firstPost !== null) {
            $postId = (int) $firstPost['id'];
            $comments->create([
                'post_id' => $postId,
                'author_name' => 'Priya Raman',
                'author_email' => 'priya@example.com',
                'author_url' => 'https://example.com',
                'body' => 'WAL mode was the detail I had been missing in my own side project. Thanks for writing this up.',
                'status' => 'approved',
                'ip' => '203.0.113.24',
            ]);
            $approved = $db->first('SELECT id FROM comments ORDER BY id DESC LIMIT 1');
            $comments->create([
                'post_id' => $postId,
                'parent_id' => $approved === null ? null : (int) $approved['id'],
                'author_name' => 'Natarajan M',
                'author_email' => 'editor@example.com',
                'body' => 'Glad it helped — the busy_timeout pragma is the other one people forget.',
                'status' => 'approved',
                'ip' => '203.0.113.11',
            ]);
            $comments->create([
                'post_id' => $postId,
                'author_name' => 'Cheap Deals Bot',
                'author_email' => 'bot@example.net',
                'body' => 'BUY CHEAP FOLLOWERS NOW http://spam.example.net',
                'status' => 'pending',
                'ip' => '198.51.100.77',
            ]);
        }

        $messages->create([
            'name' => 'Client Enquiry',
            'email' => 'prospect@example.com',
            'subject' => 'Custom CMS work',
            'body' => 'We need a small publishing platform with an admin panel. Could you share a rough timeline and budget range?',
            'ip' => '203.0.113.90',
        ]);
    }

    /** @return list<string> */
    public function reset(): array
    {
        $db = $this->app->db();
        foreach ([
            'post_tags', 'post_revisions', 'post_views', 'comments', 'posts', 'pages',
            'categories', 'tags', 'media', 'messages', 'audit_log', 'remember_tokens',
            'login_attempts', 'settings', 'users', 'migrations',
        ] as $table) {
            $db->exec('DROP TABLE IF EXISTS ' . $table);
        }

        return $this->migrate();
    }
}
