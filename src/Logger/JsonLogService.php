<?php

namespace Fagathe\CorePhp\Logger;

use Fagathe\CorePhp\File\JsonFileHandler;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Service de gestion des logs au format JSON.
 * 
 * Gère uniquement l'enregistrement et la récupération de données 
 * dans les fichiers .json de logs.
 * 
 * @author fagathe-dev <https://github.com/fagathe-dev/>
 */
class JsonLogService
{
    private JsonFileHandler $jsonFileHandler;
    private string $logFilePath;
    private ?JsonLoggerRotation $rotationService = null;

    /**
     * @param string        $logFilePath Chemin du fichier de log
     * @param Security|null $security    Service de sécurité (optionnel, pour rotation)
     */
    public function __construct(
        string $logFilePath,
        private readonly ?Security $security = null
    ) {
        $this->logFilePath = $logFilePath;
        $this->jsonFileHandler = new JsonFileHandler();
    }

    /**
     * Sauvegarde un log dans le fichier JSON.
     * 
     * @param Log $log Instance de log à sauvegarder
     * 
     * @return bool True si la sauvegarde a réussi
     */
    public function save(Log $log): bool
    {
        try {
            $logs = $this->readLogs();
            $logs[] = $log->toArray();

            return $this->jsonFileHandler->writeJson($this->logFilePath, $logs);

        } catch (\Throwable $e) {
            error_log('Erreur JsonLogService: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Lit tous les logs du fichier.
     * 
     * @return array
     */
    public function readLogs(): array
    {
        try {
            $logs = $this->jsonFileHandler->readJson($this->logFilePath, true);
            return $logs ?? [];

        } catch (\Throwable $e) {
            error_log('Erreur lecture logs: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Supprime les anciens fichiers de logs selon la durée de rétention.
     * 
     * Utilise le service JsonLoggerRotation pour supprimer les fichiers
     * de logs plus anciens que LOGS_RETENTION_DELAY jours.
     * 
     * @return array Rapport de l'opération avec statistiques
     * 
     * @throws \RuntimeException Si la dépendance Security n'est pas fournie
     */
    public function deleteOldFiles(): array
    {
        if ($this->security === null) {
            throw new \RuntimeException(
                'JsonLogService requires Security to be injected for deleteOldFiles() operation'
            );
        }

        if ($this->rotationService === null) {
            $this->rotationService = new JsonLoggerRotation($this->security);
        }

        return $this->rotationService->deleteOldFiles();
    }
}