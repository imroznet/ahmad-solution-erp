<?php

namespace App\Company;

final class StoredUpload
{
    public function __construct(
        public string $path,
        public string $originalFilename,
        public string $mimeType,
        public string $extension,
        public int $sizeBytes,
    ) {}
}
