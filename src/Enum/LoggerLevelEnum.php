<?php

namespace Fagathe\CorePhp\Enum;

/**
 * Énumération des niveaux de log disponibles.
 * 
 * Définit les différents niveaux de gravité pour les logs,
 * du plus bas (Debug) au plus élevé (Critical).
 * 
 * @author Journal App
 */
enum LoggerLevelEnum: string
{
    case Debug = 'debug';
    case Info = 'info';
    case Notice = 'notice';
    case Warning = 'warning';
    case Error = 'error';
    case Critical = 'critical';

    /**
     * Retourne la couleur associée au niveau de log pour l'affichage.
     * 
     * @return string La classe CSS Bootstrap pour la couleur
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Debug => 'secondary',
            self::Info => 'info',
            self::Notice => 'primary',
            self::Warning => 'warning',
            self::Error, self::Critical => 'danger',
        };
    }

    /**
     * Retourne l'icône associée au niveau de log.
     * 
     * @return string La classe d'icône
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::Info, self::Debug => 'ri-information-line',
            self::Notice => 'ri-book-line',
            self::Warning => 'ri-alert-line',
            self::Error, self::Critical => 'ri-error-warning-line',
        };
    }

    /**
     * Retourne la priorité numérique du niveau.
     * 
     * @return int Plus le nombre est élevé, plus le niveau est critique
     */
    public function getPriority(): int
    {
        return match ($this) {
            self::Debug => 100,
            self::Info => 200,
            self::Notice => 250,
            self::Warning => 300,
            self::Error => 400,
            self::Critical => 500,
        };
    }

    /**
     * Vérifie si ce niveau est plus élevé qu'un autre niveau.
     * 
     * @param LoggerLevelEnum $level Le niveau à comparer
     * @return bool True si ce niveau est plus élevé
     */
    public function isHigherThan(LoggerLevelEnum $level): bool
    {
        return $this->getPriority() > $level->getPriority();
    }

    /**
     * Retourne tous les niveaux disponibles.
     * 
     * @return array<string, string> Tableau associatif [value => name]
     */
    public static function getChoices(): array
    {
        return [
            'Debug' => self::Debug->value,
            'Info' => self::Info->value,
            'Notice' => self::Notice->value,
            'Warning' => self::Warning->value,
            'Error' => self::Error->value,
            'Critical' => self::Critical->value,
        ];
    }
}