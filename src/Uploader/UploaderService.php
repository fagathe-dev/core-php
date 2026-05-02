<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Uploader;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Filesystem\Filesystem;
use Fagathe\CorePhp\Uploader\FileUploadException;
use Fagathe\CorePhp\Uploader\UploadResult;
use Fagathe\CorePhp\Trait\LoggerTrait;
use Fagathe\CorePhp\Enum\LoggerLevelEnum;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\Slugger\SluggerInterface;
use Throwable;

class UploaderService
{
    use LoggerTrait;

    protected string $subDirectory = '';
    private Filesystem $filesystem;

    public function __construct(
        private readonly Security $security,
        private readonly SluggerInterface $slugger,
        private readonly string $projectDir, // Chemin racine du projet Symfony
    ) {
        $this->filesystem = new Filesystem();
    }

    public function setUploadDirectory(string $subDirectory): self
    {
        $this->subDirectory = trim($subDirectory, '/');
        return $this;
    }

    /**
     * Retourne le chemin absolu vers le dossier public du projet.
     */
    private function getAbsolutePublicDir(): string
    {
        $publicFolderName = defined('PUBLIC_DIR') ? (string) PUBLIC_DIR : 'public';
        return rtrim($this->projectDir, '/\\') . DIRECTORY_SEPARATOR . $publicFolderName;
    }

    /**
     * Retourne le chemin relatif du dossier d'upload (ex: uploads).
     */
    private function getRelativeUploadDir(): string
    {
        return defined('UPLOAD_DIR') ? (string) UPLOAD_DIR : 'uploads';
    }

    public function upload(UploadedFile $file, ?string $oldFilename = null): UploadResult
    {
        $originalName = $file->getClientOriginalName();
        $safeFilename = $this->slugger->slug(pathinfo($originalName, PATHINFO_FILENAME))->lower();
        $extension = $file->guessExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;

        // 1. Calcul du chemin relatif final pour la BDD (ex: uploads/avatars)
        $relativeDir = $this->getRelativeUploadDir() . ($this->subDirectory ? '/' . $this->subDirectory : '');

        // 2. Calcul du chemin ABSOLU sur le disque pour PHP (ex: /Users/.../public/uploads/avatars)
        $absoluteTargetDir = $this->getAbsolutePublicDir() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);

        if (!$this->filesystem->exists($absoluteTargetDir)) {
            $this->filesystem->mkdir($absoluteTargetDir, 0755);
        }

        try {
            // Déplacement physique du fichier
            $file->move($absoluteTargetDir, $newFilename);

            if ($oldFilename) {
                $this->delete($oldFilename);
            }

            // 3. Construction du résultat avec le chemin relatif propre pour l'application
            $finalRelativePath = $relativeDir . '/' . $newFilename;

            return new UploadResult(
                relativePath: str_replace('//', '/', $finalRelativePath),
                originalName: $originalName,
                newName: $newFilename,
                size: filesize($absoluteTargetDir . DIRECTORY_SEPARATOR . $newFilename),
                mimeType: $file->getClientMimeType(),
                extension: $extension,
            );
        } catch (Throwable $e) {
            throw new FileUploadException("Échec de l'upload : " . $e->getMessage());
        }
    }

    public function delete(string $path): void
    {
        // On construit le chemin absolu à partir de la racine public
        $absolutePath = $this->getAbsolutePublicDir() . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        
        if ($this->filesystem->exists($absolutePath)) {
            $this->filesystem->remove($absolutePath);
        }
    }
}