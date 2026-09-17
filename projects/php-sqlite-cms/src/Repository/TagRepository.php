<?php

declare(strict_types=1);

namespace Cms\Repository;

/**
 * Tags, including "find or create" used by the post editor's comma field.
 */
final class TagRepository extends Repository
{
    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->all(
            'SELECT t.*, (SELECT COUNT(*) FROM post_tags pt WHERE pt.tag_id = t.id) AS post_count
             FROM tags t ORDER BY t.name ASC'
        );
    }

    /** @return list<array<string, mixed>> */
    public function popular(int $limit = 20): array
    {
        return $this->db->all(
            'SELECT t.id, t.name, t.slug, COUNT(pt.post_id) AS post_count
             FROM tags t
             LEFT JOIN post_tags pt ON pt.tag_id = t.id
             LEFT JOIN posts p ON p.id = pt.post_id AND p.status = \'published\'
             GROUP BY t.id, t.name, t.slug
             HAVING COUNT(p.id) > 0
             ORDER BY post_count DESC, t.name ASC
             LIMIT ' . max(1, min($limit, 60))
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM tags WHERE id = ?', [$id]);
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->first('SELECT * FROM tags WHERE slug = ?', [$slug]);
    }

    /**
     * Resolve a list of tag names to ids, creating the missing ones.
     *
     * @param list<string> $names
     * @return list<int>
     */
    public function resolveNames(array $names): array
    {
        $ids = [];
        foreach ($names as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $slug = str_slug($name);
            $existing = $this->findBySlug($slug);
            if ($existing !== null) {
                $ids[] = (int) $existing['id'];
                continue;
            }

            $ids[] = $this->create($name);
        }

        return array_values(array_unique($ids));
    }

    public function create(string $name): int
    {
        return $this->db->insert('tags', [
            'name' => mb_substr(trim($name), 0, 90),
            'slug' => str_slug($name),
            'usage_count' => 0,
            'created_at' => $this->now(),
        ]);
    }

    public function update(int $id, string $name): void
    {
        $this->db->update('tags', ['name' => trim($name), 'slug' => str_slug($name)], 'id', $id);
    }

    public function delete(int $id): void
    {
        $this->db->delete('post_tags', 'tag_id = ?', [$id]);
        $this->db->delete('tags', 'id = ?', [$id]);
    }

    public function merge(int $sourceId, int $targetId): void
    {
        if ($sourceId === $targetId) {
            return;
        }

        $postIds = array_map(
            static fn (array $row): int => (int) $row['post_id'],
            $this->db->all('SELECT post_id FROM post_tags WHERE tag_id = ?', [$sourceId])
        );

        foreach ($postIds as $postId) {
            $exists = $this->db->first('SELECT 1 AS ok FROM post_tags WHERE post_id = ? AND tag_id = ?', [$postId, $targetId]);
            if ($exists === null) {
                $this->db->insert('post_tags', ['post_id' => $postId, 'tag_id' => $targetId]);
            }
        }

        $this->delete($sourceId);
        $this->refreshUsageCounts();
    }

    public function refreshUsageCounts(): void
    {
        $this->db->exec(
            'UPDATE tags SET usage_count = (
                SELECT COUNT(*) FROM post_tags pt WHERE pt.tag_id = tags.id
             )'
        );
    }

    public function count(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM tags');
    }
}
