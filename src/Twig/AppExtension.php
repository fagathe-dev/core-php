<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            // On enregistre un nouveau filtre Twig nommé 'typeof'
            new TwigFilter('typeof', [$this, 'gettype']),
        ];
    }

    /**
     * Retourne le type de la variable en utilisant la fonction PHP gettype().
     * @param mixed $var La variable à inspecter.
     * @return string Le type de la variable.
     */
    public function gettype(mixed $var): string
    {
        return \gettype($var);
    }
}