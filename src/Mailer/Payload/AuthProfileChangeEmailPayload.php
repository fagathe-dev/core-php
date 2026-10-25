<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer\Payload;

use Fagathe\CorePhp\Mailer\EmailType;

/**
 * auth_profile_change_email : confirmation d'une nouvelle adresse e-mail.
 *
 * Le destinataire est la nouvelle adresse, envoyée sous la clé "newEmail" (et non "email").
 */
final readonly class AuthProfileChangeEmailPayload implements EmailPayloadInterface
{
    public function __construct(
        public string $username,
        public string $newEmail,
        public string $confirmationUrl,
    ) {
        PayloadGuard::notBlank($username, 'username');
        PayloadGuard::notBlank($newEmail, 'newEmail');
        PayloadGuard::httpsUrl($confirmationUrl, 'confirmationUrl');
    }

    public function getType(): EmailType
    {
        return EmailType::AUTH_PROFILE_CHANGE_EMAIL;
    }

    public function toArray(): array
    {
        return [
            'username' => $this->username,
            'newEmail' => $this->newEmail,
            'confirmationUrl' => $this->confirmationUrl,
        ];
    }
}
