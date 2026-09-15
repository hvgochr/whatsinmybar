<?php

namespace App\Entity;

use App\Enum\CommentModerationStatus;
use App\Repository\CommentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommentRepository::class)]
#[ORM\Index(name: 'idx_comment_recipe', columns: ['recipe_id'])]
#[ORM\Index(name: 'idx_comment_author', columns: ['author_id'])]
#[ORM\Index(name: 'idx_comment_parent', columns: ['parent_id'])]
#[ORM\Index(name: 'idx_comment_recipe_created', columns: ['recipe_id', 'created_at', 'id'])]
#[ORM\Index(name: 'idx_comment_moderation_status', columns: ['moderation_status'])]
#[ORM\HasLifecycleCallbacks]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Recipe $recipe;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $author;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'replies')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?self $parent = null;

    #[ORM\Column(options: ['default' => 1])]
    private int $depth = 1;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $replies;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 2000)]
    private string $message = '';

    #[ORM\Column(length: 30, enumType: CommentModerationStatus::class)]
    private CommentModerationStatus $moderationStatus = CommentModerationStatus::Visible;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct(Recipe $recipe, User $author)
    {
        $this->recipe = $recipe;
        $this->author = $author;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->replies = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecipe(): Recipe
    {
        return $this->recipe;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): void
    {
        if (null !== $parent && $parent->getRecipe() !== $this->recipe) {
            throw new \InvalidArgumentException('Parent comment must belong to the same recipe.');
        }

        $this->parent = $parent;
        $this->depth = null === $parent ? 1 : $parent->getDepth() + 1;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    /**
     * @return Collection<int, self>
     */
    public function getReplies(): Collection
    {
        return $this->replies;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getPublicMessage(): ?string
    {
        if (null !== $this->deletedAt || !$this->moderationStatus->isPubliclyReadable()) {
            return null;
        }

        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = trim($message);
    }

    public function getModerationStatus(): CommentModerationStatus
    {
        return $this->moderationStatus;
    }

    public function setModerationStatus(CommentModerationStatus $moderationStatus): void
    {
        $this->moderationStatus = $moderationStatus;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function softDelete(): void
    {
        $this->deletedAt = new \DateTimeImmutable();
        $this->moderationStatus = CommentModerationStatus::Removed;
    }

    public function isAuthor(User $user): bool
    {
        if (null === $this->author->getId() || null === $user->getId()) {
            return $this->author === $user;
        }

        return $this->author->getId() === $user->getId();
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
