<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Tests\Mailer;

use Fagathe\CorePhp\Mailer\AbstractMailerHandler;
use Fagathe\CorePhp\Mailer\EmailDeliverabilityChecker;
use Fagathe\CorePhp\Mailer\Exception\EmailSendingException;
use Fagathe\CorePhp\Mailer\Exception\InvalidEmailDataException;
use Fagathe\CorePhp\Mailer\Exception\MailerExceptionInterface;
use Fagathe\CorePhp\Mailer\Exception\RecipientUnreachableException;
use Fagathe\CorePhp\Tests\Mailer\Fixtures\ContactDto;
use Fagathe\CorePhp\Tests\Mailer\Fixtures\ContactHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Validator\Validation;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class AbstractMailerHandlerTest extends TestCase
{
    /** Dossier de logs écrit par LoggerTrait pour le namespace Fixtures. */
    private const string FIXTURE_LOGS_DIR = LOGS_DIR.'fixtures';

    private MailerInterface $mailer;

    protected function setUp(): void
    {
        $this->mailer = new class implements MailerInterface {
            /** @var list<RawMessage> */
            public array $sent = [];
            public ?\Throwable $failure = null;

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                if (null !== $this->failure) {
                    throw $this->failure;
                }
                $this->sent[] = $message;
            }
        };
    }

    public static function tearDownAfterClass(): void
    {
        (new Filesystem())->remove(self::FIXTURE_LOGS_DIR);
        @rmdir(LOGS_DIR); // seulement s'il est vide

    }

    public function testSendBuildsEmailWithLayoutComponentsAndDefaults(): void
    {
        $this->createHandler()->send(new ContactDto('jane.doe@gmail.com', 'Jane <Doe>', 'Bonjour, voici mon message.'));

        self::assertCount(1, $this->mailer->sent);
        $email = $this->mailer->sent[0];
        self::assertInstanceOf(TemplatedEmail::class, $email);

        self::assertSame('Nouveau message de Jane <Doe>', $email->getSubject());
        self::assertSame('owner@gmail.com', $email->getTo()[0]->getAddress());
        self::assertSame('"Mon App" <noreply@app.test>', $email->getFrom()[0]->toString());
        self::assertSame('jane.doe@gmail.com', $email->getReplyTo()[0]->getAddress());

        $html = $this->render($email);
        self::assertStringContainsString('Nouveau message de Jane &lt;Doe&gt;', $html, 'composant _text + échappement');
        self::assertStringContainsString('Bonjour, voici mon message.', $html, 'composant _alert');
        self::assertStringContainsString('src="https://app.test/images/logo.webp"', $html, 'logo rendu absolu');
        self::assertStringContainsString('mailto:support@app.test', $html);
        self::assertStringContainsString('Mon App. Tous droits réservés.', $html);
    }

    public function testLayoutHidesLogoSupportAndSenderWhenNotConfigured(): void
    {
        $handler = $this->createHandler(appName: null, appUri: null, senderEmail: null, supportEmail: null);
        $email = $handler->createEmail(new ContactDto('jane.doe@gmail.com', 'Jane', 'Bonjour, voici mon message.'));

        self::assertSame([], $email->getFrom());
        $html = $this->render($email);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('Besoin d\'aide', $html);
    }

    public function testAbsoluteLogoUrlIsKeptAsIs(): void
    {
        $email = $this->createHandler(appUri: null, logoPath: 'https://cdn.test/logo.png')
            ->createEmail(new ContactDto('jane.doe@gmail.com', 'Jane', 'Bonjour, voici mon message.'));

        self::assertStringContainsString('src="https://cdn.test/logo.png"', $this->render($email));
    }

    public function testInvalidDtoIsRejectedBeforeSending(): void
    {
        try {
            $this->createHandler()->send(new ContactDto('pas-un-email', '', 'court'));
            self::fail('InvalidEmailDataException attendue.');
        } catch (InvalidEmailDataException $e) {
            self::assertInstanceOf(MailerExceptionInterface::class, $e);
            self::assertSame(['email', 'name', 'message'], array_keys($e->getViolations()));
        }

        self::assertSame([], $this->mailer->sent);
    }

    public function testUnreachableRecipientIsRejectedBeforeSending(): void
    {
        // Domaine réservé : rejeté sans requête DNS.
        $handler = new ContactHandler('owner@example.com');
        $this->injectDependencies($handler, checkDeliverability: true);

        try {
            $handler->send(new ContactDto('jane.doe@gmail.com', 'Jane', 'Bonjour, voici mon message.'));
            self::fail('RecipientUnreachableException attendue.');
        } catch (RecipientUnreachableException $e) {
            self::assertSame('owner@example.com', $e->getRecipientEmail());
        }

        self::assertSame([], $this->mailer->sent);
    }

    public function testTransportFailureIsWrappedAsDefinitiveWhenRecipientIsRejected(): void
    {
        $this->mailer->failure = new TransportException('Expected response code "250" but got code "550", with message "550 5.1.1 User unknown".');

        try {
            $this->createHandler()->send(new ContactDto('jane.doe@gmail.com', 'Jane', 'Bonjour, voici mon message.'));
            self::fail('EmailSendingException attendue.');
        } catch (EmailSendingException $e) {
            self::assertTrue($e->isRecipientRejected());
            self::assertFalse($e->isRetryable());
            self::assertSame($this->mailer->failure, $e->getPrevious());
        }
    }

    public function testTransportFailureIsRetryableWhenServerIsUnavailable(): void
    {
        $this->mailer->failure = new TransportException('Connection could not be established with host "smtp.test:587".');

        $this->expectException(EmailSendingException::class);
        try {
            $this->createHandler()->send(new ContactDto('jane.doe@gmail.com', 'Jane', 'Bonjour, voici mon message.'));
        } catch (EmailSendingException $e) {
            self::assertTrue($e->isRetryable());
            throw $e;
        }
    }

    public function testNonTransportErrorsAreRethrownUnchanged(): void
    {
        $this->mailer->failure = new \LogicException('Template introuvable.');

        $this->expectExceptionObject($this->mailer->failure);
        $this->createHandler()->send(new ContactDto('jane.doe@gmail.com', 'Jane', 'Bonjour, voici mon message.'));
    }

    public function testCreateEmailDoesNotSend(): void
    {
        $email = $this->createHandler()->createEmail(new ContactDto('jane.doe@gmail.com', 'Jane', 'Bonjour, voici mon message.'));

        self::assertSame('contact.html.twig', $email->getHtmlTemplate());
        self::assertSame([], $this->mailer->sent);
    }

    private function createHandler(
        ?string $appName = 'Mon App',
        ?string $appUri = 'https://app.test',
        ?string $senderEmail = 'noreply@app.test',
        ?string $logoPath = '/images/logo.webp',
        ?string $supportEmail = 'support@app.test',
    ): ContactHandler {
        $handler = new ContactHandler();
        $this->injectDependencies($handler, false, $appName, $appUri, $senderEmail, $logoPath, $supportEmail);

        return $handler;
    }

    /**
     * Reproduit l'appel #[Required] fait par le conteneur.
     */
    private function injectDependencies(
        AbstractMailerHandler $handler,
        bool $checkDeliverability,
        ?string $appName = 'Mon App',
        ?string $appUri = 'https://app.test',
        ?string $senderEmail = 'noreply@app.test',
        ?string $logoPath = '/images/logo.webp',
        ?string $supportEmail = 'support@app.test',
    ): void {
        $handler->setMailerDependencies(
            $this->mailer,
            new EmailDeliverabilityChecker($checkDeliverability),
            Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(),
            null,
            $appName,
            $appUri,
            $senderEmail,
            $logoPath,
            $supportEmail,
        );
    }

    private function render(TemplatedEmail $email): string
    {
        $loader = new FilesystemLoader(__DIR__.'/Fixtures/templates');
        $loader->addPath(\dirname(__DIR__, 2).'/templates', 'CorePhp');

        (new BodyRenderer(new Environment($loader)))->render($email);

        return (string) $email->getHtmlBody();
    }
}
