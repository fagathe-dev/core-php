<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Command;

use Fagathe\CorePhp\Logger\JsonLoggerRotation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Rotation des fichiers de logs : supprime les fichiers plus anciens que la durée de rétention
 * (core_php.logger.retention_delay) puis les dossiers devenus vides. Le travail est délégué à JsonLoggerRotation.
 */
#[AsCommand(
    name: 'core-php:log-file-rotation',
    description: 'Rotation et nettoyage des fichiers de logs obsolètes',
)]
final class LogFilesRotationCommand extends Command
{
    public function __construct(
        private readonly JsonLoggerRotation $rotation,
        #[Autowire(param: 'core_php.logger.logs_dir')]
        private readonly string $logsDir,
        #[Autowire(param: 'core_php.logger.retention_delay')]
        private readonly int $retentionDelay,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(
            <<<'HELP'
Supprime les fichiers de logs plus anciens que la durée de rétention (core_php.logger.retention_delay,
30 jours par défaut) ainsi que les dossiers devenus vides.

  php bin/console core-php:log-file-rotation

À planifier quotidiennement (cron).
HELP
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Rotation des fichiers de logs');
        $io->text([
            'Durée de rétention : ' . $this->retentionDelay . ' jours',
            'Répertoire des logs : ' . $this->logsDir,
        ]);
        $io->newLine();

        $report = $this->rotation->deleteOldFiles();

        $statistics = [
            sprintf('  • Date limite de conservation : %s', $report['threshold_date'] ?? 'n/a'),
            sprintf('  • Fichiers supprimés : %d', $report['files_deleted'] ?? 0),
            sprintf('  • Dossiers vides supprimés : %d', $report['folders_deleted'] ?? 0),
        ];

        if ($report['success'] ?? false) {
            $io->success(['Rotation des logs terminée avec succès.', '', 'Statistiques :', ...$statistics]);

            return Command::SUCCESS;
        }

        $errors = $report['errors'] ?? [];
        $io->warning([
            'Rotation des logs terminée avec des erreurs.',
            '',
            'Statistiques :',
            ...$statistics,
            sprintf('  • Erreurs rencontrées : %d', count($errors)),
        ]);

        if ($errors !== []) {
            $io->section('Détails des erreurs');
            $io->listing(array_map('strval', $errors));
        }

        return Command::FAILURE;
    }
}
