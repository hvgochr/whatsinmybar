<?php

namespace App\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\RecipeIngredient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProcessorInterface<RecipeIngredient, RecipeIngredient|null>
 */
final readonly class RecipeIngredientProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<RecipeIngredient, RecipeIngredient> $persistProcessor
     * @param ProcessorInterface<RecipeIngredient, void>             $removeProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?RecipeIngredient
    {
        $recipe = $data->getRecipe();
        if (null !== $recipe && !$recipe->getRecipeIngredients()->contains($data)) {
            $recipe->addRecipeIngredient($data);
        }

        if ($operation instanceof DeleteOperationInterface) {
            $this->removeProcessor->process($data, $operation, $uriVariables, $context);
            $recipe?->recalculateContainsAlcohol();
            $this->entityManager->flush();

            return null;
        }

        $recipe?->recalculateContainsAlcohol();

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
