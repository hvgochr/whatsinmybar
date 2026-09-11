<?php

namespace App\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\RecipeIngredient;
use App\Entity\RecipeStep;
use App\Enum\RecipeStatus;
use App\Security\RecipeAccess;
use App\Service\RecipePublicationValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProcessorInterface<RecipeIngredient|RecipeStep, RecipeIngredient|RecipeStep|null>
 */
final readonly class RecipePartProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RecipePublicationValidator $publicationValidator,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RecipeIngredient|RecipeStep|null
    {
        $recipe = $data->getRecipe();
        if (null === $recipe) {
            throw new AccessDeniedException('Recipe required.');
        }

        $delete = $operation instanceof DeleteOperationInterface;
        if ($data instanceof RecipeIngredient) {
            $delete ? $recipe->removeRecipeIngredient($data) : $recipe->addRecipeIngredient($data);
        } else {
            $delete ? $recipe->removeStep($data) : $recipe->addStep($data);
        }
        $recipe->recalculateContainsAlcohol();
        if (!$this->security->isGranted(RecipeAccess::Manage, $recipe)) {
            throw new AccessDeniedException('Cannot manage the proposed recipe.');
        }
        if (RecipeStatus::Published === $recipe->getStatus()) {
            $this->publicationValidator->validate($recipe);
        }

        $delete ? $this->entityManager->remove($data) : $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $delete ? null : $data;
    }
}
