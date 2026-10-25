<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer\Payload;

use Fagathe\CorePhp\Mailer\EmailType;

/**
 * contact_recap : récapitulatif envoyé au visiteur après un formulaire de contact.
 */
final readonly class ContactRecapPayload implements EmailPayloadInterface
{
    public function __construct(
        public string $email,
        public string $name,
        public string $subject,
        public string $message,
        public ?string $senderEmail = null,
        public ?string $phone = null,
    ) {
        PayloadGuard::notBlank($email, 'email');
        PayloadGuard::notBlank($name, 'name');
        PayloadGuard::notBlank($subject, 'subject');
        PayloadGuard::notBlank($message, 'message');
    }

    public function getType(): EmailType
    {
        return EmailType::CONTACT_RECAP;
    }

    public function toArray(): array
    {
        return PayloadGuard::withoutNulls([
            'email' => $this->email,
            'name' => $this->name,
            'subject' => $this->subject,
            'message' => $this->message,
            'senderEmail' => $this->senderEmail,
            'phone' => $this->phone,
        ]);
    }
}
