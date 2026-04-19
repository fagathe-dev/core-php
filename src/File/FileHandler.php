<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\File;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

/**
 * Gestionnaire de fichiers pour la lecture et l'écriture de fichiers.
 * 
 * Fournit des méthodes de base pour manipuler les fichiers avec
 * gestion d'erreurs et création automatique des répertoires.
 * 
 * @author Journal App
 */
class FileHandler
{
    use FileTrait;

    public function __construct()
    {
        $this->setFilesystem(new Filesystem());
    }

    /**
     * Lit le contenu d'un fichier.
     * 
     * @param string $filePath Chemin complet vers le fichier
     * @return string|null Contenu du fichier ou null si erreur
     */
    public function read(string $filePath): ?string
    {
        if (!$this->filesystem->exists($filePath)) {
            return null;
        }

        try {
            $content = file_get_contents($filePath);
            return $content !== false ? $content : null;
        } catch (\Throwable $e) {
            error_log('Erreur lecture fichier: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Écrit du contenu dans un fichier.
     * 
     * @param string $filePath Chemin complet vers le fichier
     * @param string $content Contenu à écrire
     * @return bool True si l'écriture a réussi
     */
    public function write(string $filePath, string $content): bool
    {
        try {
            $this->ensureDirectoryExists(dirname($filePath));
            $this->filesystem->dumpFile($filePath, $content);
            return true;
        } catch (IOExceptionInterface $e) {
            error_log('Erreur écriture fichier: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Ajoute du contenu à la fin d'un fichier.
     * 
     * @param string $filePath Chemin complet vers le fichier
     * @param string $content Contenu à ajouter
     * @return bool True si l'ajout a réussi
     */
    public function append(string $filePath, string $content): bool
    {
        try {
            $this->ensureDirectoryExists(dirname($filePath));
            $this->filesystem->appendToFile($filePath, $content);
            return true;
        } catch (IOExceptionInterface $e) {
            error_log('Erreur ajout fichier: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifie si un fichier existe.
     * 
     * @param string $filePath Chemin vers le fichier
     * @return bool True si le fichier existe
     */
    public function exists(string $filePath): bool
    {
        return $this->filesystem->exists($filePath);
    }

    /**
     * Supprime un fichier.
     * 
     * @param string $filePath Chemin vers le fichier
     * @return bool True si la suppression a réussi
     */
    public function delete(string $filePath): bool
    {
        try {
            if ($this->filesystem->exists($filePath)) {
                $this->filesystem->remove($filePath);
            }
            return true;
        } catch (IOExceptionInterface $e) {
            error_log('Erreur suppression fichier: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtient la taille d'un fichier en octets.
     * 
     * @param string $filePath Chemin vers le fichier
     * @return int|null Taille en octets ou null si erreur
     */
    public function getSize(string $filePath): ?int
    {
        if (!$this->filesystem->exists($filePath)) {
            return null;
        }

        $size = filesize($filePath);
        return $size !== false ? $size : null;
    }

    /**
     * Obtient la taille formatée d'un fichier.
     * 
     * @param string $filePath Chemin vers le fichier
     * @param int $precision Nombre de décimales
     * @return string Taille formatée
     */
    public function getFormattedSize(string $filePath, int $precision = 2): string
    {
        $size = $this->getSize($filePath);
        return $this->formatFileSize($size, $precision);
    }

    /**
     * Obtient la date de dernière modification d'un fichier.
     * 
     * @param string $filePath Chemin vers le fichier
     * @return \DateTime|null Date de modification ou null si erreur
     */
    public function getLastModified(string $filePath): ?\DateTime
    {
        if (!$this->filesystem->exists($filePath)) {
            return null;
        }

        $time = filemtime($filePath);
        if ($time === false) {
            return null;
        }

        return new \DateTime('@' . $time);
    }

    /**
     * Copie un fichier vers un autre emplacement.
     * 
     * @param string $source Chemin source
     * @param string $destination Chemin destination
     * @param bool $overwrite Écraser le fichier de destination s'il existe
     * @return bool True si la copie a réussi
     */
    public function copy(string $source, string $destination, bool $overwrite = false): bool
    {
        try {
            if (!$this->filesystem->exists($source)) {
                return false;
            }

            if (!$overwrite && $this->filesystem->exists($destination)) {
                return false;
            }

            $this->ensureDirectoryExists(dirname($destination));
            $this->filesystem->copy($source, $destination, $overwrite);
            return true;
        } catch (IOExceptionInterface $e) {
            error_log('Erreur copie fichier: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Assure que le répertoire existe, le crée si nécessaire.
     * 
     * @param string $directory Chemin du répertoire
     * @return void
     * @throws IOExceptionInterface Si impossible de créer le répertoire
     */
    protected function ensureDirectoryExists(string $directory): void
    {
        if (!$this->filesystem->exists($directory)) {
            $this->filesystem->mkdir($directory, 0755);
        }
    }

    /**
     * Obtient l'instance Filesystem utilisée.
     * 
     * @return Filesystem
     */
    protected function getFilesystem(): Filesystem
    {
        return $this->filesystem;
    }
}