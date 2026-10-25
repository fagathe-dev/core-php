<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Logger;

use DateTimeImmutable;
use Fagathe\CorePhp\Enum\LoggerLevelEnum;
use Fagathe\CorePhp\Trait\DatetimeTrait;
use Iterator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Service de rotation et nettoyage des logs.
 *
 * Gère la suppression automatique des fichiers de logs obsolètes
 * et des dossiers vides selon la durée de rétention configurée.
 *
 * @author fagathe-dev <https://github.com/fagathe-dev/>
 */
final class JsonLoggerRotation
{
    use DatetimeTrait;

    private const DATE_FORMAT = 'd-m-Y';
    private const DATE_PATTERN = '/(\d{2}-\d{2}-\d{4})/';

    private Filesystem $filesystem;
    private readonly string $logsDir;
    private readonly int $retentionDelay;
    /** @var array<string> */
    private array $foldersToDelete = [];
    private ?Logger $logger = null;

    /**
     * @param Security    $security       Service de sécurité Symfony
     * @param string|null $logsDir        Injecté via core_php.logger.logs_dir (fallback sur constante LOGS_DIR)
     * @param int|null    $retentionDelay Injecté via core_php.logger.retention_delay (fallback sur constante LOGS_RETENTION_DELAY)
     */
    public function __construct(
        private readonly Security $security,
        ?string $logsDir = null,
        ?int $retentionDelay = null,
    ) {
        $this->filesystem = new Filesystem();

        $resolvedLogsDir = $logsDir ?? (defined('LOGS_DIR') ? (string) LOGS_DIR : null);
        if ($resolvedLogsDir === null || '' === trim($resolvedLogsDir)) {
            throw new \RuntimeException('Logs directory must be configured via "core_php.logger.logs_dir" or `LOGS_DIR` constant.');
        }

        $resolvedRetentionDelay = $retentionDelay ?? (defined('LOGS_RETENTION_DELAY') ? (int) LOGS_RETENTION_DELAY : null);
        if ($resolvedRetentionDelay === null) {
            throw new \RuntimeException('Logs retention delay must be configured via "core_php.logger.retention_delay" or `LOGS_RETENTION_DELAY` constant.');
        }

        $this->logsDir = rtrim($resolvedLogsDir, '/\\') . DIRECTORY_SEPARATOR;
        $this->retentionDelay = $resolvedRetentionDelay;
    }

    /**
     * Récupère tous les fichiers de logs.
     *
     * @return Iterator<string, SplFileInfo>
     */
    private function logFiles(): Iterator
    {
        return (new Finder())
            ->files()
            ->in($this->logsDir)
            ->name('*.json')
            ->sortByName()
            ->getIterator();
    }

    /**
     * Lance le processus de suppression des anciens fichiers de logs.
     *
     * Supprime les fichiers de logs plus anciens que $this->retentionDelay
     * et nettoie les dossiers vides.
     *
     * @return array<string, mixed> Rapport de l'opération avec les statistiques
     */
    public function deleteOldFiles(): array
    {
        $this->initLogger();

        $report = [
            'success' => false,
            'files_deleted' => 0,
            'folders_deleted' => 0,
            'errors' => [],
            'threshold_date' => null,
        ];

        if (!$this->filesystem->exists($this->logsDir)) {
            $error = 'Le répertoire de base "' . $this->logsDir . '" n\'existe pas.';
            $report['errors'][] = $error;
            $this->log(
                LoggerLevelEnum::Error,
                ['message' => $error],
                ['action' => 'log.rotation.directory_missing', 'origin' => 'cli.app:log-file-rotation']
            );

            return $report;
        }

        $this->log(
            LoggerLevelEnum::Info,
            ['message' => 'Début du nettoyage de fichiers de log'],
            ['action' => 'log.rotation.start', 'origin' => 'cli.app:log-file-rotation']
        );

        $report = $this->deleteOldLogFiles($report);
        $report = $this->deleteEmptyDirectories($report);

        $this->foldersToDelete = [];

        $report['success'] = empty($report['errors']);

        $this->log(
            $report['success'] ? LoggerLevelEnum::Info : LoggerLevelEnum::Warning,
            [
                'message' => 'Fin du nettoyage de fichiers de log',
                'files_deleted' => $report['files_deleted'],
                'folders_deleted' => $report['folders_deleted'],
                'errors_count' => count($report['errors']),
            ],
            ['action' => 'log.rotation.complete', 'origin' => 'cli.app:log-file-rotation']
        );

        return $report;
    }

    /**
     * Supprime les fichiers de logs obsolètes.
     *
     * @param array<string, mixed> $report Rapport en cours de construction
     *
     * @return array<string, mixed> Rapport mis à jour
     */
    private function deleteOldLogFiles(array $report): array
    {
        $files = $this->logFiles();
        $thresholdDate = $this->now()
            ->modify('-' . $this->retentionDelay . ' days')
            ->setTime(0, 0, 0);

        $report['threshold_date'] = $thresholdDate->format(self::DATE_FORMAT);

        $this->log(
            LoggerLevelEnum::Info,
            [
                'message' => 'Date limite de conservation',
                'threshold_date' => $report['threshold_date'],
                'retention_days' => $this->retentionDelay,
            ],
            ['action' => 'log.rotation.threshold', 'origin' => 'cli.app:log-file-rotation']
        );

        foreach ($files as $file) {
            $result = $this->deleteFileIfOld($file, $thresholdDate);
            if ($result['deleted']) {
                $report['files_deleted']++;
            }
            if (isset($result['error'])) {
                $report['errors'][] = $result['error'];
            }
        }

        return $report;
    }

    /**
     * Supprime un fichier s'il est plus ancien que la date seuil.
     *
     * @param SplFileInfo       $file          Fichier à vérifier
     * @param DateTimeImmutable $thresholdDate Date limite de conservation
     *
     * @return array{deleted: bool, error?: string} Résultat de l'opération
     */
    private function deleteFileIfOld(SplFileInfo $file, DateTimeImmutable $thresholdDate): array
    {
        $result = ['deleted' => false];
        $fileName = $file->getRelativePathname();

        preg_match(self::DATE_PATTERN, $fileName, $matches);
        $dateString = $matches[1] ?? null;

        if (!$dateString) {
            return $result;
        }

        $fileDate = DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $dateString);
        if ($fileDate === false) {
            return $result;
        }

        $fileDate = $fileDate->setTime(0, 0, 0);

        if ($fileDate < $thresholdDate) {
            $filePath = $file->getRealPath();
            if ($filePath !== false && $this->filesystem->exists($filePath)) {
                try {
                    $this->filesystem->remove($filePath);
                    $result['deleted'] = true;

                    $this->log(
                        LoggerLevelEnum::Info,
                        [
                            'message' => 'Fichier de log supprimé',
                            'file_path' => $filePath,
                            'file_date' => $dateString,
                        ],
                        ['action' => 'log.rotation.file_deleted', 'origin' => 'cli.app:log-file-rotation']
                    );
                } catch (IOExceptionInterface $e) {
                    $error = 'Erreur lors de la suppression du fichier ' . $filePath . ' : ' . $e->getMessage();
                    $result['error'] = $error;

                    $this->log(
                        LoggerLevelEnum::Error,
                        [
                            'message' => 'Erreur de suppression de fichier',
                            'file_path' => $filePath,
                            'error' => $e->getMessage(),
                        ],
                        ['action' => 'log.rotation.file_delete_error', 'origin' => 'cli.app:log-file-rotation']
                    );
                }
            }
        }

        return $result;
    }

    /**
     * Supprime les dossiers vides après nettoyage des logs.
     *
     * @param array<string, mixed> $report Rapport en cours de construction
     *
     * @return array<string, mixed> Rapport mis à jour
     */
    private function deleteEmptyDirectories(array $report): array
    {
        try {
            $finder = new Finder();
            $folders = $finder->directories()
                ->in($this->logsDir)
                ->ignoreDotFiles(true);

            // Trier par profondeur décroissante (dossiers les plus profonds d'abord)
            $sortedFolders = iterator_to_array($folders);
            usort($sortedFolders, static function (\SplFileInfo $a, \SplFileInfo $b): int {
                return substr_count($b->getPathname(), DIRECTORY_SEPARATOR) <=>
                    substr_count($a->getPathname(), DIRECTORY_SEPARATOR);
            });

            foreach ($sortedFolders as $dir) {
                $this->isFolderEmptyRecursive($dir->getPathname(), true);
            }

            $deletedCount = $this->removeFoldersToDelete();
            $report['folders_deleted'] = $deletedCount;
        } catch (IOExceptionInterface $e) {
            $error = 'Erreur lors de l\'exploration du répertoire ' . $this->logsDir . ' : ' . $e->getMessage();
            $report['errors'][] = $error;

            $this->log(
                LoggerLevelEnum::Error,
                [
                    'message' => 'Erreur d\'exploration des dossiers',
                    'directory' => $this->logsDir,
                    'error' => $e->getMessage(),
                ],
                ['action' => 'log.rotation.directory_scan_error', 'origin' => 'cli.app:log-file-rotation']
            );
        }

        return $report;
    }

    /**
     * Supprime les dossiers marqués pour suppression.
     *
     * @return int Nombre de dossiers supprimés
     */
    private function removeFoldersToDelete(): int
    {
        if (empty($this->foldersToDelete)) {
            $this->log(
                LoggerLevelEnum::Info,
                ['message' => 'Aucun dossier vide à supprimer'],
                ['action' => 'log.rotation.no_empty_folders', 'origin' => 'cli.app:log-file-rotation']
            );

            return 0;
        }

        $deletedCount = 0;

        $this->log(
            LoggerLevelEnum::Info,
            [
                'message' => 'Dossiers vides identifiés',
                'count' => count($this->foldersToDelete),
                'folders' => $this->foldersToDelete,
            ],
            ['action' => 'log.rotation.empty_folders_found', 'origin' => 'cli.app:log-file-rotation']
        );

        foreach ($this->foldersToDelete as $folder) {
            try {
                if ($this->filesystem->exists($folder)) {
                    $this->filesystem->remove($folder);
                    $deletedCount++;

                    $this->log(
                        LoggerLevelEnum::Info,
                        [
                            'message' => 'Dossier vide supprimé',
                            'folder' => $folder,
                        ],
                        ['action' => 'log.rotation.folder_deleted', 'origin' => 'cli.app:log-file-rotation']
                    );
                }
            } catch (IOExceptionInterface $e) {
                $this->log(
                    LoggerLevelEnum::Error,
                    [
                        'message' => 'Erreur de suppression de dossier',
                        'folder' => $folder,
                        'error' => $e->getMessage(),
                    ],
                    ['action' => 'log.rotation.folder_delete_error', 'origin' => 'cli.app:log-file-rotation']
                );
            }
        }

        return $deletedCount;
    }

    /**
     * Vérifie si un dossier est vide récursivement.
     *
     * Un dossier est considéré vide s'il ne contient aucun fichier JSON
     * et que tous ses sous-dossiers sont également vides (ou marqués à supprimer).
     *
     * @param string $directoryPath  Chemin du dossier à vérifier
     * @param bool   $ignoreDotFiles Ignorer les fichiers cachés
     *
     * @return bool True si le dossier est vide
     */
    private function isFolderEmptyRecursive(string $directoryPath, bool $ignoreDotFiles = true): bool
    {
        if (!$this->filesystem->exists($directoryPath)) {
            return true;
        }

        try {
            $finder = (new Finder())->in($directoryPath)->depth('== 0')->ignoreDotFiles($ignoreDotFiles);

            // Compter les fichiers .json directement présents
            $jsonFilesCount = (clone $finder)
                ->files()
                ->name('*.json')
                ->count();

            // Vérifier récursivement les sous-dossiers directs
            $directories = (clone $finder)->directories();
            $allSubDirsEmpty = true;

            foreach ($directories as $dir) {
                if (!$this->isFolderEmptyRecursive($dir->getPathname(), $ignoreDotFiles)) {
                    $allSubDirsEmpty = false;
                }
            }

            $isEmpty = $jsonFilesCount === 0 && $allSubDirsEmpty;

            if ($isEmpty) {
                $this->addFolderToDelete($directoryPath);
            }

            return $isEmpty;
        } catch (IOExceptionInterface $e) {
            $this->log(
                LoggerLevelEnum::Error,
                [
                    'message' => 'Erreur de vérification de dossier vide',
                    'directory' => $directoryPath,
                    'error' => $e->getMessage(),
                ],
                ['action' => 'log.rotation.folder_check_error', 'origin' => 'cli.app:log-file-rotation']
            );

            return false;
        }
    }

    /**
     * Ajoute un dossier à la liste de suppression.
     *
     * @param string $folder Chemin du dossier à supprimer
     */
    private function addFolderToDelete(string $folder): void
    {
        if (!in_array($folder, $this->foldersToDelete, true)) {
            $this->foldersToDelete[] = $folder;
        }
    }

    /**
     * Initialise le logger (lazy loading) en lui transmettant $this->logsDir.
     */
    private function initLogger(): void
    {
        if ($this->logger === null) {
            $this->logger = new Logger(
                'system/log-rotation',
                $this->security,
                false, // Ne pas logger l'IP pour éviter les boucles infinies
                $this->logsDir
            );
        }
    }

    /**
     * Log une opération.
     *
     * @param LoggerLevelEnum      $level   Niveau de log
     * @param array<string, mixed> $content Contenu du log
     * @param array<string, mixed> $context Contexte additionnel
     */
    private function log(LoggerLevelEnum $level, array $content = [], array $context = []): void
    {
        try {
            if ($this->logger !== null) {
                $this->logger->log($level, $content, $context);
            }
        } catch (\Throwable $e) {
            // En cas d'erreur, on utilise error_log de PHP
            error_log('JsonLoggerRotation Logger Error: ' . $e->getMessage());
        }
    }
}