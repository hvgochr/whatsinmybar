<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\IngredientRepository;
use App\State\IngredientProcessor;
use App\Util\SlugNormalizer;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: IngredientRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_ingredient_slug', columns: ['slug'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'])]
#[ApiResource(
    operations: [
        new GetCollection(paginationClientEnabled: true),
        new Post(security: "is_granted('ROLE_ADMIN')", processor: IngredientProcessor::class),
        new Get(),
        new Patch(security: "is_granted('ROLE_ADMIN')", processor: IngredientProcessor::class),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['ingredient:read']],
    denormalizationContext: ['groups' => ['ingredient:write']],
)]
class Ingredient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiProperty(identifier: false)]
    #[Groups(['ingredient:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Groups(['ingredient:read', 'ingredient:write'])]
    private string $name = '';

    #[ORM\Column(length: 160)]
    #[ApiProperty(identifier: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/')]
    #[Groups(['ingredient:read', 'ingredient:write'])]
    private string $slug = '';

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['ingredient:read', 'ingredient:write'])]
    private bool $containsAlcohol = false;

    #[ORM\Column]
    #[Groups(['ingredient:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['ingredient:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = trim($name);

        if ('' === $this->slug) {
            $this->slug = SlugNormalizer::normalize($this->name);
        }
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = SlugNormalizer::normalize($slug);
    }

    public function containsAlcohol(): bool
    {
        return $this->containsAlcohol;
    }

    public function getContainsAlcohol(): bool
    {
        return $this->containsAlcohol;
    }

    public function isContainsAlcohol(): bool
    {
        return $this->containsAlcohol;
    }

    public function setContainsAlcohol(bool $containsAlcohol): void
    {
        $this->containsAlcohol = $containsAlcohol;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function prepareForInsert(): void
    {
        if ('' === $this->slug) {
            $this->slug = SlugNormalizer::normalize($this->name);
        }
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
