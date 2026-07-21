<?php

namespace App\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Recipe;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProcessorInterface<Recipe, Recipe|null>
 */
final readonly class RecipeProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<Recipe, Recipe> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Recipe
    {
        if ($operation instanceof DeleteOperationInterface) {
            $data->softDelete();
        }

        if (null === $data->getAuthor()) {
            $user = $this->security->getUser();
            if (!$user instanceof User) {
                throw new AccessDeniedException('Authentication required to create a recipe.');
            }

            $data->setAuthor($user);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
