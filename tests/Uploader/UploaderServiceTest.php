<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Tests\Uploader;

use Fagathe\CorePhp\Uploader\UploaderService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class UploaderServiceTest extends TestCase
{
    private string $projectDir;
    private Filesystem $filesystem;
    private UploaderService $uploader;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->projectDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'core-php-uploader-' . uniqid();

        $this->filesystem->dumpFile($this->projectDir . '/.env', 'SECRET=1');
        $this->filesystem->dumpFile($this->projectDir . '/public/index.php', '<?php');
        $this->filesystem->dumpFile($this->projectDir . '/public/uploads/avatars/7/me.png', 'png');
        $this->filesystem->dumpFile($this->projectDir . '/public/uploads/avatars/8/other.png', 'png');

        $this->uploader = new UploaderService(
            security: $this->createStub(Security::class),
            slugger: new AsciiSlugger(),
            projectDir: $this->projectDir,
            publicDir: 'public',
            uploadDir: 'uploads',
            renameFile: true,
        );
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDir);
    }

    public function testDeleteRemovesFileOfUploadDirectory(): void
    {
        self::assertTrue($this->uploader->delete('uploads/avatars/7/me.png'));
        self::assertFileDoesNotExist($this->projectDir . '/public/uploads/avatars/7/me.png');
        self::assertFileExists($this->projectDir . '/public/uploads/avatars/8/other.png');
    }

    public function testDeleteAcceptsLeadingSlashAndBackslashes(): void
    {
        self::assertTrue($this->uploader->delete('/uploads\\avatars\\7\\me.png'));
        self::assertFileDoesNotExist($this->projectDir . '/public/uploads/avatars/7/me.png');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedPathProvider(): iterable
    {
        yield 'sortie du dossier public' => ['../.env'];
        yield 'remontée depuis le dossier d\'upload' => ['uploads/avatars/7/../../../../.env'];
        yield 'fichier public hors uploads' => ['index.php'];
        yield 'dossier d\'upload' => ['uploads'];
        yield 'sous-dossier' => ['uploads/avatars/7'];
        yield 'fichier inexistant' => ['uploads/avatars/7/missing.png'];
        yield 'octet nul' => ["uploads/avatars/7/me.png\0.jpg"];
        yield 'chemin vide' => [''];
    }

    #[DataProvider('rejectedPathProvider')]
    public function testDeleteIgnoresPathOutsideUploadDirectory(string $path): void
    {
        self::assertFalse($this->uploader->delete($path));

        self::assertFileExists($this->projectDir . '/.env');
        self::assertFileExists($this->projectDir . '/public/index.php');
        self::assertFileExists($this->projectDir . '/public/uploads/avatars/7/me.png');
    }

    public function testDeleteIgnoresSymlinkPointingOutsideUploadDirectory(): void
    {
        $link = $this->projectDir . '/public/uploads/avatars/7/link.env';
        if (!@symlink($this->projectDir . '/.env', $link)) {
            self::markTestSkipped('Création de liens symboliques impossible sur cet environnement.');
        }

        self::assertFalse($this->uploader->delete('uploads/avatars/7/link.env'));
        self::assertFileExists($this->projectDir . '/.env');
    }

    public function testUploadReplacesOldFileOfUploadDirectory(): void
    {
        $source = $this->projectDir . '/new.png';
        $this->filesystem->dumpFile($source, 'new');

        $result = $this->uploader
            ->setUploadDirectory('avatars/7')
            ->upload(new UploadedFile($source, 'New Avatar.png', 'image/png', null, true), 'uploads/avatars/7/me.png');

        self::assertStringStartsWith('uploads/avatars/7/new-avatar-', $result->relativePath);
        self::assertFileExists($this->projectDir . '/public/' . $result->relativePath);
        self::assertFileDoesNotExist($this->projectDir . '/public/uploads/avatars/7/me.png');
    }

    public function testUploadKeepsFileWhenOldPathIsOutsideUploadDirectory(): void
    {
        $source = $this->projectDir . '/new.png';
        $this->filesystem->dumpFile($source, 'new');

        $this->uploader
            ->setUploadDirectory('avatars/7')
            ->upload(new UploadedFile($source, 'new.png', 'image/png', null, true), '../.env');

        self::assertFileExists($this->projectDir . '/.env');
    }
}
