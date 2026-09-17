<?php

declare(strict_types=1);

namespace Cms\Repository;

/**
 * Admin activity trail ("who changed what").
 */
final class AuditRepository extends Repository
{
    private const SELECT = 'SELECT a.*, u.name AS user_name, u.avatar AS user_avatar
        FROM audit_log a LEFT JOIN users u ON u.id = a.user_id';

    /** @param array<string, mixed> $meta */
    public function record(?int $userId, string $action, ?string $entity = null, ?int $entityId = null, array $meta = [], ?string $ip = null): void
    {
        $this->db->insert('audit_log', [
            'user_id' => $userId,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'meta' => $meta === [] ? null : (string) json_encode($meta, JSON_UNESCAPED_UNICODE),
            'ip' => $ip,
            'created_at' => $this->now(),
        ]);
    }

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $conditions[] = 'a.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }
        if (!empty($filters['entity'])) {
            $conditions[] = 'a.entity = ?';
            $params[] = (string) $filters['entity'];
        }
        if (!empty($filters['search'])) {
            $conditions[] = '(LOWER(a.action) LIKE ? OR LOWER(a.meta) LIKE ?)';
            $term = '%' . mb_strtolower(trim((string) $filters['search'])) . '%';
            $params = array_merge($params, [$term, $term]);
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        return $this->paginateQuery(
            self::SELECT . $where . ' ORDER BY a.created_at DESC, a.id DESC',
            'SELECT COUNT(*) FROM audit_log a' . $where,
            $params,
            $page,
            $perPage
        );
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 8): array
    {
        return $this->db->all(
            self::SELECT . ' ORDER BY a.created_at DESC, a.id DESC LIMIT ' . max(1, min($limit, 50))
        );
    }

    public function prune(int $days = 90): int
    {
        return $this->db->delete(
            'audit_log',
            'created_at < ?',
            [(new \DateTimeImmutable("-{$days} days"))->format('Y-m-d H:i:s')]
        );
    }

    public function count(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM audit_log');
    }
}
