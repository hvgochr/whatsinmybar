<?php

namespace App\Doctrine\Orm\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Recipe;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class RecipeSearchExtension implements QueryCollectionExtensionInterface
{
    /**
     * @param class-string         $resourceClass
     * @param array<string, mixed> $context
     */
    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (Recipe::class !== $resourceClass) {
            return;
        }

        $filters = $this->filters($context);
        $rootAlias = $queryBuilder->getRootAliases()[0];

        $this->applyTextSearch($queryBuilder, $queryNameGenerator, $rootAlias, $filters);
        $this->applyCategoryFilter($queryBuilder, $queryNameGenerator, $rootAlias, $filters);
        $this->applyIngredientFilter($queryBuilder, $queryNameGenerator, $rootAlias, $filters);
        $this->applyAlcoholFilter($queryBuilder, $rootAlias, $filters);
        $this->applyAuthorFilter($queryBuilder, $queryNameGenerator, $rootAlias, $filters);
        $this->applyPopularityFilter($queryBuilder, $queryNameGenerator, $rootAlias, $filters);
        $this->applyPublishedDateFilters($queryBuilder, $queryNameGenerator, $rootAlias, $filters);
        $this->applySort($queryBuilder, $rootAlias, $filters);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function filters(array $context): array
    {
        $filters = $context['filters'] ?? [];

        return is_array($filters) ? $filters : [];
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyTextSearch(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $rootAlias, array $filters): void
    {
        $query = trim((string) ($filters['q'] ?? ''));
        if ('' === $query) {
            return;
        }

        $parameter = $queryNameGenerator->generateParameterName('recipe_search');
        $queryBuilder
            ->andWhere(sprintf(
                '(LOWER(%1$s.title) LIKE :%2$s OR LOWER(%1$s.description) LIKE :%2$s OR LOWER(%1$s.slug) LIKE :%2$s)',
                $rootAlias,
                $parameter,
            ))
            ->setParameter($parameter, '%'.mb_strtolower($query).'%')
        ;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyCategoryFilter(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $rootAlias, array $filters): void
    {
        $category = trim((string) ($filters['category'] ?? ''));
        if ('' === $category) {
            return;
        }

        $alias = $queryNameGenerator->generateJoinAlias('category');
        $parameter = $queryNameGenerator->generateParameterName('category_slug');
        $queryBuilder
            ->innerJoin(sprintf('%s.categories', $rootAlias), $alias)
            ->andWhere(sprintf('%s.slug = :%s', $alias, $parameter))
            ->setParameter($parameter, $category)
        ;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyIngredientFilter(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $rootAlias, array $filters): void
    {
        $ingredient = trim((string) ($filters['ingredient'] ?? ''));
        if ('' === $ingredient) {
            return;
        }

        $recipeIngredientAlias = $queryNameGenerator->generateJoinAlias('recipe_ingredient');
        $ingredientAlias = $queryNameGenerator->generateJoinAlias('ingredient');
        $parameter = $queryNameGenerator->generateParameterName('ingredient_slug');
        $queryBuilder
            ->innerJoin(sprintf('%s.recipeIngredients', $rootAlias), $recipeIngredientAlias)
            ->innerJoin(sprintf('%s.ingredient', $recipeIngredientAlias), $ingredientAlias)
            ->andWhere(sprintf('%s.slug = :%s', $ingredientAlias, $parameter))
            ->setParameter($parameter, $ingredient)
        ;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyAlcoholFilter(QueryBuilder $queryBuilder, string $rootAlias, array $filters): void
    {
        if (!array_key_exists('alcohol', $filters) || '' === trim((string) $filters['alcohol'])) {
            return;
        }

        $containsAlcohol = $this->booleanFilter((string) $filters['alcohol'], 'alcohol');
        $operator = $containsAlcohol ? 'true' : 'false';
        $queryBuilder->andWhere(sprintf(
            '(%1$s.containsAlcoholOverride = %2$s OR (%1$s.containsAlcoholOverride IS NULL AND %1$s.containsAlcoholComputed = %2$s))',
            $rootAlias,
            $operator,
        ));
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyAuthorFilter(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $rootAlias, array $filters): void
    {
        $author = trim((string) ($filters['author'] ?? ''));
        if ('' === $author) {
            return;
        }

        $alias = $queryNameGenerator->generateJoinAlias('author');
        $parameter = $queryNameGenerator->generateParameterName('author_username');
        $queryBuilder
            ->innerJoin(sprintf('%s.author', $rootAlias), $alias)
            ->andWhere(sprintf('%s.username = :%s', $alias, $parameter))
            ->setParameter($parameter, $author)
        ;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyPopularityFilter(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $rootAlias, array $filters): void
    {
        if (!array_key_exists('minFavorites', $filters) || '' === trim((string) $filters['minFavorites'])) {
            return;
        }

        $minFavorites = filter_var($filters['minFavorites'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if (false === $minFavorites) {
            throw new BadRequestHttpException('minFavorites must be a positive integer or zero.');
        }

        $parameter = $queryNameGenerator->generateParameterName('min_favorites');
        $queryBuilder
            ->andWhere(sprintf('%s.favoriteCount >= :%s', $rootAlias, $parameter))
            ->setParameter($parameter, $minFavorites)
        ;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyPublishedDateFilters(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $rootAlias, array $filters): void
    {
        $this->applyPublishedDateFilter($queryBuilder, $queryNameGenerator, $rootAlias, $filters, 'publishedAfter', '>=');
        $this->applyPublishedDateFilter($queryBuilder, $queryNameGenerator, $rootAlias, $filters, 'publishedBefore', '<=');
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyPublishedDateFilter(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $rootAlias, array $filters, string $filterName, string $operator): void
    {
        if (!array_key_exists($filterName, $filters) || '' === trim((string) $filters[$filterName])) {
            return;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $filters[$filterName]);
        if (!$date instanceof \DateTimeImmutable) {
            throw new BadRequestHttpException(sprintf('%s must be a valid YYYY-MM-DD date.', $filterName));
        }

        if ('publishedBefore' === $filterName) {
            $date = $date->setTime(23, 59, 59);
        }

        $parameter = $queryNameGenerator->generateParameterName($filterName);
        $queryBuilder
            ->andWhere(sprintf('%s.publishedAt %s :%s', $rootAlias, $operator, $parameter))
            ->setParameter($parameter, $date)
        ;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applySort(QueryBuilder $queryBuilder, string $rootAlias, array $filters): void
    {
        $sort = trim((string) ($filters['sort'] ?? 'newest'));

        match ($sort) {
            'popular' => $queryBuilder->addOrderBy(sprintf('%s.favoriteCount', $rootAlias), 'DESC')->addOrderBy(sprintf('%s.publishedAt', $rootAlias), 'DESC'),
            'newest' => $queryBuilder->addOrderBy(sprintf('%s.publishedAt', $rootAlias), 'DESC'),
            'oldest' => $queryBuilder->addOrderBy(sprintf('%s.publishedAt', $rootAlias), 'ASC'),
            default => throw new BadRequestHttpException('sort must be one of: popular, newest, oldest.'),
        };

        $queryBuilder->addOrderBy(sprintf('%s.id', $rootAlias), 'DESC');
    }

    private function booleanFilter(string $value, string $filterName): bool
    {
        return match (mb_strtolower(trim($value))) {
            '1', 'true', 'yes' => true,
            '0', 'false', 'no' => false,
            default => throw new BadRequestHttpException(sprintf('%s must be a boolean value.', $filterName)),
        };
    }
}
