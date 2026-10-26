<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Tests\Logger;

use Fagathe\CorePhp\Logger\Log;
use Fagathe\CorePhp\Logger\LoggerTemplate;
use Fagathe\CorePhp\Logger\ThrowableNormalizer;
use PHPUnit\Framework\TestCase;

final class LogTest extends TestCase
{
    public function testThrowableIsNormalizedWithStackTrace(): void
    {
        $previous = new \InvalidArgumentException('Cause initiale', 12);
        $exception = new \RuntimeException('Échec du traitement', 0, $previous);

        $log = (new Log())->setContent(['message' => 'Erreur', 'exception' => $exception]);
        $data = $log->getContent('exception');

        self::assertSame(\RuntimeException::class, $data['class']);
        self::assertSame('Échec du traitement', $data['message']);
        self::assertSame(__LINE__ - 7, $data['line']);
        self::assertSame('tests/Logger/LogTest.php', $data['file'], 'chemin relatif à PROJECT_DIR');
        self::assertNotEmpty($data['trace']);
        self::assertSame(['file', 'line', 'class', 'type', 'function'], array_keys($data['trace'][0]), 'aucun argument conservé');

        self::assertSame(\InvalidArgumentException::class, $data['previous']['class']);
        self::assertSame(12, $data['previous']['code']);
    }

    public function testThrowableIsNormalizedWhenAddedAsContent(): void
    {
        $log = (new Log())->addContent('exception', new \LogicException('Boom'));

        self::assertSame('Boom', $log->getContent('exception')['message']);
        self::assertNotFalse(json_encode($log->toArray()), 'le log reste sérialisable');
    }

    public function testStringExceptionIsKeptAsIs(): void
    {
        $log = (new Log())->setContent(['exception' => 'Message seul']);

        self::assertSame('Message seul', $log->getContent('exception'));
    }

    public function testTraceIsTruncatedAndPreviousChainIsBounded(): void
    {
        $exception = $this->deepException(ThrowableNormalizer::MAX_FRAMES + 10);
        $data = ThrowableNormalizer::normalize($exception);

        self::assertCount(ThrowableNormalizer::MAX_FRAMES, $data['trace']);
        self::assertGreaterThanOrEqual(10, $data['truncated_frames']);

        $chain = new \RuntimeException('0');
        for ($i = 1; $i <= ThrowableNormalizer::MAX_PREVIOUS + 3; $i++) {
            $chain = new \RuntimeException((string) $i, 0, $chain);
        }

        $depth = 0;
        for ($node = ThrowableNormalizer::normalize($chain); isset($node['previous']); $node = $node['previous']) {
            $depth++;
        }
        self::assertSame(ThrowableNormalizer::MAX_PREVIOUS, $depth);
    }

    public function testTemplateRendersStackTraceAndKeepsLegacyRendering(): void
    {
        $normalized = (new Log())->setContent(['exception' => new \RuntimeException('Erreur <html>')]);
        $html = (new LoggerTemplate($normalized))->generate();

        self::assertStringContainsString('RuntimeException', $html);
        self::assertStringContainsString('Erreur &lt;html&gt;', $html);
        self::assertStringContainsString('Stack trace', $html);
        self::assertStringContainsString('testTemplateRendersStackTraceAndKeepsLegacyRendering()', $html);

        $legacy = (new Log())->setContent(['exception' => 'Message seul']);
        $legacyHtml = (new LoggerTemplate($legacy))->generate();

        self::assertStringContainsString('Message seul', $legacyHtml);
        self::assertStringNotContainsString('Stack trace', $legacyHtml);
    }

    private function deepException(int $depth): \Throwable
    {
        return $depth === 0 ? new \RuntimeException('Profond') : $this->deepException($depth - 1);
    }
}
