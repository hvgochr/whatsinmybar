<?php

namespace App\Doctrine\Orm\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Recipe;
use App\Entity\User;
use App\Enum\RecipeStatus;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class RecipeVisibilityExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private Security $security)
    {
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

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $publishedParameter = $queryNameGenerator->generateParameterName('published_status');
        $user = $this->security->getUser();

        if ($user instanceof User) {
            $authorParameter = $queryNameGenerator->generateParameterName('author');
            $queryBuilder
                ->andWhere(sprintf('%s.status = :%s OR %s.author = :%s', $rootAlias, $publishedParameter, $rootAlias, $authorParameter))
                ->setParameter($authorParameter, $user)
            ;
        } else {
            $queryBuilder->andWhere(sprintf('%s.status = :%s', $rootAlias, $publishedParameter));
        }

        $queryBuilder->setParameter($publishedParameter, RecipeStatus::Published);
    }
}
