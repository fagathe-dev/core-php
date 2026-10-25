<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer\Exception;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * L'API Mailer n'a pas pu être jointe (DNS, connexion, timeout…).
 *
 * Attention : après un timeout, l'e-mail a pu être envoyé ; réessayer peut créer un doublon.
 */
final class MailerTransportException extends \RuntimeException implements MailerExceptionInterface
{
    public static function fromTransportException(TransportExceptionInterface $previous): self
    {
        return new self(\sprintf('API Mailer injoignable : %s', $previous->getMessage()), 0, $previous);
    }

    public function isRetryable(): bool
    {
        return true;
    }
}
