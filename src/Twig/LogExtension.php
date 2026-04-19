<?php

namespace Fagathe\CorePhp\Twig;

use Fagathe\CorePhp\Logger\Log;
use Fagathe\CorePhp\Logger\LoggerTemplate;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class LogExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('generate_log', [$this, 'generateLog'], [
                'is_safe' => ['html'] // Indispensable pour que le HTML ne soit pas échappé
            ]),
        ];
    }

    /**
     * Génère le HTML pour un log donné.
     *
     * @param Log|null $log L'objet Log à afficher
     * @return string Le HTML généré
     */
    public function generateLog(?Log $log = null): string
    {
        if (!$log || is_null($log)) {
            return '';
        }

        // On instancie le template à la volée avec le log courant
        return (new LoggerTemplate($log))->generate();
    }
}