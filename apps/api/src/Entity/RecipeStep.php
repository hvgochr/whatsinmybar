<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\RecipeStepRepository;
use App\Security\RecipeAccess;
use App\State\RecipePartProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RecipeStepRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_recipe_step_position', columns: ['recipe_id', 'position'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Post(denormalizationContext: ['groups' => ['recipe_step:write', 'recipe_step:create'], 'allow_extra_attributes' => false], security: "is_granted('ROLE_USER')", securityPostDenormalize: "object.getRecipe() and is_granted('".RecipeAccess::Manage."', object.getRecipe())", processor: RecipePartProcessor::class),
        new Get(security: "object.getRecipe() and is_granted('".RecipeAccess::View."', object.getRecipe())"),
        new Patch(security: "object.getRecipe() and is_granted('".RecipeAccess::Manage."', object.getRecipe())", processor: RecipePartProcessor::class),
        new Delete(security: "object.getRecipe() and is_granted('".RecipeAccess::Manage."', object.getRecipe())", processor: RecipePartProcessor::class),
    ],
    normalizationContext: ['groups' => ['recipe_step:read']],
    denormalizationContext: ['groups' => ['recipe_step:write'], 'allow_extra_attributes' => false],
)]
class RecipeStep
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['recipe:read', 'recipe_step:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'steps')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['recipe_step:read', 'recipe_step:create'])]
    #[Assert\NotNull]
    private ?Recipe $recipe = null;

    #[ORM\Column]
    #[Assert\Positive]
    #[Groups(['recipe:read', 'recipe_step:read', 'recipe_step:write'])]
    private int $position = 1;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 2000)]
    #[Groups(['recipe:read', 'recipe_step:read', 'recipe_step:write'])]
    private string $instruction = '';

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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getInstruction(): string
    {
        return $this->instruction;
    }

    public function setInstruction(string $instruction): void
    {
        $this->instruction = trim($instruction);
    }
}
