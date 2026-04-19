<?php

namespace Fagathe\CorePhp\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CodeExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('ds_code', [$this, 'renderCode'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Fonction pour afficher du code Twig sans qu'il soit interprété
     * 
     * @param string $code Le code à afficher
     * @return string Le code avec les délimiteurs Twig et HTML échappés
     */
    public function renderCode(string $code): string
    {
        // Nettoyer le code : supprimer les espaces en début/fin
        $code = trim($code);

        // D'abord échapper le HTML pour éviter l'interprétation des balises
        $code = htmlspecialchars($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Puis remplacer les délimiteurs Twig pour éviter l'interprétation par Twig
        $replacements = [
            '{{' => '&#123;&#123;',
            '}}' => '&#125;&#125;',
            '{%' => '&#123;%',
            '%}' => '%&#125;',
            '{#' => '&#123;#',
            '#}' => '#&#125;'
        ];

        $escaped = str_replace(array_keys($replacements), array_values($replacements), $code);

        // Décoder seulement les entités Twig, pas les entités HTML
        $twigDecodings = [
            '&#123;&#123;' => '{&#123;',
            '&#125;&#125;' => '&#125;}',
            '&#123;%' => '{%',
            '%&#125;' => '%}',
            '&#123;#' => '{#',
            '#&#125;' => '#}'
        ];

        return str_replace(array_keys($twigDecodings), array_values($twigDecodings), $escaped);
    }
}