<?php

namespace App\Service\Upload;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface AvatarStorageInterface
{
    public function store(UploadedFile $file): string;
}
