<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Tests\Mailer;

use Fagathe\CorePhp\Mailer\EmailDeliverabilityChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailDeliverabilityCheckerTest extends TestCase
{
    /**
     * Cas résolus sans requête DNS.
     */
    #[DataProvider('provideUndeliverableAddresses')]
    public function testRejectsMalformedAndReservedAddresses(string $email): void
    {
        self::assertFalse((new EmailDeliverabilityChecker())->isDeliverable($email));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUndeliverableAddresses(): iterable
    {
        yield 'mal formée' => ['pas-un-email'];
        yield 'example.com' => ['jane@example.com'];
        yield 'casse ignorée' => ['jane@EXAMPLE.org'];
        yield 'localhost' => ['jane@localhost'];
    }

    public function testDisabledCheckerAcceptsEverything(): void
    {
        self::assertTrue((new EmailDeliverabilityChecker(enabled: false))->isDeliverable('jane@example.com'));
    }
}
