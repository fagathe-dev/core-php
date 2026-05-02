<?php

namespace Fagathe\CorePhp\Logger;

use Fagathe\CorePhp\Logger\Logger;
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

    private Finder $finder;
    private Filesystem $filesystem;
    private array $foldersToDelete = [];
    private ?Logger $logger = null;

    /**
     * @param Security $security Service de sécurité Symfony
     */
    public function __construct(
        private readonly Security $security
    ) {
        $this->finder = new Finder();
        $this->filesystem = new Filesystem();
        if (!defined('LOGS_DIR')) {
            throw new \RuntimeException('Undefined constant `LOGS_DIR` must be defined.');
        }
        if (!defined('LOGS_RETENTION_DELAY')) {
            throw new \RuntimeException('Undefined constant `LOGS_RETENTION_DELAY` must be defined.');
        }
    }

    /**
     * Récupère tous les fichiers de logs.
     * 
     * @return Iterator
     */
    private function logFiles(): Iterator
    {
        return $this->finder->files()
            ->in(LOGS_DIR)
            ->name('*.json')
            ->sortByName()
            ->getIterator();
    }

    /**
     * Lance le processus de suppression des anciens fichiers de logs.
     * 
     * Supprime les fichiers de logs plus anciens que LOGS_RETENTION_DELAY
     * et nettoie les dossiers vides.
     * 
     * @return array Rapport de l'opération avec les statistiques
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

        if (!$this->filesystem->exists(LOGS_DIR)) {
            $error = 'Le répertoire de base "' . LOGS_DIR . '" n\'existe pas.';
            $report['errors'][] = $error;
            $this->log(LoggerLevelEnum::Error, ['message' => $error], ['action' => 'log.rotation.directory_missing', 'origin' => 'cli.app:log-file-rotation']);
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
                'errors_count' => count($report['errors'])
            ],
            ['action' => 'log.rotation.complete', 'origin' => 'cli.app:log-file-rotation']
        );

        return $report;
    }

    /**
     * Supprime les fichiers de logs obsolètes.
     * 
     * @param array $report Rapport en cours de construction
     * 
     * @return array Rapport mis à jour
     */
    private function deleteOldLogFiles(array $report): array
    {
        $files = $this->logFiles();
        $thresholdDate = $this->now()
            ->modify('-' . LOGS_RETENTION_DELAY . ' days')
            ->setTime(0, 0, 0);

        $report['threshold_date'] = $thresholdDate->format(self::DATE_FORMAT);

        $this->log(
            LoggerLevelEnum::Info,
            [
                'message' => 'Date limite de conservation',
                'threshold_date' => $report['threshold_date'],
                'retention_days' => LOGS_RETENTION_DELAY
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
     * @param SplFileInfo      $file          Fichier à vérifier
     * @param DateTimeImmutable $thresholdDate Date limite de conservation
     * 
     * @return array Résultat de l'opération
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
            if ($this->filesystem->exists($filePath)) {
                try {
                    $this->filesystem->remove($filePath);
                    $result['deleted'] = true;

                    $this->log(
                        LoggerLevelEnum::Info,
                        [
                            'message' => 'Fichier de log supprimé',
                            'file_path' => $filePath,
                            'file_date' => $dateString
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
                            'error' => $e->getMessage()
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
     * @param array $report Rapport en cours de construction
     * 
     * @return array Rapport mis à jour
     */
    private function deleteEmptyDirectories(array $report): array
    {
        try {
            $finder = new Finder();
            $folders = $finder->directories()
                ->in(LOGS_DIR)
                ->ignoreDotFiles(true);

            // Trier par profondeur décroissante (dossiers les plus profonds d'abord)
            $sortedFolders = iterator_to_array($folders);
            usort($sortedFolders, function (\SplFileInfo $a, \SplFileInfo $b) {
                return substr_count($b->getPathname(), DIRECTORY_SEPARATOR) <=>
                    substr_count($a->getPathname(), DIRECTORY_SEPARATOR);
            });

            foreach ($sortedFolders as $dir) {
                $this->isFolderEmptyRecursive($dir->getPathname(), true);
            }

            $deletedCount = $this->removeFoldersToDelete();
            $report['folders_deleted'] = $deletedCount;

        } catch (IOExceptionInterface $e) {
            $error = 'Erreur lors de l\'exploration du répertoire ' . LOGS_DIR . ' : ' . $e->getMessage();
            $report['errors'][] = $error;

            $this->log(
                LoggerLevelEnum::Error,
                [
                    'message' => 'Erreur d\'exploration des dossiers',
                    'directory' => LOGS_DIR,
                    'error' => $e->getMessage()
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
                'folders' => $this->foldersToDelete
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
                            'folder' => $folder
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
                        'error' => $e->getMessage()
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
     * et que tous ses sous-dossiers sont également vides.
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
            $finder = (new Finder())->in($directoryPath)->ignoreDotFiles($ignoreDotFiles);

            // Compter les fichiers .json
            $jsonFilesCount = (clone $finder)
                ->files()
                ->name('*.json')
                ->count();

            // Compter les dossiers
            $directories = (clone $finder)->directories();
            $directoriesCount = $directories->count();

            // Vérifier récursivement les sous-dossiers
            if ($directoriesCount > 0) {
                foreach ($directories as $dir) {
                    $this->isFolderEmptyRecursive($dir->getPathname(), true);
                }
            }

            $isEmpty = $directoriesCount === 0 && $jsonFilesCount === 0;

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
                    'error' => $e->getMessage()
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
     * 
     * @return void
     */
    private function addFolderToDelete(string $folder): void
    {
        if (!in_array($folder, $this->foldersToDelete, true)) {
            $this->foldersToDelete[] = $folder;
        }
    }

    /**
     * Initialise le logger (lazy loading).
     * 
     * @return void
     */
    private function initLogger(): void
    {
        if ($this->logger === null) {
            $this->logger = new Logger(
                'system/log-rotation',
                $this->security,
                false // Ne pas logger l'IP pour éviter les boucles infinies
            );
        }
    }

    /**
     * Log une opération.
     * 
     * @param LoggerLevelEnum $level   Niveau de log
     * @param array           $content Contenu du log
     * @param array           $context Contexte additionnel
     * 
     * @return void
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
