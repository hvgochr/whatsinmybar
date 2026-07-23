<?php

namespace App\Tests\Report;

use App\Entity\Report;
use App\Entity\User;
use App\Enum\ReportReason;
use App\Enum\ReportStatus;
use App\Enum\ReportTargetType;
use PHPUnit\Framework\TestCase;

final class ReportTest extends TestCase
{
    public function testReportDefaultsToOpenStatus(): void
    {
        $reporter = $this->user('reporter@example.com', 'reporter');
        $report = new Report($reporter, ReportTargetType::Recipe, 42, ReportReason::Spam);

        self::assertSame($reporter, $report->getReporter());
        self::assertSame(ReportStatus::Open, $report->getStatus());
        self::assertSame(ReportTargetType::Recipe, $report->getTargetType());
        self::assertSame(42, $report->getTargetId());
        self::assertSame(ReportReason::Spam, $report->getReason());
    }

    public function testMessageIsTrimmedAndBlankMessageBecomesNull(): void
    {
        $report = new Report($this->user('reporter@example.com', 'reporter'), ReportTargetType::Comment, 1, ReportReason::Other);

        $report->setMessage('  Needs review.  ');
        self::assertSame('Needs review.', $report->getMessage());

        $report->setMessage('   ');
        self::assertNull($report->getMessage());
    }

    public function testReviewStoresReviewerAndReviewedAt(): void
    {
        $reviewer = $this->user('admin@example.com', 'admin');
        $report = new Report($this->user('reporter@example.com', 'reporter'), ReportTargetType::User, 1, ReportReason::Abuse);

        $report->review(ReportStatus::Reviewing, $reviewer);

        self::assertSame(ReportStatus::Reviewing, $report->getStatus());
        self::assertSame($reviewer, $report->getReviewedBy());
        self::assertInstanceOf(\DateTimeImmutable::class, $report->getReviewedAt());
    }

    private function user(string $email, string $username): User
    {
        return new User($email, $username, new \DateTimeImmutable('1990-01-01'));
    }
}
