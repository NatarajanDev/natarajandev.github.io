<?php

declare(strict_types=1);

namespace Cms\Repository;

/**
 * Contact-form submissions ("inbox" in the admin panel).
 */
final class MessageRepository extends Repository
{
    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['status'])) {
            $conditions[] = 'status = ?';
            $params[] = (string) $filters['status'];
        }
        if (!empty($filters['search'])) {
            $conditions[] = '(LOWER(name) LIKE ? OR LOWER(email) LIKE ? OR LOWER(subject) LIKE ? OR LOWER(body) LIKE ?)';
            $term = '%' . mb_strtolower(trim((string) $filters['search'])) . '%';
            $params = array_merge($params, [$term, $term, $term, $term]);
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        return $this->paginateQuery(
            'SELECT * FROM messages' . $where . ' ORDER BY created_at DESC, id DESC',
            'SELECT COUNT(*) FROM messages' . $where,
            $params,
            $page,
            $perPage
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM messages WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        return $this->db->insert('messages', [
            'name' => (string) $data['name'],
            'email' => (string) $data['email'],
            'subject' => (string) $data['subject'],
            'body' => (string) $data['body'],
            'status' => 'new',
            'ip' => $data['ip'] ?? null,
            'created_at' => $this->now(),
        ]);
    }

    public function setStatus(int $id, string $status): void
    {
        $this->db->update('messages', ['status' => $status], 'id', $id);
    }

    public function delete(int $id): void
    {
        $this->db->delete('messages', 'id = ?', [$id]);
    }

    public function unreadCount(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM messages WHERE status = ?', ['new']);
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        return [
            'total' => (int) $this->db->scalar('SELECT COUNT(*) FROM messages'),
            'new' => $this->unreadCount(),
            'read' => (int) $this->db->scalar('SELECT COUNT(*) FROM messages WHERE status = ?', ['read']),
            'archived' => (int) $this->db->scalar('SELECT COUNT(*) FROM messages WHERE status = ?', ['archived']),
        ];
    }
}
