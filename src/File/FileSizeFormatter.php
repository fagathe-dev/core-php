<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\File;

/**
 * Classe utilitaire pour formater la taille d'un fichier en un format lisible (Ko, Mo, Go).
 */
class FileSizeFormatter
{
    /**
     * Convertit la taille d'un fichier en octets vers un format plus lisible (B, Ko, Mo, Go).
     *
     * @param int|null $filesize La taille du fichier en octets.
     * @param int $precision Le nombre de décimales à conserver.
     * @return string La taille formatée.
     */
    public static function formatFileSize(?int $filesize, int $precision = 2): string
    {
        // Traiter le cas de zéro pour éviter les problèmes de logarithmes.
        if ($filesize === 0 || $filesize === null) {
            return '0 octets';
        }

        $units = ['octets', 'Ko', 'Mo', 'Go', 'To', 'Po', 'Eo', 'Zo', 'Yo'];
        $power = $filesize > 0 ? floor(log($filesize, 1024)) : 0;

        $size = $filesize / (1024 ** $power);

        // 1. Formatage avec la convention française (virgule décimale, espace pour les milliers)
        $formatted = number_format($size, $precision, ',', ' ');

        // 2. Élimination des zéros non significatifs à la fin, après la virgule.
        // On commence par enlever les zéros, puis la virgule s'il ne reste rien d'autre.
        $cleanFormatted = rtrim($formatted, '0');
        $cleanFormatted = rtrim($cleanFormatted, ',');

        return $cleanFormatted . ' ' . $units[$power];
    }
}