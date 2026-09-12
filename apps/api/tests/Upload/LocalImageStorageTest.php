<?php

namespace App\Tests\Upload;

use App\Service\Upload\LocalImageStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class LocalImageStorageTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/image-test-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    #[DataProvider('formats')]
    public function testRealImagesAreReencodedAndResized(string $format): void
    {
        $source = $this->directory.'/source';
        $image = imagecreatetruecolor(20, 10);
        $encode = 'image'.$format;
        $encode($image, $source);
        file_put_contents($source, 'private-metadata-or-appended-script', FILE_APPEND);
        $storage = new LocalImageStorage($this->directory, '/uploads/test', 10000, 'Test', outputDimension: 8);
        $path = $storage->store(new UploadedFile($source, 'misleading.txt', 'text/plain', test: true));
        $result = file_get_contents($this->directory.'/'.basename($path));
        self::assertIsString($result);
        self::assertStringNotContainsString('private-metadata-or-appended-script', $result);
        $decoded = imagecreatefromstring($result);
        self::assertNotFalse($decoded);
        self::assertSame(8, imagesx($decoded));
        self::assertSame(4, imagesy($decoded));
        $storage->remove($path);
        self::assertFileDoesNotExist($this->directory.'/'.basename($path));
    }

    /** @return iterable<array{string}> */
    public static function formats(): iterable
    {
        yield ['jpeg'];
        yield ['png'];
        yield ['webp'];
    }

    #[DataProvider('invalidImages')]
    public function testInvalidImagesAreRejected(string $bytes): void
    {
        $storage = new LocalImageStorage($this->directory, '/uploads/test', 1024, 'Test');
        $this->expectException(BadRequestHttpException::class);
        $storage->store($this->upload($bytes));
    }

    /** @return iterable<array{string}> */
    public static function invalidImages(): iterable
    {
        yield 'empty' => [''];
        yield 'text' => ['not an image'];
        yield 'jpeg signature' => ["\xff\xd8\xff".'not an image'];
        yield 'png signature' => ["\x89PNG\r\n\x1a\n".'not an image'];
        yield 'webp signature' => ['RIFFxxxxWEBPnot an image'];
        yield 'corrupt PNG' => [base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')];
        yield 'oversized' => [str_repeat('a', 1025)];
        yield 'svg' => ['<svg xmlns="http://www.w3.org/2000/svg"/>'];
    }

    #[DataProvider('dimensionLimits')]
    public function testDimensionsAreCheckedBeforeDecoding(int $width, int $height): void
    {
        // Valid IHDR with excessive dimensions; no pixel allocation needed for this fixture.
        $header = 'IHDR'.pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        $bytes = "\x89PNG\r\n\x1a\n".pack('N', 13).$header.pack('N', crc32($header));
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('dimensions are excessive');
        (new LocalImageStorage($this->directory, '/uploads/test', 1024, 'Test'))->store($this->upload($bytes));
    }

    /** @return iterable<array{int, int}> */
    public static function dimensionLimits(): iterable
    {
        yield [4097, 1];
        yield [1, 4097];
        yield [3000, 3000];
    }

    public function testRemovalCannotEscapeStorageOrFollowSymlinks(): void
    {
        $storage = new LocalImageStorage($this->directory, '/uploads/test', 1024, 'Test');
        $source = $this->directory.'/keep';
        file_put_contents($source, 'keep');
        symlink($source, $this->directory.'/'.str_repeat('a', 32).'.png');
        $storage->remove('/uploads/test/../keep');
        $storage->remove('/uploads/other/keep');
        $storage->remove('/uploads/test/'.str_repeat('a', 32).'.png');
        self::assertFileExists($source);
        self::assertSame([], iterator_to_array($storage->files()));
    }

    private function upload(string $bytes): UploadedFile
    {
        $source = $this->directory.'/source';
        file_put_contents($source, $bytes);

        return new UploadedFile($source, 'test.png', 'image/png', test: true);
    }
}
