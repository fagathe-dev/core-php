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
    /** Mêmes options que JsonFileHandler::writeJson() par défaut. */
    private const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE;

    /** Octets lus en fin de fichier pour localiser le "]" final. */
    private const TAIL_LENGTH = 64;

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
            // Ajout en place (sans relire tout le fichier) ; réécriture complète si le fichier n'est pas un tableau JSON
            return $this->appendEntry($log->toArray()) ?? $this->rewriteWith($log->toArray());

        } catch (\Throwable $e) {
            error_log('Erreur JsonLogService: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Ajoute une entrée à la fin du tableau JSON sous verrou exclusif (écritures concurrentes sûres).
     *
     * Le fichier obtenu est identique à celui produit par writeJson() avec JSON_PRETTY_PRINT.
     *
     * @return bool|null null si le contenu actuel n'est pas un tableau JSON reconnu
     */
    private function appendEntry(array $entry): ?bool
    {
        $json = json_encode($entry, self::JSON_FLAGS);
        if ($json === false) {
            error_log('Erreur encodage JSON : ' . json_last_error_msg());
            return false;
        }

        // Indentation d'un élément de tableau, comme json_encode() du tableau complet
        $item = '    ' . str_replace("\n", "\n    ", $json);

        $directory = \dirname($this->logFilePath);
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return false;
        }

        $handle = @fopen($this->logFilePath, 'c+');
        if ($handle === false) {
            return false;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return false;
            }

            $size = (int) fstat($handle)['size'];
            if ($size === 0) {
                return fwrite($handle, "[\n" . $item . "\n]") !== false;
            }

            $tailLength = min($size, self::TAIL_LENGTH);
            fseek($handle, -$tailLength, SEEK_END);
            $tail = rtrim((string) fread($handle, $tailLength));

            if (!str_ends_with($tail, ']')) {
                return null;
            }

            $beforeBracket = rtrim(substr($tail, 0, -1));
            if ($beforeBracket === '') {
                return null;
            }

            // Position juste après le dernier élément (ou après "[" si le tableau est vide)
            $offset = $size - $tailLength + \strlen($beforeBracket);
            $separator = str_ends_with($beforeBracket, '[') ? "\n" : ",\n";

            ftruncate($handle, $offset);
            fseek($handle, $offset);
            $written = fwrite($handle, $separator . $item . "\n]");
            fflush($handle);

            return $written !== false;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Comportement historique : relit le fichier et le réécrit entièrement.
     */
    private function rewriteWith(array $entry): bool
    {
        $logs = $this->readLogs();
        $logs[] = $entry;

        return $this->jsonFileHandler->writeJson($this->logFilePath, $logs, self::JSON_FLAGS);
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