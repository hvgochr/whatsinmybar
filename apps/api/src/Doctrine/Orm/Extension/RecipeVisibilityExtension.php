<?php

namespace App\Doctrine\Orm\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Recipe;
use App\Entity\User;
use App\Enum\RecipeModerationStatus;
use App\Enum\RecipeStatus;
use App\Security\AlcoholAccessPolicy;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class RecipeVisibilityExtension implements QueryCollectionExtensionInterface
{
    public function __construct(
        private Security $security,
        private AlcoholAccessPolicy $alcoholAccessPolicy,
    ) {
    }

    /**
     * @param class-string         $resourceClass
     * @param array<string, mixed> $context
     */
    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (Recipe::class !== $resourceClass) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere(sprintf('%s.deletedAt IS NULL', $rootAlias));
        $user = $this->security->getUser();

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $publishedParameter = $queryNameGenerator->generateParameterName('published_status');
        $visibleModerationParameter = $queryNameGenerator->generateParameterName('visible_moderation_status');

        if ($user instanceof User) {
            $authorParameter = $queryNameGenerator->generateParameterName('author');
            $queryBuilder
                ->andWhere(sprintf('(%s.status = :%s OR %s.author = :%s)', $rootAlias, $publishedParameter, $rootAlias, $authorParameter))
                ->setParameter($authorParameter, $user)
            ;
        } else {
            $queryBuilder->andWhere(sprintf('%s.status = :%s', $rootAlias, $publishedParameter));
        }

        $queryBuilder
            ->andWhere(sprintf('%s.moderationStatus = :%s', $rootAlias, $visibleModerationParameter))
            ->setParameter($publishedParameter, RecipeStatus::Published)
            ->setParameter($visibleModerationParameter, RecipeModerationStatus::Visible)
        ;

        if (!$this->alcoholAccessPolicy->canAccessAlcohol($user)) {
            $queryBuilder->andWhere(sprintf(
                '%1$s.containsAlcoholOverride = false OR (%1$s.containsAlcoholOverride IS NULL AND %1$s.containsAlcoholComputed = false)',
                $rootAlias,
            ));
        }
    }
}
