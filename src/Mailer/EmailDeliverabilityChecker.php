<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Vérifie, avant l'envoi, que le domaine d'une adresse peut recevoir des e-mails.
 *
 * Désactivable via le paramètre core_php.email.check_deliverability (tests, hors ligne).
 */
final class EmailDeliverabilityChecker
{
    private const RESERVED_DOMAINS = [
        'example.com',
        'example.net',
        'example.org',
        'example.edu',
        'invalid',
        'test',
        'localhost',
    ];

    public function __construct(
        #[Autowire(param: 'core_php.email.check_deliverability')]
        private readonly bool $enabled = true,
    ) {}

    public function isDeliverable(string $email): bool
    {
        if (!$this->enabled) {
            return true;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $domain = strtolower(substr((string) strrchr($email, '@'), 1));

        // 1. Domaines de test / réservés IANA
        if (\in_array($domain, self::RESERVED_DOMAINS, true)) {
            return false;
        }

        // 2. Serveurs MX
        $mxRecords = @dns_get_record($domain, DNS_MX);

        if (!empty($mxRecords)) {
            foreach ($mxRecords as $record) {
                $target = trim((string) ($record['target'] ?? ''));
                // RFC 7505 : un MX pointant vers "." signifie "ne reçoit aucun mail"
                if ($target !== '' && $target !== '.') {
                    return true;
                }
            }

            return false;
        }

        // 3. RFC 5321 : sans MX, l'enregistrement A fait foi
        return checkdnsrr($domain, 'A');
    }
}
