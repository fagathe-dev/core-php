<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer;

use Fagathe\CorePhp\Mailer\Exception\MailerApiException;
use Fagathe\CorePhp\Mailer\Exception\MailerConfigurationException;
use Fagathe\CorePhp\Mailer\Exception\MailerTransportException;
use Fagathe\CorePhp\Mailer\Payload\EmailPayloadInterface;
use Fagathe\CorePhp\Mailer\Response\HealthCheckResponse;
use Fagathe\CorePhp\Mailer\Response\SendEmailResponse;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client HTTP de l'API Mailer, authentifié par jeton Bearer statique.
 *
 * Configuré par config/mailer_api.yaml (API_MAILER_BASE_URL, API_MAILER_SECRET_KEY).
 */
final class MailerApiClient implements MailerClientInterface
{
    private const SEND_PATH = '/api/emails/send';
    private const HEALTH_CHECK_PATH = '/api/health-check';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $baseUri = null,
        #[\SensitiveParameter]
        private readonly ?string $apiToken = null,
    ) {}

    public function send(EmailPayloadInterface $payload): SendEmailResponse
    {
        $data = $this->request('POST', self::SEND_PATH, [
            'json' => [
                'type' => $payload->getType()->value,
                'data' => $payload->toArray(),
            ],
        ]);

        return SendEmailResponse::fromArray($data);
    }

    public function healthCheck(): HealthCheckResponse
    {
        return HealthCheckResponse::fromArray($this->request('GET', self::HEALTH_CHECK_PATH));
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<mixed> corps JSON décodé d'une réponse 2xx
     */
    private function request(string $method, string $path, array $options = []): array
    {
        $this->assertConfigured();

        try {
            $response = $this->httpClient->request($method, rtrim((string) $this->baseUri, '/') . $path, $options + [
                'auth_bearer' => $this->apiToken,
                'headers' => ['Accept' => 'application/json'],
            ]);

            $statusCode = $response->getStatusCode();

            try {
                $data = $response->toArray(false);
            } catch (DecodingExceptionInterface $e) {
                // 401 sans corps, page HTML d'un proxy…
                if ($statusCode < 300) {
                    throw MailerApiException::invalidResponse($statusCode, $e);
                }
                $data = [];
            }
        } catch (TransportExceptionInterface $e) {
            throw MailerTransportException::fromTransportException($e);
        }

        if ($statusCode >= 300) {
            throw MailerApiException::fromResponse($statusCode, $data);
        }

        return $data;
    }

    private function assertConfigured(): void
    {
        if (null === $this->baseUri || '' === trim($this->baseUri)) {
            throw MailerConfigurationException::missingBaseUri();
        }

        if (null === $this->apiToken || '' === trim($this->apiToken)) {
            throw MailerConfigurationException::missingApiToken();
        }
    }
}
