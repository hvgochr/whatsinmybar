<?php

namespace App\Tests\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class BootstrapAdminCommandTest extends KernelTestCase
{
    public function testBootstrapCreatesAdministratorWithoutChangingPasswordOnRerun(): void
    {
        $kernel = self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $email = 'bootstrap-'.$suffix.'@example.com';
        $username = 'bootstrap_'.$suffix;
        $environmentName = 'TEST_BOOTSTRAP_ADMIN_PASSWORD';
        $_SERVER[$environmentName] = $_ENV[$environmentName] = 'unique-secure-password';

        try {
            $tester = new CommandTester((new Application($kernel))->find('app:admin:bootstrap'));
            $options = ['--email' => $email, '--username' => $username, '--birth-date' => '1990-01-01', '--password-env' => $environmentName];
            self::assertSame(Command::SUCCESS, $tester->execute($options, ['interactive' => false]));
            $entityManager->clear();
            $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            self::assertInstanceOf(User::class, $user);
            self::assertContains('ROLE_ADMIN', $user->getRoles());
            self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'unique-secure-password'));
            $passwordHash = $user->getPassword();

            $_SERVER[$environmentName] = $_ENV[$environmentName] = 'different-secure-password';
            self::assertSame(Command::SUCCESS, $tester->execute($options, ['interactive' => false]));
            $entityManager->clear();
            $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            self::assertInstanceOf(User::class, $user);
            self::assertSame($passwordHash, $user->getPassword());
            self::assertStringContainsString('already active', $tester->getDisplay());
        } finally {
            unset($_SERVER[$environmentName], $_ENV[$environmentName]);
        }
    }

    public function testBootstrapPromotesExactExistingAccountWithoutPassword(): void
    {
        $kernel = self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $user = new User('promote-'.$suffix.'@example.com', 'promote_'.$suffix, new \DateTimeImmutable('1990-01-01'));
        $user->setPassword('existing-hash');
        $entityManager->persist($user);
        $entityManager->flush();

        $tester = new CommandTester((new Application($kernel))->find('app:admin:bootstrap'));
        self::assertSame(Command::SUCCESS, $tester->execute([
            '--email' => $user->getEmail(),
            '--username' => $user->getUsername(),
        ], ['interactive' => false]));

        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertSame('existing-hash', $user->getPassword());
    }

    public function testBootstrapRejectsMissingPasswordAndIdentifierCollision(): void
    {
        $kernel = self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $existing = new User('collision-'.$suffix.'@example.com', 'collision_'.$suffix, new \DateTimeImmutable('1990-01-01'));
        $existing->setPassword('existing-hash');
        $entityManager->persist($existing);
        $entityManager->flush();

        $tester = new CommandTester((new Application($kernel))->find('app:admin:bootstrap'));
        self::assertSame(Command::FAILURE, $tester->execute([
            '--email' => $existing->getEmail(),
            '--username' => 'different_'.$suffix,
        ], ['interactive' => false]));
        self::assertStringContainsString('different account', $tester->getDisplay());

        self::assertSame(Command::INVALID, $tester->execute([
            '--email' => 'missing-'.$suffix.'@example.com',
            '--username' => 'missing_'.$suffix,
            '--birth-date' => '1990-01-01',
            '--password-env' => 'UNSET_BOOTSTRAP_PASSWORD',
        ], ['interactive' => false]));
        self::assertStringContainsString('12 to 4096', $tester->getDisplay());
    }
}
