<?php

namespace App\Tests\Upload;

use App\Entity\Recipe;
use App\Entity\User;
use App\Enum\RecipeStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RecipeImageUploadApiTest extends WebTestCase
{
    public function testPhpTransportLimitDoesNotUndercutRecipeImageValidation(): void
    {
        self::assertGreaterThanOrEqual(5 * 1024 * 1024, $this->iniBytes((string) ini_get('upload_max_filesize')));
        self::assertGreaterThan(5 * 1024 * 1024, $this->iniBytes((string) ini_get('post_max_size')));
    }

    public function testAuthorCanUploadAndRemoveRecipeImage(): void
    {
        $client = static::createClient();
        $author = $this->createUser('very-secure-password');
        $token = $this->loginAsUser($client, $author);
        $recipe = $this->createRecipe($author);

        $client->request('POST', '/api/recipes/'.$recipe->getSlug().'/image', files: [
            'image' => $this->pngUpload(),
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();

        $payload = $this->jsonResponse($client);
        self::assertSame($recipe->getSlug(), $payload['recipeSlug']);
        self::assertIsString($payload['imagePath']);
        self::assertMatchesRegularExpression('#^/uploads/recipes/[a-f0-9]{32}\.png$#', $payload['imagePath']);

        $client->request('GET', '/api/recipes/'.$recipe->getSlug(), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame($payload['imagePath'], $this->jsonResponse($client)['imagePath']);

        $client->request('DELETE', '/api/recipes/'.$recipe->getSlug().'/image', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();

        $deletePayload = $this->jsonResponse($client);
        self::assertSame($recipe->getSlug(), $deletePayload['recipeSlug']);
        self::assertNull($deletePayload['imagePath']);
    }

    public function testNonAuthorCannotUploadRecipeImage(): void
    {
        $client = static::createClient();
        $author = $this->createUser('very-secure-password');
        $other = $this->createUser('very-secure-password');
        $token = $this->loginAsUser($client, $other);
        $recipe = $this->createRecipe($author);

        $client->request('POST', '/api/recipes/'.$recipe->getSlug().'/image', files: [
            'image' => $this->pngUpload(),
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testRecipeImageUploadRejectsUnsupportedFiles(): void
    {
        $client = static::createClient();
        $author = $this->createUser('very-secure-password');
        $token = $this->loginAsUser($client, $author);
        $recipe = $this->createRecipe($author);

        $client->request('POST', '/api/recipes/'.$recipe->getSlug().'/image', files: [
            'image' => $this->textUpload(),
        ], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    private function loginAsUser(KernelBrowser $client, User $user): string
    {
        $client->jsonRequest('POST', '/api/auth/login', [
            'email' => $user->getEmail(),
            'password' => 'very-secure-password',
        ]);

        self::assertResponseIsSuccessful();

        $payload = $this->jsonResponse($client);
        self::assertIsString($payload['token']);

        return $payload['token'];
    }

    private function createUser(string $password): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $suffix = bin2hex(random_bytes(6));

        $user = new User(
            sprintf('recipe-upload-%s@example.com', $suffix),
            sprintf('recipe_upload_%s', $suffix),
            new \DateTimeImmutable('1990-01-01'),
        );
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createRecipe(User $author): Recipe
    {
        $suffix = bin2hex(random_bytes(6));
        $recipe = new Recipe();
        $recipe->setAuthor($author);
        $recipe->setTitle(sprintf('Recipe Upload %s', $suffix));
        $recipe->setDescription('Recipe used to test image uploads.');
        $recipe->setStatus(RecipeStatus::Draft);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($recipe);
        $entityManager->flush();

        return $recipe;
    }

    private function pngUpload(): UploadedFile
    {
        $filePath = tempnam(sys_get_temp_dir(), 'recipe-image-upload');
        self::assertIsString($filePath);

        imagepng(imagecreatetruecolor(2, 2), $filePath);

        return new UploadedFile($filePath, 'recipe.png', 'image/png', test: true);
    }

    private function textUpload(): UploadedFile
    {
        $filePath = tempnam(sys_get_temp_dir(), 'recipe-image-upload');
        self::assertIsString($filePath);
        file_put_contents($filePath, 'not an image');

        return new UploadedFile($filePath, 'recipe.txt', 'text/plain', test: true);
    }

    private function iniBytes(string $value): int
    {
        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
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
