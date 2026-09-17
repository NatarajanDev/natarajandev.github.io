<?php

declare(strict_types=1);

namespace Cms\Repository;

/**
 * Categories with optional parent/child nesting and per-category post counts.
 */
final class CategoryRepository extends Repository
{
    /** @return list<array<string, mixed>> Flat list ordered for display. */
    public function all(bool $withCounts = true): array
    {
        $sql = 'SELECT c.*, p.name AS parent_name';
        if ($withCounts) {
            $sql .= ", (SELECT COUNT(*) FROM posts po WHERE po.category_id = c.id) AS post_count,
                      (SELECT COUNT(*) FROM posts po WHERE po.category_id = c.id AND po.status = 'published') AS published_count";
        }
        $sql .= ' FROM categories c LEFT JOIN categories p ON p.id = c.parent_id
                  ORDER BY c.sort_order ASC, c.name ASC';

        return $this->db->all($sql);
    }

    /**
     * Nested tree: parents with a "children" key.
     *
     * @return list<array<string, mixed>>
     */
    public function tree(): array
    {
        $rows = $this->all();
        $byId = [];
        foreach ($rows as $row) {
            $row['children'] = [];
            $byId[(int) $row['id']] = $row;
        }

        $tree = [];
        foreach ($byId as $id => $row) {
            $parentId = (int) ($row['parent_id'] ?? 0);
            if ($parentId !== 0 && isset($byId[$parentId])) {
                $byId[$parentId]['children'][] = $id;
            } else {
                $tree[] = $id;
            }
        }

        $build = static function (int $id) use (&$build, &$byId): array {
            $node = $byId[$id];
            $node['children'] = array_map($build, $node['children']);

            return $node;
        };

        return array_map($build, $tree);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM categories WHERE id = ?', [$id]);
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->first('SELECT * FROM categories WHERE slug = ?', [$slug]);
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM categories WHERE slug = ?';
        $params = [$slug];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        return (int) $this->db->scalar($sql, $params) > 0;
    }

    public function uniqueSlug(string $name, ?int $exceptId = null): string
    {
        $base = str_slug($name);
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

        return $this->db->insert('categories', [
            'parent_id' => $data['parent_id'] ?? null,
            'name' => (string) $data['name'],
            'slug' => (string) $data['slug'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $fields = array_intersect_key($data, array_flip(['parent_id', 'name', 'slug', 'description', 'color', 'sort_order']));
        $fields['updated_at'] = $this->now();

        $this->db->update('categories', $fields, 'id', $id);
    }

    public function delete(int $id): void
    {
        // Posts keep existing but lose the category; children move up a level.
        $this->db->run('UPDATE posts SET category_id = NULL WHERE category_id = ?', [$id]);
        $this->db->run('UPDATE categories SET parent_id = NULL WHERE parent_id = ?', [$id]);
        $this->db->delete('categories', 'id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function popular(int $limit = 6): array
    {
        return $this->db->all(
            "SELECT c.id, c.name, c.slug, c.color, c.description,
                    (SELECT COUNT(*) FROM posts p WHERE p.category_id = c.id AND p.status = 'published') AS published_count
             FROM categories c
             ORDER BY published_count DESC, c.name ASC LIMIT " . max(1, min($limit, 20))
        );
    }

    public function count(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM categories');
    }
}
