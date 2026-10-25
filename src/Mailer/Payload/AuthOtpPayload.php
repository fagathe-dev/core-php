<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer\Payload;

use Fagathe\CorePhp\Mailer\EmailType;

/**
 * auth_otp : code de sécurité à usage unique.
 *
 * Le code est toujours une chaîne : un nombre JSON est rejeté par l'API (500).
 */
final readonly class AuthOtpPayload implements EmailPayloadInterface
{
    public const int CODE_MIN_LENGTH = 4;
    public const int CODE_MAX_LENGTH = 12;

    public function __construct(
        public string $email,
        #[\SensitiveParameter]
        public string $code,
        public ?string $username = null,
        public ?string $actionLabel = null,
        public ?string $expiresIn = null,
    ) {
        PayloadGuard::notBlank($email, 'email');
        PayloadGuard::length($code, 'code', self::CODE_MIN_LENGTH, self::CODE_MAX_LENGTH);
    }

    public function getType(): EmailType
    {
        return EmailType::AUTH_OTP;
    }

    public function toArray(): array
    {
        return PayloadGuard::withoutNulls([
            'email' => $this->email,
            'code' => $this->code,
            'username' => $this->username,
            'actionLabel' => $this->actionLabel,
            'expiresIn' => $this->expiresIn,
        ]);
    }
}
