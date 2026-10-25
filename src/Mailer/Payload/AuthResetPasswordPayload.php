<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer\Payload;

use Fagathe\CorePhp\Mailer\EmailType;

/**
 * auth_reset_password : lien de réinitialisation du mot de passe.
 */
final readonly class AuthResetPasswordPayload implements EmailPayloadInterface
{
    public function __construct(
        public string $email,
        public string $username,
        public string $resetUrl,
    ) {
        PayloadGuard::notBlank($email, 'email');
        PayloadGuard::notBlank($username, 'username');
        PayloadGuard::notBlank($resetUrl, 'resetUrl');
    }

    public function getType(): EmailType
    {
        return EmailType::AUTH_RESET_PASSWORD;
    }

    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'username' => $this->username,
            'resetUrl' => $this->resetUrl,
        ];
    }
}
