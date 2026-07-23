<?php

namespace App\Tests\Report;

use App\Enum\RecipeModerationStatus;
use App\Enum\ReportReason;
use App\Enum\ReportStatus;
use App\Enum\ReportTargetType;
use PHPUnit\Framework\TestCase;

final class ReportEnumTest extends TestCase
{
    public function testReportStatusesMatchSpecification(): void
    {
        self::assertSame(['open', 'reviewing', 'resolved', 'rejected'], array_map(
            static fn (ReportStatus $status): string => $status->value,
            ReportStatus::cases(),
        ));
    }

    public function testReportReasonsMatchSpecification(): void
    {
        self::assertSame([
            'spam',
            'abuse',
            'illegal_content',
            'wrong_alcohol_classification',
            'copyright',
            'other',
        ], array_map(static fn (ReportReason $reason): string => $reason->value, ReportReason::cases()));
    }

    public function testReportTargetsMatchSpecification(): void
    {
        self::assertSame(['recipe', 'comment', 'user'], array_map(
            static fn (ReportTargetType $targetType): string => $targetType->value,
            ReportTargetType::cases(),
        ));
    }

    public function testRecipeModerationVisibility(): void
    {
        self::assertTrue(RecipeModerationStatus::Visible->isPubliclyReadable());
        self::assertFalse(RecipeModerationStatus::Hidden->isPubliclyReadable());
        self::assertFalse(RecipeModerationStatus::PendingReview->isPubliclyReadable());
        self::assertFalse(RecipeModerationStatus::Removed->isPubliclyReadable());
    }
}
