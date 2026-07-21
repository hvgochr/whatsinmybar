<?php

namespace App\Tests\Alcohol;

use App\Entity\Recipe;
use App\Entity\User;
use App\Enum\RecipeStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AlcoholRestrictionApiTest extends WebTestCase
{
    public function testAnonymousUserCannotSeeAlcoholicRecipesInCollectionOrDetail(): void
    {
        $client = static::createClient();
        $alcoholicRecipe = $this->createPublishedRecipe(containsAlcohol: true);
        $nonAlcoholicRecipe = $this->createPublishedRecipe(containsAlcohol: false);

        $client->request('GET', '/api/recipes');

        self::assertResponseIsSuccessful();

        $slugs = $this->collectionSlugs($client);
        self::assertNotContains($alcoholicRecipe->getSlug(), $slugs);
        self::assertContains($nonAlcoholicRecipe->getSlug(), $slugs);

        $client->request('GET', '/api/recipes/'.$alcoholicRecipe->getSlug());

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testMinorUserCannotSeeAlcoholicRecipesInCollectionOrDetail(): void
    {
        $client = static::createClient();
        $minorToken = $this->loginAsUser($client, new \DateTimeImmutable('2012-01-01'));
        $alcoholicRecipe = $this->createPublishedRecipe(containsAlcohol: true);
        $nonAlcoholicRecipe = $this->createPublishedRecipe(containsAlcohol: false);

        $client->request('GET', '/api/recipes', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$minorToken,
        ]);

        self::assertResponseIsSuccessful();

        $slugs = $this->collectionSlugs($client);
        self::assertNotContains($alcoholicRecipe->getSlug(), $slugs);
        self::assertContains($nonAlcoholicRecipe->getSlug(), $slugs);

        $client->request('GET', '/api/recipes/'.$alcoholicRecipe->getSlug(), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$minorToken,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAdultUserCanSeeAlcoholicRecipesInCollectionAndDetail(): void
    {
        $client = static::createClient();
        $adultToken = $this->loginAsUser($client, new \DateTimeImmutable('1990-01-01'));
        $alcoholicRecipe = $this->createPublishedRecipe(containsAlcohol: true);

        $client->request('GET', '/api/recipes', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adultToken,
        ]);

        self::assertResponseIsSuccessful();
        self::assertContains($alcoholicRecipe->getSlug(), $this->collectionSlugs($client));

        $client->request('GET', '/api/recipes/'.$alcoholicRecipe->getSlug(), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$adultToken,
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testOverrideFalseMakesComputedAlcoholicRecipeVisibleToRestrictedUsers(): void
    {
        $client = static::createClient();
        $recipe = $this->createPublishedRecipe(containsAlcohol: true);
        $recipe->setContainsAlcoholOverride(false);
        $this->flush();

        $client->request('GET', '/api/recipes');

        self::assertResponseIsSuccessful();
        self::assertContains($recipe->getSlug(), $this->collectionSlugs($client));

        $client->request('GET', '/api/recipes/'.$recipe->getSlug());

        self::assertResponseIsSuccessful();

        $payload = $this->jsonResponse($client);
        self::assertFalse($payload['containsAlcohol']);
    }

    public function testOverrideTrueMakesComputedNonAlcoholicRecipeRestricted(): void
    {
        $client = static::createClient();
        $recipe = $this->createPublishedRecipe(containsAlcohol: false);
        $recipe->setContainsAlcoholOverride(true);
        $this->flush();

        $client->request('GET', '/api/recipes');

        self::assertResponseIsSuccessful();
        self::assertNotContains($recipe->getSlug(), $this->collectionSlugs($client));

        $client->request('GET', '/api/recipes/'.$recipe->getSlug());

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    private function loginAsUser(KernelBrowser $client, \DateTimeImmutable $birthDate, array $roles = []): string
    {
        $password = 'very-secure-password';
        $user = $this->createUser($password, $birthDate, $roles);

        $client->jsonRequest('POST', '/api/auth/login', [
            'email' => $user->getEmail(),
            'password' => $password,
        ]);

        self::assertResponseIsSuccessful();

        $payload = $this->jsonResponse($client);
        self::assertIsString($payload['token']);

        return $payload['token'];
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $password, \DateTimeImmutable $birthDate, array $roles = []): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $suffix = bin2hex(random_bytes(6));

        $user = new User(
            sprintf('alcohol-user-%s@example.com', $suffix),
            sprintf('alcohol_user_%s', $suffix),
            $birthDate,
        );
        $user->setRoles($roles);
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createPublishedRecipe(bool $containsAlcohol): Recipe
    {
        $suffix = bin2hex(random_bytes(4));
        $author = $this->createUser('very-secure-password', new \DateTimeImmutable('1990-01-01'));
        $recipe = new Recipe();
        $recipe->setAuthor($author);
        $recipe->setTitle(sprintf('%s Recipe %s', $containsAlcohol ? 'Alcoholic' : 'Zero Proof', $suffix));
        $recipe->setDescription('Published recipe for alcohol restriction tests.');
        $recipe->setStatus(RecipeStatus::Published);
        $recipe->setContainsAlcoholComputed($containsAlcohol);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($recipe);
        $entityManager->flush();

        return $recipe;
    }

    private function flush(): void
    {
        static::getContainer()->get(EntityManagerInterface::class)->flush();
    }

    /**
     * @return list<string>
     */
    private function collectionSlugs(KernelBrowser $client): array
    {
        return array_map(
            static fn (array $recipe): string => (string) $recipe['slug'],
            $this->collectionItems($client),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectionItems(KernelBrowser $client): array
    {
        $payload = $this->jsonResponse($client);

        if (isset($payload['member']) && is_array($payload['member'])) {
            return $payload['member'];
        }

        if (isset($payload['hydra:member']) && is_array($payload['hydra:member'])) {
            return $payload['hydra:member'];
        }

        return array_is_list($payload) ? $payload : [];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function jsonResponse(KernelBrowser $client): array
    {
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);

        return $payload;
    }
}
