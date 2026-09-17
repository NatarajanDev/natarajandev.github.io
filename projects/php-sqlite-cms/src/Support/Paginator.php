<?php

declare(strict_types=1);

namespace Cms\Support;

/**
 * Array paginator that renders Bootstrap-free, accessible pagination links.
 */
final class Paginator
{
    private int $lastPage;

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed> $query Extra query string values preserved in links
     */
    public function __construct(
        private array $items,
        private int $total,
        private int $perPage,
        private int $currentPage,
        private string $basePath,
        private array $query = []
    ) {
        $this->perPage = max(1, $perPage);
        $this->lastPage = max(1, (int) ceil($this->total / $this->perPage));
        $this->currentPage = max(1, min($currentPage, $this->lastPage));
    }

    /** @return list<array<string, mixed>> */
    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return $this->lastPage;
    }

    public function hasPages(): bool
    {
        return $this->lastPage > 1;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /** "Showing 16–30 of 84" */
    public function summary(): string
    {
        if ($this->total === 0) {
            return 'No results';
        }

        $from = ($this->currentPage - 1) * $this->perPage + 1;
        $to = min($this->currentPage * $this->perPage, $this->total);

        return sprintf('Showing %d–%d of %d', $from, $to, $this->total);
    }

    public function url(int $page): string
    {
        $query = array_filter(
            array_merge($this->query, ['page' => $page]),
            static fn (mixed $value): bool => $value !== null && $value !== ''
        );

        return url($this->basePath) . ($query === [] ? '' : '?' . http_build_query($query));
    }

    /** @return list<int|string> Page numbers with "…" gaps */
    public function window(int $radius = 2): array
    {
        $pages = [];
        for ($page = 1; $page <= $this->lastPage; $page++) {
            $inWindow = abs($page - $this->currentPage) <= $radius;
            $isEdge = $page === 1 || $page === $this->lastPage;
            if ($inWindow || $isEdge) {
                $pages[] = $page;
            } elseif (end($pages) !== '…') {
                $pages[] = '…';
            }
        }

        return $pages;
    }

    public function links(string $class = 'pager'): string
    {
        if (!$this->hasPages()) {
            return '';
        }

        $html = '<nav class="' . e($class) . '" aria-label="Pagination">';
        $html .= $this->currentPage > 1
            ? '<a class="pager__link" rel="prev" href="' . e($this->url($this->currentPage - 1)) . '">&larr; Prev</a>'
            : '<span class="pager__link pager__link--disabled">&larr; Prev</span>';

        foreach ($this->window() as $page) {
            if ($page === '…') {
                $html .= '<span class="pager__gap">…</span>';
                continue;
            }
            $isCurrent = $page === $this->currentPage;
            $html .= $isCurrent
                ? '<span class="pager__link pager__link--current" aria-current="page">' . $page . '</span>'
                : '<a class="pager__link" href="' . e($this->url((int) $page)) . '">' . $page . '</a>';
        }

        $html .= $this->currentPage < $this->lastPage
            ? '<a class="pager__link" rel="next" href="' . e($this->url($this->currentPage + 1)) . '">Next &rarr;</a>'
            : '<span class="pager__link pager__link--disabled">Next &rarr;</span>';

        return $html . '</nav>';
    }
}
