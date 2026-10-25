<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer\Payload;

use Fagathe\CorePhp\Mailer\EmailType;

/**
 * Données typées d'un modèle d'e-mail de l'API Mailer.
 */
interface EmailPayloadInterface
{
    public function getType(): EmailType;

    /**
     * Contenu du champ "data" : clés camelCase, valeurs string, champs optionnels null omis.
     *
     * @return array<string, string|list<string>>
     */
    public function toArray(): array;
}
