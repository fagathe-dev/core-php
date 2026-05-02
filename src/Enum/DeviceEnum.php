<?php

/**
 * Énumération des types d'appareils
 * 
 * Cette énumération définit les différents types d'appareils supportés
 * par le système de détection.
 * 
 * Inspiré du template GitHub avec les mêmes valeurs.
 * 
 * @author GitHub Copilot
 * @since 1.0.0
 * @version 1.0.0
 * @package Fagathe\CorePhp\Enum
 */

declare(strict_types=1);

namespace Fagathe\CorePhp\Enum;

/**
 * Énumération des types d'appareils
 */
enum DeviceEnum: string
{
    case Desktop = 'Desktop';
    case Mobile = 'Mobile';
    case Tablet = 'Tablet';
    case Terminal = 'Terminal / CLI';
    case Unknown = 'Unknown Device';

    /**
     * Obtient une représentation textuelle lisible
     * 
     * @return string Description du type d'appareil
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::Desktop => 'Ordinateur de bureau',
            self::Mobile => 'Téléphone mobile',
            self::Tablet => 'Tablette',
            self::Terminal => 'Terminal / CLI',
            self::Unknown => 'Appareil inconnu'
        };
    }

    /**
     * Obtient l'icône Bootstrap/Remix associée
     * 
     * @return string Classe CSS de l'icône
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::Desktop => 'ri-desktop-line',
            self::Mobile => 'ri-mobile-line',
            self::Tablet => 'ri-tablet-line',
            self::Terminal => 'ri-terminal-line',
            self::Unknown => 'ri-question-line'
        };
    }

    /**
     * Obtient la couleur Bootstrap associée
     * 
     * @return string Classe de couleur Bootstrap
     */
    public function getColorClass(): string
    {
        return match ($this) {
            self::Desktop => 'text-primary',
            self::Mobile => 'text-success',
            self::Tablet => 'text-info',
            self::Terminal => 'text-warning',
            self::Unknown => 'text-muted'
        };
    }

    /**
     * Vérifie si l'appareil est mobile (Mobile ou Tablet)
     * 
     * @return bool True si mobile ou tablette
     */
    public function isMobile(): bool
    {
        return in_array($this, [self::Mobile, self::Tablet], true);
    }

    /**
     * Obtient tous les types d'appareils disponibles
     * 
     * @return array<DeviceEnum> Tableau de tous les types
     */
    public static function getAllTypes(): array
    {
        return self::cases();
    }

    /**
     * Crée une instance depuis une chaîne de caractères
     * 
     * @param string $value Valeur à convertir
     * @return DeviceEnum Instance correspondante ou Unknown
     */
    public static function fromString(string $value): self
    {
        foreach (self::cases() as $case) {
            if (strcasecmp($case->value, $value) === 0) {
                return $case;
            }
        }

        return self::Unknown;
    }
}