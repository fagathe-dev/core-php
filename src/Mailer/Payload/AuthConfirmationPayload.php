<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer\Payload;

use Fagathe\CorePhp\Mailer\EmailType;

/**
 * auth_confirmation : confirmation de l'adresse e-mail après inscription.
 */
final readonly class AuthConfirmationPayload implements EmailPayloadInterface
{
    public function __construct(
        public string $email,
        public string $username,
        public string $confirmationUrl,
    ) {
        PayloadGuard::notBlank($email, 'email');
        PayloadGuard::notBlank($username, 'username');
        PayloadGuard::httpsUrl($confirmationUrl, 'confirmationUrl');
    }

    public function getType(): EmailType
    {
        return EmailType::AUTH_CONFIRMATION;
    }

    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'username' => $this->username,
            'confirmationUrl' => $this->confirmationUrl,
        ];
    }
}
