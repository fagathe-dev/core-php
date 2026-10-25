<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer\Exception;

/**
 * Données d'un payload refusées avant l'appel à l'API.
 */
final class InvalidPayloadException extends \InvalidArgumentException implements MailerExceptionInterface
{
    public static function blankField(string $field): self
    {
        return new self(\sprintf('Le champ "%s" est obligatoire.', $field));
    }

    public static function insecureUrl(string $field, string $url): self
    {
        return new self(\sprintf('Le champ "%s" doit être une URL commençant par https:// ("%s" donné).', $field, $url));
    }

    public static function invalidLength(string $field, int $min, int $max, int $length): self
    {
        return new self(\sprintf('Le champ "%s" doit contenir entre %d et %d caractères (%d donnés).', $field, $min, $max, $length));
    }
}
