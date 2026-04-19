<?php

namespace Fagathe\CorePhp\Trait;

use Fagathe\CorePhp\Logger\Logger;
use Fagathe\CorePhp\Enum\LoggerLevelEnum;

/**
 * Trait pour la gestion centralisée du Logger (journalisation).
 * * Ce trait permet aux classes qui l'utilisent d'accéder au service Logger 
 * et de générer automatiquement des noms de fichiers de log basés sur la classe hôte.
 */
trait LoggerTrait
{

    /**
     * Génère un log avec le service Logger personnalisé.
     * * Permet de logger les actions des services avec contexte automatique.
     * Le filename est automatiquement généré basé sur le nom de la classe.
     * * @param LoggerLevelEnum $level   Niveau du log
     * @param array          $content Contenu du log
     * @param array          $context Contexte additionnel
     * @param string|null    $customFilename Filename personnalisé (optionnel)
     * * @return void
     */
    protected function generateLog(
        LoggerLevelEnum $level = LoggerLevelEnum::Info,
        array $content = [],
        array $context = [],
        ?string $customFilename = null
    ): void {
        // Générer automatiquement le filename si non fourni
        $filename = $customFilename ?? $this->generateServiceLogFilename();

        // Créer le Logger à la volée avec le fichier spécifique
        $logger = new Logger($filename, $this->security, true);
        $logger->log($level, $content, $context);
    }

    /**
     * Génère le filename de log automatiquement basé sur le nom de la classe hôte et son namespace.
     * * Logique :
     * 1. Récupère le dernier segment du namespace (ex: "Service" dans "Fagathe\CorePhp\Service") pour le dossier.
     * 2. Récupère le nom de la classe (ex: "UserService") et le convertit en kebab-case.
     * * Exemple: Fagathe\CorePhp\Service\UserService -> service/user-service
     * * @return string Le chemin relatif du fichier de log (sans extension, sans date)
     */
    private function generateServiceLogFilename(): string
    {
        $reflection = new \ReflectionClass($this);

        // 1. Récupérer le dossier (Dernière partie du namespace)
        // Ex: Fagathe\CorePhp\Service -> Service
        $namespaceParts = explode('\\', $reflection->getNamespaceName());
        $directory = strtolower(end($namespaceParts));

        // 2. Récupérer le nom de la classe en kebab-case
        // Ex: UserService -> user-service
        $className = $reflection->getShortName();
        $filename = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $className));

        // 3. Assembler le chemin : service/user-service
        return sprintf('%s/%s', $directory, $filename);
    }
}