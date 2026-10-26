<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Tests\Command;

use Fagathe\CorePhp\Command\LogFilesRotationCommand;
use Fagathe\CorePhp\Logger\JsonLoggerRotation;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class LogFilesRotationCommandTest extends TestCase
{
    private string $logsDir;

    protected function setUp(): void
    {
        $this->logsDir = sys_get_temp_dir().'/core-php-rotation-'.uniqid().'/';
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->logsDir);
    }

    public function testOldFilesAndEmptyFoldersAreRemoved(): void
    {
        $filesystem = new Filesystem();
        $oldFile = $this->logsDir.'service/old-service-01-01-2026.json';
        $recentFile = $this->logsDir.'mailer/recent-mailer-26-10-2026.json';

        $filesystem->dumpFile($oldFile, '[]');
        $filesystem->dumpFile($recentFile, '[]');
        touch($oldFile, strtotime('-40 days'));

        $tester = new CommandTester($this->createCommand(30));
        $status = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $status, $tester->getDisplay());
        self::assertFileDoesNotExist($oldFile);
        self::assertDirectoryDoesNotExist($this->logsDir.'service', 'dossier devenu vide supprimé');
        self::assertFileExists($recentFile);
        self::assertStringContainsString('Fichiers supprimés : 1', $tester->getDisplay());
    }

    public function testMissingLogsDirectoryFails(): void
    {
        $tester = new CommandTester($this->createCommand(30));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString("n'existe pas", $tester->getDisplay());
    }

    private function createCommand(int $retentionDelay): LogFilesRotationCommand
    {
        $rotation = new JsonLoggerRotation($this->createStub(Security::class), $this->logsDir, $retentionDelay);

        return new LogFilesRotationCommand($rotation, $this->logsDir, $retentionDelay);
    }
}
