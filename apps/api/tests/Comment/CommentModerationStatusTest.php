<?php

namespace App\Tests\Comment;

use App\Enum\CommentModerationStatus;
use PHPUnit\Framework\TestCase;

final class CommentModerationStatusTest extends TestCase
{
    public function testModerationStatusesMatchSpecification(): void
    {
        self::assertSame([
            'visible',
            'hidden',
            'pending_review',
            'removed',
        ], array_map(static fn (CommentModerationStatus $status): string => $status->value, CommentModerationStatus::cases()));
    }
}
