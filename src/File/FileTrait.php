<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\File;

use Fagathe\CorePhp\File\FileSizeFormatter;
use Fagathe\CorePhp\File\FileTypeEnum; // Ajuster le namespace si nécessaire (gardé Fagathe\Libs\File)
use SplFileInfo;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;

/**
 * Fournit des méthodes utilitaires pour la manipulation des fichiers (MIME et formatage de taille).
 */
trait FileTrait
{

    protected Filesystem $filesystem;

    /**
     * Tente de faire correspondre un type MIME à un type de fichier lisible (Archive, Image, PDF, etc.).
     *
     * @param string $mimeType Le type MIME à vérifier.
     * @return string|null La description du type de fichier ou null s'il n'est pas reconnu.
     */
    protected function matchMimeType(string $mimeType): ?string
    {
        // On suppose que FileTypeEnum est accessible.
        // NOTE: Si FileTypeEnum est dans Fagathe\Libs\File, ajuster l'USE en haut.
        return FileTypeEnum::matchMime($mimeType);
    }

    /**
     * Formate la taille d'un fichier en octets vers un format plus lisible.
     *
     * @param int|null $filesize La taille du fichier en octets.
     * @param int $precision Le nombre de décimales à conserver (par défaut 2).
     * @return string La taille formatée (ex: "5.45 MB").
     */
    protected function formatFileSize(?int $filesize, int $precision = 2): string
    {
        return FileSizeFormatter::formatFileSize($filesize, $precision);
    }

    /**
     * Initialise et retourne le composant Filesystem.
     * Cette méthode suppose que la classe qui utilise ce trait a une façon d'obtenir ou d'initialiser Filesystem.
     * Dans un contexte Symfony, il est préférable d'injecter Filesystem dans le constructeur de la classe utilisatrice.
     *
     * @param Filesystem $filesystem
     */
    public function setFilesystem(Filesystem $filesystem): void
    {
        $this->filesystem = $filesystem;
    }

    /**
     * Récupère l'instance de Filesystem. Doit être initialisée au préalable via setFilesystem.
     *
     * @return Filesystem
     * @throws \LogicException si Filesystem n'est pas initialisé.
     */
    protected function getFilesystem(): Filesystem
    {
        if ($this->filesystem === null) {
            throw new \LogicException(sprintf(
                'Le composant Filesystem doit être initialisé via la méthode %s::setFilesystem() avant d\'être utilisé.',
                static::class
            ));
        }

        return $this->filesystem;
    }

    /**
     * Supprime un fichier donné et supprime le répertoire parent s'il devient vide.
     *
     * @param string $filePath Le chemin complet du fichier à supprimer (doit être un chemin absolu).
     * @return bool Vrai si la suppression a réussi ou si le fichier n'existait pas, faux sinon.
     */
    protected function deleteFileAndEmptyDirectory(string $filePath): bool
    {
        $fs = $this->getFilesystem();

        // 1. Vérifier si le fichier existe avant de continuer
        if (!$fs->exists($filePath)) {
            // Le fichier n'existe pas, l'opération de "suppression" est considérée comme réussie.
            return true;
        }

        try {
            // Déterminer le chemin du répertoire parent
            // SplFileInfo est utilisé pour extraire le répertoire sans dépendre du composant Path (non standard ici).
            $directoryPath = (new SplFileInfo($filePath))->getPath();

            // 2. Supprimer le fichier
            $fs->remove($filePath);

            // 3. Vérifier si le répertoire parent existe toujours et s'il est vide
            if ($fs->exists($directoryPath) && $this->isDirectoryEmpty($directoryPath)) {
                // Le répertoire existe et est vide (ne contient que '.' et '..'), on le supprime
                $fs->remove($directoryPath);
            }

            return true;

        } catch (IOExceptionInterface $e) {
            // Gérer l'exception en cas d'erreur d'IO (permissions insuffisantes, disque plein, etc.)
            error_log(sprintf('Erreur lors de la suppression du fichier ou du répertoire (%s) : %s', $filePath, $e->getMessage()));

            return false;
        }
    }

    /**
     * Vérifie si un répertoire est vide (en excluant '.' et '..').
     *
     * @param string $directoryPath Le chemin du répertoire.
     * @return bool Vrai si le répertoire est vide, faux sinon.
     */
    private function isDirectoryEmpty(string $directoryPath): bool
    {
        // Utilise scandir pour obtenir la liste des fichiers/dossiers.
        // Si scandir retourne FALSE, cela peut indiquer un problème de permission, on retourne FALSE pour être sûr.
        $files = @scandir($directoryPath, SCANDIR_SORT_NONE);

        if ($files === false) {
            return false;
        }

        // Un répertoire vide contient uniquement les entrées '.' et '..'
        return count($files) <= 2;
    }

    /**
     * Récupère la liste des fichiers dans un répertoire donné, avec un filtre d'extension optionnel.
     *
     * @param string $folder Le chemin absolu du répertoire à scanner.
     * @param string|null $extension L'extension à filtrer (ex: 'jpg', 'pdf'). Si null, tous les fichiers sont retournés.
     * @param bool $raiseException Déclenche une exception si le dossier n'est pas trouvé. Par défaut, retourne un tableau vide.
     * @return array<string> Un tableau de chemins de fichiers absolus.
     */
    protected function getFilesInFolder(string $folder, ?string $extension = null, bool $raiseException = false): array
    {
        // On n'a pas besoin de Filesystem ici, mais plutôt de Finder
        $finder = new Finder();
        $files = [];

        try {
            // On s'assure que le dossier existe avant de chercher
            if (!$this->getFilesystem()->exists($folder)) {
                if ($raiseException) {
                    throw new DirectoryNotFoundException(sprintf('Le répertoire "%s" n\'existe pas.', $folder));
                }
                return []; // Retourne tableau vide si dossier inexistant et pas d'exception demandée
            }

            // Configure le Finder
            $finder->files()->in($folder);

            if ($extension !== null) {
                // Le Finder filtre par le nom du fichier. 
                // On utilise une expression régulière pour cibler l'extension.
                // Le point est échappé. L'extension peut être précédée d'un point ou non dans l'argument.
                $extPattern = str_starts_with($extension, '.') ? substr($extension, 1) : $extension;
                $finder->name('/\.' . preg_quote($extPattern, '/') . '$/i');
            }

            // Récupère les chemins absolus des fichiers trouvés
            /** @var SplFileInfo $file */
            foreach ($finder as $file) {
                $files[] = $file->getRealPath();
            }

        } catch (DirectoryNotFoundException $e) {
            if ($raiseException) {
                // Relance l'exception si l'utilisateur l'a demandé
                throw $e;
            }
            // Log l'erreur si besoin, sinon retourne simplement un tableau vide
        } catch (\Throwable $e) {
            // Gérer les autres exceptions potentielles (ex: problème de permission)
            error_log(sprintf('Erreur lors de la récupération des fichiers dans le dossier "%s" : %s', $folder, $e->getMessage()));
            if ($raiseException) {
                throw $e;
            }
        }

        return $files;
    }
}