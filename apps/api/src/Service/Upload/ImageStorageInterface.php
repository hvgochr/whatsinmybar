<?php

namespace App\Service\Upload;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface ImageStorageInterface
{
    public function remove(string $path): void;

    public function localPath(string $path): ?string;

    /** @return iterable<string, int> */
    public function files(): iterable;

    public function store(UploadedFile $file): string;
}
