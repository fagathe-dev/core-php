<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Tests;

use Fagathe\CorePhp\CorePhpBundle;
use Fagathe\CorePhp\Mailer\Exception\MailerConfigurationException;
use Fagathe\CorePhp\Mailer\MailerApiClient;
use Fagathe\CorePhp\Mailer\MailerClientInterface;
use Fagathe\CorePhp\Mailer\Payload\AuthOtpPayload;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class CorePhpBundleTest extends TestCase
{
    private const array ENV_VARS = ['API_MAILER_BASE_URL', 'API_MAILER_SECRET_KEY'];

    protected function tearDown(): void
    {
        foreach (self::ENV_VARS as $name) {
            unset($_ENV[$name], $_SERVER[$name]);
        }
    }

    public function testExtensionLoadsMailerApiConfiguration(): void
    {
        $builder = $this->createContainerBuilder();
        $bundle = new CorePhpBundle();
        $bundle->getContainerExtension()?->load([], $builder);

        self::assertTrue($builder->hasDefinition(MailerApiClient::class));
        $definition = $builder->getDefinition(MailerApiClient::class);
        self::assertTrue($definition->isAutowired());
        self::assertTrue($definition->isAutoconfigured());
        self::assertSame('%env(default:core_php.mailer_api.default_base_uri:API_MAILER_BASE_URL)%', $definition->getArgument('$baseUri'));
        self::assertSame('%env(default::API_MAILER_SECRET_KEY)%', $definition->getArgument('$apiToken'));

        self::assertTrue($builder->hasAlias(MailerClientInterface::class));
        self::assertSame(MailerApiClient::class, (string) $builder->getAlias(MailerClientInterface::class));

        self::assertSame(MailerApiClient::DEFAULT_BASE_URI, $builder->getParameter('core_php.mailer_api.default_base_uri'));
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

    public function testInterfaceResolvesToClientWithDefaultsWhenEnvIsMissing(): void
    {
        $httpClient = new MockHttpClient([]);
        $client = $this->compileAndGetClient($httpClient);

        self::assertInstanceOf(MailerApiClient::class, $client);

        // Le conteneur compile sans API_MAILER_SECRET_KEY ; l'erreur n'apparaît qu'à l'appel.
        $this->expectException(MailerConfigurationException::class);
        try {
            $client->healthCheck();
        } finally {
            self::assertSame(0, $httpClient->getRequestsCount());
        }
    }

    public function testClientUsesEnvironmentVariables(): void
    {
        $_ENV['API_MAILER_BASE_URL'] = 'https://mailer.internal';
        $_ENV['API_MAILER_SECRET_KEY'] = 'env-token';

        $response = new JsonMockResponse(['status' => 'success', 'message' => 'E-mail envoyé avec succès.']);
        $client = $this->compileAndGetClient(new MockHttpClient($response));

        self::assertTrue($client->send(new AuthOtpPayload('jane.doe@gmail.com', '482910'))->isSuccess());
        self::assertSame('https://mailer.internal/api/emails/send', $response->getRequestUrl());
        self::assertContains('Authorization: Bearer env-token', $response->getRequestOptions()['headers']);
    }

    public function testClientFallsBackToDefaultBaseUri(): void
    {
        $_ENV['API_MAILER_SECRET_KEY'] = 'env-token';

        $response = new JsonMockResponse(['status' => 'ok', 'service' => 'mailer-app', 'timestamp' => '2026-10-25T10:00:00+00:00']);
        $client = $this->compileAndGetClient(new MockHttpClient($response));

        $client->healthCheck();

        self::assertSame(MailerApiClient::DEFAULT_BASE_URI.'/api/health-check', $response->getRequestUrl());
    }

    private function createContainerBuilder(): ContainerBuilder
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('kernel.project_dir', PROJECT_DIR);
        $builder->setParameter('kernel.environment', 'test');
        $builder->setParameter('kernel.debug', false);

        return $builder;
    }

    /**
     * Compile un conteneur comme le ferait le Kernel d'un projet hôte, avec le
     * client HTTP fourni par FrameworkBundle remplacé par un mock.
     */
    private function compileAndGetClient(HttpClientInterface $httpClient): MailerClientInterface
    {
        $builder = $this->createContainerBuilder();
        $bundle = new CorePhpBundle();
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);

        $builder->registerExtension($extension);
        $builder->loadFromExtension($extension->getAlias());
        $bundle->build($builder);

        $builder->register(HttpClientInterface::class)->setSynthetic(true)->setPublic(true);
        // Les services du bundle sont privés : on expose l'alias pour le test.
        $builder->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->getAlias(MailerClientInterface::class)->setPublic(true);
            }
        });

        $builder->compile(true);
        $builder->set(HttpClientInterface::class, $httpClient);

        $client = $builder->get(MailerClientInterface::class);
        self::assertInstanceOf(MailerClientInterface::class, $client);

        return $client;
    }
}
