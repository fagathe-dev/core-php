<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Tests\Mailer;

use Fagathe\CorePhp\Mailer\Exception\MailerApiException;
use Fagathe\CorePhp\Mailer\Exception\MailerAuthenticationException;
use Fagathe\CorePhp\Mailer\Exception\MailerConfigurationException;
use Fagathe\CorePhp\Mailer\Exception\MailerTransportException;
use Fagathe\CorePhp\Mailer\Exception\MailerValidationException;
use Fagathe\CorePhp\Mailer\MailerApiClient;
use Fagathe\CorePhp\Mailer\Payload\AuthOtpPayload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class MailerApiClientTest extends TestCase
{
    private const string BASE_URI = 'https://mailer.test';
    private const string TOKEN = 'secret-token';

    public function testSendPostsJsonPayloadWithBearerToken(): void
    {
        $response = new JsonMockResponse(['status' => 'success', 'message' => 'E-mail envoyé avec succès.']);
        $client = new MailerApiClient(new MockHttpClient($response), self::BASE_URI, self::TOKEN);

        $result = $client->send(new AuthOtpPayload('jane.doe@gmail.com', '482910', 'janedoe'));

        self::assertSame('POST', $response->getRequestMethod());
        self::assertSame('https://mailer.test/api/emails/send', $response->getRequestUrl());

        $options = $response->getRequestOptions();
        self::assertContains('Authorization: Bearer '.self::TOKEN, $options['headers']);
        self::assertContains('Content-Type: application/json', $options['headers']);
        self::assertContains('Accept: application/json', $options['headers']);
        self::assertSame(
            '{"type":"auth_otp","data":{"email":"jane.doe@gmail.com","code":"482910","username":"janedoe"}}',
            $options['body'],
        );

        self::assertTrue($result->isSuccess());
        self::assertSame('E-mail envoyé avec succès.', $result->message);
    }

    public function testTrailingSlashInBaseUriIsIgnored(): void
    {
        $response = new JsonMockResponse(['status' => 'success', 'message' => 'ok']);
        $client = new MailerApiClient(new MockHttpClient($response), self::BASE_URI.'/', self::TOKEN);

        $client->send(new AuthOtpPayload('jane.doe@gmail.com', '482910'));

        self::assertSame('https://mailer.test/api/emails/send', $response->getRequestUrl());
    }

    public function testHealthCheck(): void
    {
        $response = new JsonMockResponse(['status' => 'ok', 'service' => 'mailer-app', 'timestamp' => '2026-10-25T10:00:00+00:00']);
        $client = new MailerApiClient(new MockHttpClient($response), self::BASE_URI, self::TOKEN);

        $result = $client->healthCheck();

        self::assertSame('GET', $response->getRequestMethod());
        self::assertSame('https://mailer.test/api/health-check', $response->getRequestUrl());
        self::assertContains('Authorization: Bearer '.self::TOKEN, $response->getRequestOptions()['headers']);

        self::assertTrue($result->isOk());
        self::assertSame('mailer-app', $result->service);
        self::assertEquals(new \DateTimeImmutable('2026-10-25T10:00:00+00:00'), $result->timestamp);
    }

    public function testUnauthorizedWithEmptyBody(): void
    {
        $client = $this->createClient(new MockResponse('', [
            'http_code' => 401,
            'response_headers' => ['WWW-Authenticate' => 'Bearer error="invalid_token"'],
        ]));

        try {
            $client->send(new AuthOtpPayload('jane.doe@gmail.com', '482910'));
            self::fail('Une MailerAuthenticationException était attendue.');
        } catch (MailerAuthenticationException $e) {
            self::assertSame(401, $e->getStatusCode());
            self::assertNull($e->getErrorCode());
            self::assertSame([], $e->getViolations());
            self::assertFalse($e->isRetryable());
        }
    }

    public function testUnprocessableEntityExposesViolations(): void
    {
        $client = $this->createClient(new JsonMockResponse([
            'error' => 'Échec de la validation des données.',
            'violations' => [
                'email' => 'L\'adresse e-mail "jane.doe@" n\'est pas valide.',
                'code' => 'Le code OTP est obligatoire.',
            ],
        ], ['http_code' => 422]));

        try {
            $client->send(new AuthOtpPayload('jane.doe@gmail.com', '482910'));
            self::fail('Une MailerValidationException était attendue.');
        } catch (MailerValidationException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertSame('Échec de la validation des données.', $e->getErrorCode());
            self::assertSame([
                'email' => 'L\'adresse e-mail "jane.doe@" n\'est pas valide.',
                'code' => 'Le code OTP est obligatoire.',
            ], $e->getViolations());
            self::assertFalse($e->isRetryable());
        }
    }

    public function testBadGatewayIsRetryable(): void
    {
        $client = $this->createClient(new JsonMockResponse([
            'error' => 'smtp_transport_error',
            'message' => 'Échec de transmission avec le serveur SMTP.',
            'details' => 'Connection could not be established with host smtp.test',
        ], ['http_code' => 502]));

        try {
            $client->send(new AuthOtpPayload('jane.doe@gmail.com', '482910'));
            self::fail('Une MailerApiException était attendue.');
        } catch (MailerApiException $e) {
            self::assertSame(502, $e->getStatusCode());
            self::assertSame('smtp_transport_error', $e->getErrorCode());
            self::assertSame('Connection could not be established with host smtp.test', $e->getDetails());
            self::assertStringContainsString('Échec de transmission avec le serveur SMTP.', $e->getMessage());
            self::assertTrue($e->isRetryable());
        }
    }

    #[DataProvider('provideRetryability')]
    public function testRetryability(int $statusCode, ?string $errorCode, bool $expected): void
    {
        $body = null !== $errorCode ? ['error' => $errorCode] : [];
        $client = $this->createClient(new JsonMockResponse($body, ['http_code' => $statusCode]));

        try {
            $client->send(new AuthOtpPayload('jane.doe@gmail.com', '482910'));
            self::fail('Une MailerApiException était attendue.');
        } catch (MailerApiException $e) {
            self::assertSame($statusCode, $e->getStatusCode());
            self::assertSame($expected, $e->isRetryable());
        }
    }

    /**
     * @return iterable<string, array{int, ?string, bool}>
     */
    public static function provideRetryability(): iterable
    {
        yield '400 JSON invalide' => [400, 'Format de charge utile JSON invalide.', false];
        yield '401' => [401, null, false];
        yield '422 destinataire' => [422, 'email_recipient_error', false];
        yield '500 internal_error' => [500, 'internal_error', false];
        yield '502 smtp_transport_error' => [502, 'smtp_transport_error', true];
        yield '502 mailer_transport_error' => [502, 'mailer_transport_error', true];
        yield '503 queue_transport_error' => [503, 'queue_transport_error', true];
        yield '503 mailer_dsn_not_configured' => [503, 'mailer_dsn_not_configured', false];
        yield '503 mailer_dsn_invalid' => [503, 'mailer_dsn_invalid', false];
    }

    #[DataProvider('provideMissingToken')]
    public function testMissingTokenThrowsConfigurationExceptionWithoutCallingTheApi(?string $token): void
    {
        $httpClient = new MockHttpClient([]);
        $client = new MailerApiClient($httpClient, self::BASE_URI, $token);

        try {
            $client->send(new AuthOtpPayload('jane.doe@gmail.com', '482910'));
            self::fail('Une MailerConfigurationException était attendue.');
        } catch (MailerConfigurationException $e) {
            self::assertStringContainsString('API_MAILER_SECRET_KEY', $e->getMessage());
        }

        $this->expectException(MailerConfigurationException::class);
        try {
            $client->healthCheck();
        } finally {
            self::assertSame(0, $httpClient->getRequestsCount());
        }
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function provideMissingToken(): iterable
    {
        yield 'variable absente (null)' => [null];
        yield 'variable vide' => [''];
        yield 'espaces' => ['  '];
    }

    public function testEmptyBaseUriThrowsConfigurationException(): void
    {
        $client = new MailerApiClient(new MockHttpClient([]), '', self::TOKEN);

        $this->expectException(MailerConfigurationException::class);
        $this->expectExceptionMessage('API_MAILER_BASE_URL');

        $client->healthCheck();
    }

    public function testNetworkErrorIsWrappedInTransportException(): void
    {
        $client = $this->createClient(new MockResponse('', ['error' => 'Connexion refusée']));

        try {
            $client->send(new AuthOtpPayload('jane.doe@gmail.com', '482910'));
            self::fail('Une MailerTransportException était attendue.');
        } catch (MailerTransportException $e) {
            self::assertTrue($e->isRetryable());
            self::assertStringContainsString('Connexion refusée', $e->getMessage());
        }
    }

    public function testNonJsonSuccessResponseThrowsApiException(): void
    {
        $client = $this->createClient(new MockResponse('<html>OK</html>', ['http_code' => 200]));

        $this->expectException(MailerApiException::class);
        $this->expectExceptionMessage('illisible');

        $client->healthCheck();
    }

    public function testDefaultBaseUriIsUsedWhenNotProvided(): void
    {
        $response = new JsonMockResponse(['status' => 'ok', 'service' => 'mailer-app', 'timestamp' => '2026-10-25T10:00:00+00:00']);
        $client = new MailerApiClient(new MockHttpClient($response), apiToken: self::TOKEN);

        $client->healthCheck();

        self::assertSame(MailerApiClient::DEFAULT_BASE_URI.'/api/health-check', $response->getRequestUrl());
    }

    private function createClient(MockResponse $response): MailerApiClient
    {
        return new MailerApiClient(new MockHttpClient($response), self::BASE_URI, self::TOKEN);
    }
}
