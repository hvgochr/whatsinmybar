<?php

namespace App\Tests\Recipe;

use App\Entity\Ingredient;
use App\Entity\Recipe;
use App\Entity\RecipeIngredient;
use App\Entity\RecipeStep;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RecipeWriteSecurityTest extends WebTestCase
{
    public function testRelationsAreImmutableAndOtherUsersCannotWrite(): void
    {
        $client = self::createClient();
        [$owner, $recipe, $alcohol] = $this->fixture();
        [$other, $target] = $this->fixture();
        [$admin] = $this->fixture(admin: true);
        foreach ([$owner, $admin] as $token) {
            foreach (['recipe_steps' => $recipe->getSteps()->first()->getId(), 'recipe_ingredients' => $recipe->getRecipeIngredients()->first()->getId()] as $resource => $id) {
                $this->refused($client, 'PATCH', '/api/'.$resource.'/'.$id, ['recipe' => '/api/recipes/'.$target->getSlug()], $token, 400);
            }
        }
        foreach (['recipe_steps' => $recipe->getSteps()->first()->getId(), 'recipe_ingredients' => $recipe->getRecipeIngredients()->first()->getId()] as $resource => $id) {
            foreach (['PATCH', 'DELETE'] as $method) {
                $this->refused($client, $method, '/api/'.$resource.'/'.$id, [], $other, 403);
            }
            $this->refused($client, 'POST', '/api/'.$resource, array_merge(['recipe' => '/api/recipes/'.$recipe->getSlug()], 'recipe_steps' === $resource ? ['instruction' => 'Mix.'] : ['ingredient' => '/api/ingredients/'.$alcohol->getSlug()]), $other, 403);
        }
        $this->refused($client, 'PUT', '/api/recipes/'.$recipe->getSlug().'/aggregate', $this->payload($alcohol), $other, 403);
        $this->refused($client, 'POST', '/api/recipes/'.$recipe->getSlug().'/publish', [], $other, 403);
        $client->jsonRequest('PATCH', '/api/recipe_steps/'.$recipe->getSteps()->first()->getId(), ['instruction' => 'Stir gently.'], server: $this->headers($owner));
        self::assertResponseIsSuccessful();
    }

    public function testMinorCannotWriteAlcoholThroughAggregateOrIndividualIngredients(): void
    {
        $client = self::createClient();
        [$token, $recipe, $alcohol] = $this->fixture(minor: true);
        $this->refused($client, 'POST', '/api/recipes/aggregate', $this->payload($alcohol), $token, 403);
        $this->refused($client, 'PUT', '/api/recipes/'.$recipe->getSlug().'/aggregate', $this->payload($alcohol), $token, 403);
        $this->refused($client, 'PATCH', '/api/recipe_ingredients/'.$recipe->getRecipeIngredients()->first()->getId(), ['ingredient' => '/api/ingredients/'.$alcohol->getSlug()], $token, 403);
        $this->refused($client, 'POST', '/api/recipe_ingredients', ['recipe' => '/api/recipes/'.$recipe->getSlug(), 'ingredient' => '/api/ingredients/'.$alcohol->getSlug(), 'position' => 2], $token, 403);
        $this->refused($client, 'POST', '/api/recipes/aggregate', $this->payload($alcohol), null, 401);
        $client->request('POST', '/api/recipes/'.$recipe->getSlug().'/publish', server: $this->headers($token));
        self::assertResponseIsSuccessful();
    }

    public function testAdultAndAdminCanWriteAlcoholAndOnlyAdminCanModerate(): void
    {
        $client = self::createClient();
        [$owner, $recipe, $alcohol] = $this->fixture();
        [$admin] = $this->fixture(admin: true);
        foreach ([$owner, $admin] as $token) {
            $client->jsonRequest('POST', '/api/recipes/aggregate', $this->payload($alcohol), server: $this->headers($token));
            self::assertResponseStatusCodeSame(201);
            $client->jsonRequest('PUT', '/api/recipes/'.$recipe->getSlug().'/aggregate', $this->payload($alcohol), server: $this->headers($token));
            self::assertResponseIsSuccessful();
            $this->refused($client, 'PATCH', '/api/recipes/'.$recipe->getSlug(), ['description' => 'Must not persist.', 'moderationStatus' => 'hidden'], $token, 400);
            $this->refused($client, 'POST', '/api/recipes', ['title' => 'Moderation bypass', 'description' => 'Invalid.', 'moderationStatus' => 'visible'], $token, 400);
        }
        $this->refused($client, 'PATCH', '/api/admin/recipes/'.$recipe->getSlug(), ['moderationStatus' => 'hidden'], $owner, 403);
        $client->jsonRequest('PATCH', '/api/admin/recipes/'.$recipe->getSlug(), ['moderationStatus' => 'hidden'], server: $this->headers($admin));
        self::assertResponseIsSuccessful();
        $this->refused($client, 'PATCH', '/api/recipes/'.$recipe->getSlug(), ['moderationStatus' => 'visible'], $owner, 403);
    }

    public function testEveryPublicationPathRejectsIncompleteRecipesAndPublishedPartsStayValid(): void
    {
        $client = self::createClient();
        [$owner, $recipe] = $this->fixture();
        [$admin, $empty] = $this->fixture(admin: true, complete: false);
        $this->refused($client, 'POST', '/api/recipes', ['title' => 'Incomplete', 'description' => 'No parts.', 'status' => 'published'], $owner, 422);
        $this->refused($client, 'PATCH', '/api/recipes/'.$empty->getSlug(), ['status' => 'published'], $admin, 422);
        $this->refused($client, 'POST', '/api/recipes/'.$empty->getSlug().'/publish', [], $admin, 422);
        $this->refused($client, 'PATCH', '/api/admin/recipes/'.$empty->getSlug(), ['status' => 'published', 'moderationStatus' => 'hidden'], $admin, 422);
        $client->jsonRequest('PATCH', '/api/recipes/'.$recipe->getSlug(), ['status' => 'published'], server: $this->headers($owner));
        self::assertResponseIsSuccessful();
        foreach (['recipe_steps' => $recipe->getSteps()->first()->getId(), 'recipe_ingredients' => $recipe->getRecipeIngredients()->first()->getId()] as $resource => $id) {
            $this->refused($client, 'DELETE', '/api/'.$resource.'/'.$id, [], $owner, 422);
        }
        $this->refused($client, 'PATCH', '/api/recipe_steps/'.$recipe->getSteps()->first()->getId(), ['instruction' => '   '], $owner, 422);
        $this->refused($client, 'PATCH', '/api/recipe_ingredients/'.$recipe->getRecipeIngredients()->first()->getId(), ['quantity' => '-1'], $owner, 422);
        $client->jsonRequest('PATCH', '/api/admin/recipes/'.$recipe->getSlug(), ['status' => 'published'], server: $this->headers($admin));
        self::assertResponseIsSuccessful();
    }

    /** @return array{string, Recipe, Ingredient} */
    private function fixture(bool $minor = false, bool $admin = false, bool $complete = true): array
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(6));
        $user = new User($suffix.'@example.com', 'writer_'.$suffix, new \DateTimeImmutable($minor ? '-15 years' : '-30 years'));
        $user->setPassword('unused');
        if ($admin) {
            $user->setRoles(['ROLE_ADMIN']);
        }
        $em->persist($user);
        $recipe = new Recipe();
        $recipe->setAuthor($user);
        $recipe->setTitle('Security '.$suffix);
        $recipe->setDescription('Valid content.');
        $ingredient = new Ingredient();
        $ingredient->setName('Juice '.$suffix);
        $em->persist($ingredient);
        $alcohol = new Ingredient();
        $alcohol->setName('Gin '.$suffix);
        $alcohol->setContainsAlcohol(true);
        $em->persist($alcohol);
        if ($complete) {
            $step = new RecipeStep();
            $step->setInstruction('Mix with ice.');
            $recipe->addStep($step);
            $part = new RecipeIngredient();
            $part->setIngredient($ingredient);
            $part->setQuantity('30');
            $recipe->addRecipeIngredient($part);
        }
        $em->persist($recipe);
        $em->flush();

        return [self::getContainer()->get(JWTTokenManagerInterface::class)->create($user), $recipe, $alcohol];
    }

    /** @return array<string, mixed> */
    private function payload(Ingredient $ingredient): array
    {
        return ['title' => 'Proposed '.bin2hex(random_bytes(6)), 'description' => 'Valid proposal.', 'difficulty' => 'easy', 'preparationTimeMinutes' => 5, 'servings' => 1, 'categories' => [], 'steps' => [['instruction' => 'Mix.']], 'ingredients' => [['ingredient' => '/api/ingredients/'.$ingredient->getSlug(), 'quantity' => '30', 'unit' => 'ml']]];
    }

    /** @return array<string, string> */
    private function headers(?string $token): array
    {
        return ['HTTP_AUTHORIZATION' => null === $token ? '' : 'Bearer '.$token, 'CONTENT_TYPE' => 'application/merge-patch+json'];
    }

    /** @param array<string, mixed> $payload */
    private function refused(KernelBrowser $client, string $method, string $url, array $payload, ?string $token, int $status): void
    {
        $before = $this->snapshot();
        $client->jsonRequest($method, $url, $payload, server: array_merge($this->headers($token), ['CONTENT_TYPE' => 'PATCH' === $method ? 'application/merge-patch+json' : 'application/json']));
        self::assertResponseStatusCodeSame($status);
        self::assertSame($before, $this->snapshot(), 'Rejected writes must leave all stored recipe data unchanged.');
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function snapshot(): array
    {
        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $result = [];
        foreach (['recipe', 'recipe_step', 'recipe_ingredient', 'recipe_category'] as $table) {
            $result[$table] = $connection->fetchAllAssociative('SELECT * FROM '.$table.' ORDER BY 1, 2');
        }

        return $result;
    }
}
