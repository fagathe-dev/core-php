<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Uploader;

/**
 * Exception personnalisée pour les erreurs liées à l'upload.
 * Permet de distinguer une erreur d'upload d'un crash système dans les try/catch.
 */
class FileUploadException extends \RuntimeException
{
}