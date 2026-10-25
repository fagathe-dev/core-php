<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer;

use Fagathe\CorePhp\Mailer\Exception\MailerApiException;
use Fagathe\CorePhp\Mailer\Exception\MailerConfigurationException;
use Fagathe\CorePhp\Mailer\Exception\MailerTransportException;
use Fagathe\CorePhp\Mailer\Payload\EmailPayloadInterface;
use Fagathe\CorePhp\Mailer\Response\HealthCheckResponse;
use Fagathe\CorePhp\Mailer\Response\SendEmailResponse;

/**
 * Client du microservice API Mailer. Type-hinter cette interface pour l'autowiring.
 */
interface MailerClientInterface
{
    /**
     * Envoie un e-mail (synchrone : un succès signifie que le serveur SMTP l'a accepté).
     *
     * @throws MailerConfigurationException jeton ou URL non configuré
     * @throws MailerApiException           réponse d'erreur de l'API (voir isRetryable())
     * @throws MailerTransportException     API injoignable
     */
    public function send(EmailPayloadInterface $payload): SendEmailResponse;

    /**
     * @throws MailerConfigurationException jeton ou URL non configuré
     * @throws MailerApiException           réponse d'erreur de l'API
     * @throws MailerTransportException     API injoignable
     */
    public function healthCheck(): HealthCheckResponse;
}
