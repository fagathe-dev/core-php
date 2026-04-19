<?php

namespace Fagathe\CorePhp\File; // Ajusté pour Fagathe\CorePhp\Utils\File

final class MimeType
{
    // Constantes de MimeType
    public const ARCHIVE_MIMES = ['application/x-bzip', 'application/x-7z-compressed', 'application/zip', 'application/x-bzip2', 'application/x-rar-compressed', 'application/x-tar'];
    public const AUDIO_MIMES = ['audio/aac', 'audio/x-wav', 'audio/webm', 'audio/x-mpeg-3', 'audio/mpeg3', 'audio/3gpp', 'audio/3gpp2', 'audio/ogg', 'audio/midi'];
    public const CODE_MIMES = ['text/css', 'text/html', 'text/javascript', 'application/javascript', 'application/json', 'application/xml', 'text/xml', 'application/x-httpd-php', 'text/x-python', 'text/x-java-source', 'text/x-csrc', 'text/x-c++src'];
    public const IMAGE_MIMES = ['image/bmp', 'image/webp', 'image/svg+xml', 'image/tiff', 'image/png', 'image/gif', 'image/x-icon', 'image/jpeg', 'image/x-canon-cr2'];
    public const PDF_MIMES = ['application/pdf'];
    public const PRESENTATION_MIMES = ['application/vnd.oasis.opendocument.presentation', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'];
    public const SPREADSHEET_MIMES = ['application/vnd.oasis.opendocument.spreadsheet'];
    public const WORD_PROCESSING_MIMES = ['text/csv'];
    public const TEXTE_MIMES = ['application/vnd.oasis.opendocument.text'];
    public const VIDEO_MIMES = ['video/mpeg', 'video/x-msvideo', 'video/quicktime', 'video/msvideo', 'video/webm', 'video/x-msvideo', 'video/mp4', 'video/3gpp', 'video/3gpp2', 'video/ogg'];

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
        return array_merge(
            self::ARCHIVE_MIMES,
            self::AUDIO_MIMES,
            self::CODE_MIMES,
            self::IMAGE_MIMES,
            self::PDF_MIMES,
            self::PRESENTATION_MIMES,
            self::SPREADSHEET_MIMES,
            self::WORD_PROCESSING_MIMES,
            self::TEXTE_MIMES,
            self::VIDEO_MIMES
        );
    }
}