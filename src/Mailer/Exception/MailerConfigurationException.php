<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer\Exception;

/**
 * Le client n'est pas configuré (variable d'environnement absente ou vide).
 */
final class MailerConfigurationException extends \LogicException implements MailerExceptionInterface
{
    public static function missingApiToken(): self
    {
        return new self('Le jeton de l\'API Mailer est vide : définissez la variable d\'environnement API_MAILER_SECRET_KEY (.env.local ou secrets:set).');
    }

    public static function missingBaseUri(): self
    {
        return new self('L\'URL de l\'API Mailer est vide : définissez la variable d\'environnement API_MAILER_BASE_URL.');
    }
}
