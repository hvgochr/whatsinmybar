<?php

namespace App\Entity;

use App\Enum\ReportReason;
use App\Enum\ReportStatus;
use App\Enum\ReportTargetType;
use App\Repository\ReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReportRepository::class)]
#[ORM\Index(name: 'idx_report_status', columns: ['status'])]
#[ORM\Index(name: 'idx_report_target', columns: ['target_type', 'target_id'])]
#[ORM\Index(name: 'idx_report_reporter', columns: ['reporter_id'])]
#[ORM\HasLifecycleCallbacks]
class Report
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $reporter;

    #[ORM\Column(length: 30, enumType: ReportTargetType::class)]
    private ReportTargetType $targetType;

    #[ORM\Column]
    #[Assert\Positive]
    private int $targetId;

    #[ORM\Column(length: 50, enumType: ReportReason::class)]
    private ReportReason $reason;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $message = null;

    #[ORM\Column(length: 30, enumType: ReportStatus::class)]
    private ReportStatus $status = ReportStatus::Open;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $reviewedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $reporter, ReportTargetType $targetType, int $targetId, ReportReason $reason)
    {
        $this->reporter = $reporter;
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        $this->reason = $reason;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReporter(): User
    {
        return $this->reporter;
    }

    public function getTargetType(): ReportTargetType
    {
        return $this->targetType;
    }

    public function getTargetId(): int
    {
        return $this->targetId;
    }

    public function getReason(): ReportReason
    {
        return $this->reason;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): void
    {
        $message = null === $message ? null : trim($message);
        $this->message = '' === $message ? null : $message;
    }

    public function getStatus(): ReportStatus
    {
        return $this->status;
    }

    public function review(ReportStatus $status, User $reviewedBy): void
    {
        $this->status = $status;
        $this->reviewedBy = $reviewedBy;
        $this->reviewedAt = new \DateTimeImmutable();
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
