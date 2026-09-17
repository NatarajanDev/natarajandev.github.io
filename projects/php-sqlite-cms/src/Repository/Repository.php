<?php

declare(strict_types=1);

namespace Cms\Repository;

use Cms\Database\Connection;

/**
 * Base repository: shared pagination + small query helpers.
 *
 * All SQL lives in repositories so controllers stay free of database details.
 */
abstract class Repository
{
    public function __construct(protected Connection $db)
    {
    }

    /**
     * Run a SELECT together with its COUNT(*) twin.
     *
     * @param array<string|int, mixed> $params
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    protected function paginateQuery(string $selectSql, string $countSql, array $params, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 200));
        $offset = ($page - 1) * $perPage;

        $total = (int) $this->db->scalar($countSql, $params);
        $items = $this->db->all($selectSql . sprintf(' LIMIT %d OFFSET %d', $perPage, $offset), $params);

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Build a "WHERE ..." clause from non-empty filters.
     *
     * @param array<string, array{0: string, 1: mixed}> $clauses Map of filter key => [sql, value]
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function where(array $clauses, array $filters): array
    {
        $sql = [];
        $params = [];

        foreach ($clauses as $key => [$condition, $value]) {
            if ($value === null || $value === '' || $value === false) {
                continue;
            }
            $sql[] = $condition;
            $params[] = $value;
        }

        return [$sql === [] ? '' : ' WHERE ' . implode(' AND ', $sql), $params];
    }

    /** @param list<int> $ids */
    protected function placeholders(array $ids): string
    {
        return implode(', ', array_fill(0, count($ids), '?'));
    }

    /** @param list<int> $ids @return list<int> */
    protected function intIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }

    protected function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s');
    }
}
