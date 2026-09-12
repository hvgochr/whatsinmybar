<?php

namespace App\Tests\Upload;

use App\Command\CleanOrphanImagesCommand;
use App\Entity\User;
use App\Service\Upload\LocalImageStorage;
use App\Service\Upload\UploadLock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class CleanOrphanImagesCommandTest extends KernelTestCase
{
    public function testDryRunPreservesReferencedRecentAndUnknownFiles(): void
    {
        self::bootKernel();
        $directory = sys_get_temp_dir().'/orphan-test-'.bin2hex(random_bytes(8));
        mkdir($directory);
        try {
            $storage = new LocalImageStorage($directory, '/uploads/test', 1024, 'Test');
            $old = str_repeat('a', 32).'.png';
            $recent = str_repeat('b', 32).'.png';
            $referenced = str_repeat('c', 32).'.png';
            $partial = '.'.str_repeat('d', 32).'.png.tmp';
            foreach ([$old, $recent, $referenced, $partial, 'unknown.txt'] as $name) {
                file_put_contents($directory.'/'.$name, 'test');
                touch($directory.'/'.$name, time() - 48 * 3600);
            }
            touch($directory.'/'.$recent);
            symlink($directory.'/unknown.txt', $directory.'/'.str_repeat('e', 32).'.png');
            $em = self::getContainer()->get(EntityManagerInterface::class);
            $suffix = bin2hex(random_bytes(6));
            $user = new User($suffix.'@example.com', 'orphan_'.$suffix, new \DateTimeImmutable('1990-01-01'));
            $user->setPassword('unused');
            $user->setAvatarPath('/uploads/test/'.$referenced);
            $user->setDeletedAt(new \DateTimeImmutable());
            $em->persist($user);
            $em->flush();
            // An empty second store ensures each candidate is counted only once.
            $recipes = new LocalImageStorage($directory.'/recipes', '/uploads/recipes', 1024, 'Recipe');
            $tester = new CommandTester(new CleanOrphanImagesCommand($em, $storage, $recipes, new UploadLock($directory.'/.lock')));
            self::assertSame(0, $tester->execute(['--dry-run' => true]));
            self::assertStringContainsString('DRY-RUN /uploads/test/'.$old, $tester->getDisplay());
            self::assertStringContainsString('DRY-RUN /uploads/test/'.$partial, $tester->getDisplay());
            self::assertStringContainsString('2 eligible orphan(s)', $tester->getDisplay());
            self::assertStringNotContainsString($recent, $tester->getDisplay());
            self::assertStringNotContainsString($referenced, $tester->getDisplay());
            foreach ([$old, $recent, $referenced, $partial, 'unknown.txt'] as $name) {
                self::assertFileExists($directory.'/'.$name);
            }
            self::assertSame(2, $tester->execute(['--grace-hours' => '0']));
            self::assertSame(2, $tester->execute(['--dry-run' => true, '--delete' => true]));
        } finally {
            (new Filesystem())->remove($directory);
        }
    }
}
