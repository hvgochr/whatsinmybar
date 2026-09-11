<?php

namespace App\Tests\Command;

use App\Entity\Recipe;
use App\Enum\RecipeModerationStatus;
use App\Service\RecipePublicationValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedDevDataCommandTest extends KernelTestCase
{
    public function testSeedCreatesAndRepairsPublishedStepsIdempotently(): void
    {
        $kernel = self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();
        $connection->beginTransaction();

        try {
            foreach (['report', 'comment', 'favorite', 'recipe_ingredient', 'recipe_step', 'recipe_category', 'recipe', 'category', 'ingredient'] as $table) {
                $connection->executeStatement('DELETE FROM '.$table);
            }
            $tester = new CommandTester(new Application($kernel)->find('app:seed:dev'));
            $tester->execute([]);
            $tester->assertCommandIsSuccessful();
            $em->clear();
            $validator = self::getContainer()->get(RecipePublicationValidator::class);
            foreach (['seed-negroni', 'seed-lime-soda'] as $slug) {
                $recipe = $em->getRepository(Recipe::class)->findOneBy(['slug' => $slug]);
                self::assertInstanceOf(Recipe::class, $recipe);
                self::assertCount(2, $recipe->getSteps());
                $validator->validate($recipe);
                // Simulate old seed data and preserve an existing customized step.
                foreach ($recipe->getSteps()->toArray() as $step) {
                    if ('seed-negroni' === $slug && 1 === $step->getPosition()) {
                        $step->setInstruction('Keep this customized instruction.');
                    } else {
                        $recipe->removeStep($step);
                    }
                }
                $recipe->setModerationStatus(RecipeModerationStatus::Hidden);
            }
            $em->flush();
            $em->clear();
            $tester->execute([]);
            $tester->assertCommandIsSuccessful();
            $em->clear();
            foreach (['seed-negroni', 'seed-lime-soda'] as $slug) {
                $recipe = $em->getRepository(Recipe::class)->findOneBy(['slug' => $slug]);
                self::assertInstanceOf(Recipe::class, $recipe);
                self::assertSame([1, 2], $recipe->getSteps()->map(static fn ($step) => $step->getPosition())->toArray());
                self::assertSame(RecipeModerationStatus::Hidden, $recipe->getModerationStatus());
                $validator->validate($recipe);
                if ('seed-negroni' === $slug) {
                    self::assertSame('Keep this customized instruction.', $recipe->getSteps()->first()->getInstruction());
                }
            }
            $before = $connection->fetchAllAssociative('SELECT * FROM recipe_step ORDER BY id');
            $tester->execute([]);
            $tester->assertCommandIsSuccessful();
            self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM recipe_step ORDER BY id'));
        } finally {
            $connection->rollBack();
            $em->clear();
        }
    }
}
