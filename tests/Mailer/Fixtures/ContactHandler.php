<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Tests\Mailer\Fixtures;

use Fagathe\CorePhp\Mailer\AbstractMailerHandler;
use Symfony\Component\Mime\Address;

/**
 * Handler d'exemple, tel qu'un projet hôte le déclarerait.
 *
 * @extends AbstractMailerHandler<ContactDto>
 */
final class ContactHandler extends AbstractMailerHandler
{
    // Dépendance propre au handler : le constructeur reste libre (voir setMailerDependencies()).
    public function __construct(private readonly string $ownerEmail = 'owner@gmail.com') {}

    protected function getTemplate(): string
    {
        return 'contact.html.twig';
    }

    protected function getSubject(object $dto): string
    {
        return 'Nouveau message de '.$dto->name;
    }

    protected function getRecipients(object $dto): array
    {
        return [$this->ownerEmail];
    }

    protected function getReplyTo(object $dto): ?Address
    {
        return new Address($dto->email, $dto->name);
    }
}
