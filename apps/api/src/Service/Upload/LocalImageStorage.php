<?php

namespace App\Service\Upload;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class LocalImageStorage implements AvatarStorageInterface, RecipeImageStorageInterface
{
    public function __construct(
        private string $targetDirectory,
        private string $publicPathPrefix,
        private int $maxSizeBytes,
        private string $label,
        private int $maxDimension = 4096,
        private int $maxPixels = 8000000,
        private int $outputDimension = 1600,
    ) {
    }

    public function store(UploadedFile $file): string
    {
        if (!$file->isValid()) {
            throw new BadRequestHttpException($this->label.' upload failed.');
        }
        $size = $file->getSize();
        if (false === $size || $size <= 0 || $size > $this->maxSizeBytes) {
            throw new BadRequestHttpException($this->label.' file size is invalid.');
        }
        $data = file_get_contents($file->getPathname());
        $info = false === $data ? false : @getimagesizefromstring($data);
        if (false === $info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new BadRequestHttpException($this->label.' must be a JPEG, PNG, or WebP image.');
        }
        [$width, $height] = $info;
        if ($width < 1 || $height < 1 || $width > $this->maxDimension || $height > $this->maxDimension || $width * $height > $this->maxPixels) {
            throw new BadRequestHttpException($this->label.' image dimensions are excessive.');
        }

        // Reject even recoverable decoder warnings (e.g. truncated JPEG data).
        set_error_handler(static function (int $severity, string $message): never {
            throw new \ErrorException($message, 0, $severity);
        });
        try {
            $image = imagecreatefromstring($data);
        } catch (\ErrorException) {
            $image = false;
        } finally {
            restore_error_handler();
        }
        if (false === $image) {
            throw new BadRequestHttpException($this->label.' image cannot be decoded.');
        }

        $scale = min(1, $this->outputDimension / max($width, $height));
        $output = imagecreatetruecolor(max(1, (int) floor($width * $scale)), max(1, (int) floor($height * $scale)));
        imagealphablending($output, false);
        imagesavealpha($output, true);
        imagecopyresampled($output, $image, 0, 0, 0, 0, imagesx($output), imagesy($output), $width, $height);
        unset($image, $data);

        if (!is_dir($this->targetDirectory) && !mkdir($this->targetDirectory, 0775, true) && !is_dir($this->targetDirectory)) {
            throw new \RuntimeException('Unable to create upload directory.');
        }
        $extension = match ($info[2]) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            default => 'webp',
        };
        $filename = bin2hex(random_bytes(16)).'.'.$extension;
        $temporary = $this->targetDirectory.'/.'.$filename.'.tmp';
        try {
            $written = match ($extension) {
                'jpg' => imagejpeg($output, $temporary, 85),
                'png' => imagepng($output, $temporary, 6),
                default => imagewebp($output, $temporary, 85),
            };
            $outputSize = is_file($temporary) ? filesize($temporary) : false;
            if (!$written || false === $outputSize || 0 === $outputSize) {
                throw new \RuntimeException('Unable to encode image.');
            }
            if ($outputSize > $this->maxSizeBytes) {
                throw new BadRequestHttpException($this->label.' encoded file is too large.');
            }
            if (!rename($temporary, $this->targetDirectory.'/'.$filename)) {
                throw new \RuntimeException('Unable to publish image.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }

        return $this->publicPathPrefix.'/'.$filename;
    }

    public function remove(string $path): void
    {
        $file = $this->localPath($path);
        if (null !== $file && is_file($file) && !unlink($file)) {
            throw new \RuntimeException('Unable to remove image.');
        }
    }

    public function localPath(string $path): ?string
    {
        if (!str_starts_with($path, $this->publicPathPrefix.'/')) {
            return null;
        }
        $name = substr($path, strlen($this->publicPathPrefix) + 1);
        if (1 !== preg_match('/^(?:[a-f0-9]{32}\.(jpg|png|webp)|\.[a-f0-9]{32}\.(jpg|png|webp)\.tmp)$/D', $name) || is_link($this->targetDirectory.'/'.$name)) {
            return null;
        }

        return $this->targetDirectory.'/'.$name;
    }

    /** @return iterable<string, int> Public path => modification time, including incomplete writes. */
    public function files(): iterable
    {
        if (!is_dir($this->targetDirectory)) {
            return;
        }
        foreach (new \DirectoryIterator($this->targetDirectory) as $file) {
            if ($file->isLink() || !$file->isFile()) {
                continue;
            }
            $name = $file->getFilename();
            if (1 === preg_match('/^(?:[a-f0-9]{32}\.(jpg|png|webp)|\.[a-f0-9]{32}\.(jpg|png|webp)\.tmp)$/D', $name)) {
                yield $this->publicPathPrefix.'/'.$name => $file->getMTime();
            }
        }
    }
}
