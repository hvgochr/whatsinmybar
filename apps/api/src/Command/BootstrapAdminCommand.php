<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Util\StrictDateParser;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'app:admin:bootstrap', description: 'Create or promote one administrator safely and idempotently.')]
final class BootstrapAdminCommand extends Command
{
    private const LOCK_KEY = 8_126_041_994;

    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Administrator email address')
            ->addOption('username', null, InputOption::VALUE_REQUIRED, 'Administrator public username')
            ->addOption('birth-date', null, InputOption::VALUE_REQUIRED, 'Birth date for a new account (YYYY-MM-DD)')
            ->addOption('password-env', null, InputOption::VALUE_REQUIRED, 'Environment variable containing the password for a new account', 'APP_ADMIN_PASSWORD')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower(trim((string) $input->getOption('email')));
        $username = trim((string) $input->getOption('username'));

        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('A valid --email is required.');

            return Command::INVALID;
        }
        if (mb_strlen($username) < 3 || mb_strlen($username) > 50 || 1 !== preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $io->error('--username must contain 3 to 50 letters, numbers or underscores.');

            return Command::INVALID;
        }

        $existing = $this->matchingUser($email, $username);
        if (false === $existing) {
            $io->error('The email or username is already assigned to a different account.');

            return Command::FAILURE;
        }

        $password = null;
        $birthDate = null;
        if (null === $existing) {
            $birthDate = StrictDateParser::yearMonthDay($input->getOption('birth-date'));
            if (null === $birthDate || $birthDate > new \DateTimeImmutable('today')) {
                $io->error('A valid, non-future --birth-date in YYYY-MM-DD format is required for a new account.');

                return Command::INVALID;
            }
            $password = $this->newAccountPassword($input, $io);
            if (null === $password) {
                return Command::INVALID;
            }
        }

        try {
            $action = $this->entityManager->wrapInTransaction(function () use ($birthDate, $email, $password, $username): string {
                $this->entityManager->getConnection()->executeQuery('SELECT pg_advisory_xact_lock(:lockKey)', ['lockKey' => self::LOCK_KEY]);
                $user = $this->matchingUser($email, $username);
                if (false === $user) {
                    throw new \RuntimeException('The email or username became assigned to a different account.');
                }

                if (!$user instanceof User) {
                    $user = new User($email, $username, $birthDate);
                    $user->setPassword($this->passwordHasher->hashPassword($user, $password));
                    $user->setRoles(['ROLE_ADMIN']);
                    $violations = $this->validator->validate($user);
                    if ($violations->count() > 0) {
                        throw new \RuntimeException((string) $violations);
                    }
                    $this->entityManager->persist($user);
                    $this->entityManager->flush();

                    return 'created';
                }

                if (null !== $user->getDeletedAt()) {
                    throw new \RuntimeException('The matching account is deleted and must be reviewed before promotion.');
                }
                if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                    return 'unchanged';
                }

                $roles = array_values(array_filter($user->getRoles(), static fn (string $role): bool => 'ROLE_USER' !== $role));
                $roles[] = 'ROLE_ADMIN';
                $user->setRoles(array_values(array_unique($roles)));
                $this->entityManager->flush();

                return 'promoted';
            });
        } catch (UniqueConstraintViolationException) {
            $io->error('The email or username was claimed concurrently. Rerun the command to verify the account.');

            return Command::FAILURE;
        } catch (\RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(match ($action) {
            'created' => 'Administrator account created.',
            'promoted' => 'Existing account promoted to administrator.',
            default => 'Administrator account is already active.',
        });

        return Command::SUCCESS;
    }

    private function matchingUser(string $email, string $username): User|false|null
    {
        $byEmail = $this->users->findOneBy(['email' => $email]);
        $byUsername = $this->users->findOneBy(['username' => $username]);
        if ($byEmail instanceof User && $byUsername instanceof User && $byEmail !== $byUsername) {
            return false;
        }

        $user = $byEmail ?? $byUsername;
        if (!$user instanceof User) {
            return null;
        }

        return $user->getEmail() === $email && $user->getUsername() === $username ? $user : false;
    }

    private function newAccountPassword(InputInterface $input, SymfonyStyle $io): ?string
    {
        $environmentName = trim((string) $input->getOption('password-env'));
        if (1 !== preg_match('/^[A-Z][A-Z0-9_]*$/', $environmentName)) {
            $io->error('--password-env must be an uppercase environment variable name.');

            return null;
        }

        $environmentValue = $_SERVER[$environmentName] ?? $_ENV[$environmentName] ?? getenv($environmentName);
        $password = is_string($environmentValue) ? $environmentValue : '';
        if ('' === $password && $input->isInteractive()) {
            $password = (string) $io->askHidden('New administrator password');
        }

        if (mb_strlen($password) < 12 || mb_strlen($password) > 4096) {
            $io->error(sprintf('A 12 to 4096 character password is required through %s or hidden interactive input.', $environmentName));

            return null;
        }

        return $password;
    }
}
