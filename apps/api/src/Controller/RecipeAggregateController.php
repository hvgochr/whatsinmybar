<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Ingredient;
use App\Entity\Recipe;
use App\Entity\RecipeIngredient;
use App\Entity\RecipeStep;
use App\Entity\User;
use App\Enum\IngredientUnit;
use App\Enum\RecipeDifficulty;
use App\Enum\RecipeStatus;
use App\Repository\CategoryRepository;
use App\Repository\IngredientRepository;
use App\Repository\RecipeRepository;
use App\Security\RecipeAccess;
use App\Service\RecipePublicationValidator;
use App\Util\SlugNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RecipeAggregateController extends AbstractController
{
    public function __construct(
        private readonly RecipeRepository $recipeRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly IngredientRepository $ingredientRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly RecipePublicationValidator $publicationValidator,
    ) {
    }

    #[Route('/api/recipes/aggregate', name: 'api_recipe_aggregate_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }
        $this->denyAccessUnlessGranted('ROLE_USER');

        $recipe = new Recipe();
        $recipe->setAuthor($user);

        return $this->save($recipe, $request, JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/recipes/{slug}/aggregate', name: 'api_recipe_aggregate_update', methods: ['PUT'])]
    public function update(string $slug, Request $request): JsonResponse
    {
        $recipe = $this->recipeRepository->findOneBy(['slug' => $slug]);
        if (!$recipe instanceof Recipe || null !== $recipe->getDeletedAt()) {
            throw $this->createNotFoundException('Recipe not found.');
        }

        $this->denyAccessUnlessGranted(RecipeAccess::Manage, $recipe);

        return $this->save($recipe, $request);
    }

    private function save(Recipe $recipe, Request $request, int $status = JsonResponse::HTTP_OK): JsonResponse
    {
        $payload = $this->decodeJson($request);
        $violations = $this->validator->validate($payload, $this->aggregateConstraint());
        if ($violations->count() > 0) {
            return $this->validationErrorResponse($violations);
        }

        $resolved = $this->resolveRelations($payload);
        if (null === $recipe->getId()) {
            $slug = SlugNormalizer::normalize($payload['title']);
            if ('' === $slug || 1 !== preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
                $resolved['errors'][] = ['property' => '[title]', 'message' => 'Title must produce a valid recipe slug.'];
            } elseif ($this->recipeRepository->findOneBy(['slug' => $slug]) instanceof Recipe) {
                $resolved['errors'][] = ['property' => '[title]', 'message' => 'A recipe with this title already exists.'];
            }
        }
        if ([] !== $resolved['errors']) {
            return $this->validationErrorsResponse($resolved['errors']);
        }

        $steps = $this->steps($payload['steps']);
        $ingredients = $this->ingredients($payload['ingredients'], $resolved['ingredients']);

        // Build an independent proposed aggregate before touching managed collections.
        $proposed = new Recipe();
        $proposed->setAuthor($recipe->getAuthor());
        $proposed->setTitle($payload['title']);
        if (null !== $recipe->getId()) {
            $proposed->setSlug($recipe->getSlug());
        }
        $proposed->setDescription($payload['description']);
        $proposed->setDifficulty(RecipeDifficulty::from($payload['difficulty']));
        $proposed->setPreparationTimeMinutes($payload['preparationTimeMinutes']);
        $proposed->setServings($payload['servings']);
        $proposed->setContainsAlcoholOverride($recipe->getContainsAlcoholOverride());
        foreach ($steps as $step) {
            $proposed->addStep($step);
        }
        foreach ($ingredients as $ingredient) {
            $proposed->addRecipeIngredient($ingredient);
        }
        $this->denyAccessUnlessGranted(RecipeAccess::Manage, $proposed);
        if (RecipeStatus::Published === $recipe->getStatus()) {
            $this->publicationValidator->validate($proposed);
        }

        $this->entityManager->wrapInTransaction(function () use ($recipe, $payload, $resolved, $steps, $ingredients): void {
            $recipe->setTitle($payload['title']);
            $recipe->setDescription($payload['description']);
            $recipe->setDifficulty(RecipeDifficulty::from($payload['difficulty']));
            $recipe->setPreparationTimeMinutes($payload['preparationTimeMinutes']);
            $recipe->setServings($payload['servings']);

            foreach ($recipe->getCategories()->toArray() as $category) {
                $recipe->removeCategory($category);
            }
            foreach ($resolved['categories'] as $category) {
                $recipe->addCategory($category);
            }

            $hasExistingParts = !$recipe->getSteps()->isEmpty() || !$recipe->getRecipeIngredients()->isEmpty();
            foreach ($recipe->getSteps()->toArray() as $step) {
                $recipe->removeStep($step);
            }
            foreach ($recipe->getRecipeIngredients()->toArray() as $ingredient) {
                $recipe->removeRecipeIngredient($ingredient);
            }
            $recipe->getSteps()->clear();
            $recipe->getRecipeIngredients()->clear();

            $this->entityManager->persist($recipe);

            // Delete old positions before inserting replacements. Both flushes stay in
            // this transaction, so a later failure restores the complete old aggregate.
            if ($hasExistingParts) {
                $this->entityManager->flush();
            }

            foreach ($steps as $step) {
                $recipe->addStep($step);
            }
            foreach ($ingredients as $ingredient) {
                $recipe->addRecipeIngredient($ingredient);
            }
        });

        return $this->json($recipe, $status, [], ['groups' => ['recipe:read']]);
    }

    private function aggregateConstraint(): Assert\Collection
    {
        return new Assert\Collection(
            fields: [
                'title' => new Assert\Required([
                    new Assert\NotNull(),
                    new Assert\Type('string'),
                    new Assert\NotBlank(),
                    new Assert\Length(max: 160),
                ]),
                'description' => new Assert\Required([
                    new Assert\NotNull(),
                    new Assert\Type('string'),
                    new Assert\NotBlank(),
                    new Assert\Length(max: 5000),
                ]),
                'difficulty' => new Assert\Required([
                    new Assert\NotNull(),
                    new Assert\Type('string'),
                    new Assert\Choice(choices: array_column(RecipeDifficulty::cases(), 'value')),
                ]),
                'preparationTimeMinutes' => new Assert\Required([
                    new Assert\NotNull(),
                    new Assert\Type('integer'),
                    new Assert\Positive(),
                    new Assert\LessThanOrEqual(2147483647),
                ]),
                'servings' => new Assert\Required([
                    new Assert\NotNull(),
                    new Assert\Type('integer'),
                    new Assert\Positive(),
                    new Assert\LessThanOrEqual(2147483647),
                ]),
                'categories' => new Assert\Required([
                    new Assert\NotNull(),
                    new Assert\Type('array'),
                    $this->listConstraint(),
                    new Assert\Count(max: 20),
                    new Assert\All([
                        new Assert\NotNull(),
                        new Assert\Type('string'),
                        new Assert\Regex('/^\/api\/categories\/[a-z0-9]+(?:-[a-z0-9]+)*$/'),
                    ]),
                ]),
                'steps' => new Assert\Required([
                    new Assert\NotNull(),
                    new Assert\Type('array'),
                    $this->listConstraint(),
                    new Assert\Count(min: 1, max: 100),
                    new Assert\All([
                        new Assert\NotNull(),
                        new Assert\Type('array'),
                        new Assert\Collection(
                            fields: [
                                'instruction' => new Assert\Required([
                                    new Assert\NotNull(),
                                    new Assert\Type('string'),
                                    new Assert\NotBlank(),
                                    new Assert\Length(max: 2000),
                                ]),
                            ],
                            allowExtraFields: false,
                        ),
                    ]),
                ]),
                'ingredients' => new Assert\Required([
                    new Assert\NotNull(),
                    new Assert\Type('array'),
                    $this->listConstraint(),
                    new Assert\Count(min: 1, max: 100),
                    new Assert\All([
                        new Assert\NotNull(),
                        new Assert\Type('array'),
                        new Assert\Collection(
                            fields: [
                                'ingredient' => new Assert\Required([
                                    new Assert\NotNull(),
                                    new Assert\Type('string'),
                                    new Assert\Regex('/^\/api\/ingredients\/[a-z0-9]+(?:-[a-z0-9]+)*$/'),
                                ]),
                                'quantity' => new Assert\Required([
                                    new Assert\NotNull(),
                                    new Assert\Type('string'),
                                    new Assert\Regex('/^(?:0|[1-9]\d{0,5})(?:\.\d{1,2})?$/'),
                                    new Assert\Positive(),
                                ]),
                                'unit' => new Assert\Required([
                                    new Assert\NotNull(),
                                    new Assert\Type('string'),
                                    new Assert\Choice(choices: array_column(IngredientUnit::cases(), 'value')),
                                ]),
                                'note' => new Assert\Optional([
                                    new Assert\AtLeastOneOf([
                                        new Assert\IsNull(),
                                        new Assert\Type('string'),
                                    ]),
                                    new Assert\Length(max: 1000),
                                ]),
                            ],
                            allowExtraFields: false,
                        ),
                    ]),
                ]),
            ],
            allowExtraFields: false,
        );
    }

    private function listConstraint(): Assert\Callback
    {
        return new Assert\Callback(static function (mixed $value, ExecutionContextInterface $context): void {
            if (is_array($value) && !array_is_list($value)) {
                $context->buildViolation('This value must be a JSON list.')->addViolation();
            }
        });
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{categories: list<Category>, ingredients: list<Ingredient>, errors: list<array{property: string, message: string}>}
     */
    private function resolveRelations(array $payload): array
    {
        $categories = [];
        $ingredients = [];
        $errors = [];

        foreach ($payload['categories'] as $index => $iri) {
            $category = $this->categoryRepository->findOneBy(['slug' => basename($iri)]);
            if (!$category instanceof Category) {
                $errors[] = ['property' => sprintf('[categories][%d]', $index), 'message' => 'Category not found.'];
                continue;
            }

            $categories[$category->getSlug()] = $category;
        }

        foreach ($payload['ingredients'] as $index => $item) {
            $ingredient = $this->ingredientRepository->findOneBy(['slug' => basename($item['ingredient'])]);
            if (!$ingredient instanceof Ingredient) {
                $errors[] = ['property' => sprintf('[ingredients][%d][ingredient]', $index), 'message' => 'Ingredient not found.'];
                continue;
            }

            $ingredients[] = $ingredient;
        }

        return [
            'categories' => array_values($categories),
            'ingredients' => $ingredients,
            'errors' => $errors,
        ];
    }

    /**
     * @param list<array{instruction: string}> $payload
     *
     * @return list<RecipeStep>
     */
    private function steps(array $payload): array
    {
        return array_map(static function (array $item, int $index): RecipeStep {
            $step = new RecipeStep();
            $step->setPosition($index + 1);
            $step->setInstruction($item['instruction']);

            return $step;
        }, $payload, array_keys($payload));
    }

    /**
     * @param list<array{quantity: string, unit: string, note?: string}> $payload
     * @param list<Ingredient>                                           $resolvedIngredients
     *
     * @return list<RecipeIngredient>
     */
    private function ingredients(array $payload, array $resolvedIngredients): array
    {
        return array_map(static function (array $item, int $index) use ($resolvedIngredients): RecipeIngredient {
            $recipeIngredient = new RecipeIngredient();
            $recipeIngredient->setIngredient($resolvedIngredients[$index]);
            $recipeIngredient->setQuantity($item['quantity']);
            $recipeIngredient->setUnit(IngredientUnit::from($item['unit']));
            $recipeIngredient->setPosition($index + 1);
            $recipeIngredient->setNote($item['note'] ?? null);

            return $recipeIngredient;
        }, $payload, array_keys($payload));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(Request $request): array
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Invalid JSON body.');
        }

        if (!is_array($payload) || array_is_list($payload)) {
            throw new BadRequestHttpException('Expected a JSON object.');
        }

        return $payload;
    }

    private function validationErrorResponse(ConstraintViolationListInterface $violations): JsonResponse
    {
        $errors = [];

        foreach ($violations as $violation) {
            $errors[] = [
                'property' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
            ];
        }

        return $this->validationErrorsResponse($errors);
    }

    /**
     * @param list<array{property: string, message: string}> $errors
     */
    private function validationErrorsResponse(array $errors): JsonResponse
    {
        return $this->json([
            'error' => [
                'status' => JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
                'code' => 'validation_failed',
                'message' => 'Validation failed.',
                'violations' => $errors,
            ],
            'errors' => $errors,
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
