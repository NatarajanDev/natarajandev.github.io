<?php

declare(strict_types=1);

namespace Cms\Repository;

/**
 * Posts: listing with filters, slug lookup, tags, revisions, analytics.
 */
final class PostRepository extends Repository
{
    private const SELECT = 'SELECT p.*,
            c.name AS category_name, c.slug AS category_slug, c.color AS category_color,
            u.name AS author_name, u.avatar AS author_avatar,
            (SELECT COUNT(*) FROM comments cm WHERE cm.post_id = p.id AND cm.status = \'approved\') AS comment_count
        FROM posts p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN users u ON u.id = p.user_id';

    /**
     * @param array<string, mixed> $filters status, category_id, category_slug, tag_slug, user_id, author_slug,
     *                                       search, featured, published_only, year, month, ids
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 10, string $order = 'p.published_at DESC, p.id DESC'): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['status'])) {
            $conditions[] = 'p.status = ?';
            $params[] = (string) $filters['status'];
        }
        if (!empty($filters['statuses']) && is_array($filters['statuses'])) {
            $placeholders = implode(', ', array_fill(0, count($filters['statuses']), '?'));
            $conditions[] = "p.status IN ({$placeholders})";
            $params = array_merge($params, array_values($filters['statuses']));
        }
        if (!empty($filters['category_id'])) {
            $conditions[] = 'p.category_id = ?';
            $params[] = (int) $filters['category_id'];
        }
        if (!empty($filters['category_slug'])) {
            $conditions[] = 'c.slug = ?';
            $params[] = (string) $filters['category_slug'];
        }
        if (!empty($filters['tag_slug'])) {
            $conditions[] = 'EXISTS (SELECT 1 FROM post_tags pt JOIN tags t ON t.id = pt.tag_id
                                     WHERE pt.post_id = p.id AND t.slug = ?)';
            $params[] = (string) $filters['tag_slug'];
        }
        if (!empty($filters['user_id'])) {
            $conditions[] = 'p.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }
        if (!empty($filters['author_slug'])) {
            $conditions[] = 'LOWER(REPLACE(u.name, \' \', \'-\')) = ?';
            $params[] = strtolower((string) $filters['author_slug']);
        }
        if (!empty($filters['featured'])) {
            $conditions[] = 'p.featured = 1';
        }
        if (!empty($filters['search'])) {
            $conditions[] = '(LOWER(p.title) LIKE ? OR LOWER(p.excerpt) LIKE ? OR LOWER(p.content) LIKE ?)';
            $term = '%' . mb_strtolower(trim((string) $filters['search'])) . '%';
            $params = array_merge($params, [$term, $term, $term]);
        }
        if (!empty($filters['year'])) {
            $conditions[] = $this->yearCondition();
            $params[] = sprintf('%04d', (int) $filters['year']);
        }
        if (!empty($filters['month']) && !empty($filters['year'])) {
            $conditions[] = $this->monthCondition();
            $params[] = sprintf('%04d-%02d', (int) $filters['year'], (int) $filters['month']);
        }
        if (!empty($filters['published_only'])) {
            $conditions[] = "p.status = 'published' AND (p.published_at IS NULL OR p.published_at <= ?)";
            $params[] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        }
        if (!empty($filters['ids']) && is_array($filters['ids'])) {
            $ids = $this->intIds($filters['ids']);
            if ($ids !== []) {
                $conditions[] = 'p.id IN (' . $this->placeholders($ids) . ')';
                $params = array_merge($params, $ids);
            }
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $allowedOrders = [
            'p.published_at DESC, p.id DESC',
            'p.published_at ASC, p.id ASC',
            'p.created_at DESC',
            'p.updated_at DESC',
            'p.views DESC',
            'p.title ASC',
        ];
        $order = in_array($order, $allowedOrders, true) ? $order : $allowedOrders[0];

        return $this->paginateQuery(
            self::SELECT . $where . ' ORDER BY ' . $order,
            'SELECT COUNT(*) FROM posts p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN users u ON u.id = p.user_id' . $where,
            $params,
            $page,
            $perPage
        );
    }

    public function published(int $page = 1, int $perPage = 10, array $filters = []): array
    {
        return $this->paginate(array_merge($filters, ['published_only' => true]), $page, $perPage);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->first(self::SELECT . ' WHERE p.id = ?', [$id]);
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug, bool $publishedOnly = true): ?array
    {
        $sql = self::SELECT . ' WHERE p.slug = ?';
        $params = [$slug];
        if ($publishedOnly) {
            $sql .= " AND p.status = 'published' AND (p.published_at IS NULL OR p.published_at <= ?)";
            $params[] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        }

        return $this->db->first($sql, $params);
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM posts WHERE slug = ?';
        $params = [$slug];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        return (int) $this->db->scalar($sql, $params) > 0;
    }

    /** Generate a unique slug, appending -2, -3, ... when needed. */
    public function uniqueSlug(string $title, ?int $exceptId = null): string
    {
        $base = str_slug($title);
        $slug = $base;
        $suffix = 2;
        while ($this->slugExists($slug, $exceptId)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $now = $this->now();

        return $this->db->insert('posts', [
            'user_id' => (int) $data['user_id'],
            'category_id' => $data['category_id'] ?? null,
            'title' => (string) $data['title'],
            'slug' => (string) $data['slug'],
            'excerpt' => $data['excerpt'] ?? null,
            'content' => $data['content'] ?? '',
            'content_format' => (string) ($data['content_format'] ?? 'markdown'),
            'cover_image' => $data['cover_image'] ?? null,
            'status' => (string) ($data['status'] ?? 'draft'),
            'featured' => !empty($data['featured']) ? 1 : 0,
            'allow_comments' => array_key_exists('allow_comments', $data) ? (int) (bool) $data['allow_comments'] : 1,
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
            'og_image' => $data['og_image'] ?? null,
            'canonical_url' => $data['canonical_url'] ?? null,
            'published_at' => $data['published_at'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $fields = array_intersect_key($data, array_flip([
            'user_id', 'category_id', 'title', 'slug', 'excerpt', 'content', 'content_format', 'cover_image',
            'status', 'featured', 'allow_comments', 'seo_title', 'seo_description', 'og_image', 'canonical_url',
            'published_at',
        ]));

        foreach (['featured', 'allow_comments'] as $bool) {
            if (array_key_exists($bool, $fields)) {
                $fields[$bool] = (int) (bool) $fields[$bool];
            }
        }

        $fields['updated_at'] = $this->now();
        $this->db->update('posts', $fields, 'id', $id);
    }

    public function delete(int $id): void
    {
        $this->db->delete('post_tags', 'post_id = ?', [$id]);
        $this->db->delete('post_revisions', 'post_id = ?', [$id]);
        $this->db->delete('post_views', 'post_id = ?', [$id]);
        $this->db->delete('comments', 'post_id = ?', [$id]);
        $this->db->delete('posts', 'id = ?', [$id]);
    }

    /** @param list<int> $ids */
    public function deleteMany(array $ids): int
    {
        $ids = $this->intIds($ids);
        if ($ids === []) {
            return 0;
        }

        foreach (['post_tags', 'post_revisions', 'post_views'] as $table) {
            $this->db->delete($table, 'post_id IN (' . $this->placeholders($ids) . ')', $ids);
        }

        return $this->db->delete('posts', 'id IN (' . $this->placeholders($ids) . ')', $ids);
    }

    /** @param list<int> $ids */
    public function setStatus(array $ids, string $status): int
    {
        $ids = $this->intIds($ids);
        if ($ids === []) {
            return 0;
        }

        $publishedAt = $status === 'published' ? $this->now() : null;
        $statement = $this->db->run(
            'UPDATE posts SET status = ?, published_at = COALESCE(published_at, ?), updated_at = ?
             WHERE id IN (' . $this->placeholders($ids) . ')',
            array_merge([$status, $publishedAt, $this->now()], $ids)
        );

        return $statement->rowCount();
    }

    /** @param list<int> $ids */
    public function setFeatured(array $ids, bool $featured): int
    {
        $ids = $this->intIds($ids);
        if ($ids === []) {
            return 0;
        }

        return $this->db->run(
            'UPDATE posts SET featured = ?, updated_at = ? WHERE id IN (' . $this->placeholders($ids) . ')',
            array_merge([$featured ? 1 : 0, $this->now()], $ids)
        )->rowCount();
    }

    public function incrementViews(int $id, ?string $date = null): void
    {
        $this->db->run('UPDATE posts SET views = views + 1 WHERE id = ?', [$id]);

        $day = $date ?? date('Y-m-d');
        $existing = $this->db->first(
            'SELECT views FROM post_views WHERE post_id = ? AND view_date = ?',
            [$id, $day]
        );

        if ($existing === null) {
            $this->db->insert('post_views', ['post_id' => $id, 'view_date' => $day, 'views' => 1]);

            return;
        }

        $this->db->run(
            'UPDATE post_views SET views = views + 1 WHERE post_id = ? AND view_date = ?',
            [$id, $day]
        );
    }

    /** @return array{previous: ?array<string, mixed>, next: ?array<string, mixed>} */
    public function adjacent(array $post): array
    {
        $publishedAt = (string) ($post['published_at'] ?? $post['created_at']);

        $previous = $this->db->first(
            self::SELECT . " WHERE p.status = 'published' AND p.published_at < ? ORDER BY p.published_at DESC LIMIT 1",
            [$publishedAt]
        );
        $next = $this->db->first(
            self::SELECT . " WHERE p.status = 'published' AND p.published_at > ? ORDER BY p.published_at ASC LIMIT 1",
            [$publishedAt]
        );

        return ['previous' => $previous, 'next' => $next];
    }

    /** @return list<array<string, mixed>> */
    public function related(array $post, int $limit = 3): array
    {
        $tagIds = $this->tagIdsFor((int) $post['id']);
        $params = [];
        $tagCondition = '';

        if ($tagIds !== []) {
            $tagCondition = 'OR EXISTS (SELECT 1 FROM post_tags pt WHERE pt.post_id = p.id AND pt.tag_id IN ('
                . $this->placeholders($tagIds) . '))';
            $params = array_merge($params, $tagIds);
        }

        $sql = self::SELECT . " WHERE p.status = 'published' AND p.id <> ?
                AND (p.category_id = ? {$tagCondition})
                ORDER BY p.published_at DESC LIMIT " . max(1, min($limit, 12));

        return $this->db->all($sql, array_merge([(int) $post['id'], (int) ($post['category_id'] ?? 0)], $params));
    }

    /** @return list<array<string, mixed>> */
    public function popular(int $limit = 5, int $days = 30): array
    {
        $since = (new \DateTimeImmutable("-{$days} days"))->format('Y-m-d 00:00:00');

        return $this->db->all(
            self::SELECT . " WHERE p.status = 'published' AND p.published_at >= ?
             ORDER BY p.views DESC, p.published_at DESC LIMIT " . max(1, min($limit, 20)),
            [$since]
        );
    }

    /** @return list<array<string, mixed>> */
    public function featured(int $limit = 3): array
    {
        return $this->db->all(
            self::SELECT . " WHERE p.status = 'published' AND p.featured = 1
             ORDER BY p.published_at DESC LIMIT " . max(1, min($limit, 12))
        );
    }

    /** @return list<array<string, mixed>> Revisions used by the dashboard activity feed. */
    public function latestForAdmin(int $limit = 6, ?int $userId = null): array
    {
        $sql = self::SELECT;
        $params = [];
        if ($userId !== null) {
            $sql .= ' WHERE p.user_id = ?';
            $params[] = $userId;
        }

        return $this->db->all($sql . ' ORDER BY p.updated_at DESC LIMIT ' . max(1, min($limit, 30)), $params);
    }

    /**
     * Scheduled posts whose time has come — used by bin/cron.php.
     *
     * @return list<array<string, mixed>>
     */
    public function dueForPublishing(): array
    {
        return $this->db->all(
            "SELECT id, title, slug FROM posts
             WHERE status = 'scheduled' AND published_at IS NOT NULL AND published_at <= ?
             ORDER BY published_at ASC",
            [$this->now()]
        );
    }

    /** @return array<string, int> */
    public function counts(?int $userId = null): array
    {
        $where = $userId === null ? '' : ' WHERE user_id = ' . (int) $userId;

        return [
            'total' => (int) $this->db->scalar('SELECT COUNT(*) FROM posts' . $where),
            'published' => (int) $this->db->scalar("SELECT COUNT(*) FROM posts WHERE status = 'published'" . ($userId !== null ? ' AND user_id = ' . (int) $userId : '')),
            'draft' => (int) $this->db->scalar("SELECT COUNT(*) FROM posts WHERE status = 'draft'" . ($userId !== null ? ' AND user_id = ' . (int) $userId : '')),
            'scheduled' => (int) $this->db->scalar("SELECT COUNT(*) FROM posts WHERE status = 'scheduled'" . ($userId !== null ? ' AND user_id = ' . (int) $userId : '')),
            'featured' => (int) $this->db->scalar('SELECT COUNT(*) FROM posts WHERE featured = 1' . ($userId !== null ? ' AND user_id = ' . (int) $userId : '')),
            'views' => (int) $this->db->scalar('SELECT COALESCE(SUM(views), 0) FROM posts' . $where),
            'published_this_month' => (int) $this->db->scalar(
                "SELECT COUNT(*) FROM posts WHERE status = 'published' AND published_at >= ?",
                [(new \DateTimeImmutable('first day of this month'))->format('Y-m-d 00:00:00')]
            ),
        ];
    }

    /**
     * Daily view totals for the dashboard chart.
     *
     * @return array<string, int> date => views
     */
    public function viewsSeries(int $days = 14): array
    {
        $start = (new \DateTimeImmutable("-" . ($days - 1) . " days"))->format('Y-m-d');
        $rows = $this->db->all(
            'SELECT view_date, SUM(views) AS total FROM post_views WHERE view_date >= ? GROUP BY view_date',
            [$start]
        );

        $series = [];
        $byDate = [];
        foreach ($rows as $row) {
            $byDate[(string) $row['view_date']] = (int) $row['total'];
        }

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = (new \DateTimeImmutable("-{$i} days"))->format('Y-m-d');
            $series[$date] = $byDate[$date] ?? 0;
        }

        return $series;
    }

    /**
     * Post counts grouped by month for the archive widget.
     *
     * @return list<array{year: int, month: int, total: int}>
     */
    public function archive(): array
    {
        $rows = $this->db->all(
            "SELECT " . $this->yearExpression() . " AS yr, " . $this->monthExpression() . " AS mo, COUNT(*) AS total
             FROM posts WHERE status = 'published' AND published_at IS NOT NULL
             GROUP BY yr, mo ORDER BY yr DESC, mo DESC LIMIT 24"
        );

        return array_map(static fn (array $row): array => [
            'year' => (int) $row['yr'],
            'month' => (int) $row['mo'],
            'total' => (int) $row['total'],
        ], $rows);
    }

    // ---------------------------------------------------------------- tags

    /** @param list<int> $tagIds */
    public function syncTags(int $postId, array $tagIds): void
    {
        $tagIds = $this->intIds($tagIds);
        $this->db->delete('post_tags', 'post_id = ?', [$postId]);

        foreach ($tagIds as $tagId) {
            $this->db->insert('post_tags', ['post_id' => $postId, 'tag_id' => $tagId]);
        }

        (new TagRepository($this->db))->refreshUsageCounts();
    }

    /** @return list<int> */
    public function tagIdsFor(int $postId): array
    {
        $rows = $this->db->all('SELECT tag_id FROM post_tags WHERE post_id = ?', [$postId]);

        return array_map(static fn (array $row): int => (int) $row['tag_id'], $rows);
    }

    /** @return list<array<string, mixed>> */
    public function tagsFor(int $postId): array
    {
        return $this->db->all(
            'SELECT t.id, t.name, t.slug FROM tags t
             JOIN post_tags pt ON pt.tag_id = t.id
             WHERE pt.post_id = ? ORDER BY t.name ASC',
            [$postId]
        );
    }

    /**
     * Tags for many posts at once (avoids N+1 queries on archive pages).
     *
     * @param list<int> $postIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function tagsForMany(array $postIds): array
    {
        $postIds = $this->intIds($postIds);
        if ($postIds === []) {
            return [];
        }

        $rows = $this->db->all(
            'SELECT pt.post_id, t.id, t.name, t.slug FROM post_tags pt
             JOIN tags t ON t.id = pt.tag_id
             WHERE pt.post_id IN (' . $this->placeholders($postIds) . ')
             ORDER BY t.name ASC',
            $postIds
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['post_id']][] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
            ];
        }

        return $grouped;
    }

    // ----------------------------------------------------------- revisions

    /** @param array<string, mixed> $post */
    public function addRevision(array $post, ?int $userId, int $keep = 15): void
    {
        $this->db->insert('post_revisions', [
            'post_id' => (int) $post['id'],
            'user_id' => $userId,
            'title' => (string) $post['title'],
            'content' => (string) ($post['content'] ?? ''),
            'status' => (string) ($post['status'] ?? 'draft'),
            'created_at' => $this->now(),
        ]);

        $this->pruneRevisions((int) $post['id'], $keep);
    }

    /** @return list<array<string, mixed>> */
    public function revisions(int $postId, int $limit = 15): array
    {
        return $this->db->all(
            'SELECT r.*, u.name AS author_name FROM post_revisions r
             LEFT JOIN users u ON u.id = r.user_id
             WHERE r.post_id = ? ORDER BY r.created_at DESC, r.id DESC LIMIT ' . max(1, $limit),
            [$postId]
        );
    }

    /** @return array<string, mixed>|null */
    public function findRevision(int $id, int $postId): ?array
    {
        return $this->db->first('SELECT * FROM post_revisions WHERE id = ? AND post_id = ?', [$id, $postId]);
    }

    public function pruneRevisions(int $postId, int $keep = 15): void
    {
        $ids = array_map(
            static fn (array $row): int => (int) $row['id'],
            $this->db->all(
                'SELECT id FROM post_revisions WHERE post_id = ? ORDER BY created_at DESC, id DESC',
                [$postId]
            )
        );

        $stale = array_slice($ids, max(0, $keep));
        if ($stale !== []) {
            $this->db->delete('post_revisions', 'id IN (' . $this->placeholders($stale) . ')', $stale);
        }
    }

    private function yearExpression(): string
    {
        return $this->db->config('driver') === 'sqlite' ? "strftime('%Y', published_at)" : 'YEAR(published_at)';
    }

    private function monthExpression(): string
    {
        return $this->db->config('driver') === 'sqlite' ? "strftime('%m', published_at)" : 'MONTH(published_at)';
    }

    private function yearCondition(): string
    {
        return $this->db->config('driver') === 'sqlite'
            ? "strftime('%Y', p.published_at) = ?"
            : 'YEAR(p.published_at) = ?';
    }

    private function monthCondition(): string
    {
        return $this->db->config('driver') === 'sqlite'
            ? "strftime('%Y-%m', p.published_at) = ?"
            : "DATE_FORMAT(p.published_at, '%Y-%m') = ?";
    }
}
