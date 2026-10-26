<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer\Exception;

/**
 * Le domaine du destinataire ne peut pas recevoir d'e-mails (domaine réservé, ni MX ni A).
 * Détecté avant l'envoi par EmailDeliverabilityChecker.
 */
final class RecipientUnreachableException extends \RuntimeException implements MailerExceptionInterface
{
    public function __construct(private readonly string $recipientEmail)
    {
        parent::__construct(\sprintf('L\'adresse e-mail <%s> est injoignable ou n\'existe pas.', $recipientEmail));
    }

    public function getRecipientEmail(): string
    {
        return $this->recipientEmail;
    }
}
