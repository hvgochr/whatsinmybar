<?php

namespace App\Command;

use App\Entity\Recipe;
use App\Entity\User;
use App\Service\Upload\AvatarStorageInterface;
use App\Service\Upload\RecipeImageStorageInterface;
use App\Service\Upload\UploadLock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:uploads:clean', description: 'List orphan images; deletion requires --delete. Back up uploads first.')]
final class CleanOrphanImagesCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly AvatarStorageInterface $avatars, private readonly RecipeImageStorageInterface $recipes, private readonly UploadLock $lock)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Explicitly list only (the default).');
        $this->addOption('delete', null, InputOption::VALUE_NONE, 'Delete eligible orphans.');
        $this->addOption('grace-hours', null, InputOption::VALUE_REQUIRED, 'Minimum age in hours (at least 1).', '24');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $hours = filter_var($input->getOption('grace-hours'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 87600]]);
        if (false === $hours || ($input->getOption('dry-run') && $input->getOption('delete'))) {
            $output->writeln('<error>Use a grace period of 1–87600 hours; --dry-run and --delete are mutually exclusive.</error>');

            return Command::INVALID;
        }
        $delete = (bool) $input->getOption('delete');
        $this->lock->run(true, function () use ($hours, $delete, $output): void {
            // Include soft-deleted and hidden entities: they can be restored.
            $references = [];
            foreach ([[User::class, 'avatarPath'], [Recipe::class, 'imagePath']] as [$class, $field]) {
                foreach ($this->entityManager->createQuery('SELECT e.'.$field.' AS path FROM '.$class.' e WHERE e.'.$field.' IS NOT NULL')->getArrayResult() as $row) {
                    $references[$row['path']] = true;
                }
            }
            $cutoff = time() - $hours * 3600;
            $count = 0;
            foreach ([$this->avatars, $this->recipes] as $storage) {
                foreach ($storage->files() as $path => $modifiedAt) {
                    if ($modifiedAt >= $cutoff || isset($references[$path])) {
                        continue;
                    }
                    $output->writeln(($delete ? 'DELETE ' : 'DRY-RUN ').$path);
                    if ($delete) {
                        $storage->remove($path);
                    }
                    ++$count;
                }
            }
            $output->writeln(sprintf('%d eligible orphan(s).', $count));
        });

        return Command::SUCCESS;
    }
}
