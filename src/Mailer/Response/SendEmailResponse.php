<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer\Response;

/**
 * Réponse 200 de POST /api/emails/send : {"status": "success", "message": "..."}.
 */
final readonly class SendEmailResponse
{
    public function __construct(
        public string $status,
        public string $message,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            \is_string($data['status'] ?? null) ? $data['status'] : '',
            \is_string($data['message'] ?? null) ? $data['message'] : '',
        );
    }

    public function isSuccess(): bool
    {
        return 'success' === $this->status;
    }
}
