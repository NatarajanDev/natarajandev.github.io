<?php

declare(strict_types=1);

namespace Cms\Repository;

/**
 * Uploaded media library entries.
 */
final class MediaRepository extends Repository
{
    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 24): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['type'])) {
            $conditions[] = 'm.mime_type LIKE ?';
            $params[] = (string) $filters['type'] . '%';
        }
        if (!empty($filters['search'])) {
            $conditions[] = '(LOWER(m.original_name) LIKE ? OR LOWER(m.alt_text) LIKE ?)';
            $term = '%' . mb_strtolower(trim((string) $filters['search'])) . '%';
            $params = array_merge($params, [$term, $term]);
        }
        if (!empty($filters['user_id'])) {
            $conditions[] = 'm.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        return $this->paginateQuery(
            'SELECT m.*, u.name AS author_name FROM media m LEFT JOIN users u ON u.id = m.user_id' . $where . ' ORDER BY m.created_at DESC, m.id DESC',
            'SELECT COUNT(*) FROM media m' . $where,
            $params,
            $page,
            $perPage
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM media WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        return $this->db->insert('media', [
            'user_id' => (int) $data['user_id'],
            'filename' => (string) $data['filename'],
            'original_name' => (string) $data['original_name'],
            'path' => (string) $data['path'],
            'thumb_path' => $data['thumb_path'] ?? null,
            'mime_type' => (string) $data['mime_type'],
            'size' => (int) $data['size'],
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'alt_text' => $data['alt_text'] ?? null,
            'caption' => $data['caption'] ?? null,
            'created_at' => $this->now(),
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $fields = array_intersect_key($data, array_flip(['alt_text', 'caption', 'original_name']));
        if ($fields !== []) {
            $this->db->update('media', $fields, 'id', $id);
        }
    }

    public function delete(int $id): void
    {
        $this->db->delete('media', 'id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 12): array
    {
        return $this->db->all(
            'SELECT * FROM media ORDER BY created_at DESC, id DESC LIMIT ' . max(1, min($limit, 50))
        );
    }

    /** @return array{total: int, bytes: int, images: int, documents: int} */
    public function stats(): array
    {
        return [
            'total' => (int) $this->db->scalar('SELECT COUNT(*) FROM media'),
            'bytes' => (int) $this->db->scalar('SELECT COALESCE(SUM(size), 0) FROM media'),
            'images' => (int) $this->db->scalar("SELECT COUNT(*) FROM media WHERE mime_type LIKE 'image/%'"),
            'documents' => (int) $this->db->scalar("SELECT COUNT(*) FROM media WHERE mime_type NOT LIKE 'image/%'"),
        ];
    }
}
