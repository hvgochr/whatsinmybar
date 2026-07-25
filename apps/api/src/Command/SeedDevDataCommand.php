<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\Ingredient;
use App\Entity\Recipe;
use App\Entity\RecipeIngredient;
use App\Entity\User;
use App\Enum\IngredientUnit;
use App\Enum\RecipeDifficulty;
use App\Enum\RecipeStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed:dev', description: 'Seed idempotent development data.')]
final class SeedDevDataCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ('prod' === $this->environment) {
            $io->error('Development seed data cannot be loaded in prod.');

            return Command::FAILURE;
        }

        $admin = $this->user('admin@example.com', 'admin', ['ROLE_ADMIN']);
        $jane = $this->user('jane@example.com', 'jane_doe');
        $max = $this->user('max@example.com', 'max_mixer');

        $classics = $this->category('Classics', 'Timeless cocktail recipes.');
        $zeroProof = $this->category('Zero Proof', 'Alcohol-free cocktail recipes.');

        $gin = $this->ingredient('Gin', containsAlcohol: true);
        $campari = $this->ingredient('Campari', containsAlcohol: true);
        $vermouth = $this->ingredient('Sweet Vermouth', containsAlcohol: true);
        $lime = $this->ingredient('Lime Juice');
        $syrup = $this->ingredient('Simple Syrup');
        $soda = $this->ingredient('Soda Water');

        $negroni = $this->recipe($jane, 'Seed Negroni', 'A bitter, stirred classic.', RecipeStatus::Published, RecipeDifficulty::Easy);
        $negroni->addCategory($classics);
        $this->ingredientLine($negroni, $gin, '30', IngredientUnit::Milliliter, 1);
        $this->ingredientLine($negroni, $campari, '30', IngredientUnit::Milliliter, 2);
        $this->ingredientLine($negroni, $vermouth, '30', IngredientUnit::Milliliter, 3);
        $negroni->recalculateContainsAlcohol();

        $limeSoda = $this->recipe($max, 'Seed Lime Soda', 'A bright zero-proof highball.', RecipeStatus::Published, RecipeDifficulty::Easy);
        $limeSoda->addCategory($zeroProof);
        $this->ingredientLine($limeSoda, $lime, '25', IngredientUnit::Milliliter, 1);
        $this->ingredientLine($limeSoda, $syrup, '15', IngredientUnit::Milliliter, 2);
        $this->ingredientLine($limeSoda, $soda, '120', IngredientUnit::Milliliter, 3);
        $limeSoda->recalculateContainsAlcohol();

        $draft = $this->recipe($admin, 'Seed Draft Martini', 'A private admin draft.', RecipeStatus::Draft, RecipeDifficulty::Medium);
        $draft->addCategory($classics);

        $this->entityManager->flush();

        $io->success('Development seed data is ready.');

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $roles
     */
    private function user(string $email, string $username, array $roles = []): User
    {
        $repository = $this->entityManager->getRepository(User::class);
        $user = $repository->findOneBy(['email' => $email]);
        if ($user instanceof User) {
            return $user;
        }

        $user = new User($email, $username, new \DateTimeImmutable('1990-01-01'));
        $user->setRoles($roles);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'very-secure-password'));

        $this->entityManager->persist($user);

        return $user;
    }

    private function category(string $name, string $description): Category
    {
        $repository = $this->entityManager->getRepository(Category::class);
        $category = $repository->findOneBy(['slug' => $this->slug($name)]);
        if ($category instanceof Category) {
            return $category;
        }

        $category = new Category();
        $category->setName($name);
        $category->setDescription($description);

        $this->entityManager->persist($category);

        return $category;
    }

    private function ingredient(string $name, bool $containsAlcohol = false): Ingredient
    {
        $repository = $this->entityManager->getRepository(Ingredient::class);
        $ingredient = $repository->findOneBy(['slug' => $this->slug($name)]);
        if ($ingredient instanceof Ingredient) {
            return $ingredient;
        }

        $ingredient = new Ingredient();
        $ingredient->setName($name);
        $ingredient->setContainsAlcohol($containsAlcohol);

        $this->entityManager->persist($ingredient);

        return $ingredient;
    }

    private function recipe(User $author, string $title, string $description, RecipeStatus $status, RecipeDifficulty $difficulty): Recipe
    {
        $repository = $this->entityManager->getRepository(Recipe::class);
        $recipe = $repository->findOneBy(['slug' => $this->slug($title)]);
        if ($recipe instanceof Recipe) {
            return $recipe;
        }

        $recipe = new Recipe();
        $recipe->setAuthor($author);
        $recipe->setTitle($title);
        $recipe->setDescription($description);
        $recipe->setDifficulty($difficulty);
        $recipe->setStatus($status);

        $this->entityManager->persist($recipe);

        return $recipe;
    }

    private function ingredientLine(Recipe $recipe, Ingredient $ingredient, string $quantity, IngredientUnit $unit, int $position): void
    {
        foreach ($recipe->getRecipeIngredients() as $recipeIngredient) {
            if ($recipeIngredient->getIngredient() === $ingredient) {
                return;
            }
        }

        $recipeIngredient = new RecipeIngredient();
        $recipeIngredient->setRecipe($recipe);
        $recipeIngredient->setIngredient($ingredient);
        $recipeIngredient->setQuantity($quantity);
        $recipeIngredient->setUnit($unit);
        $recipeIngredient->setPosition($position);
        $recipe->addRecipeIngredient($recipeIngredient);

        $this->entityManager->persist($recipeIngredient);
    }

    private function slug(string $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/', '-', trim(strtolower($value))));
    }
}
