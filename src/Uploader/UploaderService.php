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
        private readonly string $projectDir,
        private readonly string $environment
    ) {
        $this->filesystem = new Filesystem();
    }

    /**
     * @param string $subDirectory
     * @return self
     */
    public function setUploadDirectory(string $subDirectory): self
    {
        $this->subDirectory = trim($subDirectory, '/');
        return $this;
    }

    /**
     * Calcule le chemin absolu du répertoire d'uploads de manière robuste.
     * @return string
     */
    private function getBaseDirectory(): string
    {
        return (defined('PUBLIC_DIR') ? (string) PUBLIC_DIR . '/' : 'public/') . (defined('UPLOAD_DIR') ? (string) UPLOAD_DIR : 'uploads/');
    }

    /**
     * @param UploadedFile $file
     * @param string|null $oldFilename
     * 
     * @return UploadResult
     */
    public function upload(UploadedFile $file, ?string $oldFilename = null): UploadResult
    {
        $originalName = $file->getClientOriginalName();

        $this->generateLog(LoggerLevelEnum::Info, [
            'action' => 'upload_start',
            'file' => $originalName,
        ]);

        return $this->handleStandardUpload($file, $originalName, $oldFilename);
    }

    /**
     * @param UploadedFile $file
     * @param string $originalName
     * @param string|null $oldFilename
     * 
     * @return UploadResult
     */
    private function handleStandardUpload(UploadedFile $file, string $originalName, ?string $oldFilename): UploadResult
    {
        $safeFilename = $this->slugger->slug(pathinfo($originalName, PATHINFO_FILENAME))->lower();
        $extension = $file->guessExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;

        $targetDir = rtrim($this->getBaseDirectory() . '/' . $this->subDirectory, '/');

        if (!$this->filesystem->exists($targetDir)) {
            $this->filesystem->mkdir($targetDir, 0755);
        }

        try {
            $file->move($targetDir, $newFilename);

            if ($oldFilename) {
                $this->delete($oldFilename);
            }

            $relativePath = 'uploads/' . ltrim($this->subDirectory . '/' . $newFilename, '/');

            return new UploadResult(
                relativePath: str_replace('//', '/', $relativePath),
                originalName: $originalName,
                newName: $newFilename,
                size: filesize($targetDir . '/' . $newFilename),
                mimeType: $file->getClientMimeType(),
                extension: $extension,
            );
        } catch (Throwable $e) {
            throw new FileUploadException("Échec de l'upload : " . $e->getMessage());
        }
    }

    /**
     * @param string $path
     * 
     * @return void
     */
    public function delete(string $path): void
    {
        $publicDir = defined('PUBLIC_DIR') ? (string) PUBLIC_DIR : 'public';
        if (!str_starts_with($publicDir, '/') && !preg_match('/^[a-zA-Z]:\\\\/', $publicDir)) {
            $publicDir = rtrim($this->projectDir, '/\\') . '/' . $publicDir;
        }

        $abs = rtrim($publicDir, '/\\') . '/' . ltrim($path, '/\\');
        if ($this->filesystem->exists($abs)) {
            $this->filesystem->remove($abs);
        }
    }
}