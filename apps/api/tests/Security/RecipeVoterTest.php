<?php

namespace App\Tests\Security;

use App\Entity\Recipe;
use App\Entity\User;
use App\Enum\RecipeModerationStatus;
use App\Enum\RecipeStatus;
use App\Security\AlcoholAccessPolicy;
use App\Security\RecipeAccess;
use App\Security\RecipeVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class RecipeVoterTest extends TestCase
{
    public function testAnonymousUserCanViewPublishedNonAlcoholicRecipe(): void
    {
        $recipe = $this->publishedRecipe(containsAlcohol: false);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($recipe, null, RecipeAccess::View));
    }

    #[DataProvider('restrictedAlcoholUsers')]
    public function testRestrictedUsersCannotViewPublishedAlcoholicRecipe(?User $user): void
    {
        $recipe = $this->publishedRecipe(containsAlcohol: true);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($recipe, $user, RecipeAccess::View));
    }

    public function testAdultUserCanViewPublishedAlcoholicRecipe(): void
    {
        $recipe = $this->publishedRecipe(containsAlcohol: true);
        $adult = new User('adult@example.com', 'adult', new \DateTimeImmutable('1990-01-01'));

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($recipe, $adult, RecipeAccess::View));
    }

    public function testAdminCanViewPublishedAlcoholicRecipe(): void
    {
        $recipe = $this->publishedRecipe(containsAlcohol: true);
        $admin = new User('admin@example.com', 'admin', new \DateTimeImmutable('2012-01-01'));
        $admin->setRoles(['ROLE_ADMIN']);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($recipe, $admin, RecipeAccess::View));
    }

    public function testHiddenRecipeIsOnlyVisibleToAdmin(): void
    {
        $recipe = $this->publishedRecipe(containsAlcohol: false);
        $recipe->setModerationStatus(RecipeModerationStatus::Hidden);
        $adult = new User('adult@example.com', 'adult', new \DateTimeImmutable('1990-01-01'));
        $admin = new User('admin-hidden@example.com', 'admin_hidden', new \DateTimeImmutable('1990-01-01'));
        $admin->setRoles(['ROLE_ADMIN']);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($recipe, null, RecipeAccess::View));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($recipe, $adult, RecipeAccess::View));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($recipe, $admin, RecipeAccess::View));
    }

    public function testMinorAuthorCannotManageAlcoholicDraft(): void
    {
        $author = new User('minor-author@example.com', 'minor_author', new \DateTimeImmutable('2012-01-01'));
        $recipe = new Recipe();
        $recipe->setAuthor($author);
        $recipe->setContainsAlcoholOverride(true);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($recipe, $author, RecipeAccess::Manage));
    }

    /**
     * @return iterable<string, array{0: User|null}>
     */
    public static function restrictedAlcoholUsers(): iterable
    {
        yield 'anonymous' => [null];
        yield 'minor' => [new User('minor@example.com', 'minor', new \DateTimeImmutable('2012-01-01'))];
    }

    private function publishedRecipe(bool $containsAlcohol): Recipe
    {
        $recipe = new Recipe();
        $recipe->setTitle('Published recipe');
        $recipe->setDescription('Visible recipe.');
        $recipe->setStatus(RecipeStatus::Published);
        $recipe->setContainsAlcoholOverride($containsAlcohol);

        return $recipe;
    }

    private function vote(Recipe $recipe, ?User $user, string $attribute): int
    {
        $token = null === $user ? new NullToken() : new UsernamePasswordToken($user, 'main', $user->getRoles());
        $voter = new RecipeVoter(new AlcoholAccessPolicy());

        return $voter->vote($token, $recipe, [$attribute]);
    }
}
