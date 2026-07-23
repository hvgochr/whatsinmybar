<?php

namespace App\Tests\Upload;

use App\Service\Upload\LocalImageStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class LocalImageStorageTest extends TestCase
{
    public function testImageIsStoredWithPublicPath(): void
    {
        $directory = sys_get_temp_dir().'/whatsinmybar-upload-test-'.bin2hex(random_bytes(4));
        $storage = new LocalImageStorage($directory, '/uploads/test', 1024, 'Test image');

        $publicPath = $storage->store($this->pngUpload());

        self::assertMatchesRegularExpression('#^/uploads/test/[a-f0-9]{32}\.png$#', $publicPath);
        self::assertFileExists($directory.'/'.basename($publicPath));
    }

    public function testUnsupportedImageIsRejected(): void
    {
        $storage = new LocalImageStorage(sys_get_temp_dir(), '/uploads/test', 1024, 'Test image');

        $this->expectException(BadRequestHttpException::class);

        $storage->store($this->textUpload());
    }

    public function testOversizedImageIsRejected(): void
    {
        $storage = new LocalImageStorage(sys_get_temp_dir(), '/uploads/test', 2, 'Test image');

        $this->expectException(BadRequestHttpException::class);

        $storage->store($this->pngUpload());
    }

    private function pngUpload(): UploadedFile
    {
        $filePath = tempnam(sys_get_temp_dir(), 'image-upload');
        self::assertIsString($filePath);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=', true);
        self::assertIsString($png);
        file_put_contents($filePath, $png);

        return new UploadedFile($filePath, 'image.png', 'image/png', test: true);
    }

    private function textUpload(): UploadedFile
    {
        $filePath = tempnam(sys_get_temp_dir(), 'image-upload');
        self::assertIsString($filePath);
        file_put_contents($filePath, 'not an image');

        return new UploadedFile($filePath, 'image.txt', 'text/plain', test: true);
    }
}
