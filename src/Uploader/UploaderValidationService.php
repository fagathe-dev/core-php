<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Uploader;

use Fagathe\CorePhp\File\FileTrait;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploaderValidationService
{
    use FileTrait;

    private array $allowedMimeTypes;
    private int $maxSize;

    public function __construct(
        array $allowedMimeTypes = UPLOAD_SUPPORTED_MIMES,
        int $maxSize = UPLOAD_MAX_FILESIZE
    ) {
        $this->allowedMimeTypes = $allowedMimeTypes;
        $this->maxSize = $maxSize;
    }

    /**
     * @param array $mimeTypes
     * 
     * @return self
     */
    public function setAllowedMimeTypes(array $mimeTypes): self
    {
        $this->allowedMimeTypes = $mimeTypes;
        return $this;
    }

    /**
     * @param int $maxSize
     * 
     * @return self
     */
    public function setMaxSize(int $maxSize): self
    {
        $this->maxSize = $maxSize;
        return $this;
    }

    /**
     * @param UploadedFile $file
     * 
     * @return bool
     */
    public function validate(UploadedFile $file): bool|array
    {
        $errors = [];

        if ($file->getSize() > $this->maxSize) {
            $errors[] = sprintf("Taille limite dépassée (%s max).", $this->formatFileSize($this->maxSize));
        }

        $mimeType = $file->getMimeType();
        if ($mimeType === null || !in_array($mimeType, $this->allowedMimeTypes, true)) {
            $errors[] = "Format de fichier non supporté.";
        }

        return empty($errors) ? true : $errors;
    }
}