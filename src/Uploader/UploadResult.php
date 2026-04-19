<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Uploader;

/**
 * DTO retourné par UploaderService::upload().
 * Contient les métadonnées définitives du fichier après traitement (upload standard ou conversion CR2).
 */
final class UploadResult
{
    public function __construct(
        public readonly string $relativePath,
        public readonly string $originalName,
        public readonly string $newName,
        public readonly int $size,
        public readonly string $mimeType,
        public readonly string $extension,
    ) {
    }
}
