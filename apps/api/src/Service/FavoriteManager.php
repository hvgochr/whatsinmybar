<?php

namespace App\Service;

use App\Entity\Recipe;
use App\Entity\User;
use Doctrine\DBAL\Connection;

final readonly class FavoriteManager
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function add(User $user, Recipe $recipe): FavoriteResult
    {
        [$userId, $recipeId] = $this->identifiers($user, $recipe);

        return $this->connection->transactional(function (Connection $connection) use ($recipe, $recipeId, $userId): FavoriteResult {
            $changed = 1 === $connection->executeStatement(
                <<<'SQL'
                    INSERT INTO favorite (user_id, recipe_id, created_at)
                    VALUES (:userId, :recipeId, :createdAt)
                    ON CONFLICT (user_id, recipe_id) DO NOTHING
                    SQL,
                [
                    'userId' => $userId,
                    'recipeId' => $recipeId,
                    'createdAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ],
            );

            if ($changed) {
                $favoriteCount = $connection->fetchOne(
                    'UPDATE recipe SET favorite_count = favorite_count + 1 WHERE id = :recipeId RETURNING favorite_count',
                    ['recipeId' => $recipeId],
                );
                $recipe->incrementFavoriteCount();
            } else {
                $favoriteCount = $connection->fetchOne(
                    'SELECT favorite_count FROM recipe WHERE id = :recipeId',
                    ['recipeId' => $recipeId],
                );
            }

            return new FavoriteResult($recipe, $this->integerCount($favoriteCount), true, $changed);
        });
    }

    public function remove(User $user, Recipe $recipe): FavoriteResult
    {
        [$userId, $recipeId] = $this->identifiers($user, $recipe);

        return $this->connection->transactional(function (Connection $connection) use ($recipe, $recipeId, $userId): FavoriteResult {
            $changed = 1 === $connection->executeStatement(
                'DELETE FROM favorite WHERE user_id = :userId AND recipe_id = :recipeId',
                ['userId' => $userId, 'recipeId' => $recipeId],
            );

            if ($changed) {
                $favoriteCount = $connection->fetchOne(
                    'UPDATE recipe SET favorite_count = GREATEST(0, favorite_count - 1) WHERE id = :recipeId RETURNING favorite_count',
                    ['recipeId' => $recipeId],
                );
                $recipe->decrementFavoriteCount();
            } else {
                $favoriteCount = $connection->fetchOne(
                    'SELECT favorite_count FROM recipe WHERE id = :recipeId',
                    ['recipeId' => $recipeId],
                );
            }

            return new FavoriteResult($recipe, $this->integerCount($favoriteCount), false, $changed);
        });
    }

    /**
     * @return array{int, int}
     */
    private function identifiers(User $user, Recipe $recipe): array
    {
        $userId = $user->getId();
        $recipeId = $recipe->getId();

        if (null === $userId || null === $recipeId) {
            throw new \LogicException('Favorites require persisted users and recipes.');
        }

        return [$userId, $recipeId];
    }

    private function integerCount(mixed $favoriteCount): int
    {
        if (!is_int($favoriteCount) && !is_string($favoriteCount)) {
            throw new \RuntimeException('Recipe favorite counter could not be read.');
        }

        return (int) $favoriteCount;
    }
}
