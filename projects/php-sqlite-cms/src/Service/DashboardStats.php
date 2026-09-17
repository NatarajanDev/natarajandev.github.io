<?php

declare(strict_types=1);

namespace Cms\Service;

use Cms\App;
use Cms\Repository\AuditRepository;
use Cms\Repository\CommentRepository;
use Cms\Repository\MediaRepository;
use Cms\Repository\MessageRepository;
use Cms\Repository\PageRepository;
use Cms\Repository\PostRepository;
use Cms\Repository\UserRepository;

/**
 * Aggregates the numbers shown on the admin dashboard.
 */
final class DashboardStats
{
    public function __construct(private App $app)
    {
    }

    /** @return array<string, mixed> */
    public function summary(?int $userId = null): array
    {
        $posts = new PostRepository($this->app->db());
        $comments = new CommentRepository($this->app->db());
        $media = new MediaRepository($this->app->db());
        $messages = new MessageRepository($this->app->db());
        $pages = new PageRepository($this->app->db());
        $users = new UserRepository($this->app->db());

        return [
            'posts' => $posts->counts($userId),
            'comments' => $comments->counts(),
            'media' => $media->stats(),
            'messages' => $messages->counts(),
            'pages' => $pages->counts(),
            'users' => $users->counts(),
        ];
    }

    /** @return array<string, int> date => views */
    public function chart(int $days = 14): array
    {
        return (new PostRepository($this->app->db()))->viewsSeries($days);
    }

    /** @return list<array<string, mixed>> */
    public function activity(int $limit = 8): array
    {
        return (new AuditRepository($this->app->db()))->recent($limit);
    }

    /** @return list<array<string, mixed>> */
    public function pendingComments(int $limit = 5): array
    {
        return (new CommentRepository($this->app->db()))->recent($limit, 'pending');
    }

    /** @return list<array<string, mixed>> */
    public function recentPosts(int $limit = 5, ?int $userId = null): array
    {
        return (new PostRepository($this->app->db()))->latestForAdmin($limit, $userId);
    }

    /** @return list<array<string, mixed>> */
    public function topPosts(int $limit = 5): array
    {
        return (new PostRepository($this->app->db()))->popular($limit, 30);
    }

    /** Human-readable storage usage for the dashboard footer. */
    public function storage(): array
    {
        $dbPath = (string) $this->app->config('database.sqlite', '');
        $dbSize = is_file($dbPath) ? (int) filesize($dbPath) : 0;
        $cache = $this->app->cache()->stats();

        return [
            'database' => $dbSize,
            'cache_files' => $cache['files'],
            'cache_bytes' => $cache['bytes'],
            'uploads' => $this->uploadsSize((string) $this->app->path('uploads')),
        ];
    }

    private function uploadsSize(string $directory): int
    {
        $bytes = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $bytes += (int) $file->getSize();
            }
        }

        return $bytes;
    }
}
