<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\IngredientUnit;
use App\Repository\RecipeIngredientRepository;
use App\Security\RecipeAccess;
use App\State\RecipeIngredientProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RecipeIngredientRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_recipe_ingredient_position', columns: ['recipe_id', 'position'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Post(security: "is_granted('ROLE_USER')", securityPostDenormalize: "object.getRecipe() and is_granted('".RecipeAccess::Manage."', object.getRecipe())", processor: RecipeIngredientProcessor::class),
        new Get(security: "object.getRecipe() and is_granted('".RecipeAccess::View."', object.getRecipe())"),
        new Patch(security: "object.getRecipe() and is_granted('".RecipeAccess::Manage."', object.getRecipe())", processor: RecipeIngredientProcessor::class),
        new Delete(security: "object.getRecipe() and is_granted('".RecipeAccess::Manage."', object.getRecipe())", processor: RecipeIngredientProcessor::class),
    ],
    normalizationContext: ['groups' => ['recipe_ingredient:read']],
    denormalizationContext: ['groups' => ['recipe_ingredient:write']],
)]
class RecipeIngredient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['recipe:read', 'recipe_ingredient:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'recipeIngredients')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['recipe_ingredient:read', 'recipe_ingredient:write'])]
    private ?Recipe $recipe = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['recipe:read', 'recipe_ingredient:read', 'recipe_ingredient:write'])]
    private ?Ingredient $ingredient = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    #[Assert\Positive]
    #[Groups(['recipe:read', 'recipe_ingredient:read', 'recipe_ingredient:write'])]
    private ?string $quantity = null;

    #[ORM\Column(length: 30, enumType: IngredientUnit::class)]
    #[Groups(['recipe:read', 'recipe_ingredient:read', 'recipe_ingredient:write'])]
    private IngredientUnit $unit = IngredientUnit::Milliliter;

    #[ORM\Column]
    #[Assert\Positive]
    #[Groups(['recipe:read', 'recipe_ingredient:read', 'recipe_ingredient:write'])]
    private int $position = 1;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000)]
    #[Groups(['recipe:read', 'recipe_ingredient:read', 'recipe_ingredient:write'])]
    private ?string $note = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecipe(): ?Recipe
    {
        return $this->recipe;
    }

    public function setRecipe(?Recipe $recipe): void
    {
        $this->recipe = $recipe;
    }

    public function getIngredient(): ?Ingredient
    {
        return $this->ingredient;
    }

    public function setIngredient(Ingredient $ingredient): void
    {
        $this->ingredient = $ingredient;
        $this->recipe?->recalculateContainsAlcohol();
    }

    public function getQuantity(): ?string
    {
        return $this->quantity;
    }

    public function setQuantity(float|int|string|null $quantity): void
    {
        $this->quantity = null === $quantity ? null : number_format((float) $quantity, 2, '.', '');
    }

    public function getUnit(): IngredientUnit
    {
        return $this->unit;
    }

    public function setUnit(IngredientUnit $unit): void
    {
        $this->unit = $unit;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $note = null === $note ? null : trim($note);
        $this->note = '' === $note ? null : $note;
    }

    public function containsAlcohol(): bool
    {
        return true === $this->ingredient?->containsAlcohol();
    }
}
