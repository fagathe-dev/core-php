<?php

namespace Fagathe\CorePhp\File; // Ajusté pour Fagathe\CorePhp\Utils\File

final class MimeType
{
    // Constantes de MimeType
    // Les listes incluent les variantes renvoyées par fileinfo/libmagic (UploadedFile::getMimeType()),
    // qui diffèrent souvent des types "officiels" envoyés par le navigateur.
    public const ARCHIVE_MIMES = ['application/x-bzip', 'application/x-7z-compressed', 'application/zip', 'application/x-zip-compressed', 'application/x-bzip2', 'application/x-rar-compressed', 'application/x-rar', 'application/vnd.rar', 'application/x-tar', 'application/gzip', 'application/x-gzip'];
    public const AUDIO_MIMES = ['audio/aac', 'audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/webm', 'audio/x-mpeg-3', 'audio/mpeg3', 'audio/mp4', 'audio/x-m4a', 'audio/flac', 'audio/x-flac', 'audio/3gpp', 'audio/3gpp2', 'audio/ogg', 'audio/midi'];
    public const CODE_MIMES = ['text/css', 'text/html', 'text/javascript', 'application/javascript', 'application/json', 'application/xml', 'text/xml', 'application/x-httpd-php', 'application/x-php', 'text/x-php', 'text/x-python', 'text/x-script.python', 'text/x-java-source', 'text/x-java', 'text/x-csrc', 'text/x-c', 'text/x-c++src', 'text/x-c++'];
    public const IMAGE_MIMES = ['image/bmp', 'image/x-ms-bmp', 'image/webp', 'image/svg+xml', 'image/svg', 'image/tiff', 'image/png', 'image/gif', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/jpeg', 'image/avif', 'image/heic', 'image/heif', 'image/x-canon-cr2'];
    public const PDF_MIMES = ['application/pdf'];
    public const PRESENTATION_MIMES = ['application/vnd.oasis.opendocument.presentation', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'];
    public const SPREADSHEET_MIMES = ['application/vnd.oasis.opendocument.spreadsheet', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'application/csv'];
    public const WORD_PROCESSING_MIMES = ['text/csv', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/msword', 'application/rtf', 'text/rtf'];
    public const TEXTE_MIMES = ['application/vnd.oasis.opendocument.text', 'text/plain', 'text/markdown'];
    public const VIDEO_MIMES = ['video/mpeg', 'video/x-msvideo', 'video/msvideo', 'video/quicktime', 'video/webm', 'video/mp4', 'video/x-m4v', 'video/x-matroska', 'video/3gpp', 'video/3gpp2', 'video/ogg'];

    /**
     * @description Guess the mimetype of a file
     * @param string $filePath
     * 
     * @return string
     */
    public static function guessMimetype(string $filePath): string|false
    {
        return mime_content_type($filePath);
    }

    /**
     * @description Check if a valid archive file
     * @param string $filePath
     * 
     * @return bool
     */
    public static function isArchive(string $filePath): bool
    {
        $mime = self::guessMimetype($filePath);
        return $mime !== false && in_array($mime, self::ARCHIVE_MIMES, true);
    }

    /**
     * @description Check if a valid audio file
     * @param string $filePath
     * 
     * @return bool
     */
    public static function isAudio(string $filePath): bool
    {
        $mime = self::guessMimetype($filePath);
        return $mime !== false && in_array($mime, self::AUDIO_MIMES, true);
    }

    /**
     * @description Check if a valid code file
     * @param string $filePath
     * 
     * @return bool
     */
    public static function isCode(string $filePath): bool
    {
        $mime = self::guessMimetype($filePath);
        return $mime !== false && in_array($mime, self::CODE_MIMES, true);
    }

    /**
     * @description Check if a valid image file
     * @param string $filePath
     * 
     * @return bool
     */
    public static function isImage(string $filePath): bool
    {
        $mime = self::guessMimetype($filePath);
        return $mime !== false && in_array($mime, self::IMAGE_MIMES, true);
    }

    /**
     * @description Check if a valid PDF file
     * @param string $filePath
     * 
     * @return bool
     */
    public static function isPDF(string $filePath): bool
    {
        $mime = self::guessMimetype($filePath);
        return $mime !== false && in_array($mime, self::PDF_MIMES, true);
    }

    /**
     * @param string $filePath
     * 
     * @return bool
     */
    public static function isPresentation(string $filePath): bool
    {
        $mime = self::guessMimetype($filePath);
        return $mime !== false && in_array($mime, self::PRESENTATION_MIMES, true);
    }

    /**
     * @description Check if a valid spreadsheet file
     * @param string $filePath
     * 
     * @return bool
     */
    public static function isSpreadsheet(string $filePath): bool
    {
        $mime = self::guessMimetype($filePath);
        return $mime !== false && in_array($mime, self::SPREADSHEET_MIMES, true);
    }

    /**
     * @description Check if a valid word processing file
     * @param string $filePath
     * 
     * @return bool
     */
    public static function isWordProcessing(string $filePath): bool
    {
        $mime = self::guessMimetype($filePath);
        return $mime !== false && in_array($mime, self::WORD_PROCESSING_MIMES, true);
    }

    /**
     * @description Check if a valid video file
     * @param string $filePath
     * 
     * @return bool
     */
    public static function isVideo(string $filePath): bool
    {
        $mime = self::guessMimetype($filePath);
        return $mime !== false && in_array($mime, self::VIDEO_MIMES, true);
    }

    // Simplification pour l'exemple, mais la méthode getSupportedMimetypes est importante
    public static function getSupportedMimetypes(): array
    {
        return [
            ...self::ARCHIVE_MIMES,
            ...self::AUDIO_MIMES,
            ...self::CODE_MIMES,
            ...self::IMAGE_MIMES,
            ...self::PDF_MIMES,
            ...self::PRESENTATION_MIMES,
            ...self::SPREADSHEET_MIMES,
            ...self::WORD_PROCESSING_MIMES,
            ...self::TEXTE_MIMES,
            ...self::VIDEO_MIMES
        ];
    }
}
