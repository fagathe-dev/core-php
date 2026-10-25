<?php

declare(strict_types=1);

namespace Fagathe\CorePhp;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Point d'entrée Symfony de core-php, enregistré automatiquement par Flex
 * (composer.json "type": "symfony-bundle").
 *
 * Chaque brique déclare ses services / paramètres dans un fichier config/<thème>.yaml.
 * Les constantes legacy de files/constants.php restent chargées par l'autoload Composer.
 */
final class CorePhpBundle extends AbstractBundle
{
    /**
     * Fichiers de configuration thématiques, relatifs à ce fichier.
     */
    private const CONFIG_FILES = [
        '../config/mailer_api.yaml',
        '../config/my_logger.yaml',
        '../config/my_uploader.yaml',
        '../config/my_email.yaml',
    ];

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        foreach (self::CONFIG_FILES as $file) {
            $container->import($file);
        }
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
