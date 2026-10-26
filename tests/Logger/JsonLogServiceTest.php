<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Tests\Logger;

use Fagathe\CorePhp\Enum\LoggerLevelEnum;
use Fagathe\CorePhp\File\JsonFileHandler;
use Fagathe\CorePhp\Logger\JsonLogService;
use Fagathe\CorePhp\Logger\Log;
use Fagathe\CorePhp\Trait\LoggerTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class JsonLogServiceTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/core-php-logs-'.uniqid();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove([$this->dir, LOGS_DIR.'claude-test']);
        @rmdir(LOGS_DIR); // seulement s'il est vide
    }

    public function testSaveProducesTheSameFileAsAFullRewrite(): void
    {
        $file = $this->dir.'/nested/app-26-10-2026.json';
        $service = new JsonLogService($file);
        $entries = [];

        foreach (['premier', 'deuxième "é"', 'troisième / slash'] as $index => $message) {
            $log = $this->log($message, $index);
            $entries[] = $log->toArray();
            self::assertTrue($service->save($log));
        }

        self::assertSame(json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), file_get_contents($file));
        self::assertSame($entries, $service->readLogs());
    }

    public function testSaveAppendsToAnEmptyArrayWrittenByWriteJson(): void
    {
        $file = $this->dir.'/empty.json';
        (new JsonFileHandler())->writeJson($file, []);

        $log = $this->log('unique', 0);
        (new JsonLogService($file))->save($log);

        self::assertSame(json_encode([$log->toArray()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), file_get_contents($file));
    }

    public function testSaveFallsBackToARewriteWhenTheFileIsNotAJsonArray(): void
    {
        $file = $this->dir.'/broken.json';
        (new Filesystem())->dumpFile($file, '{"not": "an array"}');

        $log = $this->log('après réparation', 0);
        self::assertTrue((new JsonLogService($file))->save($log));

        // Comportement historique (lecture complète + réécriture) conservé à l'identique
        $expected = ['not' => 'an array'];
        $expected[] = $log->toArray();
        self::assertSame(json_encode($expected, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), file_get_contents($file));
    }

    public function testLoggerTraitWorksWithoutSecurityProperty(): void
    {
        $service = new class {
            use LoggerTrait;

            public function run(): void
            {
                $this->generateLog(LoggerLevelEnum::Error, ['message' => 'Erreur', 'exception' => new \RuntimeException('Boom')], [], 'claude-test/trait');
            }
        };

        $service->run(); // failOnWarning : échoue si "Undefined property $security"

        $files = glob(LOGS_DIR.'claude-test/trait-*.json') ?: [];
        self::assertCount(1, $files);

        $logs = json_decode((string) file_get_contents($files[0]), true);
        self::assertSame('Boom', $logs[0]['content']['exception']['message']);
        self::assertNotEmpty($logs[0]['content']['exception']['trace']);
    }

    private function log(string $message, int $index): Log
    {
        return (new Log())
            ->setId('LOG_'.$index)
            ->setLevel(LoggerLevelEnum::Info)
            ->setContent(['message' => $message, 'data' => ['index' => $index, 'empty' => [], 'nested' => ['a' => [1, 2]]]])
            ->setContext(['action' => 'test.save'])
            ->setTimestamp('2026-10-26 10:00:0'.$index);
    }
}
