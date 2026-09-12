<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\RecipeDifficulty;
use App\Enum\RecipeModerationStatus;
use App\Enum\RecipeStatus;
use App\Repository\RecipeRepository;
use App\Security\RecipeAccess;
use App\State\RecipeProcessor;
use App\State\RecipeViewerStateProvider;
use App\Util\SlugNormalizer;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RecipeRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_recipe_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_recipe_status', columns: ['status'])]
#[ORM\Index(name: 'idx_recipe_published_at', columns: ['published_at'])]
#[ORM\Index(name: 'idx_recipe_author', columns: ['author_id'])]
#[ORM\Index(name: 'idx_recipe_favorite_count', columns: ['favorite_count'])]
#[ORM\Index(name: 'idx_recipe_alcohol_visibility', columns: ['contains_alcohol_computed', 'contains_alcohol_override'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'])]
#[ApiResource(
    operations: [
        new GetCollection(provider: RecipeViewerStateProvider::class),
        new Post(security: "is_granted('ROLE_USER')", processor: RecipeProcessor::class),
        new Get(security: "is_granted('".RecipeAccess::View."', object)", provider: RecipeViewerStateProvider::class),
        new Patch(security: "is_granted('".RecipeAccess::Manage."', object)", processor: RecipeProcessor::class),
        new Delete(security: "is_granted('".RecipeAccess::Manage."', object)", processor: RecipeProcessor::class),
    ],
    normalizationContext: ['groups' => ['recipe:read']],
    denormalizationContext: ['groups' => ['recipe:write'], 'allow_extra_attributes' => false],
)]
class Recipe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiProperty(identifier: false)]
    #[Groups(['recipe:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $author = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    #[Groups(['recipe:read', 'recipe:write'])]
    private string $title = '';

    #[ORM\Column(length: 180)]
    #[ApiProperty(identifier: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/')]
    #[Groups(['recipe:read', 'recipe:write'])]
    private string $slug = '';

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 5000)]
    #[Groups(['recipe:read', 'recipe:write'])]
    private string $description = '';

    #[ORM\Column(length: 20, enumType: RecipeDifficulty::class)]
    #[Groups(['recipe:read', 'recipe:write'])]
    private RecipeDifficulty $difficulty = RecipeDifficulty::Easy;

    #[ORM\Column]
    #[Assert\Positive]
    #[Groups(['recipe:read', 'recipe:write'])]
    private int $preparationTimeMinutes = 5;

    #[ORM\Column]
    #[Assert\Positive]
    #[Groups(['recipe:read', 'recipe:write'])]
    private int $servings = 1;

    #[ORM\Column(length: 20, enumType: RecipeStatus::class)]
    #[Groups(['recipe:read', 'recipe:write'])]
    private RecipeStatus $status = RecipeStatus::Draft;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['recipe:read'])]
    private bool $containsAlcoholComputed = false;

    #[ORM\Column(nullable: true)]
    #[Groups(['recipe:read'])]
    private ?bool $containsAlcoholOverride = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['recipe:read'])]
    private ?string $imagePath = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['recipe:read'])]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
    #[Groups(['recipe:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['recipe:read'])]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(length: 30, enumType: RecipeModerationStatus::class, options: ['default' => 'visible'])]
    #[Groups(['recipe:read'])]
    private RecipeModerationStatus $moderationStatus = RecipeModerationStatus::Visible;

    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['recipe:read'])]
    private int $favoriteCount = 0;

    private bool $favorited = false;

    /**
     * @var Collection<int, Category>
     */
    #[ORM\ManyToMany(targetEntity: Category::class)]
    #[ORM\JoinTable(name: 'recipe_category')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['recipe:read', 'recipe:write'])]
    private Collection $categories;

    /**
     * @var Collection<int, RecipeStep>
     */
    #[ORM\OneToMany(mappedBy: 'recipe', targetEntity: RecipeStep::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Groups(['recipe:read'])]
    private Collection $steps;

    /**
     * @var Collection<int, RecipeIngredient>
     */
    #[ORM\OneToMany(mappedBy: 'recipe', targetEntity: RecipeIngredient::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Groups(['recipe:read'])]
    private Collection $recipeIngredients;

    /**
     * @var Collection<int, Favorite>
     */
    #[ORM\OneToMany(mappedBy: 'recipe', targetEntity: Favorite::class, orphanRemoval: true)]
    private Collection $favorites;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(mappedBy: 'recipe', targetEntity: Comment::class, orphanRemoval: true)]
    private Collection $comments;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->categories = new ArrayCollection();
        $this->steps = new ArrayCollection();
        $this->recipeIngredients = new ArrayCollection();
        $this->favorites = new ArrayCollection();
        $this->comments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(User $author): void
    {
        $this->author = $author;
    }

    #[Groups(['recipe:read'])]
    public function getAuthorUsername(): ?string
    {
        return $this->author?->getUsername();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = trim($title);

        if ('' === $this->slug) {
            $this->slug = SlugNormalizer::normalize($this->title);
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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = trim($description);
    }

    public function getDifficulty(): RecipeDifficulty
    {
        return $this->difficulty;
    }

    public function setDifficulty(RecipeDifficulty $difficulty): void
    {
        $this->difficulty = $difficulty;
    }

    public function getPreparationTimeMinutes(): int
    {
        return $this->preparationTimeMinutes;
    }

    public function setPreparationTimeMinutes(int $preparationTimeMinutes): void
    {
        $this->preparationTimeMinutes = $preparationTimeMinutes;
    }

    public function getServings(): int
    {
        return $this->servings;
    }

    public function setServings(int $servings): void
    {
        $this->servings = $servings;
    }

    public function getStatus(): RecipeStatus
    {
        return $this->status;
    }

    public function setStatus(RecipeStatus $status): void
    {
        $this->status = $status;

        if (RecipeStatus::Published === $status && null === $this->publishedAt) {
            $this->publishedAt = new \DateTimeImmutable();
        }
    }

    public function publish(): void
    {
        $this->setStatus(RecipeStatus::Published);
    }

    public function archive(): void
    {
        $this->setStatus(RecipeStatus::Archived);
    }

    public function containsAlcoholComputed(): bool
    {
        return $this->containsAlcoholComputed;
    }

    public function getContainsAlcoholComputed(): bool
    {
        return $this->containsAlcoholComputed;
    }

    public function setContainsAlcoholComputed(bool $containsAlcoholComputed): void
    {
        $this->containsAlcoholComputed = $containsAlcoholComputed;
    }

    public function getContainsAlcoholOverride(): ?bool
    {
        return $this->containsAlcoholOverride;
    }

    public function setContainsAlcoholOverride(?bool $containsAlcoholOverride): void
    {
        $this->containsAlcoholOverride = $containsAlcoholOverride;
    }

    public function containsAlcohol(): bool
    {
        return $this->containsAlcoholOverride ?? $this->containsAlcoholComputed;
    }

    #[Groups(['recipe:read'])]
    public function getContainsAlcohol(): bool
    {
        return $this->containsAlcohol();
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): void
    {
        $imagePath = null === $imagePath ? null : trim($imagePath);
        $this->imagePath = '' === $imagePath ? null : $imagePath;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
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
        $this->moderationStatus = RecipeModerationStatus::Removed;
    }

    public function getModerationStatus(): RecipeModerationStatus
    {
        return $this->moderationStatus;
    }

    public function setModerationStatus(RecipeModerationStatus $moderationStatus): void
    {
        $this->moderationStatus = $moderationStatus;
    }

    public function getFavoriteCount(): int
    {
        return $this->favoriteCount;
    }

    public function incrementFavoriteCount(): void
    {
        ++$this->favoriteCount;
    }

    public function decrementFavoriteCount(): void
    {
        $this->favoriteCount = max(0, $this->favoriteCount - 1);
    }

    #[Groups(['recipe:read'])]
    public function getFavorited(): bool
    {
        return $this->favorited;
    }

    public function setFavorited(bool $favorited): void
    {
        $this->favorited = $favorited;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): void
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }
    }

    public function removeCategory(Category $category): void
    {
        $this->categories->removeElement($category);
    }

    /**
     * @return Collection<int, RecipeStep>
     */
    public function getSteps(): Collection
    {
        return $this->steps;
    }

    public function addStep(RecipeStep $step): void
    {
        if (!$this->steps->contains($step)) {
            $this->steps->add($step);
            $step->setRecipe($this);
        }
    }

    public function removeStep(RecipeStep $step): void
    {
        if ($this->steps->removeElement($step) && $step->getRecipe() === $this) {
            $step->setRecipe(null);
        }
    }

    /**
     * @return Collection<int, RecipeIngredient>
     */
    public function getRecipeIngredients(): Collection
    {
        return $this->recipeIngredients;
    }

    public function addRecipeIngredient(RecipeIngredient $recipeIngredient): void
    {
        if (!$this->recipeIngredients->contains($recipeIngredient)) {
            $this->recipeIngredients->add($recipeIngredient);
            $recipeIngredient->setRecipe($this);
            $this->recalculateContainsAlcohol();
        }
    }

    public function removeRecipeIngredient(RecipeIngredient $recipeIngredient): void
    {
        if ($this->recipeIngredients->removeElement($recipeIngredient) && $recipeIngredient->getRecipe() === $this) {
            $recipeIngredient->setRecipe(null);
            $this->recalculateContainsAlcohol();
        }
    }

    public function recalculateContainsAlcohol(): void
    {
        $this->containsAlcoholComputed = $this->recipeIngredients->exists(
            static fn (int $key, RecipeIngredient $recipeIngredient): bool => $recipeIngredient->containsAlcohol(),
        );
    }

    /**
     * @return Collection<int, Favorite>
     */
    public function getFavorites(): Collection
    {
        return $this->favorites;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    #[ORM\PrePersist]
    public function prepareForInsert(): void
    {
        if ('' === $this->slug) {
            $this->slug = SlugNormalizer::normalize($this->title);
        }
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
