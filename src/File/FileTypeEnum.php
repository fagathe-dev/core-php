<?php

namespace Fagathe\CorePhp\File;

use Fagathe\CorePhp\File\MimeType;

enum FileTypeEnum: string
{
    case Archive = 'Archive';
    case Audio = 'Audio';
    case Code = 'Code';
    case Image = 'Image';
    case PDF = 'PDF';
    case Presentation = 'Présentation';
    case Tableur = 'Tableur';
    case Traitement_De_Texte = 'Traitement de texte';
    case Texte = 'Texte';
    case Video = 'Vidéo';

    public static function matchMime(string $mimeType): ?string
    {
        return match (true) {
            in_array($mimeType, MimeType::ARCHIVE_MIMES) => static::Archive->value,
            in_array($mimeType, MimeType::AUDIO_MIMES) => static::Audio->value,
            in_array($mimeType, MimeType::CODE_MIMES) => static::Code->value,
            in_array($mimeType, MimeType::IMAGE_MIMES) => static::Image->value,
            in_array($mimeType, MimeType::PDF_MIMES) => static::PDF->value,
            in_array($mimeType, MimeType::PRESENTATION_MIMES) => static::Presentation->value,
            in_array($mimeType, MimeType::SPREADSHEET_MIMES) => static::Tableur->value,
            in_array($mimeType, MimeType::WORD_PROCESSING_MIMES) => static::Traitement_De_Texte->value,
            in_array($mimeType, MimeType::TEXTE_MIMES) => static::Texte->value,
            in_array($mimeType, MimeType::VIDEO_MIMES) => static::Video->value,
            default => null
        };
    }

    /**
     * Retourne toutes les valeurs lisibles de l'Enum.
     */
    public static function getSupportedValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}