<?php

namespace App\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Recipe;
use App\Entity\User;
use App\Repository\FavoriteRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProviderInterface<Recipe>
 */
final readonly class RecipeViewerStateProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<Recipe> $itemProvider
     * @param ProviderInterface<Recipe> $collectionProvider
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private ProviderInterface $itemProvider,
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private ProviderInterface $collectionProvider,
        private FavoriteRepository $favoriteRepository,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $provider = $operation instanceof GetCollection ? $this->collectionProvider : $this->itemProvider;
        $data = $provider->provide($operation, $uriVariables, $context);
        $user = $this->security->getUser();

        if (!$user instanceof User || null === $data) {
            return $data;
        }

        if ($data instanceof Recipe) {
            $data->setFavorited(null !== $this->favoriteRepository->findOneForUserAndRecipe($user, $data));

            return $data;
        }

        $recipes = [];
        foreach ($data as $recipe) {
            $recipes[] = $recipe;
        }

        $favoriteIds = array_fill_keys($this->favoriteRepository->findRecipeIdsForUser($user, $recipes), true);
        foreach ($recipes as $recipe) {
            $recipe->setFavorited(isset($favoriteIds[$recipe->getId()]));
        }

        return $data;
    }
}
