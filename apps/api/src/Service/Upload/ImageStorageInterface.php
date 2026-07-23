<?php

namespace App\Service\Upload;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface ImageStorageInterface
{
    public function store(UploadedFile $file): string;
}
