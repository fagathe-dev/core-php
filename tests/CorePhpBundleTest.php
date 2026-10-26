<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Tests;

use Fagathe\CorePhp\CorePhpBundle;
use Fagathe\CorePhp\Mailer\EmailDeliverabilityChecker;
use Fagathe\CorePhp\Tests\Mailer\Fixtures\ContactDto;
use Fagathe\CorePhp\Tests\Mailer\Fixtures\ContactHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

final class CorePhpBundleTest extends TestCase
{
    private const array ENV_VARS = ['APP_NAME', 'DEFAULT_URI', 'DEFAULT_EMAIL_SENDER', 'SUPPORT_EMAIL'];

    protected function tearDown(): void
    {
        foreach (self::ENV_VARS as $name) {
            unset($_ENV[$name], $_SERVER[$name]);
        }
    }

    public function testExtensionLoadsEmailConfiguration(): void
    {
        $builder = $this->createContainerBuilder();
        (new CorePhpBundle())->getContainerExtension()?->load([], $builder);

        self::assertTrue($builder->hasDefinition(EmailDeliverabilityChecker::class));
        self::assertTrue($builder->getDefinition(EmailDeliverabilityChecker::class)->isAutowired());

        $expected = [
            'core_php.email.app_name' => '%env(default::APP_NAME)%',
            'core_php.email.app_uri' => '%env(default::DEFAULT_URI)%',
            'core_php.email.support_email' => '%env(default::SUPPORT_EMAIL)%',
            'core_php.email.check_deliverability' => true,
        ];

        foreach ($expected as $name => $value) {
            self::assertSame($value, $builder->getParameter($name), $name);
        }
    }

    public function testExtensionLoadsLegacyConstantsAsParameters(): void
    {
        $builder = $this->createContainerBuilder();
        (new CorePhpBundle())->getContainerExtension()?->load([], $builder);

        // Mêmes valeurs que les define() de files/constants.php.
        $expected = [
            'core_php.logger.logs_dir' => '%kernel.project_dir%/logs/',
            'core_php.logger.retention_delay' => LOGS_RETENTION_DELAY,
            'core_php.uploader.public_dir' => PUBLIC_DIR,
            'core_php.uploader.upload_dir' => UPLOAD_DIR,
            'core_php.uploader.max_filesize' => UPLOAD_MAX_FILESIZE,
            'core_php.uploader.rename_file' => UPLOAD_RENAME_FILE,
            'core_php.email.default_sender' => DEFAULT_EMAIL_SENDER,
            'core_php.email.logo_path' => LOGO_PATH,
        ];

        foreach ($expected as $name => $value) {
            self::assertSame($value, $builder->getParameter($name), $name);
        }
        self::assertSame(LOGS_DIR, $builder->getParameterBag()->resolveValue('%core_php.logger.logs_dir%'));
    }

    /**
     * Un handler déclaré par le projet hôte reçoit, via #[Required], le mailer et le
     * contexte commun lu dans les variables d'environnement.
     */
    public function testHostHandlerIsAutowiredWithEmailParameters(): void
    {
        $_ENV['APP_NAME'] = 'Mon App';
        $_ENV['DEFAULT_EMAIL_SENDER'] = 'noreply@app.test';

        $builder = $this->createContainerBuilder();
        $bundle = new CorePhpBundle();
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);
        $builder->registerExtension($extension);
        $builder->loadFromExtension($extension->getAlias());
        $bundle->build($builder);

        $builder->register(MailerInterface::class)->setSynthetic(true)->setPublic(true);
        $builder->register(ContactHandler::class)->setAutowired(true)->setPublic(true);
        $builder->setParameter('core_php.email.check_deliverability', false);

        $builder->compile(true);

        $mailer = new class implements MailerInterface {
            public ?RawMessage $sent = null;

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->sent = $message;
            }
        };
        $builder->set(MailerInterface::class, $mailer);

        $handler = $builder->get(ContactHandler::class);
        self::assertInstanceOf(ContactHandler::class, $handler);
        $handler->send(new ContactDto('jane.doe@gmail.com', 'Jane', 'Bonjour, voici mon message.'));

        self::assertInstanceOf(Email::class, $mailer->sent);
        self::assertSame('"Mon App" <noreply@app.test>', $mailer->sent->getFrom()[0]->toString());
    }

    private function createContainerBuilder(): ContainerBuilder
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('kernel.project_dir', PROJECT_DIR);
        $builder->setParameter('kernel.environment', 'test');
        $builder->setParameter('kernel.debug', false);

        return $builder;
    }
}
