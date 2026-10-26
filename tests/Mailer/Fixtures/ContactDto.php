<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Tests\Mailer\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO d'exemple, tel qu'un projet hôte le déclarerait.
 */
final readonly class ContactDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,
        #[Assert\NotBlank]
        public string $name,
        #[Assert\Length(min: 10)]
        public string $message,
    ) {}
}
