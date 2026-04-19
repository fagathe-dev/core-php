<?php

namespace Fagathe\CorePhp\Trait;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Trait pour la gestion centralisée de la génération d'URL.
 */
trait UrlGeneratorTrait
{
    // La propriété DOIT être déclarée ici.
    protected readonly UrlGeneratorInterface $urlGenerator;

    /**
     * Génère une URL basée sur un nom de route et des paramètres.
     * * Utilise UrlGeneratorInterface pour la génération d'URL.
     * * @param string $route         Nom de la route
     * @param array  $parameters    Paramètres de la route
     * @param int    $referenceType Type de référence (ex: absolute_url, absolute_path)
     * * @return string L'URL générée
     */
    protected function generateUrl(
        string $route,
        array $parameters = [],
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH
    ): string {
        return $this->urlGenerator->generate($route, $parameters, $referenceType);
    }
}