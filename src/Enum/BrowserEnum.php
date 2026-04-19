<?php

/**
 * Énumération des navigateurs web
 * 
 * Cette énumération définit les différents navigateurs supportés
 * par le système de détection.
 * 
 * Inspiré du template GitHub avec les mêmes valeurs.
 * 
 * @author GitHub Copilot
 * @since 1.0.0
 * @version 1.0.0
 * @package Fagathe\CorePhp\Utils
 */

declare(strict_types=1);

namespace Fagathe\CorePhp\Enum;

/**
 * Énumération des navigateurs web
 */
enum BrowserEnum: string
{
    case Chrome = 'Chrome';
    case Firefox = 'Firefox';
    case Safari = 'Safari';
    case Edge = 'Edge';
    case Opera = 'Opera';
    case Unknown = 'Unknown Browser';

    /**
     * Obtient une représentation textuelle lisible
     * 
     * @return string Description du navigateur
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::Chrome => 'Google Chrome',
            self::Firefox => 'Mozilla Firefox',
            self::Safari => 'Apple Safari',
            self::Edge => 'Microsoft Edge',
            self::Opera => 'Opera Browser',
            self::Unknown => 'Navigateur inconnu'
        };
    }

    /**
     * Obtient l'icône associée au navigateur
     * 
     * @return string Classe CSS de l'icône
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::Chrome => 'ri-chrome-line',
            self::Firefox => 'ri-firefox-line',
            self::Safari => 'ri-safari-line',
            self::Edge => 'ri-edge-line',
            self::Opera => 'ri-opera-line',
            self::Unknown => 'ri-global-line'
        };
    }

    /**
     * Obtient la couleur associée au navigateur
     * 
     * @return string Classe de couleur Bootstrap
     */
    public function getColorClass(): string
    {
        return match ($this) {
            self::Chrome => 'text-warning',
            self::Firefox => 'text-danger',
            self::Safari => 'text-info',
            self::Edge => 'text-primary',
            self::Opera => 'text-success',
            self::Unknown => 'text-muted'
        };
    }

    /**
     * Vérifie si le navigateur est basé sur Chromium
     * 
     * @return bool True si basé sur Chromium
     */
    public function isChromiumBased(): bool
    {
        return in_array($this, [self::Chrome, self::Edge, self::Opera], true);
    }

    /**
     * Vérifie si le navigateur supporte les dernières fonctionnalités web
     * 
     * @return bool True si navigateur moderne
     */
    public function isModern(): bool
    {
        return $this !== self::Unknown;
    }

    /**
     * Obtient tous les navigateurs disponibles
     * 
     * @return array<BrowserEnum> Tableau de tous les navigateurs
     */
    public static function getAllBrowsers(): array
    {
        return self::cases();
    }

    /**
     * Obtient les navigateurs principaux (sans Unknown)
     * 
     * @return array<BrowserEnum> Tableau des navigateurs principaux
     */
    public static function getMainBrowsers(): array
    {
        return array_filter(self::cases(), fn(self $browser) => $browser !== self::Unknown);
    }

    /**
     * Crée une instance depuis une chaîne de caractères
     * 
     * @param string $value Valeur à convertir
     * @return BrowserEnum Instance correspondante ou Unknown
     */
    public static function fromString(string $value): self
    {
        $value = trim($value);

        foreach (self::cases() as $case) {
            if (strcasecmp($case->value, $value) === 0) {
                return $case;
            }
        }

        // Vérifications supplémentaires pour les variations de noms
        $valueLower = strtolower($value);
        return match (true) {
            str_contains($valueLower, 'chrome') => self::Chrome,
            str_contains($valueLower, 'firefox') => self::Firefox,
            str_contains($valueLower, 'safari') => self::Safari,
            str_contains($valueLower, 'edge') || str_contains($valueLower, 'edg') => self::Edge,
            str_contains($valueLower, 'opera') => self::Opera,
            default => self::Unknown
        };
    }

    /**
     * Obtient les statistiques d'usage approximatives (données fictives pour démonstration)
     * 
     * @return float Pourcentage d'usage approximatif
     */
    public function getMarketShare(): float
    {
        return match ($this) {
            self::Chrome => 65.0,
            self::Safari => 18.5,
            self::Edge => 9.0,
            self::Firefox => 5.5,
            self::Opera => 1.5,
            self::Unknown => 0.5
        };
    }
}