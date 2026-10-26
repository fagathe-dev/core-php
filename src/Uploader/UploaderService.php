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

    private readonly string $publicDir;
    private readonly string $uploadDir;
    private readonly bool $renameFile;

    public function __construct(
        private readonly Security $security,
        private readonly SluggerInterface $slugger,
        private readonly string $projectDir,
        ?string $publicDir = null,
        ?string $uploadDir = null,
        ?bool $renameFile = null,
    ) {
        $this->filesystem = new Filesystem();
        $this->publicDir = $publicDir ?? (defined('PUBLIC_DIR') ? (string) PUBLIC_DIR : 'public');
        $this->uploadDir = $uploadDir ?? (defined('UPLOAD_DIR') ? (string) UPLOAD_DIR : 'uploads');
        $this->renameFile = $renameFile ?? (defined('UPLOAD_RENAME_FILE') ? (bool) UPLOAD_RENAME_FILE : true);
    }

    /**
     * Retourne le chemin absolu vers le dossier public du projet.
     */
    private function getAbsolutePublicDir(): string
    {
        return rtrim($this->projectDir, '/\\') . DIRECTORY_SEPARATOR . trim($this->publicDir, '/\\');
    }

    public function setUploadDirectory(string $subDirectory): self
    {
        $this->subDirectory = trim($subDirectory, '/');
        return $this;
    }

    /**
     * Retourne le chemin relatif du dossier d'upload (ex: uploads).
     */
    private function getRelativeUploadDir(): string
    {
        return trim($this->uploadDir, '/\\');
    }


    public function upload(UploadedFile $file, ?string $oldFilename = null): UploadResult
    {
        $originalName = $file->getClientOriginalName();
        $safeFilename = $this->slugger->slug(pathinfo($originalName, PATHINFO_FILENAME))->lower();
        $extension = $file->guessExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION);

        $newFilename = $this->renameFile
            ? $safeFilename . '-' . uniqid() . '.' . $extension
            : $safeFilename . '.' . $extension;

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

    /**
     * Supprime un fichier uploadé à partir de son chemin relatif au dossier public (ex: uploads/avatars/photo.jpg).
     *
     * Seuls les fichiers du dossier d'upload sont supprimables : un chemin qui en sort (`..`, lien symbolique),
     * qui vise un dossier ou un fichier inexistant est ignoré.
     *
     * @return bool true si le fichier a été supprimé
     */
    public function delete(string $path): bool
    {
        $absolutePath = $this->resolveUploadedFilePath($path);

        if ($absolutePath === null) {
            return false;
        }

        $this->filesystem->remove($absolutePath);

        return true;
    }

    /**
     * Retourne le chemin absolu d'un fichier existant du dossier d'upload, null si le chemin en sort.
     */
    private function resolveUploadedFilePath(string $path): ?string
    {
        $segments = explode('/', str_replace('\\', '/', $path));

        if (str_contains($path, "\0") || in_array('..', $segments, true)) {
            return null;
        }

        $relativePath = implode('/', array_filter($segments, static fn(string $segment): bool => $segment !== '' && $segment !== '.'));
        $uploadDir = $this->getRelativeUploadDir();

        if (!str_starts_with($relativePath, $uploadDir . '/')) {
            return null;
        }

        $absolutePath = $this->getAbsolutePublicDir() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        // Le fichier réel (liens symboliques résolus) doit rester dans le dossier d'upload
        $realPath = realpath($absolutePath);
        $realUploadDir = realpath($this->getAbsolutePublicDir() . DIRECTORY_SEPARATOR . $uploadDir);

        if ($realPath === false || $realUploadDir === false || !is_file($realPath)
            || !str_starts_with($realPath, $realUploadDir . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $absolutePath;
    }
}
