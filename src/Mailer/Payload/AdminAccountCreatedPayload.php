<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer\Payload;

use Fagathe\CorePhp\Mailer\EmailType;
use Fagathe\CorePhp\Mailer\Exception\InvalidPayloadException;

/**
 * admin_account_created : informe un utilisateur qu'un compte a été créé pour lui.
 *
 * Contient un mot de passe en clair : préférer renseigner $changePasswordUrl.
 */
final readonly class AdminAccountCreatedPayload implements EmailPayloadInterface
{
    /**
     * @param string|list<string> $roles
     */
    public function __construct(
        public string $email,
        public string $username,
        #[\SensitiveParameter]
        public string $plainPassword,
        public string|array $roles,
        public string $loginUrl,
        public ?string $changePasswordUrl = null,
        public ?string $createdBy = 'dashboard',
    ) {
        PayloadGuard::notBlank($email, 'email');
        PayloadGuard::notBlank($username, 'username');
        PayloadGuard::notBlank($plainPassword, 'plainPassword');
        PayloadGuard::notBlank($loginUrl, 'loginUrl');

        if ([] === $roles || (\is_string($roles) && '' === trim($roles))) {
            throw InvalidPayloadException::blankField('roles');
        }
    }

    public function getType(): EmailType
    {
        return EmailType::ADMIN_ACCOUNT_CREATED;
    }

    public function toArray(): array
    {
        return PayloadGuard::withoutNulls([
            'email' => $this->email,
            'username' => $this->username,
            'plainPassword' => $this->plainPassword,
            'roles' => $this->roles,
            'loginUrl' => $this->loginUrl,
            'changePasswordUrl' => $this->changePasswordUrl,
            'createdBy' => $this->createdBy,
        ]);
    }

    /**
     * Masque le mot de passe dans var_dump() / dump().
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['plainPassword' => '********'] + get_object_vars($this);
    }
}
