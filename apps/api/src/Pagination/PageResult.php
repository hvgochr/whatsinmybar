<?php

namespace App\Pagination;

/**
 * @template T of object
 */
final readonly class PageResult
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public array $items,
        public int $totalItems,
    ) {
    }
}
