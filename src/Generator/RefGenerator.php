<?php

namespace Fagathe\CorePhp\Generator;

use Fagathe\CorePhp\Trait\DatetimeTrait;

/**
 * Service dédié à la génération de références métier uniques basées sur le temps.
 * Utilise DatetimeTrait pour gérer la date/heure et le formatage.
 */
class RefGenerator
{
    use DatetimeTrait;

    /**
     * Génère une référence temporelle unique.
     * Format : PREFIX_YYYYMMDDHHMMSS.mmm
     * Exemple : REF_20251128003853.255
     *
     * @param string $prefix Le préfixe de la référence (par défaut 'REF').
     * @return string
     */
    public function generate(string $prefix = 'REF'): string
    {
        // Utilisation de la méthode now() et formatDateTime() provenant du DatetimeTrait
        // Ceci garantit que la logique de gestion du temps (y compris les millisecondes)
        // est centralisée dans le trait que vous avez fourni.
        $timestamp = $this->formatDateTime('YmdHis.v', $this->now());

        return strtoupper($prefix) . '_' . $timestamp;
    }
}