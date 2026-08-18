<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Ingredient;
use App\Service\RecipeAlcoholClassificationUpdater;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProcessorInterface<Ingredient, Ingredient>
 */
final readonly class IngredientProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<Ingredient, Ingredient> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private RecipeAlcoholClassificationUpdater $classificationUpdater,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Ingredient
    {
        $this->classificationUpdater->recalculateForIngredient($data);

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
