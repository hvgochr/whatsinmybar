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
        if (!$data instanceof RecipeIngredient) {
            return null;
        }

        $recipe = $data->getRecipe();

        if ($operation instanceof DeleteOperationInterface) {
            $result = $this->removeProcessor->process($data, $operation, $uriVariables, $context);
            $recipe?->recalculateContainsAlcohol();
            $this->entityManager->flush();

            return $result instanceof RecipeIngredient ? $result : null;
        }

        $recipe?->recalculateContainsAlcohol();

        /** @var RecipeIngredient $recipeIngredient */
        $recipeIngredient = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        return $recipeIngredient;
    }
}
