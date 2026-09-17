<?php

declare(strict_types=1);

namespace Cms\Repository;

/**
 * Static pages, optionally nested one level deep for menus.
 */
final class PageRepository extends Repository
{
    private const SELECT = 'SELECT pg.*, u.name AS author_name
        FROM pages pg LEFT JOIN users u ON u.id = pg.user_id';

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['status'])) {
            $conditions[] = 'pg.status = ?';
            $params[] = (string) $filters['status'];
        }
        if (!empty($filters['search'])) {
            $conditions[] = '(LOWER(pg.title) LIKE ? OR LOWER(pg.content) LIKE ?)';
            $term = '%' . mb_strtolower(trim((string) $filters['search'])) . '%';
            $params = array_merge($params, [$term, $term]);
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        return $this->paginateQuery(
            self::SELECT . $where . ' ORDER BY pg.sort_order ASC, pg.title ASC',
            'SELECT COUNT(*) FROM pages pg' . $where,
            $params,
            $page,
            $perPage
        );
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->all(self::SELECT . ' ORDER BY pg.sort_order ASC, pg.title ASC');
    }

    /** @return list<array<string, mixed>> */
    public function menu(): array
    {
        return $this->db->all(
            "SELECT id, title, slug, parent_id, sort_order FROM pages
             WHERE status = 'published' AND show_in_menu = 1
             ORDER BY sort_order ASC, title ASC"
        );
    }

    /** @return list<array<string, mixed>> */
    public function published(): array
    {
        return $this->db->all(self::SELECT . " WHERE pg.status = 'published' ORDER BY pg.title ASC");
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->first(self::SELECT . ' WHERE pg.id = ?', [$id]);
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug, bool $publishedOnly = true): ?array
    {
        $sql = self::SELECT . ' WHERE pg.slug = ?';
        if ($publishedOnly) {
            $sql .= " AND pg.status = 'published'";
        }

        return $this->db->first($sql, [$slug]);
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM pages WHERE slug = ?';
        $params = [$slug];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        return (int) $this->db->scalar($sql, $params) > 0;
    }

    public function uniqueSlug(string $title, ?int $exceptId = null): string
    {
        $base = str_slug($title);
        $slug = $base;
        $suffix = 2;
        while ($this->slugExists($slug, $exceptId)) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $now = $this->now();

        return $this->db->insert('pages', [
            'user_id' => (int) $data['user_id'],
            'parent_id' => $data['parent_id'] ?? null,
            'title' => (string) $data['title'],
            'slug' => (string) $data['slug'],
            'content' => (string) ($data['content'] ?? ''),
            'content_format' => (string) ($data['content_format'] ?? 'markdown'),
            'template' => (string) ($data['template'] ?? 'default'),
            'status' => (string) ($data['status'] ?? 'published'),
            'show_in_menu' => !empty($data['show_in_menu']) ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $fields = array_intersect_key($data, array_flip([
            'user_id', 'parent_id', 'title', 'slug', 'content', 'content_format', 'template',
            'status', 'show_in_menu', 'sort_order', 'seo_title', 'seo_description',
        ]));

        if (array_key_exists('show_in_menu', $fields)) {
            $fields['show_in_menu'] = (int) (bool) $fields['show_in_menu'];
        }
        $fields['updated_at'] = $this->now();

        $this->db->update('pages', $fields, 'id', $id);
    }

    public function delete(int $id): void
    {
        // Detach children instead of orphaning them.
        $this->db->run('UPDATE pages SET parent_id = NULL WHERE parent_id = ?', [$id]);
        $this->db->delete('pages', 'id = ?', [$id]);
    }

    /** @param list<int> $ids */
    public function deleteMany(array $ids): int
    {
        $ids = $this->intIds($ids);
        if ($ids === []) {
            return 0;
        }
        $this->db->run('UPDATE pages SET parent_id = NULL WHERE parent_id IN (' . $this->placeholders($ids) . ')', $ids);

        return $this->db->delete('pages', 'id IN (' . $this->placeholders($ids) . ')', $ids);
    }

    /** @return array{total: int, published: int, drafts: int, in_menu: int} */
    public function counts(): array
    {
        return [
            'total' => (int) $this->db->scalar('SELECT COUNT(*) FROM pages'),
            'published' => (int) $this->db->scalar("SELECT COUNT(*) FROM pages WHERE status = 'published'"),
            'drafts' => (int) $this->db->scalar("SELECT COUNT(*) FROM pages WHERE status = 'draft'"),
            'in_menu' => (int) $this->db->scalar('SELECT COUNT(*) FROM pages WHERE show_in_menu = 1'),
        ];
    }
}
