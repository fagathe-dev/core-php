<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer\Response;

/**
 * Réponse 200 de GET /api/health-check : {"status": "ok", "service": "mailer-app", "timestamp": "<ATOM>"}.
 */
final readonly class HealthCheckResponse
{
    public function __construct(
        public string $status,
        public string $service,
        public ?\DateTimeImmutable $timestamp = null,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $timestamp = \is_string($data['timestamp'] ?? null)
            ? \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $data['timestamp'])
            : false;

        return new self(
            \is_string($data['status'] ?? null) ? $data['status'] : '',
            \is_string($data['service'] ?? null) ? $data['service'] : '',
            false !== $timestamp ? $timestamp : null,
        );
    }

    public function isOk(): bool
    {
        return 'ok' === $this->status;
    }
}
