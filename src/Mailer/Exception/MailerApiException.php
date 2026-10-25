<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer\Exception;

/**
 * Réponse d'erreur de l'API Mailer.
 *
 * Corps attendu : {"error": "...", "message": "...", "details": "...", "violations": {"champ": "message"}}
 * (tous les champs sont optionnels ; un 401 n'a pas de corps).
 */
class MailerApiException extends \RuntimeException implements MailerExceptionInterface
{
    /**
     * Codes "error" d'un 503 dus à une mauvaise configuration du mailer : réessayer ne sert à rien.
     */
    private const array NON_RETRYABLE_503_ERRORS = [
        'mailer_dsn_not_configured',
        'mailer_dsn_invalid',
    ];

    /**
     * @param array<string, string> $violations
     */
    final public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly ?string $errorCode = null,
        private readonly ?string $details = null,
        private readonly array $violations = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    /**
     * Construit l'exception adaptée au code HTTP à partir du corps JSON décodé.
     *
     * @param array<mixed> $body
     */
    public static function fromResponse(int $statusCode, array $body): self
    {
        $errorCode = self::stringOrNull($body['error'] ?? null);
        $message = self::stringOrNull($body['message'] ?? null) ?? $errorCode;

        $class = match ($statusCode) {
            401 => MailerAuthenticationException::class,
            422 => MailerValidationException::class,
            default => self::class,
        };

        return new $class(
            \sprintf('API Mailer : erreur HTTP %d%s', $statusCode, null !== $message ? ' - '.$message : '.'),
            $statusCode,
            $errorCode,
            self::stringOrNull($body['details'] ?? null),
            self::normalizeViolations($body['violations'] ?? null),
        );
    }

    /**
     * Réponse 2xx dont le corps n'est pas le JSON attendu.
     */
    public static function invalidResponse(int $statusCode, ?\Throwable $previous = null): self
    {
        return new self(
            \sprintf('API Mailer : réponse HTTP %d illisible (JSON attendu).', $statusCode),
            $statusCode,
            previous: $previous,
        );
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Valeur du champ "error" de la réponse.
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Valeur du champ "details" de la réponse (message technique).
     */
    public function getDetails(): ?string
    {
        return $this->details;
    }

    /**
     * Valeur du champ "violations" (erreurs 422) : nom du champ de "data" => message.
     *
     * @return array<string, string>
     */
    public function getViolations(): array
    {
        return $this->violations;
    }

    /**
     * Vrai seulement pour les pannes temporaires côté mailer : 502 (SMTP / transport)
     * et 503 hors erreur de configuration du DSN.
     */
    public function isRetryable(): bool
    {
        return match ($this->statusCode) {
            502 => true,
            503 => !\in_array($this->errorCode, self::NON_RETRYABLE_503_ERRORS, true),
            default => false,
        };
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return \is_string($value) && '' !== $value ? $value : null;
    }

    /**
     * @return array<string, string>
     */
    private static function normalizeViolations(mixed $violations): array
    {
        if (!\is_array($violations)) {
            return [];
        }

        $normalized = [];
        foreach ($violations as $field => $message) {
            $normalized[(string) $field] = \is_scalar($message) ? (string) $message : (string) json_encode($message);
        }

        return $normalized;
    }
}
