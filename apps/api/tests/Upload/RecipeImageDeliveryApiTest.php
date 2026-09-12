<?php

namespace App\Tests\Upload;

use App\Entity\Recipe;
use App\Entity\User;
use App\Enum\RecipeModerationStatus;
use App\Enum\RecipeStatus;
use App\Service\Upload\RecipeImageStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RecipeImageDeliveryApiTest extends WebTestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    #[DataProvider('accessCases')]
    public function testDeliveryUsesRecipeReadPermissions(string $state, string $viewer, int $expected): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $author = $this->user($em, 'owner');
        $user = match ($viewer) {
            'anonymous' => null,
            'owner' => $author,
            default => $this->user($em, $viewer),
        };
        $source = tempnam(sys_get_temp_dir(), 'delivery');
        self::assertIsString($source);
        $this->files[] = $source;
        imagepng(imagecreatetruecolor(2, 2), $source);
        $storage = self::getContainer()->get(RecipeImageStorageInterface::class);
        $path = $storage->store(new UploadedFile($source, 'image.png', 'image/png', test: true));
        $local = $storage->localPath($path);
        self::assertIsString($local);
        $this->files[] = $local;
        $recipe = new Recipe();
        $recipe->setAuthor($author);
        $recipe->setTitle('Delivery '.bin2hex(random_bytes(6)));
        $recipe->setDescription('Delivery permission test');
        $recipe->setStatus(match ($state) {
            'draft' => RecipeStatus::Draft,
            'archived' => RecipeStatus::Archived,
            default => RecipeStatus::Published,
        });
        if (in_array($state, ['hidden', 'removed', 'pending_review'], true)) {
            $recipe->setModerationStatus(RecipeModerationStatus::from($state));
        }
        if ('deleted' === $state) {
            $recipe->softDelete();
        }
        if ('alcohol' === $state) {
            $recipe->setContainsAlcoholOverride(true);
        }
        $recipe->setImagePath($path);
        $em->persist($recipe);
        $em->flush();
        $headers = [];
        if (null !== $user) {
            $client->jsonRequest('POST', '/api/auth/login', ['email' => $user->getEmail(), 'password' => 'very-secure-password']);
            self::assertResponseIsSuccessful();
            $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$payload['token'];
        }
        $url = '/api/recipe-images/'.basename($path);
        $client->request('GET', $url, server: $headers);
        self::assertResponseStatusCodeSame($expected);
        if (200 === $expected) {
            self::assertResponseHeaderSame('Content-Type', 'image/png');
            self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
            self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
        }
        $client->request('HEAD', $url, server: $headers);
        self::assertResponseStatusCodeSame($expected);
        // A filename never grants access after its database reference is removed.
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $stored = $em->getRepository(Recipe::class)->find($recipe->getId());
        self::assertInstanceOf(Recipe::class, $stored);
        $stored->setImagePath(null);
        $em->flush();
        $client->request('GET', $url, server: $headers);
        self::assertResponseStatusCodeSame(404);
    }

    /** @return iterable<string, array{string, string, int}> */
    public static function accessCases(): iterable
    {
        foreach (['published', 'draft', 'archived', 'hidden', 'removed', 'pending_review', 'deleted', 'alcohol'] as $state) {
            foreach (['anonymous', 'minor', 'adult', 'owner', 'admin'] as $viewer) {
                $allowed = match ($state) {
                    'published' => true,
                    'draft', 'archived' => in_array($viewer, ['owner', 'admin'], true),
                    'hidden', 'removed', 'pending_review' => 'admin' === $viewer,
                    'deleted' => false,
                    'alcohol' => in_array($viewer, ['adult', 'owner', 'admin'], true),
                };
                yield $state.' '.$viewer => [$state, $viewer, $allowed ? 200 : 404];
            }
        }
    }

    private function user(EntityManagerInterface $em, string $role): User
    {
        $suffix = bin2hex(random_bytes(6));
        $user = new User($suffix.'@example.com', 'delivery_'.$suffix, new \DateTimeImmutable('minor' === $role ? '-16 years' : '1990-01-01'));
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, 'very-secure-password'));
        if ('admin' === $role) {
            $user->setRoles(['ROLE_ADMIN']);
        }
        $em->persist($user);
        $em->flush();

        return $user;
    }
}
