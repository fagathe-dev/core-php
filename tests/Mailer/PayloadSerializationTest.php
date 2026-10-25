<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Tests\Mailer;

use Fagathe\CorePhp\Mailer\EmailType;
use Fagathe\CorePhp\Mailer\Exception\InvalidPayloadException;
use Fagathe\CorePhp\Mailer\Payload\AdminAccountCreatedPayload;
use Fagathe\CorePhp\Mailer\Payload\AuthConfirmationPayload;
use Fagathe\CorePhp\Mailer\Payload\AuthOtpPayload;
use Fagathe\CorePhp\Mailer\Payload\AuthProfileChangeEmailPayload;
use Fagathe\CorePhp\Mailer\Payload\AuthResetPasswordPayload;
use Fagathe\CorePhp\Mailer\Payload\ContactRecapPayload;
use Fagathe\CorePhp\Mailer\Payload\EmailPayloadInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PayloadSerializationTest extends TestCase
{
    public function testEmailTypeValuesMatchTheApi(): void
    {
        self::assertSame([
            'auth_confirmation',
            'auth_otp',
            'auth_reset_password',
            'auth_profile_change_email',
            'admin_account_created',
            'contact_recap',
        ], array_column(EmailType::cases(), 'value'));
    }

    /**
     * @param array<string, mixed> $expectedData
     */
    #[DataProvider('providePayloads')]
    public function testPayloadSerialization(EmailPayloadInterface $payload, EmailType $expectedType, array $expectedData): void
    {
        self::assertSame($expectedType, $payload->getType());
        self::assertSame($expectedData, $payload->toArray());
        // Comparaison JSON stricte : mêmes clés camelCase, même ordre, mêmes types.
        self::assertSame(json_encode($expectedData), json_encode($payload->toArray()));
    }

    /**
     * @return iterable<string, array{EmailPayloadInterface, EmailType, array<string, mixed>}>
     */
    public static function providePayloads(): iterable
    {
        yield 'auth_confirmation' => [
            new AuthConfirmationPayload('jane.doe@gmail.com', 'janedoe', 'https://app.test/confirm/abc'),
            EmailType::AUTH_CONFIRMATION,
            ['email' => 'jane.doe@gmail.com', 'username' => 'janedoe', 'confirmationUrl' => 'https://app.test/confirm/abc'],
        ];

        yield 'auth_otp minimal (optionnels omis)' => [
            new AuthOtpPayload('jane.doe@gmail.com', '482910'),
            EmailType::AUTH_OTP,
            ['email' => 'jane.doe@gmail.com', 'code' => '482910'],
        ];

        yield 'auth_otp complet' => [
            new AuthOtpPayload('jane.doe@gmail.com', '0042', 'janedoe', 'Connexion', '10 minutes'),
            EmailType::AUTH_OTP,
            ['email' => 'jane.doe@gmail.com', 'code' => '0042', 'username' => 'janedoe', 'actionLabel' => 'Connexion', 'expiresIn' => '10 minutes'],
        ];

        yield 'auth_reset_password' => [
            new AuthResetPasswordPayload('jane.doe@gmail.com', 'janedoe', 'https://app.test/reset/xyz'),
            EmailType::AUTH_RESET_PASSWORD,
            ['email' => 'jane.doe@gmail.com', 'username' => 'janedoe', 'resetUrl' => 'https://app.test/reset/xyz'],
        ];

        yield 'auth_profile_change_email (clé newEmail)' => [
            new AuthProfileChangeEmailPayload('janedoe', 'jane.new@gmail.com', 'https://app.test/email/confirm'),
            EmailType::AUTH_PROFILE_CHANGE_EMAIL,
            ['username' => 'janedoe', 'newEmail' => 'jane.new@gmail.com', 'confirmationUrl' => 'https://app.test/email/confirm'],
        ];

        yield 'admin_account_created (createdBy par défaut)' => [
            new AdminAccountCreatedPayload('jane.doe@gmail.com', 'janedoe', 'S3cret!', ['ROLE_USER', 'ROLE_ADMIN'], 'https://app.test/login'),
            EmailType::ADMIN_ACCOUNT_CREATED,
            ['email' => 'jane.doe@gmail.com', 'username' => 'janedoe', 'plainPassword' => 'S3cret!', 'roles' => ['ROLE_USER', 'ROLE_ADMIN'], 'loginUrl' => 'https://app.test/login', 'createdBy' => 'dashboard'],
        ];

        yield 'admin_account_created (rôle string, createdBy null omis)' => [
            new AdminAccountCreatedPayload('jane.doe@gmail.com', 'janedoe', 'S3cret!', 'ROLE_USER', 'https://app.test/login', 'https://app.test/password', null),
            EmailType::ADMIN_ACCOUNT_CREATED,
            ['email' => 'jane.doe@gmail.com', 'username' => 'janedoe', 'plainPassword' => 'S3cret!', 'roles' => 'ROLE_USER', 'loginUrl' => 'https://app.test/login', 'changePasswordUrl' => 'https://app.test/password'],
        ];

        yield 'contact_recap minimal' => [
            new ContactRecapPayload('jane.doe@gmail.com', 'Jane Doe', 'Devis', 'Bonjour, je souhaite un devis.'),
            EmailType::CONTACT_RECAP,
            ['email' => 'jane.doe@gmail.com', 'name' => 'Jane Doe', 'subject' => 'Devis', 'message' => 'Bonjour, je souhaite un devis.'],
        ];

        yield 'contact_recap complet' => [
            new ContactRecapPayload('jane.doe@gmail.com', 'Jane Doe', 'Devis', 'Bonjour, je souhaite un devis.', 'jane.pro@gmail.com', '+33600000000'),
            EmailType::CONTACT_RECAP,
            ['email' => 'jane.doe@gmail.com', 'name' => 'Jane Doe', 'subject' => 'Devis', 'message' => 'Bonjour, je souhaite un devis.', 'senderEmail' => 'jane.pro@gmail.com', 'phone' => '+33600000000'],
        ];
    }

    public function testOtpCodeIsAlwaysSerializedAsJsonString(): void
    {
        $json = json_encode((new AuthOtpPayload('jane.doe@gmail.com', '482910'))->toArray());

        self::assertStringContainsString('"code":"482910"', (string) $json);
    }

    #[DataProvider('provideInvalidPayloads')]
    public function testInvalidPayloadIsRejectedBeforeAnyCall(callable $factory, string $expectedMessagePart): void
    {
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage($expectedMessagePart);

        $factory();
    }

    /**
     * @return iterable<string, array{callable, string}>
     */
    public static function provideInvalidPayloads(): iterable
    {
        yield 'confirmationUrl en http' => [
            static fn () => new AuthConfirmationPayload('jane.doe@gmail.com', 'janedoe', 'http://app.test/confirm'),
            '"confirmationUrl"',
        ];
        yield 'profile change confirmationUrl en http' => [
            static fn () => new AuthProfileChangeEmailPayload('janedoe', 'jane.new@gmail.com', 'http://app.test/confirm'),
            '"confirmationUrl"',
        ];
        yield 'code OTP trop court' => [
            static fn () => new AuthOtpPayload('jane.doe@gmail.com', '123'),
            '"code"',
        ];
        yield 'code OTP trop long' => [
            static fn () => new AuthOtpPayload('jane.doe@gmail.com', '1234567890123'),
            '"code"',
        ];
        yield 'email vide' => [
            static fn () => new AuthResetPasswordPayload('', 'janedoe', 'https://app.test/reset'),
            '"email"',
        ];
        yield 'rôles vides' => [
            static fn () => new AdminAccountCreatedPayload('jane.doe@gmail.com', 'janedoe', 'S3cret!', [], 'https://app.test/login'),
            '"roles"',
        ];
        yield 'message de contact vide' => [
            static fn () => new ContactRecapPayload('jane.doe@gmail.com', 'Jane Doe', 'Devis', '   '),
            '"message"',
        ];
    }

    public function testOtpCodeLengthBoundariesAreAccepted(): void
    {
        self::assertSame('1234', (new AuthOtpPayload('jane.doe@gmail.com', '1234'))->code);
        self::assertSame('123456789012', (new AuthOtpPayload('jane.doe@gmail.com', '123456789012'))->code);
    }

    public function testPlainPasswordIsMaskedInDumps(): void
    {
        $payload = new AdminAccountCreatedPayload('jane.doe@gmail.com', 'janedoe', 'S3cret!', 'ROLE_USER', 'https://app.test/login');

        self::assertSame('********', $payload->__debugInfo()['plainPassword']);
        self::assertStringNotContainsString('S3cret!', print_r($payload, true));
    }
}
