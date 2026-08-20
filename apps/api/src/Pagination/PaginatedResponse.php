<?php

namespace App\Pagination;

final class PaginatedResponse
{
    /**
     * @template T of object
     *
     * @param PageResult<T>                     $result
     * @param callable(T): array<string, mixed> $payload
     *
     * @return array{items: list<array<string, mixed>>, page: int, pageSize: int, totalItems: int, totalPages: int}
     */
    public static function from(PageResult $result, PageRequest $request, callable $payload): array
    {
        return [
            'items' => array_map($payload, $result->items),
            'page' => $request->page,
            'pageSize' => $request->pageSize,
            'totalItems' => $result->totalItems,
            'totalPages' => $request->totalPages($result->totalItems),
        ];
    }
}
