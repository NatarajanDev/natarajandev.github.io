<?php

declare(strict_types=1);

namespace Cms\Service;

use Cms\App;
use Cms\Repository\AuditRepository;
use Cms\Support\Request;

/**
 * Convenience wrapper that stamps audit entries with the current user + IP.
 */
final class ActivityLog
{
    private AuditRepository $repository;

    public function __construct(private App $app)
    {
        $this->repository = new AuditRepository($app->db());
    }

    /** @param array<string, mixed> $meta */
    public function record(string $action, ?string $entity = null, ?int $entityId = null, array $meta = []): void
    {
        $this->repository->record(
            $this->app->auth()->id(),
            $action,
            $entity,
            $entityId,
            $meta,
            PHP_SAPI === 'cli' ? 'cli' : Request::capture()->ip()
        );
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 8): array
    {
        return $this->repository->recent($limit);
    }

    public function repository(): AuditRepository
    {
        return $this->repository;
    }
}
