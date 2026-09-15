<?php

namespace App\Pagination;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class PageRequest
{
    public const DEFAULT_PAGE_SIZE = 20;
    public const MAX_PAGE_SIZE = 100;

    private function __construct(
        public int $page,
        public int $pageSize,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $pageSize = self::positiveInteger(
            $request->query->get('pageSize', (string) self::DEFAULT_PAGE_SIZE),
            'pageSize',
            self::MAX_PAGE_SIZE,
        );
        $page = self::positiveInteger(
            $request->query->get('page', '1'),
            'page',
            intdiv(PHP_INT_MAX, $pageSize),
        );

        return new self($page, $pageSize);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->pageSize;
    }

    public function atPage(int $page): self
    {
        if ($page < 1) {
            throw new \LogicException('A page number must be positive.');
        }

        return new self($page, $this->pageSize);
    }

    public function totalPages(int $totalItems): int
    {
        return (int) ceil($totalItems / $this->pageSize);
    }

    private static function positiveInteger(mixed $value, string $name, int $maximum): int
    {
        if (!is_string($value) || 1 !== preg_match('/^[1-9]\d*$/', $value)) {
            throw new BadRequestHttpException(sprintf('%s must be a positive integer.', $name));
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
                'max_range' => $maximum,
            ],
        ]);

        if (false === $integer) {
            if ('pageSize' === $name) {
                throw new BadRequestHttpException(sprintf('pageSize must be between 1 and %d.', self::MAX_PAGE_SIZE));
            }

            throw new BadRequestHttpException('page is too large.');
        }

        return $integer;
    }
}
