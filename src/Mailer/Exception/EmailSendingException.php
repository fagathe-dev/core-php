<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer\Exception;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Échec de transmission de l'e-mail par le transport Symfony Mailer (SMTP, API d'un fournisseur…).
 */
final class EmailSendingException extends \RuntimeException implements MailerExceptionInterface
{
    private const REJECTION_SMTP_CODES = ['550', '551', '552', '553', '554'];

    private const REJECTION_KEYWORDS = [
        'user unknown',
        'recipient rejected',
        'mailbox unavailable',
        'mailbox not found',
        'no such user',
        'domain not found',
        'does not exist',
        'invalid recipient',
        'relay access denied',
    ];

    public static function fromTransportException(TransportExceptionInterface $e): self
    {
        return new self(\sprintf('Échec de l\'envoi de l\'e-mail : %s', $e->getMessage()), 0, $e);
    }

    /**
     * true si le serveur distant a refusé le destinataire (erreur définitive, codes SMTP 55x).
     */
    public function isRecipientRejected(): bool
    {
        $message = strtolower((string) $this->getPrevious()?->getMessage());

        foreach ([...self::REJECTION_SMTP_CODES, ...self::REJECTION_KEYWORDS] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * true si un nouvel essai peut réussir (serveur indisponible, timeout…).
     */
    public function isRetryable(): bool
    {
        return !$this->isRecipientRejected();
    }
}
