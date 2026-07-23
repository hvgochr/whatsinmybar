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
    ) {
    }

    public function store(UploadedFile $file): string
    {
        $size = $file->getSize();
        if (false === $size || $size <= 0 || $size > $this->maxSizeBytes) {
            throw new BadRequestHttpException(sprintf('%s file size is invalid.', $this->label));
        }

        $extension = $this->extensionForImage($file);
        if (null === $extension) {
            throw new BadRequestHttpException(sprintf('%s must be a JPEG, PNG, or WebP image.', $this->label));
        }

        if (!is_dir($this->targetDirectory) && !mkdir($this->targetDirectory, 0775, true) && !is_dir($this->targetDirectory)) {
            throw new \RuntimeException(sprintf('Unable to create upload directory "%s".', $this->targetDirectory));
        }

        $filename = sprintf('%s.%s', bin2hex(random_bytes(16)), $extension);
        $file->move($this->targetDirectory, $filename);

        return rtrim($this->publicPathPrefix, '/').'/'.$filename;
    }

    private function extensionForImage(UploadedFile $file): ?string
    {
        $signature = file_get_contents($file->getPathname(), false, null, 0, 12);
        if (!is_string($signature)) {
            return null;
        }

        if (str_starts_with($signature, "\xFF\xD8\xFF")) {
            return 'jpg';
        }

        if (str_starts_with($signature, "\x89PNG\x0D\x0A\x1A\x0A")) {
            return 'png';
        }

        if (str_starts_with($signature, 'RIFF') && 'WEBP' === substr($signature, 8, 4)) {
            return 'webp';
        }

        return null;
    }
}
