<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer;

/**
 * Tokens des e-mails : valeurs du thème clair du Design System (sf-p05-design-system, public/css/ds-ui-kit.css).
 *
 * Les clients mail n'interprètent pas les variables CSS : les composants Twig lisent ces valeurs
 * via la globale `email_theme` (EmailThemeExtension). À tenir en phase avec le Design System.
 */
final class EmailTheme
{
    /**
     * Rôles de couleur : base (fond plein), on (texte sur base), container (fond doux), on_container (texte sur container).
     */
    public const array TOKENS = [
        // --ds-font-sans / --ds-font-mono, complétées de polices système pour les clients sans Inter
        'font_sans' => "Inter, Roboto, -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif",
        'font_mono' => "'Fira Code', Consolas, 'Courier New', monospace",

        // Surfaces (tonal elevation) : la carte blanche se détache de la page sans ombre
        'page' => '#f1f5f9',          // --ds-surface-container
        'surface' => '#ffffff',       // --ds-color-white
        'surface_low' => '#f8fafc',   // --ds-surface
        'text' => '#0f172b',          // --ds-color-on-surface
        'text_muted' => '#475569',    // --ds-color-on-surface-variant
        'outline' => '#64748b',       // --ds-color-outline (fonctionnelle, 3:1)
        'outline_variant' => '#cbd5e1', // --ds-color-outline-variant (séparateurs)
        'border' => '#e2e8f0',        // --ds-surface-container-highest (contour de carte)

        // Formes : --ds-radius-md est le rayon de base (boutons, cartes, alertes)
        'radius' => '12px',
        'radius_sm' => '8px',

        'roles' => [
            'primary' => ['base' => '#4b3fd8', 'on' => '#ffffff', 'container' => '#e5e0fa', 'on_container' => '#2b237a'],
            'secondary' => ['base' => '#525266', 'on' => '#ffffff', 'container' => '#dcdce8', 'on_container' => '#1e1e2a'],
            'tertiary' => ['base' => '#475569', 'on' => '#ffffff', 'container' => '#e2e8f0', 'on_container' => '#0f172b'],
            'success' => ['base' => '#15803d', 'on' => '#ffffff', 'container' => '#dcfce7', 'on_container' => '#14532d'],
            'warning' => ['base' => '#b45309', 'on' => '#ffffff', 'container' => '#fef3c7', 'on_container' => '#78350f'],
            'info' => ['base' => '#2563eb', 'on' => '#ffffff', 'container' => '#dbeafe', 'on_container' => '#1e3a8a'],
            'danger' => ['base' => '#dc2626', 'on' => '#ffffff', 'container' => '#fee2e2', 'on_container' => '#7f1d1d'],
        ],
    ];
}
