<?php

declare(strict_types=1);

namespace Cms\Repository;

/**
 * Threaded comments with moderation states (pending / approved / spam / trash).
 */
final class CommentRepository extends Repository
{
    private const SELECT = 'SELECT cm.*, p.title AS post_title, p.slug AS post_slug
        FROM comments cm LEFT JOIN posts p ON p.id = cm.post_id';

    public const STATUSES = ['pending', 'approved', 'spam', 'trash'];

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['status'])) {
            $conditions[] = 'cm.status = ?';
            $params[] = (string) $filters['status'];
        }
        if (!empty($filters['post_id'])) {
            $conditions[] = 'cm.post_id = ?';
            $params[] = (int) $filters['post_id'];
        }
        if (!empty($filters['search'])) {
            $conditions[] = '(LOWER(cm.body) LIKE ? OR LOWER(cm.author_name) LIKE ? OR LOWER(cm.author_email) LIKE ?)';
            $term = '%' . mb_strtolower(trim((string) $filters['search'])) . '%';
            $params = array_merge($params, [$term, $term, $term]);
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        return $this->paginateQuery(
            self::SELECT . $where . ' ORDER BY cm.created_at DESC, cm.id DESC',
            'SELECT COUNT(*) FROM comments cm' . $where,
            $params,
            $page,
            $perPage
        );
    }

    /**
     * Approved comments for a post, assembled into a reply tree.
     *
     * @return list<array<string, mixed>>
     */
    public function treeForPost(int $postId, string $status = 'approved'): array
    {
        $rows = $this->db->all(
            'SELECT id, parent_id, author_name, author_url, body, created_at
             FROM comments WHERE post_id = ? AND status = ?
             ORDER BY created_at ASC',
            [$postId, $status]
        );

        $byId = [];
        foreach ($rows as $row) {
            $row['children'] = [];
            $byId[(int) $row['id']] = $row;
        }

        $roots = [];
        foreach ($byId as $id => $row) {
            $parentId = (int) ($row['parent_id'] ?? 0);
            if ($parentId !== 0 && isset($byId[$parentId])) {
                $byId[$parentId]['children'][] = $id;
            } else {
                $roots[] = $id;
            }
        }

        $build = static function (int $id, int $depth = 0) use (&$build, &$byId): array {
            $node = $byId[$id];
            $node['depth'] = $depth;
            $node['children'] = array_map(
                static fn (int $childId): array => $build($childId, $depth + 1),
                $node['children']
            );

            return $node;
        };

        return array_map(static fn (int $id): array => $build($id), $roots);
    }

    public function countForPost(int $postId, string $status = 'approved'): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM comments WHERE post_id = ? AND status = ?',
            [$postId, $status]
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->first(self::SELECT . ' WHERE cm.id = ?', [$id]);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $now = $this->now();

        return $this->db->insert('comments', [
            'post_id' => (int) $data['post_id'],
            'parent_id' => $data['parent_id'] ?? null,
            'author_name' => (string) $data['author_name'],
            'author_email' => (string) $data['author_email'],
            'author_url' => $data['author_url'] ?? null,
            'body' => trim((string) $data['body']),
            'status' => (string) ($data['status'] ?? 'pending'),
            'ip' => $data['ip'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function setStatus(int $id, string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            return;
        }

        $this->db->update('comments', ['status' => $status, 'updated_at' => $this->now()], 'id', $id);
    }

    /** @param list<int> $ids */
    public function setStatusMany(array $ids, string $status): int
    {
        $ids = $this->intIds($ids);
        if ($ids === [] || !in_array($status, self::STATUSES, true)) {
            return 0;
        }

        return $this->db->run(
            'UPDATE comments SET status = ?, updated_at = ? WHERE id IN (' . $this->placeholders($ids) . ')',
            array_merge([$status, $this->now()], $ids)
        )->rowCount();
    }

    public function delete(int $id): void
    {
        $this->db->run('UPDATE comments SET parent_id = NULL WHERE parent_id = ?', [$id]);
        $this->db->delete('comments', 'id = ?', [$id]);
    }

    /** @param list<int> $ids */
    public function deleteMany(array $ids): int
    {
        $ids = $this->intIds($ids);
        if ($ids === []) {
            return 0;
        }
        $this->db->run('UPDATE comments SET parent_id = NULL WHERE parent_id IN (' . $this->placeholders($ids) . ')', $ids);

        return $this->db->delete('comments', 'id IN (' . $this->placeholders($ids) . ')', $ids);
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        $counts = ['total' => (int) $this->db->scalar('SELECT COUNT(*) FROM comments')];
        foreach (self::STATUSES as $status) {
            $counts[$status] = (int) $this->db->scalar('SELECT COUNT(*) FROM comments WHERE status = ?', [$status]);
        }

        return $counts;
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 5, string $status = 'pending'): array
    {
        return $this->db->all(
            self::SELECT . ' WHERE cm.status = ? ORDER BY cm.created_at DESC LIMIT ' . max(1, min($limit, 25)),
            [$status]
        );
    }

    /** Simple duplicate/spam guard: same author + body on a post within N minutes. */
    public function isDuplicate(int $postId, string $email, string $body, int $minutes = 10): bool
    {
        $since = (new \DateTimeImmutable("-{$minutes} minutes"))->format('Y-m-d H:i:s');

        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM comments WHERE post_id = ? AND author_email = ? AND body = ? AND created_at >= ?',
            [$postId, $email, trim($body), $since]
        ) > 0;
    }

    public function recentCountByIp(string $ip, int $minutes = 5): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM comments WHERE ip = ? AND created_at >= ?',
            [$ip, (new \DateTimeImmutable("-{$minutes} minutes"))->format('Y-m-d H:i:s')]
        );
    }
}
